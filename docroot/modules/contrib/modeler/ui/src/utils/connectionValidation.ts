/**
 * Shared connection-validation logic (issue #3585553).
 *
 * The wiring rules that decide whether a `Connection` is allowed live here so
 * that BOTH code paths use the exact same logic:
 *
 *  - the `<ReactFlow isValidConnection>` prop in FlowCanvas (validates a NEW
 *    edge being dragged from a source handle), and
 *  - the endpoint-reconnect commit path (validates moving an EXISTING edge's
 *    source or target endpoint to a different node).
 *
 * The rules mirror the previous inline `isValidConnection` implementation:
 *  - block condition → condition connections (a gateway must separate them),
 *  - a condition node may have AT MOST ONE outbound edge,
 *  - a condition node's INBOUND cardinality is REUSE-CONDITIONAL (issue
 *    #3589100): when condition reuse is ON it may have MULTIPLE inbound edges
 *    (fan-in); when reuse is OFF it may have AT MOST ONE inbound edge, mirroring
 *    the at-most-one-outbound rule,
 *  - a source node may not exceed its `successors.max` model constraint.
 *
 * For reconnection, the edge being moved must be EXCLUDED from BOTH the
 * per-source outbound counts AND the per-target inbound count — otherwise
 * moving an endpoint off a node would wrongly count the edge against its own
 * source's max-successors / 1-outbound limit or its own target's 1-inbound
 * limit.
 */

import type { Connection } from 'reactflow';
import type { StoreNode as Node, StoreEdge as Edge, ModelConstraints } from '../types/settings';
import { isConditionNode } from './incrementalLayout';
import { isConditionReuseEnabled } from './modelUtils';

export interface ValidateConnectionArgs {
  connection: Connection;
  nodes: Node[];
  edges: Edge[];
  modelConstraints?: ModelConstraints;
  /**
   * ID of an edge to exclude from outbound-count calculations. Set this to the
   * edge being reconnected so its own existence does not count against the
   * source node's 1-outbound / max-successors limits.
   */
  excludeEdgeId?: string;
}

/**
 * Validate a proposed connection against the model wiring rules.
 *
 * @returns `true` when the connection is allowed, `false` otherwise.
 */
export function isValidConnection({
  connection,
  nodes,
  edges,
  modelConstraints,
  excludeEdgeId,
}: ValidateConnectionArgs): boolean {
  if (!connection.source) return true;

  const sourceNode = nodes.find((n) => n.id === connection.source);

  // Resolve condition-reuse state ONCE (issue #3589100).  Reads the owner
  // constraints defensively via the shared helper: absent/false ⇒ reuse OFF.
  const reuseEnabled = isConditionReuseEnabled(modelConstraints);

  // Count inbound edges for a target node, excluding the edge being moved —
  // symmetric to `outgoingFor` below so reconnecting an existing inbound edge
  // does not count against the target's 1-inbound limit (issue #3589100).
  const inboundFor = (nodeId: string): number =>
    edges.filter((e) => e.target === nodeId && e.id !== excludeEdgeId).length;

  // Block condition → condition connections: two condition nodes may never be
  // directly wired together — a gateway must separate them.
  if (connection.target) {
    const targetNode = nodes.find((n) => n.id === connection.target);
    if (isConditionNode(sourceNode) && isConditionNode(targetNode)) {
      return false;
    }

    // Enforce the 1-INBOUND rule for condition nodes when reuse is OFF (issue
    // #3589100): a condition that can NOT be reused may have AT MOST ONE
    // incoming edge, mirroring the at-most-one-outbound rule.  When reuse is ON
    // fan-in stays allowed, so this restriction is skipped entirely.  The
    // edge being reconnected is excluded so moving an existing inbound edge of
    // the SAME condition target is still allowed.
    if (!reuseEnabled && isConditionNode(targetNode)) {
      if (inboundFor(connection.target) >= 1) {
        return false;
      }
    }
  }

  // Count outbound edges for the source node, excluding the edge being moved.
  const outgoingFor = (nodeId: string): number =>
    edges.filter((e) => e.source === nodeId && e.id !== excludeEdgeId).length;

  // Enforce the 1-outbound rule for condition nodes: a condition node may have
  // AT MOST ONE outbound edge.  (Inbound cardinality is handled above and is
  // reuse-conditional: fan-in when reuse is ON, at most one when reuse is OFF.)
  if (isConditionNode(sourceNode)) {
    if (outgoingFor(connection.source) >= 1) {
      return false;
    }
  }

  // Block edges from nodes that have reached max successors.
  if (!sourceNode?.type || !modelConstraints) return true;
  const sConstraint = modelConstraints[sourceNode.type as keyof ModelConstraints]?.successors;
  if (sConstraint?.max === undefined) return true;
  return outgoingFor(connection.source) < sConstraint.max;
}
