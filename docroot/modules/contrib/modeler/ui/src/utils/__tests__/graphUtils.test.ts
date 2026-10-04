import { getReachableNodeIds, findOwningReviewedEventId, findOwningEventId } from '../graphUtils';

describe('getReachableNodeIds', () => {
  it('includes the start node itself', () => {
    const result = getReachableNodeIds('a', []);
    expect(result.has('a')).toBe(true);
    expect(result.size).toBe(1);
  });

  it('returns an empty set for null/undefined/empty start', () => {
    expect(getReachableNodeIds(null, [{ source: 'a', target: 'b' }]).size).toBe(0);
    expect(getReachableNodeIds(undefined, [{ source: 'a', target: 'b' }]).size).toBe(0);
    expect(getReachableNodeIds('', [{ source: 'a', target: 'b' }]).size).toBe(0);
  });

  it('includes the reviewed event plus all reachable descendants', () => {
    // event → a → b ; a → c   (chain + branch)
    const edges = [
      { source: 'event', target: 'a' },
      { source: 'a', target: 'b' },
      { source: 'a', target: 'c' },
    ];
    const result = getReachableNodeIds('event', edges);
    expect([...result].sort()).toEqual(['a', 'b', 'c', 'event']);
  });

  it('does NOT include nodes upstream of the start or in unrelated flows', () => {
    // flow 1: event1 → a ; flow 2: event2 → z (unrelated)
    const edges = [
      { source: 'event1', target: 'a' },
      { source: 'event2', target: 'z' },
    ];
    const result = getReachableNodeIds('event1', edges);
    expect(result.has('a')).toBe(true);
    expect(result.has('event2')).toBe(false);
    expect(result.has('z')).toBe(false);
  });

  it('counts a SHARED node as in-flow when reachable from the start', () => {
    // Both event1 and event2 feed into the shared node `shared`.
    const edges = [
      { source: 'event1', target: 'shared' },
      { source: 'event2', target: 'shared' },
      { source: 'shared', target: 'tail' },
    ];
    const result = getReachableNodeIds('event1', edges);
    expect(result.has('shared')).toBe(true);
    expect(result.has('tail')).toBe(true);
    // event2 is NOT reachable downstream from event1.
    expect(result.has('event2')).toBe(false);
  });

  it('follows edges directionally (source → target only)', () => {
    // a → b ; selecting b must not reach a (no upstream traversal).
    const edges = [{ source: 'a', target: 'b' }];
    const result = getReachableNodeIds('b', edges);
    expect(result.has('b')).toBe(true);
    expect(result.has('a')).toBe(false);
  });

  it('handles cycles without infinite looping', () => {
    const edges = [
      { source: 'a', target: 'b' },
      { source: 'b', target: 'c' },
      { source: 'c', target: 'a' }, // cycle back to a
    ];
    const result = getReachableNodeIds('a', edges);
    expect([...result].sort()).toEqual(['a', 'b', 'c']);
  });
});

describe('findOwningReviewedEventId', () => {
  // Two independent flows: event1 → a1, event2 → a2.
  const twoFlows = [
    { source: 'event1', target: 'a1' },
    { source: 'event2', target: 'a2' },
  ];

  it('returns the single owner when only one session-event reaches the node', () => {
    const result = findOwningReviewedEventId('a1', ['event1', 'event2'], null, twoFlows);
    expect(result).toBe('event1');
  });

  it('prefers the ACTIVE event when a SHARED node is reachable from several', () => {
    // Both event1 and event2 feed into the shared node.
    const shared = [
      { source: 'event1', target: 'shared' },
      { source: 'event2', target: 'shared' },
    ];
    const result = findOwningReviewedEventId('shared', ['event1', 'event2'], 'event2', shared);
    expect(result).toBe('event2');
  });

  it('falls back to the FIRST owner (session order) when active does not own it', () => {
    const shared = [
      { source: 'event1', target: 'shared' },
      { source: 'event2', target: 'shared' },
    ];
    // active event (event3) does not reach `shared` → first owner wins.
    const result = findOwningReviewedEventId('shared', ['event1', 'event2'], 'event3', shared);
    expect(result).toBe('event1');
  });

  it('returns null when the node is in NO session-event flow', () => {
    // a2 belongs to event2, but only event1 has a session.
    const result = findOwningReviewedEventId('a2', ['event1'], 'event1', twoFlows);
    expect(result).toBeNull();
  });

  it('owns the node when the selected node IS a session-event itself', () => {
    // getReachableNodeIds includes the start id, so event2 owns event2.
    const result = findOwningReviewedEventId('event2', ['event1', 'event2'], 'event1', twoFlows);
    expect(result).toBe('event2');
  });

  it('returns null for empty session list or null/empty selected node', () => {
    expect(findOwningReviewedEventId('a1', [], 'event1', twoFlows)).toBeNull();
    expect(findOwningReviewedEventId(null, ['event1'], 'event1', twoFlows)).toBeNull();
    expect(findOwningReviewedEventId('', ['event1'], 'event1', twoFlows)).toBeNull();
    expect(findOwningReviewedEventId(undefined, ['event1'], 'event1', twoFlows)).toBeNull();
  });
});

describe('findOwningEventId (session-agnostic, Feature J caveat fix)', () => {
  // Two independent flows: event1 → a1, event2 → a2.
  const twoFlows = [
    { source: 'event1', target: 'a1' },
    { source: 'event2', target: 'a2' },
  ];

  it('resolves the owning event for an action node WITHOUT any session (single event reaches it)', () => {
    // No session needed — allEventIds is the full model event list.
    const result = findOwningEventId('a1', ['event1', 'event2'], null, twoFlows);
    expect(result).toBe('event1');
  });

  it('picks the correct owner in a multi-event model', () => {
    expect(findOwningEventId('a2', ['event1', 'event2'], null, twoFlows)).toBe('event2');
  });

  it('prefers the preferred event for a SHARED node', () => {
    const shared = [
      { source: 'event1', target: 'shared' },
      { source: 'event2', target: 'shared' },
    ];
    expect(findOwningEventId('shared', ['event1', 'event2'], 'event2', shared)).toBe('event2');
  });

  it('falls back to the FIRST owner (model order) when the preferred event does not own it', () => {
    const shared = [
      { source: 'event1', target: 'shared' },
      { source: 'event2', target: 'shared' },
    ];
    expect(findOwningEventId('shared', ['event1', 'event2'], 'event3', shared)).toBe('event1');
  });

  it('owns the node when the selected node IS an event itself', () => {
    expect(findOwningEventId('event2', ['event1', 'event2'], null, twoFlows)).toBe('event2');
  });

  it('returns null when the node belongs to NO flow (no event reaches it)', () => {
    const isolated = [{ source: 'event1', target: 'a1' }];
    // `orphan` is not reachable from any event.
    expect(findOwningEventId('orphan', ['event1'], null, isolated)).toBeNull();
  });

  it('returns null for empty allEventIds or null/empty selected node', () => {
    expect(findOwningEventId('a1', [], 'event1', twoFlows)).toBeNull();
    expect(findOwningEventId(null, ['event1'], 'event1', twoFlows)).toBeNull();
    expect(findOwningEventId('', ['event1'], 'event1', twoFlows)).toBeNull();
    expect(findOwningEventId(undefined, ['event1'], 'event1', twoFlows)).toBeNull();
  });
});
