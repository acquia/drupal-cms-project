/**
 * Position utilities for finding free (non-overlapping) positions on the canvas.
 * Used by quick-add, event-add, and drag-and-drop to place new nodes without
 * overlapping existing ones.
 *
 * Unlike layoutHelpers.ts (which uses center-based coordinates for auto-layout),
 * these functions work with ReactFlow's top-left coordinate system directly.
 */

import { LAYOUT, NODE_DIMENSIONS } from '../constants/dimensions';

// ============ Vertical Gap Helpers ============

/**
 * Calculate the required vertical gap between the bottom of one node and the
 * top of the next, using the same spacing values as auto-layout.
 *
 * Conditions are first-class nodes now (issue #3589093), so no edge ever
 * carries a condition card.  The gap is therefore always the plain row
 * spacing — there is no longer any condition-specific spacing.
 *
 * @returns Gap in pixels (top-left coordinate system)
 */
export function requiredVerticalGap(): number {
  return LAYOUT.NODE_SPACING_Y;
}

/**
 * Shift all nodes whose top-left Y position is at or below a threshold
 * downward by a given amount.  Used when inserting elements between existing
 * nodes to create room without running a full auto-layout pass.
 *
 * @param nodes        - Current node array
 * @param thresholdY   - Y coordinate; nodes with position.y >= this value are shifted
 * @param shiftAmount  - Pixels to shift downward (ignored when <= 0)
 * @param excludeIds   - Optional set of node IDs to exclude from shifting
 * @returns A new array with updated positions (unchanged nodes are returned by reference)
 */
export function shiftNodesDown<T extends { id?: string; position: { x: number; y: number } }>(
  nodes: T[],
  thresholdY: number,
  shiftAmount: number,
  excludeIds?: Set<string>,
): T[] {
  if (shiftAmount <= 0) return nodes;
  return nodes.map(node => {
    if (excludeIds && node.id && excludeIds.has(node.id)) return node;
    if (node.position.y >= thresholdY) {
      return { ...node, position: { x: node.position.x, y: node.position.y + shiftAmount } };
    }
    return node;
  });
}

interface NodeLike {
  id?: string;
  position: { x: number; y: number };
  width?: number | null;
  height?: number | null;
}

interface EdgeLike {
  source: string;
  target: string;
}

/** Padding around nodes when checking for overlap (px). */
const COLLISION_PADDING = 20;

/** Maximum number of offset attempts before giving up. */
const MAX_ATTEMPTS = 50;

/** Minimum horizontal gap between separate flows (px). */
const MIN_FLOW_GAP = LAYOUT.NODE_SPACING_X;

/**
 * Check whether a candidate rectangle overlaps any existing node.
 *
 * Both the candidate and existing nodes are treated as axis-aligned bounding
 * boxes using ReactFlow's top-left origin.  A configurable padding is added
 * around every existing node so that new nodes don't end up touching.
 */
function isOverlapping(
  candidateX: number,
  candidateY: number,
  candidateWidth: number,
  candidateHeight: number,
  existingNodes: NodeLike[],
  padding: number = COLLISION_PADDING,
): boolean {
  for (const node of existingNodes) {
    const nodeW = node.width || NODE_DIMENSIONS.DEFAULT_WIDTH;
    const nodeH = node.height || NODE_DIMENSIONS.DEFAULT_HEIGHT;

    // Expand the existing node's box by `padding` on every side.
    const ex1 = node.position.x - padding;
    const ey1 = node.position.y - padding;
    const ex2 = node.position.x + nodeW + padding;
    const ey2 = node.position.y + nodeH + padding;

    // Candidate box (no extra padding – the existing node already has it).
    const cx1 = candidateX;
    const cy1 = candidateY;
    const cx2 = candidateX + candidateWidth;
    const cy2 = candidateY + candidateHeight;

    // Standard AABB overlap test.
    if (cx1 < ex2 && cx2 > ex1 && cy1 < ey2 && cy2 > ey1) {
      return true;
    }
  }
  return false;
}

/**
 * Given a preferred position, return the closest free position that does not
 * overlap any existing node.
 *
 * The search strategy mirrors the auto-layout helper but operates in top-left
 * coordinates:
 *   1. Return the candidate immediately if it is free.
 *   2. Try shifting to the right in increments of (nodeWidth + spacingX).
 *   3. Try shifting downward in increments of (nodeHeight + spacingY).
 *   4. Try diagonal (right + down) shifts as a last resort.
 *
 * @param candidate  - Preferred { x, y } position (top-left corner).
 * @param existingNodes - All nodes currently on the canvas.
 * @param nodeWidth  - Width of the node being placed.
 * @param nodeHeight - Height of the node being placed.
 * @returns A position { x, y } guaranteed not to overlap (within MAX_ATTEMPTS).
 */
export function findFreePosition(
  candidate: { x: number; y: number },
  existingNodes: NodeLike[],
  nodeWidth: number = NODE_DIMENSIONS.DEFAULT_WIDTH,
  nodeHeight: number = NODE_DIMENSIONS.DEFAULT_HEIGHT,
): { x: number; y: number } {
  // Fast path: nothing to collide with.
  if (existingNodes.length === 0) {
    return { ...candidate };
  }

  // Fast path: candidate is already free.
  if (!isOverlapping(candidate.x, candidate.y, nodeWidth, nodeHeight, existingNodes)) {
    return { ...candidate };
  }

  const stepX = nodeWidth + LAYOUT.NODE_SPACING_X;  // 200 + 250 = 450
  const stepY = nodeHeight + LAYOUT.NODE_SPACING_Y;  // 100 + 150 = 250

  // Phase 1: try shifting right (up to ~17 attempts).
  const rightAttempts = Math.min(Math.ceil(MAX_ATTEMPTS / 3), MAX_ATTEMPTS);
  for (let i = 1; i <= rightAttempts; i++) {
    const x = candidate.x + stepX * i;
    if (!isOverlapping(x, candidate.y, nodeWidth, nodeHeight, existingNodes)) {
      return { x, y: candidate.y };
    }
  }

  // Phase 2: try shifting down (up to ~17 attempts).
  const downAttempts = Math.min(Math.ceil(MAX_ATTEMPTS / 3), MAX_ATTEMPTS);
  for (let i = 1; i <= downAttempts; i++) {
    const y = candidate.y + stepY * i;
    if (!isOverlapping(candidate.x, y, nodeWidth, nodeHeight, existingNodes)) {
      return { x: candidate.x, y };
    }
  }

  // Phase 3: try diagonal (right + down).
  const diagAttempts = MAX_ATTEMPTS - rightAttempts - downAttempts;
  for (let i = 1; i <= diagAttempts; i++) {
    const x = candidate.x + stepX * i;
    const y = candidate.y + stepY * i;
    if (!isOverlapping(x, y, nodeWidth, nodeHeight, existingNodes)) {
      return { x, y };
    }
  }

  // Fallback: exhausted all attempts – place it far to the right.
  return {
    x: candidate.x + stepX * (MAX_ATTEMPTS + 1),
    y: candidate.y,
  };
}

// ============ Flow-Aware Positioning ============

/**
 * Find all node IDs that belong to the same connected component (flow) as
 * the given node.  Edges are treated as undirected so that both upstream
 * and downstream neighbors are included.
 */
function getConnectedComponent(
  nodeId: string,
  allNodeIds: Set<string>,
  edges: EdgeLike[],
): Set<string> {
  // Build an undirected adjacency list.
  const adj = new Map<string, string[]>();
  for (const id of allNodeIds) {
    adj.set(id, []);
  }
  for (const edge of edges) {
    if (allNodeIds.has(edge.source) && allNodeIds.has(edge.target)) {
      adj.get(edge.source)!.push(edge.target);
      adj.get(edge.target)!.push(edge.source);
    }
  }

  // BFS from the seed node.
  const visited = new Set<string>();
  const queue: string[] = [nodeId];
  visited.add(nodeId);

  while (queue.length > 0) {
    const current = queue.shift()!;
    for (const neighbor of (adj.get(current) || [])) {
      if (!visited.has(neighbor)) {
        visited.add(neighbor);
        queue.push(neighbor);
      }
    }
  }
  return visited;
}

/**
 * Partition all nodes into connected components (flows).
 * Returns an array of Sets, each containing the node IDs of one flow.
 */
function getAllFlows(
  allNodeIds: Set<string>,
  edges: EdgeLike[],
): Set<string>[] {
  const remaining = new Set(allNodeIds);
  const flows: Set<string>[] = [];

  while (remaining.size > 0) {
    const seed = remaining.values().next().value as string;
    const component = getConnectedComponent(seed, allNodeIds, edges);
    flows.push(component);
    for (const id of component) {
      remaining.delete(id);
    }
  }
  return flows;
}

/** Horizontal bounding extent (min-x to max-x + width) of a set of nodes. */
interface FlowExtent {
  minX: number;
  maxX: number; // right edge of the rightmost node
}

/**
 * Compute the horizontal extent of a flow (connected component).
 */
function getFlowExtent(
  flowNodeIds: Set<string>,
  allNodes: NodeLike[],
): FlowExtent {
  let minX = Infinity;
  let maxX = -Infinity;

  for (const node of allNodes) {
    if (node.id && flowNodeIds.has(node.id)) {
      const w = node.width || NODE_DIMENSIONS.DEFAULT_WIDTH;
      minX = Math.min(minX, node.position.x);
      maxX = Math.max(maxX, node.position.x + w);
    }
  }
  return { minX, maxX };
}

/** Result of the flow-aware position search. */
interface FlowAwarePositionResult {
  /** Where to place the new node. */
  position: { x: number; y: number };
  /**
   * Node IDs that must be shifted right, with the shift amount.
   * Empty when no shift is needed.
   */
  shiftNodeIds: Set<string>;
  shiftAmount: number;
}

/**
 * Find a free position for a new successor node, respecting flow boundaries.
 *
 * Algorithm:
 *   1. Compute the source node's flow (connected component) and the
 *      nearest neighboring flow to the right.
 *   2. Try the candidate position – if free, return immediately.
 *   3. Try positions to the RIGHT of the candidate in small increments
 *      (about one node-width apart), staying within the gap between the
 *      source flow and the next flow.  This keeps the new node close to
 *      its parent.
 *   4. If no space exists without crossing into a neighbor, position the
 *      new node just right of the blocking same-flow node and shift all
 *      flows to its right to create room.
 */
export function findFlowAwarePosition(
  candidate: { x: number; y: number },
  sourceNodeId: string,
  allNodes: NodeLike[],
  edges: EdgeLike[],
  nodeWidth: number = NODE_DIMENSIONS.DEFAULT_WIDTH,
  nodeHeight: number = NODE_DIMENSIONS.DEFAULT_HEIGHT,
): FlowAwarePositionResult {
  const noShift: FlowAwarePositionResult = {
    position: { ...candidate },
    shiftNodeIds: new Set(),
    shiftAmount: 0,
  };

  // Fast path: nothing to collide with.
  if (allNodes.length === 0) {
    return noShift;
  }

  // Fast path: candidate is already free.
  if (!isOverlapping(candidate.x, candidate.y, nodeWidth, nodeHeight, allNodes)) {
    return noShift;
  }

  // Identify the source node's flow.
  const allNodeIds = new Set(allNodes.map(n => n.id).filter(Boolean) as string[]);
  const sourceFlow = getConnectedComponent(sourceNodeId, allNodeIds, edges);
  const sourceExtent = getFlowExtent(sourceFlow, allNodes);

  // Find the nearest neighboring flow to the right to know our right boundary.
  const otherNodeIds = new Set(
    allNodes.map(n => n.id).filter(id => id != null && !sourceFlow.has(id)) as string[],
  );
  const otherFlows = getAllFlows(otherNodeIds, edges);
  let rightBoundary = Infinity; // left edge of the nearest flow to our right
  for (const flow of otherFlows) {
    const extent = getFlowExtent(flow, allNodes);
    if (extent.minX > sourceExtent.minX && extent.minX < rightBoundary) {
      rightBoundary = extent.minX;
    }
  }
  // The new node's right edge must stay at least COLLISION_PADDING away from
  // the neighboring flow's left edge.
  const maxAllowedX = rightBoundary - nodeWidth - COLLISION_PADDING;

  // Use a tight step: just enough to clear one node width + padding.
  // This keeps the new node close to its left neighbor.
  const stepX = nodeWidth + COLLISION_PADDING * 2;

  // ----- Phase 1: try to the right of the candidate, within the safe zone -----
  // This keeps the node on the same row as the parent — the most natural spot.
  for (let i = 1; i <= MAX_ATTEMPTS; i++) {
    const x = candidate.x + stepX * i;
    if (x > maxAllowedX) break; // would cross into the neighboring flow
    if (!isOverlapping(x, candidate.y, nodeWidth, nodeHeight, allNodes)) {
      return {
        position: { x, y: candidate.y },
        shiftNodeIds: new Set(),
        shiftAmount: 0,
      };
    }
  }

  // ----- Phase 2: shift neighboring flows right to create room -----
  // Position the new node just to the right of the blocking same-flow node.
  // Find the rightmost same-flow node that overlaps the candidate's Y row
  // to place the new node right next to it.
  const sameFlowNodes = allNodes.filter(n => n.id != null && sourceFlow.has(n.id));
  let newX = sourceExtent.maxX + COLLISION_PADDING * 2;

  // Narrow down: find the rightmost same-flow node on this row.
  for (const node of sameFlowNodes) {
    const nodeW = node.width || NODE_DIMENSIONS.DEFAULT_WIDTH;
    const nodeH = node.height || NODE_DIMENSIONS.DEFAULT_HEIGHT;
    const nodeRight = node.position.x + nodeW;
    const verticallyOverlaps =
      candidate.y < node.position.y + nodeH + COLLISION_PADDING &&
      candidate.y + nodeHeight > node.position.y - COLLISION_PADDING;
    if (verticallyOverlaps && nodeRight + COLLISION_PADDING * 2 > newX) {
      // No need: newX is already past this node
    } else if (verticallyOverlaps) {
      newX = Math.max(newX, nodeRight + COLLISION_PADDING * 2);
    }
  }

  // If the new position still overlaps same-flow nodes, nudge further right.
  while (isOverlapping(newX, candidate.y, nodeWidth, nodeHeight, sameFlowNodes) && newX < candidate.x + stepX * MAX_ATTEMPTS) {
    newX += stepX;
  }

  const newPosition = { x: newX, y: candidate.y };

  // Compute shift: push every flow to the right of the source flow that
  // would be too close to the new node.
  const newNodeRight = newX + nodeWidth;
  let shiftNodeIds = new Set<string>();
  let shiftAmount = 0;

  for (const flow of otherFlows) {
    const extent = getFlowExtent(flow, allNodes);
    if (extent.minX >= sourceExtent.minX && extent.minX < newNodeRight + MIN_FLOW_GAP) {
      const needed = (newNodeRight + MIN_FLOW_GAP) - extent.minX;
      if (needed > shiftAmount) {
        shiftAmount = needed;
      }
      for (const id of flow) {
        shiftNodeIds.add(id);
      }
    }
  }

  return {
    position: newPosition,
    shiftNodeIds,
    shiftAmount,
  };
}
