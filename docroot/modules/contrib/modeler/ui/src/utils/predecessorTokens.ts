/**
 * predecessorTokens - Pure, hook-free resolution of PREDICTED step-data tokens
 * for a node that has no replay coverage of its own (issue #3577207).
 *
 * When a model already has replay data and the user adds a NEW successor to a
 * node the replay covered, the successor has no token data of its own until the
 * next replay run. Re-running replay just to see tokens that are almost
 * certainly already available (the successor inherits the predecessor's runtime
 * context) blocks fluid authoring. This module surfaces the predecessor's
 * replay tokens onto the successor as PREDICTED data so the user can keep
 * building.
 *
 * The walk:
 *   1. collects the DIRECT graph predecessors of the node (incoming edges);
 *   2. replaces any structural pass-through predecessor (condition / gateway)
 *      with ITS predecessors, recursively, until ACTOR nodes are reached —
 *      condition/gateway nodes are not themselves replay "actors" with token
 *      data (cycle-guarded by a `seen` set);
 *   3. for each actor predecessor, finds the replay step where it is the main
 *      actor (`findReplayStepForElement`) and, when covered, expands that step
 *      (`expandReplayStep`);
 *   4. merges the covered predecessors' token sets as a UNION.
 *
 * Returns an EMPTY object (`{}`) when NO predecessor is replay-covered.
 *
 * NON-MUTATION: every input (`replayData`, `nodes`, `edges`) is treated as
 * read-only. The result is always a fresh object built from `expandReplayStep`'s
 * deep clones plus a new merge object, so the serialized-verbatim `replayData`
 * is never touched.
 */

import type { Edge, Node } from 'reactflow';
import type { ReplayDataEntry } from '../types/settings';
import { findReplayStepForElement } from './replayStepUtils';
import { expandReplayStep } from './replayExpansion';

/** Token-data object as produced by {@link expandReplayStep} (key -> entry). */
export type StepTokenData = Record<string, unknown>;

/**
 * Whether a node is a structural pass-through (condition or gateway) that the
 * walk should step THROUGH to reach the upstream actor(s) rather than treat as
 * a replay actor with its own token data.
 *
 * Mirrors the condition/gateway detection used elsewhere (replayStepUtils):
 * `n.type === 'condition' | 'gateway'`, or the synthesized-condition-node
 * marker `n.data.__isConditionNode === true`.
 *
 * @param node
 *   The candidate node, or undefined when the predecessor id has no node.
 *
 * @returns TRUE when the node is a condition/gateway pass-through.
 */
function isPassThroughNode(node: Node | undefined): boolean {
  if (!node) return false;
  if (node.type === 'condition' || node.type === 'gateway') return true;
  const data = node.data as { __isConditionNode?: boolean } | undefined;
  return data?.__isConditionNode === true;
}

/**
 * Returns the ids of the DIRECT graph predecessors of a node (the sources of
 * every edge whose target is the node). Pure; never mutates `edges`.
 *
 * @param nodeId
 *   The id of the node whose predecessors are wanted.
 * @param edges
 *   The graph edges.
 *
 * @returns The source node ids of all incoming edges, in edge order.
 */
function getDirectPredecessorIds(nodeId: string, edges: readonly Edge[]): string[] {
  return edges.filter((e) => e.target === nodeId).map((e) => e.source);
}

/**
 * Resolves the upstream ACTOR predecessor ids of a node: the direct
 * predecessors, with any condition/gateway pass-through replaced by ITS
 * predecessors, recursively, until actor nodes are reached.
 *
 * Deterministic ordering: direct predecessors are processed in edge order, and
 * each pass-through expands to its own predecessors in edge order, so the
 * returned list preserves a stable "closer-first" traversal. A `seen` set
 * guards against cycles (a node id already visited is never re-expanded).
 *
 * Pure; never mutates `nodes` or `edges`.
 *
 * @param nodeId
 *   The id of the node whose upstream actors are wanted.
 * @param nodeById
 *   A lookup of node id -> node (built once by the caller).
 * @param edges
 *   The graph edges.
 * @param seen
 *   The set of node ids already visited (cycle guard); the starting node should
 *   be pre-seeded by the caller.
 *
 * @returns The ordered list of upstream actor node ids (may contain duplicates
 *   when multiple paths reach the same actor; callers de-duplicate as needed).
 */
function resolveUpstreamActorIds(
  nodeId: string,
  nodeById: Map<string, Node>,
  edges: readonly Edge[],
  seen: Set<string>,
): string[] {
  const actors: string[] = [];
  for (const predId of getDirectPredecessorIds(nodeId, edges)) {
    if (seen.has(predId)) continue;
    seen.add(predId);
    if (isPassThroughNode(nodeById.get(predId))) {
      // Step THROUGH the condition/gateway to ITS upstream actors.
      actors.push(...resolveUpstreamActorIds(predId, nodeById, edges, seen));
    } else {
      actors.push(predId);
    }
  }
  return actors;
}

/**
 * Resolves the PREDICTED step-data tokens for a node from its replay-covered
 * predecessors (see the module docblock).
 *
 * Multi-predecessor UNION + collision precedence (deterministic): the covered
 * predecessors are merged in WALK ORDER — direct predecessors in edge order
 * first, then their upstream actors (reached by stepping through
 * condition/gateway nodes), depth-first. Each covered predecessor's expanded
 * data is `Object.assign`ed over the accumulator in that order, so the
 * LATER-WALKED predecessor is the LAST writer and WINS a key collision. This
 * makes the closer / later-walked predecessor's value prevail, matching the
 * spec's deterministic procedure (issue #3577207 §3.4).
 *
 * @param args.nodeId
 *   The id of the (selected) node to predict tokens for.
 * @param args.nodes
 *   All graph nodes (read-only).
 * @param args.edges
 *   All graph edges (read-only).
 * @param args.replayData
 *   The compact replay steps (read-only, never mutated).
 *
 * @returns A fresh {@link StepTokenData} object — the union of every covered
 *   predecessor's expanded step tokens — or `{}` when none is covered.
 */
export function resolvePredictedTokens(args: {
  nodeId: string;
  nodes: readonly Node[];
  edges: readonly Edge[];
  replayData: readonly ReplayDataEntry[];
}): StepTokenData {
  const { nodeId, nodes, edges, replayData } = args;

  // findReplayStepForElement / expandReplayStep accept mutable array params but
  // do not mutate them; pass through without copying to avoid needless work.
  const replaySteps = replayData as ReplayDataEntry[];
  const graphEdges = edges as Edge[];

  if (!nodeId || replaySteps.length === 0) {
    return {};
  }

  const nodeById = new Map<string, Node>();
  for (const node of nodes) {
    nodeById.set(node.id, node);
  }

  // Walk to upstream actors, guarding against cycles. Seed `seen` with the start
  // node so a self-loop edge cannot re-expand it.
  const seen = new Set<string>([nodeId]);
  const actorIds = resolveUpstreamActorIds(nodeId, nodeById, graphEdges, seen);

  // De-duplicate actor ids while preserving walk order (closer-first). When the
  // same actor is reached via multiple paths we only merge it once.
  const uniqueActorIds: string[] = [];
  const mergedActors = new Set<string>();
  for (const id of actorIds) {
    if (!mergedActors.has(id)) {
      mergedActors.add(id);
      uniqueActorIds.push(id);
    }
  }

  // Merge covered predecessors as a UNION in WALK ORDER (direct predecessors in
  // edge order first, then deeper actors). `Object.assign` makes the LAST writer
  // win, so the later-walked (closer) predecessor prevails on a key collision.
  const merged: StepTokenData = {};
  for (const actorId of uniqueActorIds) {
    const idx = findReplayStepForElement(replaySteps, graphEdges, actorId, 'node');
    if (idx < 0) continue;
    const expanded = expandReplayStep(replaySteps, idx);
    if (expanded) {
      Object.assign(merged, expanded);
    }
  }

  return merged;
}
