/**
 * Incremental layout primitives.
 *
 * This module is the **single source of placement truth** for the modeler.
 * It contains pure (React-free, store-free) functions that decide where to
 * place a new node, given the current set of nodes and edges.
 *
 * Both code paths use these primitives:
 *
 * - **Quick-add / drag-to-connect / plugin API addNode**: place one new
 *   node at a time by calling {@link computeSuccessorPosition} (successor
 *   of a named parent) or {@link computeNewEventPosition} (fresh flow).
 * - **Auto-layout (model load + toolbar button)**: simulate the incremental
 *   build by walking the graph from each start node and calling
 *   {@link computeSuccessorPosition} once per edge in topological order.
 *
 * Because both paths share the same primitives, the auto-layout output is
 * by definition equivalent to "what the user would have seen if they had
 * built this model node-by-node with quick-add".
 *
 * Coordinates are top-left throughout (matching ReactFlow's coordinate
 * system).  All gap math goes through {@link requiredVerticalGap} from
 * positionUtils.ts so there is no duplicated spacing logic anywhere else
 * in the codebase.
 */

import type { StoreNode as Node, StoreEdge as Edge } from '../types/settings';
import { LAYOUT, NODE_DIMENSIONS } from '../constants/dimensions';
import {
  findFlowAwarePosition,
  findFreePosition,
  requiredVerticalGap,
} from './positionUtils';
import { generateNodeId, generateEdgeId } from './clipboardUtils';
import { t } from './translation';

// ============ Types ============

/** Result of placing a single successor node. */
export interface PlaceSuccessorResult {
  /** Updated node array with the new node inserted and any shifts applied. */
  nodes: Node[];
  /** The position assigned to the new node (top-left, ReactFlow coordinates). */
  position: { x: number; y: number };
}

/** Options for placing a successor of an existing node. */
export interface PlaceSuccessorOptions {
  /** All nodes currently on the canvas. */
  nodes: Node[];
  /** All edges currently on the canvas. */
  edges: Edge[];
  /** ID of the parent node the new successor connects from. */
  sourceNodeId: string;
  /**
   * For multi-child fan-out (gateway successors only): the index of this
   * successor in the parent's child list and the total number of siblings.
   * When omitted (or when totalSiblings <= 1), the new node is placed in
   * its parent's column — the tidy single-column layout used for plain
   * action / start nodes.
   */
  siblingIndex?: number;
  totalSiblings?: number;
  /** Whether the parent is a gateway (controls horizontal fan-out). */
  isGatewayChild?: boolean;
}

/** Options for placing a fresh event node (no parent). */
export interface PlaceNewEventOptions {
  /** All nodes currently on the canvas. */
  nodes: Node[];
  /** Optional explicit candidate position; defaults to the right of all existing flows. */
  candidate?: { x: number; y: number };
  /** Width of the new event node (defaults to START_NODE_WIDTH). */
  width?: number;
  /** Height of the new event node (defaults to START_NODE_HEIGHT). */
  height?: number;
}

// ============ Helpers ============

/**
 * Center X of a candidate child node directly under its parent so that the
 * two cards visually align in a single column.
 */
function columnAlignedX(parent: Node, childWidth: number): number {
  const parentWidth = parent.width || NODE_DIMENSIONS.CARD_WIDTH;
  return parent.position.x + (parentWidth - childWidth) / 2;
}

/**
 * Compute the X position for a gateway child given its sibling index and
 * count.  Two siblings sit symmetrically around the parent's center, three
 * siblings get one centered and the others to either side, and so on.
 */
function gatewayChildX(
  parent: Node,
  childWidth: number,
  siblingIndex: number,
  totalSiblings: number,
): number {
  const centerX = columnAlignedX(parent, childWidth);
  const step = childWidth + LAYOUT.NODE_SPACING_X;
  // Offset the index so the children are centered around the parent.
  // For 2 children: indices 0,1 -> offsets -0.5, +0.5
  // For 3 children: indices 0,1,2 -> offsets -1, 0, +1
  const offset = siblingIndex - (totalSiblings - 1) / 2;
  return centerX + offset * step;
}

// ============ Single-successor placement ============

/**
 * Place one new successor below its parent, optionally shifting downstream
 * nodes to make vertical room and shifting neighboring flows to make
 * horizontal room.  This is the primitive that drives quick-add,
 * drag-to-connect, the plugin API's `addNode` when the caller names a
 * `sourceNodeId`, and (via {@link simulateIncrementalBuild}) auto-layout.
 *
 * The plugin API's `addEdge` is deliberately NOT a caller: it only wires
 * two existing nodes (routing parallel edges where needed) and never
 * repositions either endpoint.
 *
 * The new successor must already exist in `options.nodes` with whatever
 * temporary position it was created at; this function returns an updated
 * node array in which the new node has been moved to its final position
 * and any shifts have been applied.
 *
 * @returns The updated nodes array and the position assigned to the new
 *          successor.  Callers that don't yet have the new node in the
 *          array can use {@link computeSuccessorPosition} instead.
 */
export function computeSuccessorPosition(options: PlaceSuccessorOptions): {
  position: { x: number; y: number };
  shiftNodeIds: Set<string>;
  shiftAmount: number;
} {
  const {
    nodes,
    edges,
    sourceNodeId,
    siblingIndex = 0,
    totalSiblings = 1,
    isGatewayChild = false,
  } = options;

  const sourceNode = nodes.find(n => n.id === sourceNodeId);
  if (!sourceNode) {
    return {
      position: { x: LAYOUT.DEFAULT_POSITION_X, y: LAYOUT.DEFAULT_POSITION_Y },
      shiftNodeIds: new Set(),
      shiftAmount: 0,
    };
  }

  const childWidth = NODE_DIMENSIONS.CARD_WIDTH;
  const childHeight = NODE_DIMENSIONS.CARD_HEIGHT;
  const sourceHeight = sourceNode.height || nodeHeightFallback(sourceNode);

  // Vertical: directly below the parent, using the standard row gap.
  const candidateY = sourceNode.position.y + sourceHeight + requiredVerticalGap();

  // Horizontal: gateway children fan out, plain successors stay in column.
  const candidateX = isGatewayChild && totalSiblings > 1
    ? gatewayChildX(sourceNode, childWidth, siblingIndex, totalSiblings)
    : columnAlignedX(sourceNode, childWidth);

  // Apply flow-aware collision avoidance: if the candidate lands on top of
  // an existing node, shift right within the source flow's safe zone, or
  // push neighboring flows further right to make room.
  return findFlowAwarePosition(
    { x: candidateX, y: candidateY },
    sourceNodeId,
    nodes,
    edges,
    childWidth,
    childHeight,
  );
}

/**
 * Apply the result of {@link computeSuccessorPosition} to a node array,
 * deselecting any previously selected nodes and shifting neighboring
 * flows where required.  Does **not** add the new successor itself —
 * callers are expected to push their newly created node onto the
 * returned array.
 */
export function applyFlowShifts(
  nodes: Node[],
  shiftNodeIds: Set<string>,
  shiftAmount: number,
  { deselectAll = true }: { deselectAll?: boolean } = {},
): Node[] {
  if (shiftAmount <= 0 && !deselectAll) {
    return nodes;
  }
  return nodes.map(n => {
    let node = deselectAll && n.selected ? { ...n, selected: false } : n;
    if (shiftAmount > 0 && n.id && shiftNodeIds.has(n.id)) {
      node = node === n ? { ...n } : node;
      node.position = { x: node.position.x + shiftAmount, y: node.position.y };
    }
    return node;
  });
}

// ============ New-event placement ============

/**
 * Compute the position for a brand-new event flow.  By default it sits to
 * the right of every existing flow at the topmost row, mirroring the
 * incremental "add event" UX.
 */
export function computeNewEventPosition(options: PlaceNewEventOptions): { x: number; y: number } {
  const { nodes, candidate, width, height } = options;
  const w = width ?? NODE_DIMENSIONS.START_NODE_WIDTH;
  const h = height ?? NODE_DIMENSIONS.START_NODE_HEIGHT;

  if (candidate) {
    return findFreePosition(candidate, nodes, w, h);
  }

  if (nodes.length === 0) {
    return { x: LAYOUT.LAYOUT_START_X, y: LAYOUT.LAYOUT_START_Y };
  }

  const maxX = Math.max(...nodes.map(n => n.position.x));
  const minY = Math.min(...nodes.map(n => n.position.y));
  return findFreePosition(
    { x: maxX + LAYOUT.NODE_SPACING_X, y: minY },
    nodes,
    w,
    h,
  );
}

// ============ Edge-insertion placement ============

/**
 * Position a node that has just been inserted on an existing edge,
 * splitting it into source→newNode and newNode→target.  Returns an
 * updated nodes array with the new node placed and the target node
 * (plus all its descendants) shifted downward if required.
 */
export function placeNodeOnEdge(
  nodes: Node[],
  edges: Edge[],
  newNode: Node,
  sourceNodeId: string,
  targetNodeId: string,
): Node[] {
  const sourceNode = nodes.find(n => n.id === sourceNodeId);
  const targetNode = nodes.find(n => n.id === targetNodeId);

  if (!sourceNode || !targetNode) {
    return [...nodes, newNode];
  }

  const sourceBottom = sourceNode.position.y +
    (sourceNode.height || NODE_DIMENSIONS.CARD_HEIGHT);

  // X aligns under the source.
  const newNodeX = columnAlignedX(sourceNode, NODE_DIMENSIONS.CARD_WIDTH);
  // Y sits one row gap below the source.
  const newNodeY = sourceBottom + requiredVerticalGap();

  const positionedNewNode: Node = {
    ...newNode,
    position: { x: newNodeX, y: newNodeY },
  };

  // Shift target (and descendants) downward if there is not enough room.
  const newNodeBottom = newNodeY + NODE_DIMENSIONS.CARD_HEIGHT;
  const desiredTargetY = newNodeBottom + requiredVerticalGap();
  const shift = Math.max(0, desiredTargetY - targetNode.position.y);

  const descendants = collectDescendants(targetNodeId, edges);
  descendants.add(targetNodeId);

  const updated = nodes.map(n => {
    if (shift > 0 && descendants.has(n.id)) {
      return { ...n, position: { x: n.position.x, y: n.position.y + shift } };
    }
    return n;
  });

  return [...updated, positionedNewNode];
}

// ============ Condition-adjacency invariant ============

/**
 * Build a synthetic gateway node matching the data shape minted at load
 * time by useModelDataLoader's createGatewayComponent (type 'gateway',
 * componentType 6, plugin 'gateway').  Position is temporary — callers
 * position the gateway via the placement primitives.
 */
function buildGatewayNode(): Node {
  return {
    id: generateNodeId(t('Gateway'), 'gateway'),
    type: 'gateway',
    // Temporary position — the caller positions this node.
    position: { x: 0, y: 0 },
    data: {
      // Node-level `type: 'gateway'` identifies the gateway; node data
      // mirrors the persistent shape of other gateway nodes (componentType
      // 6, plugin 'gateway') — NodeData has no `type` field.
      componentType: 6,
      plugin: 'gateway',
      label: t('Gateway'),
    },
  };
}

/** Build a plain default edge between two node ids. */
function buildDefaultEdge(source: string, target: string): Edge {
  return {
    id: generateEdgeId(source, target),
    source,
    target,
    type: 'default' as const,
    data: {},
  };
}

/** Options describing the intended insertion of a new condition node. */
export interface BuildConditionInsertionOptions {
  /** Source node id of the edge the condition is being inserted on. */
  sourceNodeId: string;
  /** Target node id of the edge the condition is being inserted on. */
  targetNodeId: string;
  /** The fully-formed condition node to insert (position is set by caller). */
  conditionNode: Node;
  /** Whether the edge's source node is itself a condition node. */
  sourceIsCondition: boolean;
  /** Whether the edge's target node is itself a condition node. */
  targetIsCondition: boolean;
}

/**
 * Result of {@link buildConditionInsertion}: the nodes and edges to add to
 * realize the insertion while preserving the "no two adjacent conditions"
 * invariant.  Callers position every node in `nodesToAdd`.
 */
export interface ConditionInsertionResult {
  /**
   * Nodes to add, in execution order: the condition node first, followed by
   * any gateway node(s) that must separate adjacent conditions.
   */
  nodesToAdd: Node[];
  /** Edges to add wiring source -> ... -> target through the new nodes. */
  edgesToAdd: Edge[];
}

/**
 * Build the set of nodes and edges required to insert a NEW condition node
 * on the edge `sourceNodeId -> targetNodeId`, guaranteeing that no two
 * condition nodes end up directly adjacent (issue #3589093).
 *
 * Conditions are first-class nodes; chaining two conditions directly is
 * semantically meaningless, so a gateway node is inserted between any pair
 * that would otherwise become adjacent.  Execution order
 * (source -> ... -> target) is always preserved.
 *
 * Four cases (the condition node is referred to as `cond`):
 *
 *   1. Neither end is a condition:
 *        source -> cond -> target
 *        nodesToAdd = [cond]
 *   2. Target IS a condition (inserting on source -> condB):
 *        source -> cond -> gateway -> condB
 *        nodesToAdd = [cond, gateway]
 *   3. Source IS a condition (inserting on condA -> target):
 *        condA -> gateway -> cond -> target
 *        nodesToAdd = [cond, gateway]
 *   4. BOTH ends are conditions (defensive — should not occur post-invariant):
 *        condA -> gateway1 -> cond -> gateway2 -> condB
 *        nodesToAdd = [cond, gateway1, gateway2]
 *
 * This function is pure: it does NOT position nodes.  Callers position the
 * condition node and any gateway(s) via placeNodeOnEdge /
 * computeSuccessorPosition exactly as they position single insertions today.
 */
export function buildConditionInsertion(
  opts: BuildConditionInsertionOptions,
): ConditionInsertionResult {
  const { sourceNodeId, targetNodeId, conditionNode, sourceIsCondition, targetIsCondition } = opts;
  const condId = conditionNode.id;

  // Case 1: neither end is a condition — current behavior.
  if (!sourceIsCondition && !targetIsCondition) {
    return {
      nodesToAdd: [conditionNode],
      edgesToAdd: [
        buildDefaultEdge(sourceNodeId, condId),
        buildDefaultEdge(condId, targetNodeId),
      ],
    };
  }

  // Case 4: both ends are conditions — two gateways so neither pair touches.
  if (sourceIsCondition && targetIsCondition) {
    const gateway1 = buildGatewayNode();
    const gateway2 = buildGatewayNode();
    return {
      nodesToAdd: [conditionNode, gateway1, gateway2],
      edgesToAdd: [
        buildDefaultEdge(sourceNodeId, gateway1.id),
        buildDefaultEdge(gateway1.id, condId),
        buildDefaultEdge(condId, gateway2.id),
        buildDefaultEdge(gateway2.id, targetNodeId),
      ],
    };
  }

  // Case 3: source is a condition — condA -> gateway -> cond -> target.
  if (sourceIsCondition) {
    const gateway = buildGatewayNode();
    return {
      nodesToAdd: [conditionNode, gateway],
      edgesToAdd: [
        buildDefaultEdge(sourceNodeId, gateway.id),
        buildDefaultEdge(gateway.id, condId),
        buildDefaultEdge(condId, targetNodeId),
      ],
    };
  }

  // Case 2: target is a condition — source -> cond -> gateway -> condB.
  const gateway = buildGatewayNode();
  return {
    nodesToAdd: [conditionNode, gateway],
    edgesToAdd: [
      buildDefaultEdge(sourceNodeId, condId),
      buildDefaultEdge(condId, gateway.id),
      buildDefaultEdge(gateway.id, targetNodeId),
    ],
  };
}

/**
 * Determine whether a node is a condition node (issue #3589093).
 * A node is a condition if its type is `condition` or its data carries the
 * `__isConditionNode` flag.
 */
export function isConditionNode(node: Node | undefined): boolean {
  if (!node) return false;
  return node.type === 'condition' || node.data?.__isConditionNode === true;
}

/**
 * Position a chain of newly-inserted nodes on an existing edge.
 *
 * This generalizes {@link placeNodeOnEdge} to a *sequence* of nodes
 * (e.g. condition + gateway) inserted between `sourceNodeId` and
 * `targetNodeId`.  Each node is stacked one row-gap below the previous,
 * and the target node (plus its descendants) is shifted down to make room
 * for the whole chain.  Nodes are placed column-aligned under the source,
 * matching the single-node insertion look.
 *
 * The vertical stacking order follows the **flow order** of the chain along
 * the edges (`sourceNodeId -> ... -> targetNodeId`), NOT the order the nodes
 * happen to appear in the `chain` array.  The `chain` array order is an
 * implementation detail of {@link buildConditionInsertion} (which always
 * returns the condition first) and does not necessarily match the execution
 * order — e.g. Case 3 wires `condA -> gateway -> cond -> target` but returns
 * `chain = [cond, gateway]`.  Stacking by array order would put the
 * condition above the gateway even though the gateway comes first in the
 * flow (issue #3589093).  We therefore walk the edges to recover the true
 * flow order before assigning Y positions.
 *
 * @param nodes      Current nodes on the canvas (the new nodes are NOT yet present).
 * @param edges      The edge set AFTER rewiring (drives both the flow-order
 *                   walk and the descendant collection for the target shift).
 * @param chain      The new nodes to place (array order is irrelevant — flow order wins).
 * @param sourceNodeId Source node id (chain hangs below this).
 * @param targetNodeId Target node id (shifted down to clear the chain).
 */
export function placeChainOnEdge(
  nodes: Node[],
  edges: Edge[],
  chain: Node[],
  sourceNodeId: string,
  targetNodeId: string,
): Node[] {
  if (chain.length === 0) return nodes;

  const sourceNode = nodes.find(n => n.id === sourceNodeId);
  const targetNode = nodes.find(n => n.id === targetNodeId);

  if (!sourceNode || !targetNode) {
    return [...nodes, ...chain];
  }

  // Order the chain by flow (source -> ... -> target) so the node that
  // executes first sits highest, regardless of the chain array's order.
  const orderedChain = orderChainByFlow(chain, edges, sourceNodeId, targetNodeId);

  const gap = requiredVerticalGap();
  const newNodeX = columnAlignedX(sourceNode, NODE_DIMENSIONS.CARD_WIDTH);
  let cursorY = sourceNode.position.y +
    (sourceNode.height || NODE_DIMENSIONS.CARD_HEIGHT);

  const positionedChain: Node[] = orderedChain.map(node => {
    cursorY += gap;
    const nodeHeight = isGatewayNode(node) ? NODE_DIMENSIONS.GATEWAY_HEIGHT : NODE_DIMENSIONS.CARD_HEIGHT;
    const positioned: Node = {
      ...node,
      position: { x: newNodeX, y: cursorY },
    };
    cursorY += nodeHeight;
    return positioned;
  });

  // Shift target (and descendants) downward if there is not enough room
  // below the last node in the chain.
  const desiredTargetY = cursorY + gap;
  const shift = Math.max(0, desiredTargetY - targetNode.position.y);

  const descendants = collectDescendants(targetNodeId, edges);
  descendants.add(targetNodeId);

  const updated = nodes.map(n => {
    if (shift > 0 && descendants.has(n.id)) {
      return { ...n, position: { x: n.position.x, y: n.position.y + shift } };
    }
    return n;
  });

  return [...updated, ...positionedChain];
}

/**
 * Order the chain nodes by their position along the flow from
 * `sourceNodeId` to `targetNodeId`, using the (already-rewired) `edges`.
 *
 * The walk starts at `sourceNodeId` and repeatedly follows the single
 * outgoing edge whose target is one of the not-yet-visited chain nodes,
 * appending that chain node and continuing from it.  It stops when it
 * reaches `targetNodeId`, when no further chain node is reachable, or once
 * every chain node has been ordered.  For the four insertion cases this
 * yields:
 *
 *   - Case 1 (`source -> cond -> target`):                 [cond]
 *   - Case 2 (`source -> cond -> gateway -> condB`):       [cond, gateway]
 *   - Case 3 (`condA -> gateway -> cond -> target`):       [gateway, cond]
 *   - Case 4 (`condA -> gw1 -> cond -> gw2 -> condB`):     [gw1, cond, gw2]
 *
 * Defensive fallback: if the walk cannot order every chain node (e.g. the
 * edges do not wire the chain end-to-end, or a chain node is unreachable
 * from the source), the original `chain` array order is returned unchanged
 * so callers never crash or drop nodes.
 */
function orderChainByFlow(
  chain: Node[],
  edges: Edge[],
  sourceNodeId: string,
  targetNodeId: string,
): Node[] {
  const chainById = new Map<string, Node>();
  for (const node of chain) chainById.set(node.id, node);

  const ordered: Node[] = [];
  const visited = new Set<string>();
  let current = sourceNodeId;

  // Walk forward at most `chain.length` hops; each hop must land on a
  // distinct, not-yet-visited chain node.
  while (ordered.length < chain.length) {
    const nextEdge = edges.find(
      e =>
        e.source === current &&
        chainById.has(e.target) &&
        !visited.has(e.target),
    );
    if (!nextEdge) break;

    const nextNode = chainById.get(nextEdge.target)!;
    ordered.push(nextNode);
    visited.add(nextNode.id);
    current = nextNode.id;

    if (current === targetNodeId) break;
  }

  // If the walk ordered every chain node, use the flow order; otherwise
  // fall back to the caller-provided array order (defensive).
  return ordered.length === chain.length ? ordered : chain;
}

/**
 * Collect all descendant node IDs reachable from `startId` via directed
 * edges.  Used by edge-insertion to know which nodes must shift down.
 */
function collectDescendants(startId: string, edges: Edge[]): Set<string> {
  const descendants = new Set<string>();
  const queue = [startId];
  while (queue.length > 0) {
    const current = queue.shift()!;
    for (const edge of edges) {
      if (edge.source === current && !descendants.has(edge.target)) {
        descendants.add(edge.target);
        queue.push(edge.target);
      }
    }
  }
  return descendants;
}

// ============ Auto-layout: simulate incremental build ============

/**
 * Identify start nodes for layout simulation.  A node qualifies as a
 * start if it is typed `start`, or has zero incoming edges, or its
 * plugin name contains `event`.  The list is sorted so explicit
 * `start`-typed nodes come first (preserving the user's mental model).
 */
function identifyStartNodes(nodes: Node[], edges: Edge[]): Node[] {
  const inDegree = new Map<string, number>();
  for (const node of nodes) inDegree.set(node.id, 0);
  for (const edge of edges) {
    if (inDegree.has(edge.target)) {
      inDegree.set(edge.target, (inDegree.get(edge.target) || 0) + 1);
    }
  }

  const candidates = nodes.filter(node =>
    node.type === 'start' ||
    inDegree.get(node.id) === 0 ||
    (node.data?.plugin && String(node.data.plugin).includes('event'))
  );

  candidates.sort((a, b) => {
    if (a.type === 'start' && b.type !== 'start') return -1;
    if (b.type === 'start' && a.type !== 'start') return 1;
    return 0;
  });

  if (candidates.length > 0) return candidates;
  // Fallback: nodes with zero in-degree (covers cycles where no node has
  // type === 'start' and no plugin is an event).
  const orphans = nodes.filter(node => inDegree.get(node.id) === 0);
  if (orphans.length > 0) return orphans;
  return nodes.length > 0 ? [nodes[0]] : [];
}

/**
 * Build an outgoing-edge adjacency map preserving the order in which
 * children appear in the edge array.  Used to drive the simulation in a
 * deterministic, user-meaningful order.
 */
function buildAdjacency(nodes: Node[], edges: Edge[]): Map<string, string[]> {
  const adj = new Map<string, string[]>();
  for (const node of nodes) adj.set(node.id, []);
  for (const edge of edges) {
    if (adj.has(edge.source) && adj.has(edge.target)) {
      adj.get(edge.source)!.push(edge.target);
    }
  }
  return adj;
}

/**
 * Decide whether a node should branch its children horizontally
 * (gateway-style) or keep them in a single column (action-style).
 * Only nodes typed `gateway` (or with `data.nodeType === 'gateway'`)
 * fan out — matching the user's mental model where gateways are the
 * explicit branching primitive.
 */
function isGatewayNode(node: Node | undefined): boolean {
  if (!node) return false;
  if (node.type === 'gateway') return true;
  if (node.data?.nodeType === 'gateway') return true;
  return false;
}

/**
 * Return the correct height fallback for a node based on its type.
 * Gateway nodes use a compact height; all others use the standard card height.
 */
function nodeHeightFallback(node: Node): number {
  return isGatewayNode(node) ? NODE_DIMENSIONS.GATEWAY_HEIGHT : NODE_DIMENSIONS.CARD_HEIGHT;
}

/**
 * Lay out a complete graph by simulating the incremental build process.
 *
 * Algorithm:
 *
 *   1. Identify start nodes and place each one to the right of the
 *      previously laid out flow.  The first start node anchors at
 *      `LAYOUT_START_X / LAYOUT_START_Y`.
 *   2. For each start node, BFS-traverse its descendants.  When visiting
 *      a node, queue every not-yet-placed child but only place it once
 *      *all* of its forward-reachable parents have been placed.  This
 *      ensures convergent (multi-parent) nodes know every parent's
 *      position before computing their own.
 *   3. Single-parent placement uses the same primitive as quick-add:
 *      column-aligned for non-gateway parents, fan-out for gateways.
 *   4. Multi-parent (convergent) placement uses the centroid of the
 *      parents' centers — naturally centering merge points under a
 *      common ancestor (e.g. event with two condition branches that
 *      converge into a single action).
 *   5. Cycle back-edges are excluded from the "all parents placed"
 *      requirement to break deadlocks; nodes still waiting at the end
 *      of BFS are placed using whichever parents have already been
 *      laid out, falling back to first-parent column when none are.
 *   6. Any nodes that remain unplaced after all reachable traversals
 *      (fully disconnected components) are placed to the right of the
 *      last laid-out flow as fresh events.
 *
 * @param nodes - Nodes to lay out (their existing positions are ignored
 *                and replaced).
 * @param edges - Edges driving the traversal order.
 * @returns A new node array with the same nodes but freshly assigned
 *          positions.  Returns `null` for empty input to preserve the
 *          existing autoLayout contract.
 */
export function simulateIncrementalBuild(nodes: Node[], edges: Edge[]): Node[] | null {
  if (!nodes || nodes.length === 0) return null;
  const safeEdges = edges || [];

  // Single start at the layout origin; subsequent starts step one flow
  // gap to the right of the previous flow's rightmost node.
  const startNodes = identifyStartNodes(nodes, safeEdges);
  const adjacency = buildAdjacency(nodes, safeEdges);

  // Build incoming-edge map so we can ask "who are this node's parents?".
  const incoming = new Map<string, string[]>();
  for (const node of nodes) incoming.set(node.id, []);
  for (const edge of safeEdges) {
    if (incoming.has(edge.target) && incoming.has(edge.source)) {
      incoming.get(edge.target)!.push(edge.source);
    }
  }

  // Working node array.  We seed it with each start node's anchor as we
  // process them, so flow-aware placement can see existing flows.
  let working: Node[] = [];
  const placed = new Set<string>();

  // Reusable map from original node ID to the original node object, so
  // we can read its type/data when needed without searching.
  const nodeById = new Map<string, Node>();
  for (const node of nodes) nodeById.set(node.id, node);

  /**
   * Place a node that has at least one already-placed parent.  Single
   * parent → use the column-aligned / fan-out primitive.  Multiple
   * parents → use the centroid of placed parents.
   */
  const placeChildNow = (childId: string, parentId: string, siblingIndex: number, totalSiblings: number): void => {
    const childOriginal = nodeById.get(childId);
    if (!childOriginal) return;

    const placedParents = (incoming.get(childId) || []).filter(p => placed.has(p));
    const parentObjects = placedParents
      .map(p => working.find(n => n.id === p))
      .filter((n): n is Node => n !== undefined);

    let position: { x: number; y: number };
    let shiftNodeIds = new Set<string>();
    let shiftAmount = 0;

    if (parentObjects.length >= 2) {
      // Convergent node: centroid of parent centers, Y below the lowest parent.
      const childWidth = childOriginal.width || NODE_DIMENSIONS.CARD_WIDTH;
      const childHeight = childOriginal.height || nodeHeightFallback(childOriginal);

      let centerXSum = 0;
      let maxBottom = -Infinity;
      for (const parent of parentObjects) {
        const pw = parent.width || NODE_DIMENSIONS.CARD_WIDTH;
        const ph = parent.height || nodeHeightFallback(parent);
        centerXSum += parent.position.x + pw / 2;
        const bottom = parent.position.y + ph;
        if (bottom > maxBottom) maxBottom = bottom;
      }
      const centroidX = centerXSum / parentObjects.length;
      const candidateX = centroidX - childWidth / 2;
      const candidateY = maxBottom + requiredVerticalGap();

      // Use flow-aware positioning anchored on the first placed parent so
      // collision avoidance and neighbor-flow shifts still work.  We feed
      // the centroid candidate; if it overlaps a same-flow node, the
      // primitive will adjust within the flow's safe zone.
      const anchorParentId = parentObjects[0].id!;
      const result = findFlowAwarePosition(
        { x: candidateX, y: candidateY },
        anchorParentId,
        working,
        safeEdges,
        childWidth,
        childHeight,
      );
      position = result.position;
      shiftNodeIds = result.shiftNodeIds;
      shiftAmount = result.shiftAmount;
    } else {
      // Single-parent placement — same code path as quick-add.
      const parentOriginal = nodeById.get(parentId);
      const isGateway = isGatewayNode(parentOriginal);

      const result = computeSuccessorPosition({
        nodes: working,
        edges: safeEdges,
        sourceNodeId: parentId,
        siblingIndex,
        totalSiblings,
        isGatewayChild: isGateway,
      });
      position = result.position;
      shiftNodeIds = result.shiftNodeIds;
      shiftAmount = result.shiftAmount;
    }

    if (shiftAmount > 0) {
      working = working.map(n => {
        if (n.id && shiftNodeIds.has(n.id)) {
          return { ...n, position: { x: n.position.x + shiftAmount, y: n.position.y } };
        }
        return n;
      });
    }

    const positionedChild: Node = { ...childOriginal, position };
    working = [...working, positionedChild];
    placed.add(childId);
  };

  for (const startNode of startNodes) {
    if (placed.has(startNode.id)) continue;

    // Place this start node to the right of all existing flows.
    const startPosition = computeNewEventPosition({
      nodes: working,
      candidate: working.length === 0
        ? { x: LAYOUT.LAYOUT_START_X, y: LAYOUT.LAYOUT_START_Y }
        : undefined,
      width: startNode.width || NODE_DIMENSIONS.START_NODE_WIDTH,
      height: startNode.height || NODE_DIMENSIONS.START_NODE_HEIGHT,
    });

    const positionedStart: Node = { ...startNode, position: startPosition };
    working = [...working, positionedStart];
    placed.add(startNode.id);

    // BFS from this start node.  Process each parent in dequeue order
    // so siblings keep their relative ordering from the edges array.
    // Children whose other parents haven't been placed yet are deferred
    // until the last of their parents is dequeued — this gives the
    // centroid placement complete information.
    const queue: string[] = [startNode.id];
    while (queue.length > 0) {
      const parentId = queue.shift()!;
      const childIds = adjacency.get(parentId) || [];

      // Determine which children are placeable now (all forward parents
      // already placed) versus deferred for a later iteration.
      const placeableNow: string[] = [];
      for (const childId of childIds) {
        if (placed.has(childId)) continue;
        const parentsOfChild = incoming.get(childId) || [];
        // Forward parents = parents that are not the child itself in a
        // cycle and that aren't downstream of childId.  We approximate
        // by counting whether every parent has been placed.  Cycle
        // back-edges will leave a parent unplaced, so we exclude
        // children whose parent is reachable only through them.
        const allParentsPlaced = parentsOfChild.every(p =>
          placed.has(p) || isOnlyReachableThrough(p, childId, adjacency, placed),
        );
        if (allParentsPlaced) placeableNow.push(childId);
      }

      const totalNew = placeableNow.length;
      placeableNow.forEach((childId, index) => {
        placeChildNow(childId, parentId, index, totalNew);
        queue.push(childId);
      });
    }

    // Sweep up any reachable children that were deferred but never
    // satisfied (cycles where one parent is downstream of the child).
    // Place them using whichever parents are already laid out.
    let progressed = true;
    while (progressed) {
      progressed = false;
      const queue2: string[] = [];
      for (const node of nodes) {
        if (placed.has(node.id)) continue;
        const parentsPlaced = (incoming.get(node.id) || []).some(p => placed.has(p));
        if (parentsPlaced) {
          const firstPlacedParent = (incoming.get(node.id) || []).find(p => placed.has(p))!;
          placeChildNow(node.id, firstPlacedParent, 0, 1);
          queue2.push(node.id);
          progressed = true;
        }
      }
      // Drain queue2 just to keep BFS-like ordering for any of their
      // children that now become placeable; the for-loop above will
      // pick them up on the next outer iteration anyway.
      void queue2;
    }
  }

  // Any nodes still unplaced are part of a fully disconnected sub-graph
  // (no path from any start node).  Treat each as its own event.
  for (const node of nodes) {
    if (placed.has(node.id)) continue;
    const position = computeNewEventPosition({
      nodes: working,
      width: node.width || NODE_DIMENSIONS.CARD_WIDTH,
      height: node.height || nodeHeightFallback(node),
    });
    working = [...working, { ...node, position }];
    placed.add(node.id);
  }

  return working;
}

/**
 * Heuristic: is `parentCandidate` only reachable through `childId`
 * (i.e. the edge `parentCandidate -> childId` is part of a back-edge
 * cycle)?  Returns true if every path from a placed node to
 * `parentCandidate` goes through `childId`, which means we should not
 * wait for `parentCandidate` to be placed before placing `childId`.
 *
 * Conservative: returns true only when `parentCandidate` is directly
 * downstream of `childId` (closing a simple cycle).  Larger cycles are
 * resolved by the post-BFS sweep instead.
 */
function isOnlyReachableThrough(
  parentCandidate: string,
  childId: string,
  adjacency: Map<string, string[]>,
  placed: Set<string>,
): boolean {
  if (placed.has(parentCandidate)) return false;
  // BFS forward from childId; if we reach parentCandidate, it's a
  // descendant of childId (i.e. the edge from parentCandidate to
  // childId is a back-edge).
  const visited = new Set<string>([childId]);
  const queue = [childId];
  while (queue.length > 0) {
    const current = queue.shift()!;
    for (const next of adjacency.get(current) || []) {
      if (next === parentCandidate) return true;
      if (!visited.has(next)) {
        visited.add(next);
        queue.push(next);
      }
    }
  }
  return false;
}
