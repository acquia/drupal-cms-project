import type { Edge, Node } from 'reactflow';
import type { ReplayDataEntry } from '../../types/settings';
import { resolvePredictedTokens } from '../predecessorTokens';

/**
 * Build a minimal node. `type` defaults to 'element' (an actor); pass
 * 'condition' / 'gateway' (or `__isConditionNode`) for pass-through nodes.
 */
function node(id: string, type = 'element', data: Record<string, unknown> = {}): Node {
  return { id, type, position: { x: 0, y: 0 }, data } as Node;
}

/** Build a minimal edge from `source` to `target`. */
function edge(id: string, source: string, target: string): Edge {
  return { id, source, target } as Edge;
}

/**
 * Build an 'execute' (actor) replay step for a node, carrying token `data`.
 * `findReplayStepForElement(..., 'node')` matches on `id` + an execution step
 * type; `expandReplayStep` reads the step's `data` object.
 */
function execStep(id: string, data: Record<string, unknown>): ReplayDataEntry {
  return { type: 'execute', id, data };
}

describe('resolvePredictedTokens', () => {
  it('returns a single covered predecessor\'s tokens', () => {
    const nodes = [node('a'), node('b')];
    const edges = [edge('e1', 'a', 'b')];
    const replayData: ReplayDataEntry[] = [
      execStep('a', { user: { label: 'User', token: '[user:name]', value: 'admin' } }),
    ];

    const result = resolvePredictedTokens({ nodeId: 'b', nodes, edges, replayData });

    expect(result).toEqual({
      user: { label: 'User', token: '[user:name]', value: 'admin' },
    });
  });

  it('returns {} when the predecessor is not replay-covered', () => {
    const nodes = [node('a'), node('b')];
    const edges = [edge('e1', 'a', 'b')];
    // Replay covers some OTHER node, not the predecessor 'a'.
    const replayData: ReplayDataEntry[] = [execStep('z', { foo: { label: 'Foo' } })];

    const result = resolvePredictedTokens({ nodeId: 'b', nodes, edges, replayData });

    expect(result).toEqual({});
  });

  it('returns {} when there is no replay data at all', () => {
    const nodes = [node('a'), node('b')];
    const edges = [edge('e1', 'a', 'b')];

    expect(resolvePredictedTokens({ nodeId: 'b', nodes, edges, replayData: [] })).toEqual({});
  });

  it('walks THROUGH a condition predecessor to the upstream actor', () => {
    // actor(a) -> condition(c) -> node(b). 'c' is a pass-through; its tokens
    // come from the actor 'a' behind it.
    const nodes = [node('a'), node('c', 'condition'), node('b')];
    const edges = [edge('e1', 'a', 'c'), edge('e2', 'c', 'b')];
    const replayData: ReplayDataEntry[] = [
      execStep('a', { entity: { label: 'Entity', token: '[node:title]', value: 'Hello' } }),
    ];

    const result = resolvePredictedTokens({ nodeId: 'b', nodes, edges, replayData });

    expect(result).toEqual({
      entity: { label: 'Entity', token: '[node:title]', value: 'Hello' },
    });
  });

  it('walks THROUGH a gateway predecessor to the upstream actor', () => {
    const nodes = [node('a'), node('g', 'gateway'), node('b')];
    const edges = [edge('e1', 'a', 'g'), edge('e2', 'g', 'b')];
    const replayData: ReplayDataEntry[] = [execStep('a', { k: { label: 'K' } })];

    expect(resolvePredictedTokens({ nodeId: 'b', nodes, edges, replayData })).toEqual({
      k: { label: 'K' },
    });
  });

  it('walks THROUGH a synthesized condition node (__isConditionNode) to the actor', () => {
    const nodes = [node('a'), node('c', 'default', { __isConditionNode: true }), node('b')];
    const edges = [edge('e1', 'a', 'c'), edge('e2', 'c', 'b')];
    const replayData: ReplayDataEntry[] = [execStep('a', { k: { label: 'K' } })];

    expect(resolvePredictedTokens({ nodeId: 'b', nodes, edges, replayData })).toEqual({
      k: { label: 'K' },
    });
  });

  it('returns the UNION of multiple covered predecessors\' tokens', () => {
    // a -> b, x -> b. Both covered with disjoint keys → union.
    const nodes = [node('a'), node('x'), node('b')];
    const edges = [edge('e1', 'a', 'b'), edge('e2', 'x', 'b')];
    const replayData: ReplayDataEntry[] = [
      execStep('a', { fromA: { label: 'A' } }),
      execStep('x', { fromX: { label: 'X' } }),
    ];

    const result = resolvePredictedTokens({ nodeId: 'b', nodes, edges, replayData });

    expect(result).toEqual({
      fromA: { label: 'A' },
      fromX: { label: 'X' },
    });
  });

  it('on a UNION key collision the closer / later-walked predecessor wins', () => {
    // Two direct predecessors collide on `shared`. Edge order: a first, then x.
    // The walk processes direct predecessors in edge order (a, x) and the
    // later-walked one is the LAST writer, so x (later in edge order) wins.
    const nodes = [node('a'), node('x'), node('b')];
    const edges = [edge('e1', 'a', 'b'), edge('e2', 'x', 'b')];
    const replayData: ReplayDataEntry[] = [
      execStep('a', { shared: { label: 'from-a' } }),
      execStep('x', { shared: { label: 'from-x' } }),
    ];

    const result = resolvePredictedTokens({ nodeId: 'b', nodes, edges, replayData });

    expect(result).toEqual({ shared: { label: 'from-x' } });
  });

  it('terminates on a cycle in the edges (no infinite loop)', () => {
    // Cycle: a -> b -> a, plus actor s -> a. Resolving for 'b' must terminate.
    const nodes = [node('s'), node('a'), node('b')];
    const edges = [
      edge('e1', 'a', 'b'),
      edge('e2', 'b', 'a'),
      edge('e3', 's', 'a'),
    ];
    const replayData: ReplayDataEntry[] = [execStep('a', { k: { label: 'K' } })];

    // 'a' is the only direct predecessor of 'b'; it is a covered actor.
    const result = resolvePredictedTokens({ nodeId: 'b', nodes, edges, replayData });
    expect(result).toEqual({ k: { label: 'K' } });
  });

  it('terminates and returns {} on a pass-through cycle with no actor', () => {
    // condition c1 <-> condition c2 cycle feeding b; neither is an actor and
    // there is no upstream actor → {} without looping forever.
    const nodes = [
      node('c1', 'condition'),
      node('c2', 'condition'),
      node('b'),
    ];
    const edges = [
      edge('e1', 'c1', 'b'),
      edge('e2', 'c2', 'c1'),
      edge('e3', 'c1', 'c2'),
    ];
    const replayData: ReplayDataEntry[] = [execStep('z', { k: { label: 'K' } })];

    expect(resolvePredictedTokens({ nodeId: 'b', nodes, edges, replayData })).toEqual({});
  });

  it('does NOT mutate the replayData input (deep-frozen) and returns a distinct object', () => {
    const nodes = [node('a'), node('b')];
    const edges = [edge('e1', 'a', 'b')];
    const stepData = { user: { label: 'User', token: '[user:name]', value: 'admin' } };
    const replayData: ReplayDataEntry[] = [execStep('a', stepData)];

    // Deep-freeze the whole replayData structure: any in-place write throws in
    // strict mode (Jest test files are modules → strict mode).
    const deepFreeze = (obj: unknown): void => {
      if (obj && typeof obj === 'object') {
        Object.values(obj as Record<string, unknown>).forEach(deepFreeze);
        Object.freeze(obj);
      }
    };
    deepFreeze(replayData);

    let result: Record<string, unknown> = {};
    expect(() => {
      result = resolvePredictedTokens({ nodeId: 'b', nodes, edges, replayData });
    }).not.toThrow();

    // The output is a fresh object (not the same reference as the input data).
    expect(result).not.toBe(stepData);
    expect(result.user).not.toBe(stepData.user);
    expect(result).toEqual(stepData);
  });
});
