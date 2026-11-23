/**
 * Shared logic for reconnecting the graph when condition nodes are deleted
 * (issue #3589093).
 *
 * A condition node may have MULTIPLE inbound edges (fan-in for a reused
 * condition) but AT MOST ONE outbound edge (issue #3589093).  When such a node
 * is removed from the canvas, simply dropping it (and its edges) would orphan
 * the downstream branch.  Instead we must reconnect EACH of the condition's N
 * predecessors directly to its single successor with a plain `default` edge —
 * exactly the same behavior as `pluginApi.removeCondition`, generalized to
 * fan-in.
 *
 * These helpers are pure and side-effect free so they can be unit-tested
 * without a store or React, and reused by both the canvas delete path
 * (useFlowEventHandlers) and the plugin API.
 */

import type { StoreNode as Node, StoreEdge as Edge } from '../types/settings';
import { isConditionNode } from './incrementalLayout';
import { generateEdgeId } from './clipboardUtils';

/**
 * Compute the replacement edges needed to keep the graph connected when a
 * set of nodes is deleted.
 *
 * For every condition node in `deletedNodeIds` that has at least one inbound
 * edge and exactly one outbound edge, a plain predecessor → successor edge is
 * produced PER inbound edge (N predecessors → 1 successor) — but each only
 * when both that predecessor and the successor survive the deletion (i.e.
 * neither is itself being deleted).  This avoids creating a dangling edge to a
 * node that is also being removed.
 *
 * @param nodes           Current graph nodes.
 * @param edges           Current graph edges.
 * @param deletedNodeIds  IDs of nodes being deleted in this operation.
 * @returns The new reconnect edges to append after the deletion.
 */
export function computeConditionReconnectEdges(
  nodes: Node[],
  edges: Edge[],
  deletedNodeIds: Set<string>,
): Edge[] {
  const nodeById = new Map<string, Node>();
  for (const node of nodes) nodeById.set(node.id, node);

  const reconnectEdges: Edge[] = [];

  for (const condNodeId of deletedNodeIds) {
    const condNode = nodeById.get(condNodeId);
    if (!isConditionNode(condNode)) continue;

    // Enforce the fan-in (N-in) / 1-outbound cardinality: only reconnect a
    // well-formed condition node.  A node with zero inbound or more than one
    // outbound edge is left to the caller's ordinary node-removal path
    // (which drops it and its edges).
    const inbound = edges.filter((e) => e.target === condNodeId);
    const outbound = edges.filter((e) => e.source === condNodeId);
    if (inbound.length < 1 || outbound.length !== 1) continue;

    const successorId = outbound[0].target;

    // Don't create a dangling edge to a successor that is also being deleted.
    if (deletedNodeIds.has(successorId)) continue;

    // Reconnect EACH surviving predecessor directly to the single successor.
    for (const inboundEdge of inbound) {
      const predecessorId = inboundEdge.source;
      // Skip a predecessor that is itself being deleted (avoid dangling edge).
      if (deletedNodeIds.has(predecessorId)) continue;

      reconnectEdges.push({
        id: generateEdgeId(predecessorId, successorId),
        source: predecessorId,
        target: successorId,
        type: 'default',
        data: {},
      });
    }
  }

  return reconnectEdges;
}
