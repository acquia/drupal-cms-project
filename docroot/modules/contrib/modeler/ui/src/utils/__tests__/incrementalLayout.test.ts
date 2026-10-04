import {
  computeSuccessorPosition,
  computeNewEventPosition,
  placeNodeOnEdge,
  simulateIncrementalBuild,
  buildConditionInsertion,
  isConditionNode,
  placeChainOnEdge,
} from '../incrementalLayout';
import type { StoreNode as Node, StoreEdge as Edge } from '../../types/settings';
import { LAYOUT, NODE_DIMENSIONS } from '../../constants/dimensions';

/**
 * Build a typed StoreNode without ceremony.  Defaults give a generic
 * `element` node sized to the standard card dimensions.
 */
function makeNode(id: string, overrides: Partial<Node> = {}): Node {
  return {
    id,
    type: overrides.type ?? 'element',
    position: overrides.position ?? { x: 0, y: 0 },
    data: overrides.data ?? {},
    width: overrides.width ?? NODE_DIMENSIONS.CARD_WIDTH,
    height: overrides.height ?? NODE_DIMENSIONS.CARD_HEIGHT,
    ...overrides,
  };
}

function makeEdge(id: string, source: string, target: string, overrides: Partial<Edge> = {}): Edge {
  return {
    id,
    source,
    target,
    data: {},
    ...overrides,
  };
}

/**
 * Build a condition node with a chosen id, used by the placeChainOnEdge
 * flow-order tests so they can feed buildConditionInsertion a predictable id.
 */
function condNodeWith(id: string): Node {
  return makeNode(id, { type: 'condition', data: { __isConditionNode: true } });
}

describe('incrementalLayout', () => {
  describe('computeSuccessorPosition', () => {
    it('places a successor in the parent column for a non-gateway parent', () => {
      const parent = makeNode('p', { position: { x: 100, y: 100 } });
      const result = computeSuccessorPosition({
        nodes: [parent],
        edges: [],
        sourceNodeId: 'p',
      });
      // X aligns with parent (same width).
      expect(result.position.x).toBe(parent.position.x);
      // Y is one plain-edge gap below the parent's bottom.
      expect(result.position.y).toBeGreaterThan(parent.position.y);
    });

    it('places multiple non-gateway successors in the same column', () => {
      const parent = makeNode('p', { position: { x: 200, y: 100 } });
      const xs: number[] = [];
      for (let i = 0; i < 3; i++) {
        const result = computeSuccessorPosition({
          nodes: [parent],
          edges: [],
          sourceNodeId: 'p',
          siblingIndex: i,
          totalSiblings: 3,
          isGatewayChild: false,
        });
        xs.push(result.position.x);
      }
      // All three siblings produce the same X — no fan-out.
      expect(new Set(xs).size).toBe(1);
      expect(xs[0]).toBe(parent.position.x);
    });

    it('fans gateway children horizontally', () => {
      const parent = makeNode('g', { type: 'gateway', position: { x: 500, y: 100 } });
      const positions = [];
      for (let i = 0; i < 3; i++) {
        const result = computeSuccessorPosition({
          nodes: [parent],
          edges: [],
          sourceNodeId: 'g',
          siblingIndex: i,
          totalSiblings: 3,
          isGatewayChild: true,
        });
        positions.push(result.position.x);
      }
      // Three distinct X positions — fan-out.
      expect(new Set(positions).size).toBe(3);
      // Center child (index 1) sits roughly under the parent.
      expect(positions[1]).toBe(parent.position.x);
      // Left and right siblings flank the parent symmetrically.
      const step = NODE_DIMENSIONS.CARD_WIDTH + LAYOUT.NODE_SPACING_X;
      expect(positions[0]).toBe(parent.position.x - step);
      expect(positions[2]).toBe(parent.position.x + step);
    });

    // Removed: the former "adds CONDITION_EXTRA_SPACING for condition edges"
    // test.  Conditions are first-class nodes now (issue #3589093), so no
    // edge ever carries a condition card and computeSuccessorPosition no
    // longer accepts a `hasCondition` option — the vertical gap is always
    // the plain row spacing.

    it('returns DEFAULT_POSITION when source node is missing', () => {
      const result = computeSuccessorPosition({
        nodes: [],
        edges: [],
        sourceNodeId: 'missing',
      });
      expect(result.position.x).toBe(LAYOUT.DEFAULT_POSITION_X);
      expect(result.position.y).toBe(LAYOUT.DEFAULT_POSITION_Y);
    });
  });

  describe('computeNewEventPosition', () => {
    it('returns LAYOUT_START_X/Y for an empty canvas', () => {
      const pos = computeNewEventPosition({ nodes: [] });
      expect(pos.x).toBe(LAYOUT.LAYOUT_START_X);
      expect(pos.y).toBe(LAYOUT.LAYOUT_START_Y);
    });

    it('places a new event to the right of existing nodes', () => {
      const existing = [
        makeNode('n1', { position: { x: 100, y: 100 } }),
        makeNode('n2', { position: { x: 500, y: 100 } }),
      ];
      const pos = computeNewEventPosition({ nodes: existing });
      // To the right of the rightmost existing node.
      expect(pos.x).toBeGreaterThanOrEqual(500 + LAYOUT.NODE_SPACING_X);
      // At the topmost row.
      expect(pos.y).toBe(100);
    });

    it('respects an explicit candidate position', () => {
      const pos = computeNewEventPosition({
        nodes: [],
        candidate: { x: 1234, y: 5678 },
      });
      expect(pos.x).toBe(1234);
      expect(pos.y).toBe(5678);
    });
  });

  describe('placeNodeOnEdge', () => {
    it('places the new node between source and target with plain row gaps', () => {
      const source = makeNode('s', { position: { x: 0, y: 0 } });
      const target = makeNode('t', { position: { x: 0, y: 1000 } });
      const newNode = makeNode('n', { position: { x: 0, y: 0 } });
      const edges: Edge[] = [makeEdge('s-n', 's', 'n'), makeEdge('n-t', 'n', 't')];

      const result = placeNodeOnEdge([source, target], edges, newNode, 's', 't');
      const placed = result.find(n => n.id === 'n')!;
      // The new node sits between source.bottom and target.top.
      expect(placed.position.y).toBeGreaterThan(source.position.y + NODE_DIMENSIONS.CARD_HEIGHT);
      expect(placed.position.y + NODE_DIMENSIONS.CARD_HEIGHT).toBeLessThan(target.position.y);
    });

    it('shifts the target (and descendants) down when there is no room', () => {
      const source = makeNode('s', { position: { x: 0, y: 0 } });
      const target = makeNode('t', { position: { x: 0, y: NODE_DIMENSIONS.CARD_HEIGHT + 10 } });
      const downstream = makeNode('d', { position: { x: 0, y: NODE_DIMENSIONS.CARD_HEIGHT + 200 } });
      const newNode = makeNode('n', { position: { x: 0, y: 0 } });
      const edges: Edge[] = [
        makeEdge('s-n', 's', 'n'),
        makeEdge('n-t', 'n', 't'),
        makeEdge('t-d', 't', 'd'),
      ];

      const result = placeNodeOnEdge([source, target, downstream], edges, newNode, 's', 't');
      const newTarget = result.find(n => n.id === 't')!;
      const newDownstream = result.find(n => n.id === 'd')!;
      expect(newTarget.position.y).toBeGreaterThan(target.position.y);
      expect(newDownstream.position.y).toBeGreaterThan(downstream.position.y);
    });
  });

  describe('simulateIncrementalBuild', () => {
    it('returns null for empty input', () => {
      expect(simulateIncrementalBuild([], [])).toBeNull();
    });

    it('places a single node at LAYOUT_START', () => {
      const nodes = [makeNode('only', { type: 'start' })];
      const result = simulateIncrementalBuild(nodes, [])!;
      expect(result).toHaveLength(1);
      expect(result[0].position.x).toBe(LAYOUT.LAYOUT_START_X);
      expect(result[0].position.y).toBe(LAYOUT.LAYOUT_START_Y);
    });

    it('places a 3-node linear chain in a single column', () => {
      const nodes: Node[] = [
        makeNode('a', { type: 'start' }),
        makeNode('b'),
        makeNode('c'),
      ];
      const edges: Edge[] = [
        makeEdge('a-b', 'a', 'b'),
        makeEdge('b-c', 'b', 'c'),
      ];
      const result = simulateIncrementalBuild(nodes, edges)!;
      const xs = new Set(result.map(n => n.position.x));
      expect(xs.size).toBe(1);
      // Y values increase along the chain.
      const a = result.find(n => n.id === 'a')!;
      const b = result.find(n => n.id === 'b')!;
      const c = result.find(n => n.id === 'c')!;
      expect(b.position.y).toBeGreaterThan(a.position.y);
      expect(c.position.y).toBeGreaterThan(b.position.y);
    });

    it('does NOT fan out a non-gateway parent with multiple successors', () => {
      const nodes: Node[] = [
        makeNode('a', { type: 'start' }),
        makeNode('b'),
        makeNode('c'),
        makeNode('d'),
      ];
      const edges: Edge[] = [
        makeEdge('a-b', 'a', 'b'),
        makeEdge('a-c', 'a', 'c'),
        makeEdge('a-d', 'a', 'd'),
      ];
      const result = simulateIncrementalBuild(nodes, edges)!;
      const a = result.find(n => n.id === 'a')!;
      // All three children share the parent's column (x equality).
      const childX = result
        .filter(n => ['b', 'c', 'd'].includes(n.id))
        .map(n => n.position.x);
      // No three-way fan-out: the parent's column is preserved for at least one child.
      expect(childX.some(x => x === a.position.x)).toBe(true);
      // No child is placed to the LEFT of the parent (which is what the
      // legacy auto-layout used to do for plain action parents).
      expect(childX.every(x => x >= a.position.x)).toBe(true);
    });

    it('fans out a gateway parent with multiple successors', () => {
      const nodes: Node[] = [
        makeNode('a', { type: 'start' }),
        makeNode('g', { type: 'gateway' }),
        makeNode('x'),
        makeNode('y'),
      ];
      const edges: Edge[] = [
        makeEdge('a-g', 'a', 'g'),
        makeEdge('g-x', 'g', 'x'),
        makeEdge('g-y', 'g', 'y'),
      ];
      const result = simulateIncrementalBuild(nodes, edges)!;
      const x = result.find(n => n.id === 'x')!;
      const y = result.find(n => n.id === 'y')!;
      // Gateway children have distinct X positions (fan-out).
      expect(x.position.x).not.toBe(y.position.x);
    });

    it('places two events side-by-side without overlap', () => {
      const nodes: Node[] = [
        makeNode('e1', { type: 'start' }),
        makeNode('e2', { type: 'start' }),
      ];
      const result = simulateIncrementalBuild(nodes, [])!;
      const e1 = result.find(n => n.id === 'e1')!;
      const e2 = result.find(n => n.id === 'e2')!;
      // Distinct X positions, both at the same row.
      expect(e1.position.x).not.toBe(e2.position.x);
      expect(Math.abs(e1.position.y - e2.position.y)).toBeLessThanOrEqual(0);
    });

    it('terminates and produces finite positions for a cycle', () => {
      const nodes: Node[] = [
        makeNode('a', { type: 'start' }),
        makeNode('b'),
        makeNode('c'),
      ];
      const edges: Edge[] = [
        makeEdge('a-b', 'a', 'b'),
        makeEdge('b-c', 'b', 'c'),
        makeEdge('c-a', 'c', 'a'), // back-edge
      ];
      const result = simulateIncrementalBuild(nodes, edges)!;
      expect(result).toHaveLength(3);
      for (const node of result) {
        expect(Number.isFinite(node.position.x)).toBe(true);
        expect(Number.isFinite(node.position.y)).toBe(true);
      }
    });

    it('places fully disconnected components without overlap', () => {
      const nodes: Node[] = [
        makeNode('a', { type: 'start' }),
        makeNode('b'),
        makeNode('isolated'),
      ];
      const edges: Edge[] = [makeEdge('a-b', 'a', 'b')];
      const result = simulateIncrementalBuild(nodes, edges)!;
      const isolated = result.find(n => n.id === 'isolated')!;
      const a = result.find(n => n.id === 'a')!;
      // Isolated node placed to the right of the connected flow.
      expect(isolated.position.x).toBeGreaterThan(a.position.x);
    });

    it('preserves the autoLayout signature for empty edges', () => {
      const nodes = [makeNode('a', { type: 'start' })];
      // Both empty array and undefined-as-array should work.
      expect(simulateIncrementalBuild(nodes, [])).not.toBeNull();
    });

    it('centers a convergent node under the centroid of its parents', () => {
      // Issue #3588454 follow-up: an event with two condition branches
      // that converge into a single action should place the action
      // centered between the two conditions (== under the event when
      // the conditions are symmetric), not in one condition's column.
      const nodes: Node[] = [
        makeNode('event', { type: 'start' }),
        makeNode('cond1'),
        makeNode('cond2'),
        makeNode('action'),
      ];
      const edges: Edge[] = [
        makeEdge('e-c1', 'event', 'cond1', { data: { condition: 'a' } }),
        makeEdge('e-c2', 'event', 'cond2', { data: { condition: 'b' } }),
        makeEdge('c1-a', 'cond1', 'action'),
        makeEdge('c2-a', 'cond2', 'action'),
      ];
      const result = simulateIncrementalBuild(nodes, edges)!;
      const event = result.find(n => n.id === 'event')!;
      const cond1 = result.find(n => n.id === 'cond1')!;
      const cond2 = result.find(n => n.id === 'cond2')!;
      const action = result.find(n => n.id === 'action')!;

      // Action's center should sit at the midpoint of cond1 and cond2 centers.
      const cond1Center = cond1.position.x + (cond1.width || NODE_DIMENSIONS.CARD_WIDTH) / 2;
      const cond2Center = cond2.position.x + (cond2.width || NODE_DIMENSIONS.CARD_WIDTH) / 2;
      const expectedCenter = (cond1Center + cond2Center) / 2;
      const actionCenter = action.position.x + (action.width || NODE_DIMENSIONS.CARD_WIDTH) / 2;
      expect(actionCenter).toBeCloseTo(expectedCenter, 0);

      // And below the lowest condition.
      const lowerConditionBottom = Math.max(
        cond1.position.y + (cond1.height || NODE_DIMENSIONS.CARD_HEIGHT),
        cond2.position.y + (cond2.height || NODE_DIMENSIONS.CARD_HEIGHT),
      );
      expect(action.position.y).toBeGreaterThanOrEqual(lowerConditionBottom);
      // event reference retained for symmetric reasoning above.
      void event;
    });

    it('places a convergent node at the centroid even with three parents', () => {
      const nodes: Node[] = [
        makeNode('e', { type: 'start' }),
        makeNode('p1'),
        makeNode('p2'),
        makeNode('p3'),
        makeNode('merge'),
      ];
      const edges: Edge[] = [
        makeEdge('e-p1', 'e', 'p1'),
        makeEdge('e-p2', 'e', 'p2'),
        makeEdge('e-p3', 'e', 'p3'),
        makeEdge('p1-m', 'p1', 'merge'),
        makeEdge('p2-m', 'p2', 'merge'),
        makeEdge('p3-m', 'p3', 'merge'),
      ];
      const result = simulateIncrementalBuild(nodes, edges)!;
      const merge = result.find(n => n.id === 'merge')!;
      const parents = ['p1', 'p2', 'p3'].map(id => result.find(n => n.id === id)!);
      const centroidX =
        parents.reduce((sum, p) => sum + p.position.x + (p.width || NODE_DIMENSIONS.CARD_WIDTH) / 2, 0) /
        parents.length;
      const mergeCenter = merge.position.x + (merge.width || NODE_DIMENSIONS.CARD_WIDTH) / 2;
      expect(mergeCenter).toBeCloseTo(centroidX, 0);
    });
  });

  // ── Condition-adjacency invariant (issue #3589093) ────────────────────
  // buildConditionInsertion must never produce a condition -> condition
  // edge: it inserts gateway node(s) whenever an end of the target edge is
  // itself a condition node.
  describe('isConditionNode', () => {
    it('recognizes condition nodes by type', () => {
      expect(isConditionNode(makeNode('c', { type: 'condition' }))).toBe(true);
    });
    it('recognizes condition nodes by __isConditionNode data flag', () => {
      expect(isConditionNode(makeNode('c', { data: { __isConditionNode: true } }))).toBe(true);
    });
    it('returns false for non-condition nodes and undefined', () => {
      expect(isConditionNode(makeNode('e', { type: 'element' }))).toBe(false);
      expect(isConditionNode(undefined)).toBe(false);
    });
  });

  describe('buildConditionInsertion', () => {
    /** A fresh condition node to insert. */
    const condNode = (): Node =>
      makeNode('newCond', { type: 'condition', data: { __isConditionNode: true } });

    /** Assert no edge in the set connects a condition id directly to another. */
    const assertNoConditionToCondition = (
      edges: Edge[],
      conditionIds: Set<string>,
    ): void => {
      for (const e of edges) {
        const both = conditionIds.has(e.source) && conditionIds.has(e.target);
        expect(both).toBe(false);
      }
    };

    it('case 1 — neither end is a condition: source -> cond -> target (no gateway)', () => {
      const cond = condNode();
      const { nodesToAdd, edgesToAdd } = buildConditionInsertion({
        sourceNodeId: 'src',
        targetNodeId: 'tgt',
        conditionNode: cond,
        sourceIsCondition: false,
        targetIsCondition: false,
      });
      // Only the condition node is added — no gateway.
      expect(nodesToAdd).toHaveLength(1);
      expect(nodesToAdd[0].id).toBe('newCond');
      expect(nodesToAdd.some(n => n.type === 'gateway')).toBe(false);
      // Edges: src -> newCond -> tgt.
      expect(edgesToAdd).toHaveLength(2);
      expect(edgesToAdd[0]).toMatchObject({ source: 'src', target: 'newCond', type: 'default' });
      expect(edgesToAdd[1]).toMatchObject({ source: 'newCond', target: 'tgt', type: 'default' });
    });

    it('case 2 — target IS a condition: source -> cond -> gateway -> condB', () => {
      const cond = condNode();
      const { nodesToAdd, edgesToAdd } = buildConditionInsertion({
        sourceNodeId: 'src',
        targetNodeId: 'condB',
        conditionNode: cond,
        sourceIsCondition: false,
        targetIsCondition: true,
      });
      // Condition node + exactly one gateway.
      expect(nodesToAdd).toHaveLength(2);
      const gateway = nodesToAdd.find(n => n.type === 'gateway')!;
      expect(gateway).toBeDefined();
      expect(gateway.data?.componentType).toBe(6);
      expect(gateway.data?.plugin).toBe('gateway');
      // Edges preserve order: src -> newCond -> gateway -> condB.
      expect(edgesToAdd).toHaveLength(3);
      expect(edgesToAdd[0]).toMatchObject({ source: 'src', target: 'newCond' });
      expect(edgesToAdd[1]).toMatchObject({ source: 'newCond', target: gateway.id });
      expect(edgesToAdd[2]).toMatchObject({ source: gateway.id, target: 'condB' });
      // No condition -> condition edge (newCond and condB are conditions).
      assertNoConditionToCondition(edgesToAdd, new Set(['newCond', 'condB']));
    });

    it('case 3 — source IS a condition: condA -> gateway -> cond -> target', () => {
      const cond = condNode();
      const { nodesToAdd, edgesToAdd } = buildConditionInsertion({
        sourceNodeId: 'condA',
        targetNodeId: 'tgt',
        conditionNode: cond,
        sourceIsCondition: true,
        targetIsCondition: false,
      });
      expect(nodesToAdd).toHaveLength(2);
      const gateway = nodesToAdd.find(n => n.type === 'gateway')!;
      expect(gateway).toBeDefined();
      // Edges preserve order: condA -> gateway -> newCond -> tgt.
      expect(edgesToAdd).toHaveLength(3);
      expect(edgesToAdd[0]).toMatchObject({ source: 'condA', target: gateway.id });
      expect(edgesToAdd[1]).toMatchObject({ source: gateway.id, target: 'newCond' });
      expect(edgesToAdd[2]).toMatchObject({ source: 'newCond', target: 'tgt' });
      assertNoConditionToCondition(edgesToAdd, new Set(['condA', 'newCond']));
    });

    it('case 4 — BOTH ends conditions: condA -> gw1 -> cond -> gw2 -> condB (two gateways)', () => {
      const cond = condNode();
      const { nodesToAdd, edgesToAdd } = buildConditionInsertion({
        sourceNodeId: 'condA',
        targetNodeId: 'condB',
        conditionNode: cond,
        sourceIsCondition: true,
        targetIsCondition: true,
      });
      // Condition node + two gateways so neither pair is adjacent.
      expect(nodesToAdd).toHaveLength(3);
      const gateways = nodesToAdd.filter(n => n.type === 'gateway');
      expect(gateways).toHaveLength(2);
      const [gw1, gw2] = gateways;
      // Edges: condA -> gw1 -> newCond -> gw2 -> condB.
      expect(edgesToAdd).toHaveLength(4);
      expect(edgesToAdd[0]).toMatchObject({ source: 'condA', target: gw1.id });
      expect(edgesToAdd[1]).toMatchObject({ source: gw1.id, target: 'newCond' });
      expect(edgesToAdd[2]).toMatchObject({ source: 'newCond', target: gw2.id });
      expect(edgesToAdd[3]).toMatchObject({ source: gw2.id, target: 'condB' });
      // No condition -> condition edge among the three condition ids.
      assertNoConditionToCondition(edgesToAdd, new Set(['condA', 'newCond', 'condB']));
    });
  });

  describe('placeChainOnEdge', () => {
    it('stacks a two-node chain below the source and shifts the target down', () => {
      // Flow here is src -> cond -> gw -> tgt, so flow order == array order
      // ([cond, gw]); the gateway must end up below the condition.  This
      // mirrors buildConditionInsertion Case 2 with a non-condition source.
      const source = makeNode('src', { position: { x: 100, y: 0 } });
      const target = makeNode('tgt', { position: { x: 100, y: 200 } });
      const chain = [
        makeNode('cond', { type: 'condition', position: { x: 0, y: 0 } }),
        makeNode('gw', { type: 'gateway', position: { x: 0, y: 0 }, height: NODE_DIMENSIONS.GATEWAY_HEIGHT }),
      ];
      const edges = [
        makeEdge('e1', 'src', 'cond'),
        makeEdge('e2', 'cond', 'gw'),
        makeEdge('e3', 'gw', 'tgt'),
      ];
      const result = placeChainOnEdge([source, target], edges, chain, 'src', 'tgt');
      const placedCond = result.find(n => n.id === 'cond')!;
      const placedGw = result.find(n => n.id === 'gw')!;
      const placedTarget = result.find(n => n.id === 'tgt')!;
      // Both chain nodes are below the source, column-aligned, and stacked
      // (gateway below the condition).
      expect(placedCond.position.y).toBeGreaterThan(source.position.y);
      expect(placedGw.position.y).toBeGreaterThan(placedCond.position.y);
      expect(placedCond.position.x).toBe(source.position.x);
      expect(placedGw.position.x).toBe(source.position.x);
      // Target shifted down to clear the whole chain.
      expect(placedTarget.position.y).toBeGreaterThanOrEqual(placedGw.position.y);
    });

    // ── Flow-order placement (issue #3589093 regression) ────────────────
    // placeChainOnEdge must position chain nodes in the order they appear
    // along the edges (sourceNodeId -> ... -> targetNodeId), NOT in the
    // order they happen to occupy the `chain` array.

    it('Case 3 regression — orders gateway ABOVE the new condition (flow: condA -> gateway -> cond -> target)', () => {
      // buildConditionInsertion Case 3 (source IS a condition) returns
      // nodesToAdd = [cond, gateway] but wires condA -> gateway -> cond -> target.
      // The previous array-order placement put the condition above the
      // gateway (the reported bug); flow-order placement must put the
      // GATEWAY above the CONDITION.
      const cond = condNodeWith('newCond');
      const { nodesToAdd, edgesToAdd } = buildConditionInsertion({
        sourceNodeId: 'condA',
        targetNodeId: 'tgt',
        conditionNode: cond,
        sourceIsCondition: true,
        targetIsCondition: false,
      });
      const gateway = nodesToAdd.find(n => n.type === 'gateway')!;

      const source = makeNode('condA', { type: 'condition', position: { x: 100, y: 0 }, data: { __isConditionNode: true } });
      const target = makeNode('tgt', { position: { x: 100, y: 200 } });
      // Callers pass the full rewired edge set (allEdges).
      const allEdges: Edge[] = [...edgesToAdd];

      const result = placeChainOnEdge([source, target], allEdges, nodesToAdd, 'condA', 'tgt');
      const placedGw = result.find(n => n.id === gateway.id)!;
      const placedCond = result.find(n => n.id === 'newCond')!;
      const placedTarget = result.find(n => n.id === 'tgt')!;

      // THE REGRESSION ASSERTION: gateway (flow-first) sits ABOVE the new condition.
      expect(placedGw.position.y).toBeLessThan(placedCond.position.y);
      // Both chain nodes sit below the source...
      expect(placedGw.position.y).toBeGreaterThan(source.position.y);
      // ...and above the (downward-shifted) target.
      expect(placedCond.position.y).toBeLessThan(placedTarget.position.y);
      // Column-aligned under the source.
      expect(placedGw.position.x).toBe(source.position.x);
      expect(placedCond.position.x).toBe(source.position.x);
    });

    it('Case 2 ordering — places the new condition ABOVE the gateway (flow: source -> cond -> gateway -> condB)', () => {
      // Non-condition source, condition target.  nodesToAdd = [cond, gateway]
      // and the flow is source -> cond -> gateway -> condB, so here flow order
      // matches array order: cond must be above gateway.
      const cond = condNodeWith('newCond');
      const { nodesToAdd, edgesToAdd } = buildConditionInsertion({
        sourceNodeId: 'src',
        targetNodeId: 'condB',
        conditionNode: cond,
        sourceIsCondition: false,
        targetIsCondition: true,
      });
      const gateway = nodesToAdd.find(n => n.type === 'gateway')!;

      const source = makeNode('src', { position: { x: 100, y: 0 } });
      const target = makeNode('condB', { type: 'condition', position: { x: 100, y: 200 }, data: { __isConditionNode: true } });
      const allEdges: Edge[] = [...edgesToAdd];

      const result = placeChainOnEdge([source, target], allEdges, nodesToAdd, 'src', 'condB');
      const placedCond = result.find(n => n.id === 'newCond')!;
      const placedGw = result.find(n => n.id === gateway.id)!;

      // Condition (flow-first) sits ABOVE the gateway.
      expect(placedCond.position.y).toBeLessThan(placedGw.position.y);
    });

    it('Case 4 ordering — stacks gateway1 < cond < gateway2 (flow: condA -> gw1 -> cond -> gw2 -> condB)', () => {
      // Both ends are conditions.  nodesToAdd = [cond, gw1, gw2] but the flow
      // is condA -> gw1 -> cond -> gw2 -> condB, so the vertical order must be
      // gw1, then cond, then gw2 — independent of the array order.
      const cond = condNodeWith('newCond');
      const { nodesToAdd, edgesToAdd } = buildConditionInsertion({
        sourceNodeId: 'condA',
        targetNodeId: 'condB',
        conditionNode: cond,
        sourceIsCondition: true,
        targetIsCondition: true,
      });
      const [gw1, gw2] = nodesToAdd.filter(n => n.type === 'gateway');

      const source = makeNode('condA', { type: 'condition', position: { x: 100, y: 0 }, data: { __isConditionNode: true } });
      const target = makeNode('condB', { type: 'condition', position: { x: 100, y: 400 }, data: { __isConditionNode: true } });
      const allEdges: Edge[] = [...edgesToAdd];

      const result = placeChainOnEdge([source, target], allEdges, nodesToAdd, 'condA', 'condB');
      const placedGw1 = result.find(n => n.id === gw1.id)!;
      const placedCond = result.find(n => n.id === 'newCond')!;
      const placedGw2 = result.find(n => n.id === gw2.id)!;

      // Flow order top-to-bottom: gateway1, condition, gateway2.
      expect(placedGw1.position.y).toBeLessThan(placedCond.position.y);
      expect(placedCond.position.y).toBeLessThan(placedGw2.position.y);
    });

    it('falls back to array order when the edges do not wire the chain end-to-end', () => {
      // Defensive: no edges connect the chain to the source, so the flow
      // walk cannot order it.  placeChainOnEdge must not crash or drop
      // nodes — it falls back to the chain array order.
      const source = makeNode('src', { position: { x: 100, y: 0 } });
      const target = makeNode('tgt', { position: { x: 100, y: 200 } });
      const chain = [
        makeNode('a', { type: 'condition', position: { x: 0, y: 0 } }),
        makeNode('b', { type: 'gateway', position: { x: 0, y: 0 }, height: NODE_DIMENSIONS.GATEWAY_HEIGHT }),
      ];
      // Only the outer edges exist; the chain is not reachable from src.
      const edges = [makeEdge('e1', 'src', 'tgt')];
      const result = placeChainOnEdge([source, target], edges, chain, 'src', 'tgt');
      const placedA = result.find(n => n.id === 'a')!;
      const placedB = result.find(n => n.id === 'b')!;
      // Both nodes still placed (none dropped), in array order: a above b.
      expect(placedA.position.y).toBeLessThan(placedB.position.y);
      expect(result.some(n => n.id === 'a')).toBe(true);
      expect(result.some(n => n.id === 'b')).toBe(true);
    });

    it('returns nodes unchanged plus chain when source or target is missing', () => {
      const source = makeNode('src');
      const chain = [makeNode('cond', { type: 'condition' })];
      const result = placeChainOnEdge([source], [], chain, 'src', 'missing');
      expect(result).toHaveLength(2);
      expect(result.some(n => n.id === 'cond')).toBe(true);
    });
  });
});
