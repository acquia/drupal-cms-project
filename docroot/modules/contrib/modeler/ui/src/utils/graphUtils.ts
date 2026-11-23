/**
 * graphUtils - Small directed-graph helpers over the modeler's nodes/edges.
 */

/** Minimal directed-edge shape (source → target node ids). */
export interface DirectedEdge {
  source: string;
  target: string;
}

/**
 * Return the set of node ids reachable from `startId` by following directed
 * edges (source → target), INCLUDING `startId` itself.
 *
 * This is the canonical "flow membership" set for a given start/event node: the
 * event plus all of its downstream descendants. A node shared with other flows
 * still counts as reachable (in-flow) if any path from `startId` leads to it.
 *
 * Mirrors the BFS reachability used by `visibleNodeIds` in Flow.tsx.
 *
 * @param startId  The id to start the traversal from. When null/empty, an empty
 *                 set is returned (no flow is in scope).
 * @param edges    The directed edges of the graph.
 */
export function getReachableNodeIds(
  startId: string | null | undefined,
  edges: ReadonlyArray<DirectedEdge>,
): Set<string> {
  const reachable = new Set<string>();
  if (!startId) return reachable;

  // Build a source → targets adjacency map.
  const adjacency = new Map<string, string[]>();
  for (const edge of edges) {
    if (!adjacency.has(edge.source)) adjacency.set(edge.source, []);
    adjacency.get(edge.source)!.push(edge.target);
  }

  // BFS from the start id.
  const queue: string[] = [startId];
  reachable.add(startId);
  while (queue.length > 0) {
    const current = queue.shift()!;
    const neighbors = adjacency.get(current);
    if (neighbors) {
      for (const neighbor of neighbors) {
        if (!reachable.has(neighbor)) {
          reachable.add(neighbor);
          queue.push(neighbor);
        }
      }
    }
  }

  return reachable;
}

/**
 * Find which reviewed-event "owns" a selected node, i.e. which session-event's
 * flow the node belongs to.
 *
 * A session-event "owns" `selectedNodeId` when the node is reachable from that
 * event (per {@link getReachableNodeIds}, which includes the event itself). A
 * node can be shared by multiple flows; among the owning events we prefer the
 * one currently being reviewed (`activeEventId`) so that selecting a shared node
 * does NOT surprise-switch the active session. Otherwise the first owner in
 * `sessionEventIds` order wins.
 *
 * Used by Flow.tsx to (a) decide whether the "Review flow" button should show
 * for a non-event node (only when its owning event has a session), and (b)
 * resolve a "Review flow" click on a non-event node to the right event's
 * session so the auto-exit effect does not immediately bounce.
 *
 * @param selectedNodeId   The currently-selected node. null/empty → no owner.
 * @param sessionEventIds  Event ids that currently HAVE a review session.
 * @param activeEventId    The currently-active reviewed event (preferred owner).
 * @param edges            The directed edges of the graph.
 * @returns The owning event id, or null when no session-event reaches the node.
 */
export function findOwningReviewedEventId(
  selectedNodeId: string | null | undefined,
  sessionEventIds: ReadonlyArray<string>,
  activeEventId: string | null | undefined,
  edges: ReadonlyArray<DirectedEdge>,
): string | null {
  if (!selectedNodeId || sessionEventIds.length === 0) return null;

  const owners: string[] = [];
  for (const eventId of sessionEventIds) {
    if (getReachableNodeIds(eventId, edges).has(selectedNodeId)) {
      owners.push(eventId);
    }
  }

  if (owners.length === 0) return null;

  // Active-preferred: if the active event owns the node, stay on it.
  if (activeEventId && owners.includes(activeEventId)) return activeEventId;

  // Otherwise the first owner in session-event order wins.
  return owners[0];
}

/**
 * Find which event "owns" a selected node STRUCTURALLY — i.e. from the graph
 * alone, regardless of whether any review session exists.
 *
 * This mirrors {@link findOwningReviewedEventId} but is session-agnostic: it
 * considers ALL event/start nodes in the model (`allEventIds`), not just those
 * that currently have a session. It is used ONLY by the "[" token picker's
 * Step-data gating/loading (Feature J), which must work BEFORE any session is
 * started. The Review-flow BUTTON visibility keeps using
 * {@link findOwningReviewedEventId} (intentionally session-gated).
 *
 * An event "owns" `selectedNodeId` when the node is reachable from that event
 * (per {@link getReachableNodeIds}, which includes the event itself). A node can
 * be shared by multiple flows; among the owning events we prefer
 * `preferredEventId` (e.g. the active reviewed event, or the single selected
 * start node) when it is an owner, otherwise the first owner in `allEventIds`
 * order wins — same shared-node semantics as `findOwningReviewedEventId`.
 *
 * @param selectedNodeId   The currently-selected node. null/empty → no owner.
 * @param allEventIds      ALL event/start node ids in the model.
 * @param preferredEventId Preferred owner when it reaches the node (tiebreak).
 * @param edges            The directed edges of the graph.
 * @returns The owning event id, or null when no event flow reaches the node.
 */
export function findOwningEventId(
  selectedNodeId: string | null | undefined,
  allEventIds: ReadonlyArray<string>,
  preferredEventId: string | null | undefined,
  edges: ReadonlyArray<DirectedEdge>,
): string | null {
  if (!selectedNodeId || allEventIds.length === 0) return null;

  const owners: string[] = [];
  for (const eventId of allEventIds) {
    if (getReachableNodeIds(eventId, edges).has(selectedNodeId)) {
      owners.push(eventId);
    }
  }

  if (owners.length === 0) return null;

  // Preferred-owner tiebreak for shared nodes.
  if (preferredEventId && owners.includes(preferredEventId)) return preferredEventId;

  // Otherwise the first owner in model order wins.
  return owners[0];
}
