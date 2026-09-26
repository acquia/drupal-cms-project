/**
 * Parallel-edge router.
 *
 * When the user adds a new edge between two nodes that are already connected
 * (either directly with an existing parallel edge, or indirectly via a chain
 * of intermediate nodes), the new edge would visually overlap the existing
 * connection in a vertical layout. This module computes a `controlOffset`
 * for the new edge — and, when appropriate, redistributes offsets across the
 * full sibling group — so that all parallel connections remain visually
 * distinguishable without moving any nodes.
 *
 * The router implements the hybrid strategy chosen for issue 3586864:
 *
 *   - **Direct parallels** (two edges sharing the exact same source/target):
 *     symmetric fan-out. With N siblings the offsets are distributed
 *     symmetrically around zero, so the original edge moves to one side and
 *     the new edge to the other.
 *
 *   - **Bypass parallels** (a new edge between source and target where an
 *     existing path runs through one or more intermediate nodes): the new
 *     edge gets a single sideways bypass curve that clears the bounding
 *     box of the intermediate chain.
 *
 * All output is expressed as `{ edgeId, controlOffset }` updates. Persisting
 * those updates is the caller's responsibility — this module is pure.
 */
import type { StoreEdge as Edge, StoreNode as Node } from '../types/settings';
import { NODE_DIMENSIONS } from '../constants/dimensions';

// ── Adjustable components ───────────────────────────────────────────────────

/**
 * Step size between adjacent siblings in a fan-out. The actual displacement
 * per edge in the symmetric distribution is a multiple of this value.
 */
export const PARALLEL_EDGE_FAN_STEP = 80;

/**
 * Extra clearance added to the bypass offset on top of the chain's bounding
 * box, so the new bypass curve does not graze condition cards or order badges
 * sitting on the existing chain edges.
 */
export const BYPASS_EDGE_CLEARANCE = 60;

// ── Public types ────────────────────────────────────────────────────────────

export interface ControlOffset {
  x: number;
  y: number;
}

export interface EdgeRouteUpdate {
  edgeId: string;
  controlOffset: ControlOffset;
}

/**
 * The kind of routing that was applied. Useful for tests, logging, and for
 * callers that want to react differently (e.g., undo descriptions).
 */
export type ParallelEdgeRouting = 'none' | 'fan-out' | 'bypass';

export interface ParallelEdgeRouteResult {
  routing: ParallelEdgeRouting;
  /**
   * Updates to apply. Always contains an entry for the new edge when routing
   * is 'fan-out' or 'bypass'. May contain entries for existing siblings when
   * fan-out re-balances the group.
   */
  updates: EdgeRouteUpdate[];
}

// ── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Equality check for control offsets, using a small epsilon to absorb any
 * floating-point drift from previous round-trips through JSON.
 */
function offsetsMatch(a: ControlOffset, b: ControlOffset): boolean {
  return Math.abs(a.x - b.x) < 0.5 && Math.abs(a.y - b.y) < 0.5;
}

/**
 * Find every existing edge that shares the same (source, target) pair.
 * The new edge is excluded by id so this works whether or not it has been
 * appended to the array yet.
 */
export function findDirectParallelEdges(
  source: string,
  target: string,
  edges: Edge[],
  excludeEdgeId?: string,
): Edge[] {
  return edges.filter(
    (e) =>
      e.id !== excludeEdgeId &&
      e.source === source &&
      e.target === target,
  );
}

/**
 * BFS-search the directed edge graph for the shortest path of node ids from
 * `source` to `target`, considering only edges other than `excludeEdgeId`.
 *
 * Returns the path as a sequence of node ids that starts with `source` and
 * ends with `target`, or `null` if no such path exists. A direct edge yields
 * `[source, target]`, length 2 — callers must treat that case as "not a
 * chain bypass" because the intermediate set is empty.
 */
export function findIntermediatePath(
  source: string,
  target: string,
  edges: Edge[],
  excludeEdgeId?: string,
): string[] | null {
  if (source === target) return null;

  // Build adjacency list once, skipping the new edge so we measure the
  // graph as it was before this edge was added.
  const adj = new Map<string, string[]>();
  for (const edge of edges) {
    if (edge.id === excludeEdgeId) continue;
    const neighbors = adj.get(edge.source);
    if (neighbors) {
      neighbors.push(edge.target);
    } else {
      adj.set(edge.source, [edge.target]);
    }
  }

  const previous = new Map<string, string | null>();
  previous.set(source, null);
  const queue: string[] = [source];

  while (queue.length > 0) {
    const current = queue.shift()!;
    if (current === target) {
      // Reconstruct the path from target back to source.
      const path: string[] = [];
      let step: string | null = current;
      while (step !== null) {
        path.unshift(step);
        step = previous.get(step) ?? null;
      }
      return path;
    }
    const neighbors = adj.get(current);
    if (!neighbors) continue;
    for (const next of neighbors) {
      if (!previous.has(next)) {
        previous.set(next, current);
        queue.push(next);
      }
    }
  }
  return null;
}

/**
 * Compute the symmetric fan-out offsets for N edges.
 *
 * The returned array has length N. Each entry is the X-offset for the
 * corresponding edge, distributed symmetrically around 0:
 *
 *   N=1 → [0]
 *   N=2 → [-step, +step]
 *   N=3 → [-step, 0, +step]
 *   N=4 → [-1.5*step, -0.5*step, +0.5*step, +1.5*step]
 *
 * The pattern keeps the visual centroid of the group at zero, so adding new
 * siblings appears as a balanced spreading rather than a one-sided shift.
 */
export function computeFanOutOffsets(
  count: number,
  step: number = PARALLEL_EDGE_FAN_STEP,
): number[] {
  if (count <= 0) return [];
  if (count === 1) return [0];
  const offsets: number[] = [];
  const center = (count - 1) / 2;
  for (let i = 0; i < count; i++) {
    offsets.push((i - center) * step);
  }
  return offsets;
}

/**
 * Returns true when every edge in the group has a default (zero) control
 * offset. That is the precondition for safely overwriting offsets during a
 * fan-out rebalance: if any sibling has a non-zero offset, we treat that as
 * a user-customized curve and leave the entire group alone.
 *
 * Choosing "all zero" rather than "all equal" is the more conservative
 * policy. After a previous fan-out the existing siblings will be at
 * non-zero positions, so subsequent additions will not rebalance them
 * either — but they still get an offset for the new edge that lands
 * outside the existing fan, which is what the fallback branch produces.
 */
function siblingsHaveDefaultOffset(siblings: Edge[]): boolean {
  return siblings.every((edge) => {
    const offset = edge.data?.controlOffset ?? { x: 0, y: 0 };
    return offsetsMatch(offset, { x: 0, y: 0 });
  });
}

/**
 * Compute the horizontal extent (min-left to max-right edge) of a node set.
 * Returns null when the set is empty.
 */
function getHorizontalExtent(
  nodeIds: Set<string>,
  nodes: Node[],
): { minX: number; maxX: number } | null {
  let minX = Infinity;
  let maxX = -Infinity;
  let found = false;
  for (const node of nodes) {
    if (!nodeIds.has(node.id)) continue;
    const width = node.width || NODE_DIMENSIONS.CARD_WIDTH;
    minX = Math.min(minX, node.position.x);
    maxX = Math.max(maxX, node.position.x + width);
    found = true;
  }
  return found ? { minX, maxX } : null;
}

// ── Main entry point ────────────────────────────────────────────────────────

interface RouteParams {
  /** The newly-created edge (already in the edges array, or about to be). */
  newEdge: Edge;
  /** Full edge list, optionally including newEdge. */
  edges: Edge[];
  /** Full node list, used to size bypass curves around intermediate chains. */
  nodes: Node[];
}

/**
 * Compute control-offset updates needed to keep `newEdge` visually distinct
 * from any existing parallel edges or chains between the same endpoints.
 *
 * The returned `updates` array is empty when no parallel collision is
 * detected; otherwise it always contains an update for `newEdge` and may
 * contain updates for existing siblings (fan-out rebalance).
 */
export function routeParallelEdge({
  newEdge,
  edges,
  nodes,
}: RouteParams): ParallelEdgeRouteResult {
  const { source, target, id } = newEdge;
  if (!source || !target || source === target) {
    return { routing: 'none', updates: [] };
  }

  // ── Case A: direct parallel — at least one other edge shares (source, target).
  const directSiblings = findDirectParallelEdges(source, target, edges, id);
  if (directSiblings.length > 0) {
    // Order the group: existing siblings in their current order, then the new
    // edge appended last. This puts the new edge at the outermost position in
    // the symmetric distribution, which feels natural ("the latest addition
    // sits at the edge of the fan").
    const groupInOrder = [...directSiblings, newEdge];

    // Only rebalance siblings if they all currently have the default
    // zero offset; that ensures we never overwrite a user-customized
    // curve and that previously fan-routed groups stay stable.
    const canRebalance = siblingsHaveDefaultOffset(directSiblings);

    if (canRebalance) {
      const fan = computeFanOutOffsets(groupInOrder.length);
      const updates: EdgeRouteUpdate[] = [];
      groupInOrder.forEach((edge, index) => {
        // Every edge is plain now (conditions are first-class nodes, issue
        // #3589093), so siblings simply fan out symmetrically.
        const newOffset: ControlOffset = { x: fan[index], y: 0 };
        const existingOffset =
          edge.data?.controlOffset ?? { x: 0, y: 0 };
        if (!offsetsMatch(existingOffset, newOffset)) {
          updates.push({ edgeId: edge.id, controlOffset: newOffset });
        }
      });
      return { routing: 'fan-out', updates };
    }

    // The user has hand-positioned the existing siblings; don't touch them.
    // Place the new edge just outside the group on the side furthest from
    // any existing offset, so it remains distinguishable.
    const existingXOffsets = directSiblings.map(
      (e) => e.data?.controlOffset?.x ?? 0,
    );
    const maxAbs = existingXOffsets.reduce(
      (max, x) => Math.max(max, Math.abs(x)),
      0,
    );
    // Push to the side opposite the average, so the new edge balances the
    // visual weight of the existing group.
    const avg =
      existingXOffsets.reduce((sum, x) => sum + x, 0) /
      existingXOffsets.length;
    const sign = avg <= 0 ? 1 : -1;
    const newX = sign * (maxAbs + PARALLEL_EDGE_FAN_STEP);
    return {
      routing: 'fan-out',
      updates: [
        { edgeId: id, controlOffset: { x: newX, y: 0 } },
      ],
    };
  }

  // ── Case B: bypass parallel — an existing path connects source to target.
  const path = findIntermediatePath(source, target, edges, id);
  if (path && path.length > 2) {
    // The intermediate set excludes source and target themselves.
    const intermediateIds = new Set(path.slice(1, -1));

    // Compute the bypass offset from the rightmost edge of the chain so the
    // new curve clears the entire chain plus any condition cards on it.
    const sourceNode = nodes.find((n) => n.id === source);
    const targetNode = nodes.find((n) => n.id === target);
    if (!sourceNode || !targetNode) {
      return { routing: 'none', updates: [] };
    }

    const sourceWidth = sourceNode.width || NODE_DIMENSIONS.CARD_WIDTH;
    const targetWidth = targetNode.width || NODE_DIMENSIONS.CARD_WIDTH;
    const sourceCenterX = sourceNode.position.x + sourceWidth / 2;
    const targetCenterX = targetNode.position.x + targetWidth / 2;
    const edgeMidX = (sourceCenterX + targetCenterX) / 2;

    // Include source and target in the extent so the chain's bounding box
    // accounts for them too — otherwise a single intermediate node directly
    // beneath the column would yield a near-zero offset.
    const extentIds = new Set<string>(intermediateIds);
    extentIds.add(source);
    extentIds.add(target);
    const extent = getHorizontalExtent(extentIds, nodes);
    if (!extent) {
      return { routing: 'none', updates: [] };
    }

    // Choose the side with more headroom. By default we route to the right
    // of the chain; if the chain extends further to the right than to the
    // left of the source/target column, route to the left instead.
    const rightHeadroom = extent.maxX - edgeMidX;
    const leftHeadroom = edgeMidX - extent.minX;
    const routeRight = rightHeadroom <= leftHeadroom;

    // Distance from the edge's natural midpoint to the far edge of the chain,
    // plus a clearance margin for cards/badges.
    const offsetMagnitude =
      (routeRight ? rightHeadroom : leftHeadroom) + BYPASS_EDGE_CLEARANCE;
    const x = routeRight ? offsetMagnitude : -offsetMagnitude;

    return {
      routing: 'bypass',
      updates: [{ edgeId: id, controlOffset: { x, y: 0 } }],
    };
  }

  // No existing connection between source and target → nothing to route.
  return { routing: 'none', updates: [] };
}

/**
 * Apply parallel edge routing updates to an edge array.
 *
 * Takes the result of `routeParallelEdge` and applies the control offset
 * updates to the provided edge array, returning a new array with the updates
 * applied.
 *
 * @param edges - The current edge array
 * @param updates - The routing updates from `routeParallelEdge`
 * @returns A new edge array with control offsets applied
 */
export function applyParallelEdgeRouting(
  edges: Edge[],
  updates: EdgeRouteUpdate[],
): Edge[] {
  if (updates.length === 0) return edges;

  const updateMap = new Map(updates.map((u) => [u.edgeId, u.controlOffset]));

  return edges.map((edge) => {
    const offset = updateMap.get(edge.id);
    if (offset) {
      return {
        ...edge,
        data: {
          ...edge.data,
          controlOffset: offset,
        },
      };
    }
    return edge;
  });
}

// ── Batch routing for auto-layout ───────────────────────────────────────────

/**
 * Route all parallel edges in a batch after auto-layout.
 *
 * During auto-layout (model load without raw positional data), edges are
 * created with a default `controlOffset` of `{ x: 0, y: 0 }`.  When
 * multiple edges share the same `(source, target)` pair they overlap
 * visually because nothing sets their offsets apart.
 *
 * This function groups edges by their `(source, target)` key and applies
 * the same symmetric fan-out logic as the interactive
 * {@link routeParallelEdge}, so parallel edges spread out horizontally
 * just as they would if the user had added them one by one.
 *
 * @param edges - The full edge array (with default control offsets).
 * @returns A new edge array with `controlOffset` set for every edge that
 *          belongs to a parallel group of 2 or more.
 */
export function routeAllParallelEdges(edges: Edge[]): Edge[] {
  // Group edges by (source, target) key.
  const groups = new Map<string, Edge[]>();
  for (const edge of edges) {
    const key = `${edge.source}\u0000${edge.target}`;
    const group = groups.get(key);
    if (group) {
      group.push(edge);
    } else {
      groups.set(key, [edge]);
    }
  }

  // Collect updates for every group that has 2+ edges.
  const allUpdates: EdgeRouteUpdate[] = [];
  for (const group of groups.values()) {
    if (group.length < 2) continue;

    const fan = computeFanOutOffsets(group.length);
    group.forEach((edge, index) => {
      allUpdates.push({
        edgeId: edge.id,
        controlOffset: { x: fan[index], y: 0 },
      });
    });
  }

  return applyParallelEdgeRouting(edges, allUpdates);
}
