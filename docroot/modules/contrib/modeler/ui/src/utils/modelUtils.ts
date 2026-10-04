import { v4 as uuidv4 } from 'uuid';
import { MarkerType } from 'reactflow';
import type { StoreNode as Node, StoreEdge as Edge, ModelData, ModelConstraints } from '../types/settings';
import { EDGE_STYLING, LAYOUT, NODE_DIMENSIONS, VIEWPORT } from '../constants/dimensions';
import { resolveNodeType } from './componentUtils';
import { getEdgeType } from './edgeTypeUtils';
import { simulateIncrementalBuild } from './incrementalLayout';
import { routeAllParallelEdges } from './parallelEdgeRouter';
import { reportError } from './errorReporting';
import { safeJsonParse } from './validation';

// Type definitions for React Flow
interface Viewport {
  x: number;
  y: number;
  zoom: number;
}

interface BoundingBox {
  x: number;
  y: number;
  width: number;
  height: number;
}

// ── Condition node translation (issue #3589093) ──────────────────────────
//
// Conditions are stored as EDGE PROPERTIES in the backend JSON, but are
// promoted to first-class condition NODES internally.  The backend contract
// is unchanged: on export, every condition node is collapsed back onto one
// edge per inbound connection, each carrying the original condition fields.
//
// CARDINALITY (corrected): a condition node may have MULTIPLE inbound edges
// (fan-in) but AT MOST ONE outbound edge.  A node with N inbound + 1 outbound
// represents a REUSED condition: it demotes to N backend edges
// (predecessor_i -> successor) that all share the SAME conditionId.
//
// REVERSIBILITY — the original backend edge id is always carried on the
// INBOUND split edge (so fan-in works), derived deterministically:
//   - non-grouped condition node:  `cond__<originalEdgeId>`
//   - grouped (reused) condition node: `cond__shared__<conditionId>__<target>`
//   - inbound edge:    `<originalEdgeId>__in`   (source -> condition node)
//   - outbound edge:   `<conditionNodeId>__out` (condition node -> target)
// On demote each emitted backend edge recovers its `<originalEdgeId>` by
// stripping the `__in` suffix from its inbound split edge id (with a
// node-id-based and source/target fallback), so round-trips are stable for
// both the non-grouped and grouped (reused) cases.
//
// CONDITION ID DE-DUPLICATION (issue #3589100): when reuse is OFF (the
// default, non-grouped path) the incoming model may carry the SAME non-empty
// `conditionId` on more than one condition edge.  Each such edge still becomes
// its own condition node, but only the FIRST keeps the shared id; subsequent
// reuses receive a fresh `uuidv4()` so that, on export, the backend edges have
// DISTINCT conditionIds instead of colliding (which previously broke diverging
// config on save).  De-duplication is keyed on `conditionId` alone, leaves
// empty ids untouched, and never affects the reuse-ON grouping branch.

/** componentType constant for a Link (condition) component. */
const CONDITION_COMPONENT_TYPE = 5;

const CONDITION_NODE_ID_PREFIX = 'cond__';
const CONDITION_NODE_SHARED_PREFIX = 'cond__shared__';
const CONDITION_EDGE_IN_SUFFIX = '__in';
const CONDITION_EDGE_OUT_SUFFIX = '__out';

/** Build the deterministic condition-node id for an original edge id. */
function conditionNodeIdFor(originalEdgeId: string): string {
  return `${CONDITION_NODE_ID_PREFIX}${originalEdgeId}`;
}

/**
 * Build the deterministic id for a SHARED (reused) condition node, keyed by
 * the grouping tuple (conditionId, target).  The two are joined with a
 * separator that cannot appear in the `cond__shared__` prefix so the id stays
 * unambiguous.
 */
function sharedConditionNodeIdFor(conditionId: string, target: string): string {
  return `${CONDITION_NODE_SHARED_PREFIX}${conditionId}__${target}`;
}

/**
 * Recover the original edge id from a synthesized condition-node id.
 * Returns null when the id does not match the synthesized prefix, or when the
 * node is a SHARED (reused) node — shared nodes carry no single original edge
 * id (each inbound edge carries its own; see {@link originalEdgeIdFromInboundEdgeId}).
 */
function originalEdgeIdFromConditionNodeId(conditionNodeId: string): string | null {
  if (conditionNodeId.startsWith(CONDITION_NODE_SHARED_PREFIX)) {
    return null;
  }
  if (!conditionNodeId.startsWith(CONDITION_NODE_ID_PREFIX)) {
    return null;
  }
  return conditionNodeId.slice(CONDITION_NODE_ID_PREFIX.length);
}

/**
 * Recover the original backend edge id from an inbound split edge id.
 * Inbound split edges are synthesized as `<originalEdgeId>__in`, so stripping
 * the suffix yields the id to reuse on demote.  Returns null when the id does
 * not match the synthesized suffix.
 */
function originalEdgeIdFromInboundEdgeId(inboundEdgeId: string): string | null {
  if (!inboundEdgeId.endsWith(CONDITION_EDGE_IN_SUFFIX)) {
    return null;
  }
  return inboundEdgeId.slice(0, -CONDITION_EDGE_IN_SUFFIX.length);
}



/**
 * Map node type back to component type constant
 */
function getComponentTypeFromNodeType(nodeType: string): string {
  switch (nodeType) {
    case 'start':
      return '1'; // Api::COMPONENT_TYPE_START
    case 'subprocess':
      return '2'; // Api::COMPONENT_TYPE_SUBPROCESS  
    case 'element':
      return '4'; // Api::COMPONENT_TYPE_ELEMENT
    case 'link':
      return '5'; // Api::COMPONENT_TYPE_LINK
    case 'gateway':
      return '6'; // Api::COMPONENT_TYPE_GATEWAY
    default:
      return '4'; // Default to element
  }
}

/**
 * Options controlling how {@link parseModelData} promotes condition edges.
 */
export interface ParseModelDataOptions {
  /**
   * When `true`, condition edges that share the SAME non-empty `conditionId`
   * AND the same target are grouped into ONE shared (reused) condition node
   * with N inbound edges + 1 outbound edge.  This is owner-opt-in and arrives
   * via `settings.modeler_api.model_constraints` (a successor constraint's
   * `allowConditionReuse` flag).  Read DEFENSIVELY: when absent/false, behave
   * as before (one condition node per condition edge — no grouping).
   */
  allowConditionReuse?: boolean;
}

/**
 * Derive whether condition reuse (grouping on load) is enabled, reading the
 * owner-provided `model_constraints` DEFENSIVELY (issue #3589093).
 *
 * Reuse is enabled when ANY successor constraint sets `allowConditionReuse`
 * to `true`.  This is the simplest correct interpretation: grouping is a
 * global load-time behavior (shared condition nodes can span source types),
 * so a single opt-in anywhere turns it on.  When `constraints` is absent or no
 * constraint sets the flag, the result is `false` (today's behavior).
 *
 * @param constraints  Owner-provided model constraints, or undefined.
 * @returns `true` when any successor constraint opts into condition reuse.
 */
export function isConditionReuseEnabled(constraints: ModelConstraints | undefined): boolean {
  if (!constraints) return false;
  return Object.values(constraints).some(
    (constraint) => constraint?.successors?.allowConditionReuse === true,
  );
}

/**
 * Parse model data from Drupal JSON format to React Flow format
 */
export function parseModelData(
  modelData: ModelData | string | null,
  options: ParseModelDataOptions = {},
): { nodes: Node[], edges: Edge[], modelData: ModelData | null } {
  if (!modelData) {
    return { nodes: [], edges: [], modelData: null };
  }

  const data = typeof modelData === 'string' ? safeJsonParse<ModelData>(modelData) : modelData;
  
  const nodes = (data.nodes || []).map((node: any) => {
    // Resolve the ReactFlow node type: prefer an explicit `type` string,
    // otherwise derive it from the integer `componentType` via the shared
    // type map.  This supports both the legacy format (with `type`) and
    // the new format where `convert()` only provides `componentType`.
    const nodeType = node.type || resolveNodeType(node.componentType);
    return {
      id: String(node.id), // Ensure ID is string
      type: nodeType,
      position: node.position || { x: LAYOUT.DEFAULT_POSITION_X, y: LAYOUT.DEFAULT_POSITION_Y },
      data: {
        label: node.label || node.id,
        plugin: node.plugin,
        configuration: node.configuration || {},
        componentType: node.componentType ?? getComponentTypeFromNodeType(nodeType),
        ...node,
      },
    };
  });

  // Create a set of valid node IDs for edge validation
  const nodeIds = new Set(nodes.map((n: any) => n.id));
  
  // Track assigned edge IDs so parallel edges (same source and target)
  // that arrive with duplicate IDs from the backend get deduplicated.
  const seenEdgeIds = new Set<string>();

  // Filter edges to only include those that reference existing nodes
  const edges = (data.edges || [])
    .filter((edge: any, _index: number) => {
      const sourceExists = nodeIds.has(String(edge.source));
      const targetExists = nodeIds.has(String(edge.target));
      return sourceExists && targetExists;
    })
    .map((edge: any, index: number) => {
      // Ensure unique edge ID.  If the backend sent a duplicate (e.g.
      // parallel edges before the backend fix), append the array index
      // to make it unique so ReactFlow receives distinct keys.
      let edgeId = edge.id || `${edge.source}-${edge.target}-${index}`;
      if (seenEdgeIds.has(edgeId)) {
        edgeId = `${edgeId}_${index}`;
      }
      seenEdgeIds.add(edgeId);
      
      // Determine edge type based on content
      // Only two edge types: 'condition' (has a condition) or 'default' (no condition).
      // Annotations belong to conditions, not edges directly.
      const hasCondition = edge.condition || edge.conditionLabel ||
        (edge.conditionConfiguration && Object.keys(edge.conditionConfiguration).length > 0);
      const edgeType = hasCondition ? 'condition' : 'default';
      
      return {
        id: edgeId,
        source: String(edge.source),
        target: String(edge.target),
        sourceHandle: 'output',
        targetHandle: 'input',
        type: edgeType,
        animated: false,
        label: edge.conditionLabel || edge.condition || '',
        markerEnd: {
          type: MarkerType.Arrow,
          width: EDGE_STYLING.ARROW_WIDTH,
          height: EDGE_STYLING.ARROW_HEIGHT,
          color: 'var(--modeler-color-edge-stroke)',
          strokeWidth: EDGE_STYLING.STROKE_WIDTH,
        },
        style: {
          stroke: 'var(--modeler-color-edge-stroke)',
          strokeWidth: EDGE_STYLING.STROKE_WIDTH,
        },
        pathOptions: {
          offset: EDGE_STYLING.CONTROL_OFFSET,
          borderRadius: EDGE_STYLING.BORDER_RADIUS,
        },
        data: {
          condition: edge.condition,
          controlOffset: edge.controlOffset || { x: 0, y: 0 }, // Add control point offset
          ...edge,
        },
      };
    });

  // Promote condition edges to first-class condition nodes (issue #3589093).
  // Every edge that carries a condition is replaced by a condition node plus
  // two plain ("default") edges that route source -> condition -> target.
  // This MUST happen before auto-layout and parallel routing so the
  // synthesized nodes/edges participate in placement just like any other.
  const { nodes: promotedNodes, edges: promotedEdges } = promoteConditionEdges(
    nodes,
    edges,
    { allowConditionReuse: options.allowConditionReuse === true },
  );

  // Apply auto-layout if all nodes have the same position (indicating they need layout)
  const unlockedNodes = promotedNodes;
  const needsLayout = unlockedNodes.length > 1 && unlockedNodes.every((n: any) => n.position.x === LAYOUT.DEFAULT_POSITION_X && n.position.y === LAYOUT.DEFAULT_POSITION_Y);
  if (needsLayout) {
    const layoutedNodes = autoLayout(promotedNodes, promotedEdges);
    // After auto-layout positions nodes, route parallel edges so that
    // multiple edges between the same source/target spread out
    // horizontally instead of overlapping.
    const routedEdges = routeAllParallelEdges(promotedEdges);
    return { nodes: layoutedNodes || promotedNodes, edges: routedEdges, modelData: data };
  }

  return { nodes: promotedNodes, edges: promotedEdges, modelData: data };
}

/**
 * Promote every condition-carrying edge into a first-class condition node
 * plus split edges (source -> condition node -> target).  Non-condition edges
 * and all original nodes pass through unchanged.
 *
 * GROUPING (reuse) is owner-opt-in via `options.allowConditionReuse`
 * (issue #3589093):
 *   - When `false`/absent (DEFAULT): one condition node per condition edge.
 *     In this path the conditionId FIELD is also DE-DUPLICATED (issue
 *     #3589100): the first condition edge bearing a given non-empty
 *     `conditionId` keeps it unchanged, while every SUBSEQUENT edge reusing an
 *     already-seen non-empty `conditionId` gets a freshly minted uuid so the
 *     resulting nodes — and the backend edges they demote to — diverge instead
 *     of colliding on a shared id.  De-duplication is keyed on `conditionId`
 *     ALONE (independent of source/target); empty ids are never touched.
 *   - When `true`: condition edges that share the SAME non-empty `conditionId`
 *     AND the same target collapse into ONE shared condition node with N
 *     inbound edges (one per source) and a single outbound edge to the shared
 *     target.  Condition edges with an empty `conditionId` are never grouped.
 *     This grouping branch is NOT affected by the reuse-OFF de-duplication.
 *
 * The transformation is reversible by {@link demoteConditionNodes}: each
 * inbound split edge carries the original backend edge id as `<id>__in`, so
 * demote can recover it for every inbound edge — making fan-in lossless.
 *
 * @param nodes    Parsed nodes (already in ReactFlow shape).
 * @param edges    Parsed edges (condition edges still carry type 'condition').
 * @param options  Promotion options (notably `allowConditionReuse`).
 * @returns New `{ nodes, edges }` with conditions represented as nodes.
 */
function promoteConditionEdges(
  nodes: Node[],
  edges: Edge[],
  options: ParseModelDataOptions = {},
): { nodes: Node[], edges: Edge[] } {
  const allowConditionReuse = options.allowConditionReuse === true;

  // Fast lookup of node positions so the condition node can be placed on the
  // original edge path (approximate midpoint of source and target).
  const positionById = new Map<string, { x: number; y: number }>();
  nodes.forEach((node) => {
    positionById.set(node.id, {
      x: node.position?.x ?? LAYOUT.DEFAULT_POSITION_X,
      y: node.position?.y ?? LAYOUT.DEFAULT_POSITION_Y,
    });
  });

  // Shared visual styling for split edges, mirroring the styling a plain
  // default edge receives during parsing.
  const splitMarkerEnd = {
    type: MarkerType.Arrow,
    width: EDGE_STYLING.ARROW_WIDTH,
    height: EDGE_STYLING.ARROW_HEIGHT,
    color: 'var(--modeler-color-edge-stroke)',
    strokeWidth: EDGE_STYLING.STROKE_WIDTH,
  };
  const splitStyle = {
    stroke: 'var(--modeler-color-edge-stroke)',
    strokeWidth: EDGE_STYLING.STROKE_WIDTH,
  };
  const splitPathOptions = {
    offset: EDGE_STYLING.CONTROL_OFFSET,
    borderRadius: EDGE_STYLING.BORDER_RADIUS,
  };

  /** Compute the midpoint position between a source and target node. */
  const midpoint = (sourceId: string, targetId: string) => {
    const sourcePos = positionById.get(sourceId) ?? {
      x: LAYOUT.DEFAULT_POSITION_X,
      y: LAYOUT.DEFAULT_POSITION_Y,
    };
    const targetPos = positionById.get(targetId) ?? {
      x: LAYOUT.DEFAULT_POSITION_X,
      y: LAYOUT.DEFAULT_POSITION_Y,
    };
    return {
      x: (sourcePos.x + targetPos.x) / 2,
      y: (sourcePos.y + targetPos.y) / 2,
    };
  };

  // Build an inbound split edge (source -> condition node).  Carries the
  // original edge's controlOffset so demote can restore it on the collapsed
  // edge.  The `as Edge` assertion is needed because ReactFlow's `Edge` type
  // is a discriminated union that types `pathOptions` per edge-type, while the
  // runtime shape here is intentionally uniform.
  const makeInboundEdge = (
    originalEdgeId: string,
    source: string,
    conditionNodeId: string,
    controlOffset: { x: number; y: number },
  ): Edge => ({
    id: `${originalEdgeId}${CONDITION_EDGE_IN_SUFFIX}`,
    source,
    target: conditionNodeId,
    sourceHandle: 'output',
    targetHandle: 'input',
    type: 'default',
    animated: false,
    label: '',
    markerEnd: splitMarkerEnd,
    style: splitStyle,
    pathOptions: splitPathOptions,
    data: {
      controlOffset: controlOffset ?? { x: 0, y: 0 },
    },
  } as Edge);

  // Build an outbound split edge (condition node -> target).
  const makeOutboundEdge = (conditionNodeId: string, target: string): Edge => ({
    id: `${conditionNodeId}${CONDITION_EDGE_OUT_SUFFIX}`,
    source: conditionNodeId,
    target,
    sourceHandle: 'output',
    targetHandle: 'input',
    type: 'default',
    animated: false,
    label: '',
    markerEnd: splitMarkerEnd,
    style: splitStyle,
    pathOptions: splitPathOptions,
    data: {
      controlOffset: { x: 0, y: 0 },
    },
  } as Edge);

  // Build a condition node from a representative condition edge's data.
  //
  // `conditionIdOverride` (issue #3589100) lets the non-grouped branch supply a
  // de-duplicated conditionId.  When provided it REPLACES the edge's own
  // `conditionId` in the node data; when omitted the node carries the edge's
  // original conditionId verbatim (the unchanged grouped/representative path).
  const makeConditionNode = (
    conditionNodeId: string,
    edgeData: Record<string, unknown>,
    position: { x: number; y: number },
    conditionIdOverride?: string,
  ): Node => ({
    id: conditionNodeId,
    type: 'condition',
    position,
    data: {
      label: (edgeData.conditionLabel as string) ?? '',
      plugin: (edgeData.condition as string) ?? '',
      configuration: (edgeData.conditionConfiguration as Record<string, unknown>) ?? {},
      conditionId: conditionIdOverride ?? (edgeData.conditionId as string) ?? '',
      annotation: (edgeData.annotation as string) ?? '',
      componentType: CONDITION_COMPONENT_TYPE,
      __isConditionNode: true,
    },
  });

  const resultNodes: Node[] = [...nodes];
  const resultEdges: Edge[] = [];

  // First pass: when reuse is enabled, decide which condition edges are part
  // of a shared group.  A group is keyed by (conditionId, target) and only
  // forms when the non-empty conditionId is shared by 2+ edges to the same
  // target.  Edges not in any group fall through to the per-edge path so the
  // default (no-grouping) behavior is preserved exactly.
  const groupKeyForEdge = new Map<string, string>(); // edge.id -> group key
  const groupMembers = new Map<string, Edge[]>();     // group key -> edges
  if (allowConditionReuse) {
    const candidatesByKey = new Map<string, Edge[]>();
    edges.forEach((edge) => {
      const isConditionEdge =
        edge.type === 'condition' || getEdgeType(edge.data) === 'condition';
      if (!isConditionEdge) return;
      const conditionId = (edge.data?.conditionId as string) ?? '';
      if (!conditionId) return; // empty conditionId is never grouped.
      const key = `${conditionId}\u0000${edge.target}`;
      const list = candidatesByKey.get(key) ?? [];
      list.push(edge);
      candidatesByKey.set(key, list);
    });
    candidatesByKey.forEach((list, key) => {
      if (list.length < 2) return; // a single edge needs no shared node.
      groupMembers.set(key, list);
      list.forEach((e) => groupKeyForEdge.set(e.id, key));
    });
  }

  // Track which shared nodes have already been emitted so each group produces
  // exactly one shared condition node and one outbound edge.
  const emittedSharedNodeIds = new Set<string>();

  // De-duplication of condition IDs in the NON-GROUPED (reuse OFF) path
  // (issue #3589100).  When reuse is OFF, every condition edge becomes its own
  // condition node, but the incoming model may legitimately carry the SAME
  // non-empty conditionId on more than one condition edge (e.g. a condition
  // reused from different sources/targets).  Without de-duplication each such
  // node would re-emit the identical conditionId on export, producing 2+
  // backend edges sharing one id — which breaks diverging config on save.
  //
  // Rule (keyed on conditionId ALONE, independent of source/target):
  //   - The FIRST condition edge with a given non-empty conditionId KEEPS its
  //     original id unchanged (stability is critical for no-edit round-trips).
  //   - Each SUBSEQUENT edge reusing an already-seen non-empty conditionId gets
  //     a freshly minted uuid so the nodes (and exported edges) diverge.
  //   - Empty/absent conditionIds are never tracked nor mutated.
  // This Set is consulted ONLY on the non-grouped path; the reuse-ON grouping
  // branch is intentionally untouched.
  const seenConditionIds = new Set<string>();

  edges.forEach((edge) => {
    const isConditionEdge =
      edge.type === 'condition' || getEdgeType(edge.data) === 'condition';

    if (!isConditionEdge) {
      // Plain edge — pass through unchanged.
      resultEdges.push(edge);
      return;
    }

    const edgeData = edge.data ?? {};
    const groupKey = groupKeyForEdge.get(edge.id);

    if (groupKey) {
      // Grouped (reused) condition: one shared node for the whole group.
      const conditionId = (edgeData.conditionId as string) ?? '';
      const sharedNodeId = sharedConditionNodeIdFor(conditionId, edge.target);

      if (!emittedSharedNodeIds.has(sharedNodeId)) {
        emittedSharedNodeIds.add(sharedNodeId);
        // Use the FIRST member edge as the representative for the node's
        // condition data; place it at the midpoint of that edge.
        const members = groupMembers.get(groupKey)!;
        const representative = members[0];
        const repData = representative.data ?? {};
        resultNodes.push(
          makeConditionNode(
            sharedNodeId,
            repData as Record<string, unknown>,
            midpoint(representative.source, edge.target),
          ),
        );
        // Single outbound edge for the shared node.
        resultEdges.push(makeOutboundEdge(sharedNodeId, edge.target));
      }

      // One inbound edge per member, carrying this edge's original id so
      // demote recovers it (lossless fan-in).
      resultEdges.push(
        makeInboundEdge(
          edge.id,
          edge.source,
          sharedNodeId,
          (edgeData.controlOffset as { x: number; y: number }) ?? { x: 0, y: 0 },
        ),
      );
      return;
    }

    // Non-grouped condition: one condition node per condition edge (default).
    const originalEdgeId = edge.id;
    const conditionNodeId = conditionNodeIdFor(originalEdgeId);

    // De-duplicate the conditionId FIELD across condition edges (issue
    // #3589100).  The NODE id (`cond__<originalEdgeId>`) is already unique, so
    // only the `conditionId` carried in the node data needs adjusting.  Compute
    // the effective conditionId: keep the original for the first occurrence of
    // a non-empty id, mint a fresh uuid for every subsequent reuse, and leave
    // empty ids alone (never tracked, never mutated).
    const originalConditionId = (edgeData.conditionId as string) ?? '';
    let effectiveConditionId = originalConditionId;
    if (originalConditionId) {
      if (seenConditionIds.has(originalConditionId)) {
        // Already seen this id on an earlier condition edge — diverge so the
        // exported backend edges do not collide on conditionId.
        effectiveConditionId = uuidv4();
      } else {
        // First occurrence: keep it unchanged (stability) and remember it.
        seenConditionIds.add(originalConditionId);
      }
    }

    resultNodes.push(
      makeConditionNode(
        conditionNodeId,
        edgeData as Record<string, unknown>,
        midpoint(edge.source, edge.target),
        effectiveConditionId,
      ),
    );
    resultEdges.push(
      makeInboundEdge(
        originalEdgeId,
        edge.source,
        conditionNodeId,
        (edgeData.controlOffset as { x: number; y: number }) ?? { x: 0, y: 0 },
      ),
    );
    resultEdges.push(makeOutboundEdge(conditionNodeId, edge.target));
  });

  return { nodes: resultNodes, edges: resultEdges };
}

/**
 * Filter out internal properties (starting with _) from configuration
 */
function filterInternalProperties(config: Record<string, unknown> | undefined): Record<string, unknown> {
  if (!config) return {};
  return Object.fromEntries(
    Object.entries(config).filter(([key]) => !key.startsWith('_'))
  );
}

/**
 * Convert a label to snake_case for use as model ID
 */
export function labelToSnakeCase(label: string): string {
  return label
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9\s_-]/g, '') // Remove special characters except spaces, underscores, hyphens
    .replace(/[\s-]+/g, '_')       // Replace spaces and hyphens with underscores
    .replace(/_+/g, '_')           // Collapse multiple underscores
    .replace(/^_|_$/g, '');        // Remove leading/trailing underscores
}

/**
 * Generate a model ID from label or create a unique fallback
 */
function generateModelId(metadata: Record<string, unknown>): string {
  // If ID is already provided, use it
  if (typeof metadata.id === 'string' && metadata.id) {
    return metadata.id;
  }

  // If label is provided, generate ID from it
  if (typeof metadata.label === 'string' && metadata.label && metadata.label !== 'Untitled Model') {
    const snakeCaseId = labelToSnakeCase(metadata.label);
    if (snakeCaseId) {
      return snakeCaseId;
    }
  }

  // Fallback to UUID
  return uuidv4();
}

/**
 * Collapse every first-class condition node back onto a single condition edge
 * (issue #3589093).  This is the exact inverse of {@link promoteConditionEdges}.
 *
 * A node qualifies for demotion when it is a condition node (`type ===
 * 'condition'` or `data.__isConditionNode`) AND it has at least one inbound
 * edge (X -> condNode) and exactly one outbound edge (condNode -> Y).  Such a
 * node is removed, its split edges are removed, and ONE condition edge X -> Y
 * is emitted PER inbound edge, each carrying all condition fields from the
 * node's data.  A node with N inbound edges therefore demotes to N backend
 * edges that all share the SAME conditionId (a REUSED condition) — see the
 * module-level documentation.
 *
 * Edge order is preserved by emitting each collapsed edge at the position of
 * its own inbound ("__in") edge.  Each collapsed edge reuses the original
 * backend edge id recovered from its inbound split edge id (`<id>__in`), so
 * fan-in round-trips are stable and lossless.
 *
 * DEFENSIVE handling (issue #3589093, fix C5): a condition node MUST never be
 * emitted as a `componentType: 5` node in the exported `nodes[]` — that would
 * violate the backend contract (conditions are edge properties).  As a
 * last-resort safety net this function NEVER leaks a condition node:
 *   - N-in / 1-out (valid reuse, N >= 1): collapse onto N predecessor →
 *     successor edges, all sharing the node's conditionId.  NO inbound edge is
 *     ever dropped.
 *   - N-in / >1-out (broken graph; should not happen after the 1-outbound
 *     connect guard): collapse onto N predecessor → FIRST-successor edges so
 *     NO inbound edge is lost, drop the extra outbound edges, and log.
 *   - 0 inbound or 0 outbound (dangling): cannot form an edge, so drop the
 *     node (and any stray split edge) entirely and log via errorReporting.
 * In every case the condition node is removed from `nodes[]`.
 *
 * @param nodes  Store nodes, possibly including condition nodes.
 * @param edges  Store edges, possibly including split edges.
 * @returns New `{ nodes, edges }` with conditions represented as edge props.
 */
function demoteConditionNodes(nodes: Node[], edges: Edge[]): { nodes: Node[], edges: Edge[] } {
  // Identify condition nodes by id.
  const conditionNodeById = new Map<string, Node>();
  nodes.forEach((node) => {
    if (node.type === 'condition' || node.data?.__isConditionNode) {
      conditionNodeById.set(node.id, node);
    }
  });

  // No condition nodes — nothing to do, return inputs untouched.
  if (conditionNodeById.size === 0) {
    return { nodes, edges };
  }

  // Map each condition node to its inbound and outbound edges so we can
  // honor the fan-in (N-in) / 1-outbound cardinality.
  const inboundByCondNode = new Map<string, Edge[]>();
  const outboundByCondNode = new Map<string, Edge[]>();
  edges.forEach((edge) => {
    if (conditionNodeById.has(edge.target)) {
      const list = inboundByCondNode.get(edge.target) ?? [];
      list.push(edge);
      inboundByCondNode.set(edge.target, list);
    }
    if (conditionNodeById.has(edge.source)) {
      const list = outboundByCondNode.get(edge.source) ?? [];
      list.push(edge);
      outboundByCondNode.set(edge.source, list);
    }
  });

  // Classify every condition node.  A node is *collapsible* when it has at
  // least one inbound AND at least one outbound edge.  Nodes with zero
  // inbound or zero outbound edges cannot form an edge and are dropped
  // outright (logged below).  Either way the condition node never survives
  // into the exported nodes[] (fix C5).
  const collapsibleCondNodeIds = new Set<string>();
  conditionNodeById.forEach((_node, condNodeId) => {
    const inbound = inboundByCondNode.get(condNodeId) ?? [];
    const outbound = outboundByCondNode.get(condNodeId) ?? [];

    if (inbound.length >= 1 && outbound.length >= 1) {
      collapsibleCondNodeIds.add(condNodeId);
      // Broken graph: more than one outbound edge violates the 1-outbound
      // invariant (Task 1 connect guard should prevent this).  We still emit
      // ONE edge per inbound edge — never dropping inbound edges — but route
      // them all to the FIRST outbound target and drop the extra outbound
      // edges, so no componentType-5 node leaks into the export.
      if (outbound.length !== 1) {
        // Internal diagnostic message for the error log — not user-facing UI.
        /* eslint-disable i18n/no-untranslated-strings */
        reportError(
          'modelUtils.demoteConditionNodes',
          `Condition node "${condNodeId}" violates the 1-outbound invariant `
          + `(in=${inbound.length}, out=${outbound.length}); collapsing every `
          + 'inbound edge to the first outbound target and dropping the extra '
          + 'outbound edges to preserve the backend contract.',
          'warning',
        );
        /* eslint-enable i18n/no-untranslated-strings */
      }
    } else {
      // Dangling condition node: cannot form an edge.  It will be removed
      // from nodes[] below; surface it so the broken graph is not silent.
      // Internal diagnostic message for the error log — not user-facing UI.
      /* eslint-disable i18n/no-untranslated-strings */
      reportError(
        'modelUtils.demoteConditionNodes',
        `Condition node "${condNodeId}" has no ${inbound.length === 0 ? 'inbound' : 'outbound'} `
        + 'edge and cannot be demoted to an edge; dropping it to preserve the backend contract.',
        'warning',
      );
      /* eslint-enable i18n/no-untranslated-strings */
    }
  });

  // Build the collapsed condition edges, keyed by the inbound edge id so we
  // can splice each in at its inbound edge's original position.  One collapsed
  // edge is emitted PER inbound edge so fan-in is never lossy.
  const collapsedEdgeByInboundId = new Map<string, Edge>();
  const splitEdgeIdsToDrop = new Set<string>();

  collapsibleCondNodeIds.forEach((condNodeId) => {
    const condNode = conditionNodeById.get(condNodeId)!;
    const inboundEdges = inboundByCondNode.get(condNodeId) ?? [];
    const outboundEdges = outboundByCondNode.get(condNodeId) ?? [];
    // The single (or first, defensively) outbound edge defines the target.
    const outboundEdge = outboundEdges[0];

    // Drop ALL split edges touching this condition node so nothing leaks.
    for (const e of inboundEdges) splitEdgeIdsToDrop.add(e.id);
    for (const e of outboundEdges) splitEdgeIdsToDrop.add(e.id);

    const data = condNode.data ?? {};
    // All N collapsed edges must share ONE conditionId.  When the node has no
    // conditionId yet (a freshly authored condition), mint a single uuid and
    // reuse it for every inbound edge so they share identity.
    const sharedConditionId = data.conditionId || uuidv4();

    inboundEdges.forEach((inboundEdge) => {
      // Recover the original backend edge id from the inbound split edge id
      // (`<originalEdgeId>__in`).  Fall back to the node-id scheme (legacy
      // non-grouped nodes), then to a deterministic source-target id.
      const recoveredFromInbound = originalEdgeIdFromInboundEdgeId(inboundEdge.id);
      const recoveredFromNode = originalEdgeIdFromConditionNodeId(condNodeId);
      const collapsedEdgeId =
        recoveredFromInbound
        ?? recoveredFromNode
        ?? `${inboundEdge.source}-${outboundEdge.target}`;

      const collapsedEdge: Edge = {
        id: collapsedEdgeId,
        source: inboundEdge.source,
        target: outboundEdge.target,
        sourceHandle: inboundEdge.sourceHandle ?? 'output',
        targetHandle: outboundEdge.targetHandle ?? 'input',
        type: 'condition',
        data: {
          condition: data.plugin ?? '',
          conditionId: sharedConditionId,
          conditionLabel: data.label ?? '',
          conditionConfiguration: data.configuration ?? {},
          annotation: data.annotation ?? '',
          controlOffset: inboundEdge.data?.controlOffset ?? { x: 0, y: 0 },
        },
      };
      collapsedEdgeByInboundId.set(inboundEdge.id, collapsedEdge);
    });
  });

  // Any edge still touching a (dangling, non-collapsible) condition node
  // would reference a node that is about to be removed — drop those too so
  // the export never points at a deleted condition node.
  edges.forEach((edge) => {
    if (conditionNodeById.has(edge.source) || conditionNodeById.has(edge.target)) {
      splitEdgeIdsToDrop.add(edge.id);
    }
  });

  // Rebuild the edge list in order: replace each inbound split edge with its
  // collapsed condition edge, drop outbound split edges, keep everything else.
  const resultEdges: Edge[] = [];
  edges.forEach((edge) => {
    const collapsed = collapsedEdgeByInboundId.get(edge.id);
    if (collapsed) {
      resultEdges.push(collapsed);
      return;
    }
    if (splitEdgeIdsToDrop.has(edge.id)) {
      // Outbound split edge already represented by the collapsed edge, or a
      // stray edge to a dropped dangling condition node.
      return;
    }
    resultEdges.push(edge);
  });

  // Drop EVERY condition node from the node list (fix C5): a condition node
  // must never be emitted as a componentType-5 node in the export.
  const resultNodes = nodes.filter((node) => !conditionNodeById.has(node.id));

  return { nodes: resultNodes, edges: resultEdges };
}

/**
 * Export model data from React Flow format to Drupal JSON format
 */
export function exportModelData(nodes: Node[], edges: Edge[], metadata: Record<string, unknown> = {}) {
  // Collapse condition nodes back onto edges before serialization so the
  // backend JSON keeps conditions as edge properties (issue #3589093).  The
  // remainder of this function — and therefore the exported JSON shape — is
  // unchanged for all non-condition data.
  const demoted = demoteConditionNodes(nodes, edges);
  nodes = demoted.nodes;
  edges = demoted.edges;

  const modelData = {
    id: generateModelId(metadata),
    version: (typeof metadata.version === 'string' ? metadata.version : undefined) || '1.0.0',
    metadata: {
      label: (typeof metadata.label === 'string' ? metadata.label : undefined) || 'Untitled Model',
      documentation: (typeof metadata.documentation === 'string' ? metadata.documentation : undefined) || '',
      executable: metadata.executable !== false,
      tags: (Array.isArray(metadata.tags) ? metadata.tags : undefined) || [],
      changelog: (typeof metadata.changelog === 'string' ? metadata.changelog : undefined) || '',
      ...metadata,
    },
    nodes: nodes.map(node => ({
      id: node.id,
      componentType: node.data.componentType,
      plugin: node.data.plugin,
      label: node.data.label,
      position: {
        x: Math.round(node.position.x),
        y: Math.round(node.position.y),
      },
      configuration: filterInternalProperties(node.data.configuration),
      annotation: node.data.annotation || '', // Include annotation in export
    })),
    edges: edges.map(edge => ({
      id: edge.id,
      source: edge.source,
      target: edge.target,
      sourceHandle: edge.sourceHandle,
      targetHandle: edge.targetHandle,
      condition: edge.data?.condition || edge.label || '',
      conditionId: edge.data?.conditionId || '', // Preserve original condition ID for round-trip stability
      conditionLabel: edge.data?.conditionLabel || '',
      conditionConfiguration: filterInternalProperties(edge.data?.conditionConfiguration ?? undefined),
      annotation: edge.data?.annotation || '', // Include annotation in export
      controlOffset: edge.data?.controlOffset || { x: 0, y: 0 }, // Include control point offset
    })),
  };

  return modelData;
}

/**
 * Generate automatic layout for nodes based on edge flow.
 *
 * Delegates to {@link simulateIncrementalBuild}, which walks the graph
 * from each start node and places successors using the same primitives
 * that quick-add and drag-to-connect use.  This guarantees that the
 * auto-layout output is by definition equivalent to "what the user
 * would have seen if they had built this model node-by-node with
 * quick-add" — there is one source of placement truth.
 *
 * Signature and return shape are preserved: returns `null` for empty
 * input, otherwise returns a node array with freshly assigned positions.
 */
export function autoLayout(nodes: Node[], edges: Edge[]): Node[] | null {
  if (!nodes || nodes.length === 0) return null;
  return simulateIncrementalBuild(nodes, edges || []);
}

/**
 * Calculate bounds for a subset of nodes
 * @param {Array} nodes - Array of nodes to calculate bounds for
 * @returns {Object} Bounds object with x, y, width, height
 */
function getNodesBounds(nodes: Node[]): BoundingBox {
  if (!nodes || nodes.length === 0) {
    return { x: 0, y: 0, width: 0, height: 0 };
  }

  const nodeWidth = NODE_DIMENSIONS.DEFAULT_WIDTH; // Estimated width
  const nodeHeight = 80; // Estimated height (no specific constant for this)

  let minX = Infinity;
  let minY = Infinity;
  let maxX = -Infinity;
  let maxY = -Infinity;

  nodes.forEach((node: Node) => {
    const x = node.position?.x || 0;
    const y = node.position?.y || 0;
    const width = node.width || nodeWidth;
    const height = node.height || nodeHeight;
    
    minX = Math.min(minX, x);
    minY = Math.min(minY, y);
    maxX = Math.max(maxX, x + width);
    maxY = Math.max(maxY, y + height);
  });
  
  return {
    x: minX,
    y: minY,
    width: maxX - minX,
    height: maxY - minY
  };
}

/**
 * Calculate viewport for fitting specific nodes
 * @param {Array} nodes - Nodes to fit
 * @param {number} viewportWidth - Width of viewport
 * @param {number} viewportHeight - Height of viewport
 * @param {number} padding - Padding factor (0.1 = 10% padding)
 * @returns {Object} Viewport object with x, y, zoom
 */
export function getFitViewport(nodes: Node[], viewportWidth: number, viewportHeight: number, padding = 0.1): Viewport {
  const bounds = getNodesBounds(nodes);
  
  if (bounds.width === 0 || bounds.height === 0) {
    return { x: 0, y: 0, zoom: 1 };
  }
  
  // Calculate zoom to fit bounds with padding
  const paddingX = bounds.width * padding;
  const paddingY = bounds.height * padding;
  const zoomX = viewportWidth / (bounds.width + paddingX * 2);
  const zoomY = viewportHeight / (bounds.height + paddingY * 2);
  const zoom = Math.min(zoomX, zoomY, VIEWPORT.MAX_ZOOM);
  
  // Calculate position to center the bounds
  const x = -bounds.x * zoom + (viewportWidth - bounds.width * zoom) / 2;
  const y = -bounds.y * zoom + (viewportHeight - bounds.height * zoom) / 2;
  
  return { x, y, zoom };
}