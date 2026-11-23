import { isValidConnection } from '../connectionValidation';
import type { StoreNode, StoreEdge, ModelConstraints } from '../../types/settings';

const node = (id: string, type: string, data: Record<string, unknown> = {}): StoreNode =>
  ({ id, type, position: { x: 0, y: 0 }, data } as unknown as StoreNode);

const edge = (id: string, source: string, target: string): StoreEdge =>
  ({ id, source, target, data: {} } as unknown as StoreEdge);

describe('connectionValidation.isValidConnection', () => {
  it('allows a connection with no source (incomplete)', () => {
    expect(
      isValidConnection({ connection: { source: null, target: 'b', sourceHandle: null, targetHandle: null }, nodes: [], edges: [] }),
    ).toBe(true);
  });

  it('blocks condition → condition connections', () => {
    const nodes = [
      node('c1', 'condition', { __isConditionNode: true }),
      node('c2', 'condition', { __isConditionNode: true }),
    ];
    expect(
      isValidConnection({
        connection: { source: 'c1', target: 'c2', sourceHandle: 'output', targetHandle: 'input' },
        nodes,
        edges: [],
      }),
    ).toBe(false);
  });

  it('blocks a second outbound edge from a condition node', () => {
    const nodes = [node('c1', 'condition', { __isConditionNode: true }), node('a', 'element'), node('b', 'element')];
    const edges = [edge('e1', 'c1', 'a')];
    expect(
      isValidConnection({
        connection: { source: 'c1', target: 'b', sourceHandle: 'output', targetHandle: 'input' },
        nodes,
        edges,
      }),
    ).toBe(false);
  });

  it('allows the condition outbound when excluding the edge being reconnected', () => {
    const nodes = [node('c1', 'condition', { __isConditionNode: true }), node('a', 'element'), node('b', 'element')];
    const edges = [edge('e1', 'c1', 'a')];
    // Moving e1's target from a to b: e1 must be excluded so it does not count
    // against the condition's 1-outbound limit.
    expect(
      isValidConnection({
        connection: { source: 'c1', target: 'b', sourceHandle: 'output', targetHandle: 'input' },
        nodes,
        edges,
        excludeEdgeId: 'e1',
      }),
    ).toBe(true);
  });

  it('blocks a new edge when source is at max successors', () => {
    const nodes = [node('g', 'gateway'), node('a', 'element'), node('b', 'element')];
    const edges = [edge('e1', 'g', 'a')];
    const modelConstraints = { gateway: { successors: { max: 1 } } } as unknown as ModelConstraints;
    expect(
      isValidConnection({
        connection: { source: 'g', target: 'b', sourceHandle: 'output', targetHandle: 'input' },
        nodes,
        edges,
        modelConstraints,
      }),
    ).toBe(false);
  });

  it('allows when source under max successors', () => {
    const nodes = [node('g', 'gateway'), node('a', 'element')];
    const modelConstraints = { gateway: { successors: { max: 2 } } } as unknown as ModelConstraints;
    expect(
      isValidConnection({
        connection: { source: 'g', target: 'a', sourceHandle: 'output', targetHandle: 'input' },
        nodes,
        edges: [],
        modelConstraints,
      }),
    ).toBe(true);
  });

  it('allows when no constraint is defined for the source type', () => {
    const nodes = [node('a', 'element'), node('b', 'element')];
    expect(
      isValidConnection({
        connection: { source: 'a', target: 'b', sourceHandle: 'output', targetHandle: 'input' },
        nodes,
        edges: [edge('e1', 'a', 'x')],
      }),
    ).toBe(true);
  });

  // ── Condition 1-inbound rule when reuse is OFF (issue #3589100) ───────────
  // When conditions can NOT be reused, a condition node may have AT MOST ONE
  // incoming edge, mirroring the at-most-one-outbound rule.  When reuse is ON,
  // fan-in (multiple inbound) stays allowed.

  it('reuse OFF: blocks a second inbound edge to a condition target', () => {
    const nodes = [node('a', 'element'), node('b', 'element'), node('c1', 'condition', { __isConditionNode: true })];
    const edges = [edge('e1', 'a', 'c1')];
    // No modelConstraints ⇒ reuse OFF; c1 already has one inbound edge.
    expect(
      isValidConnection({
        connection: { source: 'b', target: 'c1', sourceHandle: 'output', targetHandle: 'input' },
        nodes,
        edges,
      }),
    ).toBe(false);
  });

  it('reuse OFF: allows the first inbound edge to a condition target (zero existing)', () => {
    const nodes = [node('a', 'element'), node('c1', 'condition', { __isConditionNode: true })];
    expect(
      isValidConnection({
        connection: { source: 'a', target: 'c1', sourceHandle: 'output', targetHandle: 'input' },
        nodes,
        edges: [],
      }),
    ).toBe(true);
  });

  it('reuse OFF: allows reconnecting an existing inbound edge to the same condition target (excludeEdgeId)', () => {
    const nodes = [node('a', 'element'), node('b', 'element'), node('c1', 'condition', { __isConditionNode: true })];
    const edges = [edge('e1', 'a', 'c1')];
    // Moving e1's source from a to b: e1 must be excluded so it does not count
    // against c1's 1-inbound limit.
    expect(
      isValidConnection({
        connection: { source: 'b', target: 'c1', sourceHandle: 'output', targetHandle: 'input' },
        nodes,
        edges,
        excludeEdgeId: 'e1',
      }),
    ).toBe(true);
  });

  it('reuse ON: allows a second inbound edge to a condition target (fan-in preserved)', () => {
    const nodes = [node('a', 'element'), node('b', 'element'), node('c1', 'condition', { __isConditionNode: true })];
    const edges = [edge('e1', 'a', 'c1')];
    const modelConstraints = {
      element: { successors: { allowConditionReuse: true } },
    } as unknown as ModelConstraints;
    expect(
      isValidConnection({
        connection: { source: 'b', target: 'c1', sourceHandle: 'output', targetHandle: 'input' },
        nodes,
        edges,
        modelConstraints,
      }),
    ).toBe(true);
  });

  it('reuse OFF: a non-condition target with an existing inbound edge is still allowed', () => {
    const nodes = [node('a', 'element'), node('b', 'element'), node('z', 'element')];
    const edges = [edge('e1', 'a', 'z')];
    // z is NOT a condition node, so the 1-inbound rule does not apply.
    expect(
      isValidConnection({
        connection: { source: 'b', target: 'z', sourceHandle: 'output', targetHandle: 'input' },
        nodes,
        edges,
      }),
    ).toBe(true);
  });
});
