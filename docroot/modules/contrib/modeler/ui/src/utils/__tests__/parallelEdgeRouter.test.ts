import {
  routeParallelEdge,
  findDirectParallelEdges,
  findIntermediatePath,
  computeFanOutOffsets,
  applyParallelEdgeRouting,
  routeAllParallelEdges,
  PARALLEL_EDGE_FAN_STEP,
  BYPASS_EDGE_CLEARANCE,
} from '../parallelEdgeRouter';
import type { StoreEdge as Edge, StoreNode as Node } from '../../types/settings';

// ── Test helpers ────────────────────────────────────────────────────────────

function makeEdge(id: string, source: string, target: string, controlOffset?: { x: number; y: number }): Edge {
  return {
    id,
    source,
    target,
    type: 'default',
    data: controlOffset ? { controlOffset } : {},
  } as Edge;
}

function makeNode(id: string, x: number, y: number): Node {
  return {
    id,
    type: 'element',
    position: { x, y },
    data: {},
    width: 180,
    height: 120,
  } as Node;
}

// ── computeFanOutOffsets ────────────────────────────────────────────────────

describe('computeFanOutOffsets', () => {
  it('returns empty array for zero edges', () => {
    expect(computeFanOutOffsets(0)).toEqual([]);
  });

  it('returns [0] for a single edge', () => {
    expect(computeFanOutOffsets(1)).toEqual([0]);
  });

  it('distributes two edges symmetrically around zero', () => {
    const result = computeFanOutOffsets(2, 100);
    expect(result).toEqual([-50, 50]);
  });

  it('places the middle edge at zero for three edges', () => {
    const result = computeFanOutOffsets(3, 100);
    expect(result).toEqual([-100, 0, 100]);
  });

  it('keeps four edges symmetric with no zero entry', () => {
    const result = computeFanOutOffsets(4, 100);
    expect(result).toEqual([-150, -50, 50, 150]);
  });

  it('uses the default step when none is provided', () => {
    const result = computeFanOutOffsets(2);
    expect(result).toEqual([-PARALLEL_EDGE_FAN_STEP / 2, PARALLEL_EDGE_FAN_STEP / 2]);
  });

  it('keeps the centroid at zero for any count', () => {
    for (let n = 1; n <= 10; n++) {
      const offsets = computeFanOutOffsets(n);
      const sum = offsets.reduce((a, b) => a + b, 0);
      expect(Math.abs(sum)).toBeLessThan(1e-9);
    }
  });
});

// ── findDirectParallelEdges ─────────────────────────────────────────────────

describe('findDirectParallelEdges', () => {
  const edges: Edge[] = [
    makeEdge('e1', 'A', 'B'),
    makeEdge('e2', 'A', 'B'),
    makeEdge('e3', 'A', 'C'),
    makeEdge('e4', 'B', 'A'), // reverse direction — must NOT match
  ];

  it('finds all edges sharing source and target', () => {
    const result = findDirectParallelEdges('A', 'B', edges);
    expect(result.map((e) => e.id).sort()).toEqual(['e1', 'e2']);
  });

  it('excludes the new edge by id', () => {
    const result = findDirectParallelEdges('A', 'B', edges, 'e2');
    expect(result.map((e) => e.id)).toEqual(['e1']);
  });

  it('does not match the reverse-direction edge', () => {
    const result = findDirectParallelEdges('A', 'B', edges);
    expect(result.find((e) => e.id === 'e4')).toBeUndefined();
  });

  it('returns an empty array when no parallels exist', () => {
    expect(findDirectParallelEdges('X', 'Y', edges)).toEqual([]);
  });
});

// ── findIntermediatePath ────────────────────────────────────────────────────

describe('findIntermediatePath', () => {
  it('returns a direct path of length 2 when source connects directly to target', () => {
    const edges = [makeEdge('e1', 'A', 'B')];
    expect(findIntermediatePath('A', 'B', edges)).toEqual(['A', 'B']);
  });

  it('finds a single intermediate hop', () => {
    const edges = [
      makeEdge('e1', 'A', 'M'),
      makeEdge('e2', 'M', 'B'),
    ];
    expect(findIntermediatePath('A', 'B', edges)).toEqual(['A', 'M', 'B']);
  });

  it('finds the shortest path through multiple intermediates', () => {
    const edges = [
      makeEdge('e1', 'A', 'M1'),
      makeEdge('e2', 'M1', 'M2'),
      makeEdge('e3', 'M2', 'B'),
    ];
    expect(findIntermediatePath('A', 'B', edges)).toEqual(['A', 'M1', 'M2', 'B']);
  });

  it('returns null when no path exists', () => {
    const edges = [makeEdge('e1', 'A', 'M')];
    expect(findIntermediatePath('A', 'B', edges)).toBeNull();
  });

  it('returns null when source equals target', () => {
    const edges = [makeEdge('e1', 'A', 'A')];
    expect(findIntermediatePath('A', 'A', edges)).toBeNull();
  });

  it('respects edge direction', () => {
    const edges = [makeEdge('e1', 'B', 'A')];
    expect(findIntermediatePath('A', 'B', edges)).toBeNull();
  });

  it('excludes the specified edge id when searching', () => {
    const edges = [
      makeEdge('e1', 'A', 'B'),
      makeEdge('e2', 'A', 'M'),
      makeEdge('e3', 'M', 'B'),
    ];
    // Without exclusion, the direct edge yields a 2-step path.
    expect(findIntermediatePath('A', 'B', edges)).toEqual(['A', 'B']);
    // With the direct edge excluded, the search must use the longer path.
    expect(findIntermediatePath('A', 'B', edges, 'e1')).toEqual(['A', 'M', 'B']);
  });
});

// ── routeParallelEdge ───────────────────────────────────────────────────────

describe('routeParallelEdge', () => {
  describe('no parallel collision', () => {
    it('returns routing="none" when source and target are not connected', () => {
      const nodes = [makeNode('A', 0, 0), makeNode('B', 0, 200)];
      const newEdge = makeEdge('e-new', 'A', 'B');
      const result = routeParallelEdge({ newEdge, edges: [newEdge], nodes });
      expect(result.routing).toBe('none');
      expect(result.updates).toEqual([]);
    });

    it('returns routing="none" when source equals target', () => {
      const nodes = [makeNode('A', 0, 0)];
      const newEdge = makeEdge('e-new', 'A', 'A');
      const result = routeParallelEdge({ newEdge, edges: [newEdge], nodes });
      expect(result.routing).toBe('none');
    });
  });

  describe('direct parallel — fan-out', () => {
    it('redistributes a 1+1 group to ±step/2', () => {
      const nodes = [makeNode('A', 0, 0), makeNode('B', 0, 200)];
      const existing = makeEdge('e1', 'A', 'B');
      const newEdge = makeEdge('e2', 'A', 'B');
      const result = routeParallelEdge({
        newEdge,
        edges: [existing, newEdge],
        nodes,
      });

      expect(result.routing).toBe('fan-out');
      expect(result.updates).toHaveLength(2);
      const e1Update = result.updates.find((u) => u.edgeId === 'e1');
      const e2Update = result.updates.find((u) => u.edgeId === 'e2');
      expect(e1Update?.controlOffset).toEqual({ x: -PARALLEL_EDGE_FAN_STEP / 2, y: 0 });
      expect(e2Update?.controlOffset).toEqual({ x: PARALLEL_EDGE_FAN_STEP / 2, y: 0 });
    });

    it('appends outside the existing group when siblings already have non-zero offsets', () => {
      const nodes = [makeNode('A', 0, 0), makeNode('B', 0, 200)];
      // Two existing edges fanned out from a previous addition.
      const existing1 = makeEdge('e1', 'A', 'B', {
        x: -PARALLEL_EDGE_FAN_STEP / 2,
        y: 0,
      });
      const existing2 = makeEdge('e2', 'A', 'B', {
        x: PARALLEL_EDGE_FAN_STEP / 2,
        y: 0,
      });
      const newEdge = makeEdge('e3', 'A', 'B');

      const result = routeParallelEdge({
        newEdge,
        edges: [existing1, existing2, newEdge],
        nodes,
      });

      // Existing siblings have non-zero offsets so we treat them as
      // user-customized and only place the new edge.
      expect(result.routing).toBe('fan-out');
      // No updates for existing edges.
      expect(result.updates.find((u) => u.edgeId === 'e1')).toBeUndefined();
      expect(result.updates.find((u) => u.edgeId === 'e2')).toBeUndefined();
      // The new edge lands outside the existing fan.
      const newUpdate = result.updates.find((u) => u.edgeId === 'e3');
      expect(newUpdate).toBeDefined();
      expect(Math.abs(newUpdate!.controlOffset.x)).toBeGreaterThan(
        PARALLEL_EDGE_FAN_STEP / 2,
      );
    });

    it('preserves user-customized offsets and only routes the new edge', () => {
      const nodes = [makeNode('A', 0, 0), makeNode('B', 0, 200)];
      // User has hand-positioned the existing edge.
      const customOffset = { x: 200, y: 50 };
      const existing = makeEdge('e1', 'A', 'B', customOffset);
      const newEdge = makeEdge('e2', 'A', 'B');

      const result = routeParallelEdge({
        newEdge,
        edges: [existing, newEdge],
        nodes,
      });

      expect(result.routing).toBe('fan-out');
      // The existing edge must NOT appear in updates.
      expect(result.updates.find((u) => u.edgeId === 'e1')).toBeUndefined();
      // The new edge gets an offset on the opposite side of the existing one.
      const newUpdate = result.updates.find((u) => u.edgeId === 'e2');
      expect(newUpdate).toBeDefined();
      expect(newUpdate!.controlOffset.x).toBeLessThan(0);
    });

    it('skips updates whose offset already matches the computed value', () => {
      const nodes = [makeNode('A', 0, 0), makeNode('B', 0, 200)];
      // Existing sibling at zero offset (default), so a rebalance is allowed.
      const existing = makeEdge('e1', 'A', 'B');
      // New edge happens to already be at its computed fan-out target.
      const newEdge = makeEdge('e2', 'A', 'B', {
        x: PARALLEL_EDGE_FAN_STEP / 2,
        y: 0,
      });

      const result = routeParallelEdge({
        newEdge,
        edges: [existing, newEdge],
        nodes,
      });

      expect(result.routing).toBe('fan-out');
      // Only the existing edge needs an update — the new edge is already in place.
      expect(result.updates).toHaveLength(1);
      expect(result.updates[0].edgeId).toBe('e1');
      expect(result.updates[0].controlOffset).toEqual({
        x: -PARALLEL_EDGE_FAN_STEP / 2,
        y: 0,
      });
    });
  });

  describe('bypass parallel — chain routing', () => {
    it('curves to the right of a vertical chain by default', () => {
      // A → M → B, all in a single vertical column at x=0.
      const nodes = [
        makeNode('A', 0, 0),
        makeNode('M', 0, 200),
        makeNode('B', 0, 400),
      ];
      const edges = [
        makeEdge('e1', 'A', 'M'),
        makeEdge('e2', 'M', 'B'),
        // The new direct edge from A to B.
        makeEdge('e3', 'A', 'B'),
      ];
      const result = routeParallelEdge({
        newEdge: edges[2],
        edges,
        nodes,
      });

      expect(result.routing).toBe('bypass');
      expect(result.updates).toHaveLength(1);
      const update = result.updates[0];
      expect(update.edgeId).toBe('e3');
      // Default routing in a perfectly vertical column goes right (rightHeadroom == leftHeadroom yields routeRight=true).
      expect(update.controlOffset.x).toBeGreaterThan(0);
      // Magnitude must clear the chain plus the clearance margin.
      expect(update.controlOffset.x).toBeGreaterThanOrEqual(BYPASS_EDGE_CLEARANCE);
      expect(update.controlOffset.y).toBe(0);
    });

    it('curves to the side with more headroom when the chain is asymmetric', () => {
      // Chain extends to the LEFT of the source/target column,
      // so routing should go RIGHT (more headroom there).
      const nodes = [
        makeNode('A', 0, 0),
        makeNode('M', -300, 200), // intermediate to the left
        makeNode('B', 0, 400),
      ];
      const edges = [
        makeEdge('e1', 'A', 'M'),
        makeEdge('e2', 'M', 'B'),
        makeEdge('e3', 'A', 'B'),
      ];
      const result = routeParallelEdge({
        newEdge: edges[2],
        edges,
        nodes,
      });
      expect(result.routing).toBe('bypass');
      expect(result.updates[0].controlOffset.x).toBeGreaterThan(0);
    });

    it('curves left when the chain extends further to the right', () => {
      const nodes = [
        makeNode('A', 0, 0),
        makeNode('M', 300, 200), // intermediate to the right
        makeNode('B', 0, 400),
      ];
      const edges = [
        makeEdge('e1', 'A', 'M'),
        makeEdge('e2', 'M', 'B'),
        makeEdge('e3', 'A', 'B'),
      ];
      const result = routeParallelEdge({
        newEdge: edges[2],
        edges,
        nodes,
      });
      expect(result.routing).toBe('bypass');
      expect(result.updates[0].controlOffset.x).toBeLessThan(0);
    });

    it('handles a chain of multiple intermediate nodes', () => {
      const nodes = [
        makeNode('A', 0, 0),
        makeNode('M1', 0, 200),
        makeNode('M2', 0, 400),
        makeNode('M3', 0, 600),
        makeNode('B', 0, 800),
      ];
      const edges = [
        makeEdge('e1', 'A', 'M1'),
        makeEdge('e2', 'M1', 'M2'),
        makeEdge('e3', 'M2', 'M3'),
        makeEdge('e4', 'M3', 'B'),
        makeEdge('e5', 'A', 'B'),
      ];
      const result = routeParallelEdge({
        newEdge: edges[4],
        edges,
        nodes,
      });
      expect(result.routing).toBe('bypass');
      expect(result.updates).toHaveLength(1);
      expect(Math.abs(result.updates[0].controlOffset.x)).toBeGreaterThan(0);
    });

    it('returns routing="none" if the source or target node is missing', () => {
      const nodes = [makeNode('A', 0, 0)]; // B is absent
      const edges = [
        makeEdge('e1', 'A', 'M'),
        makeEdge('e2', 'M', 'B'),
        makeEdge('e3', 'A', 'B'),
      ];
      const result = routeParallelEdge({
        newEdge: edges[2],
        edges,
        nodes,
      });
      expect(result.routing).toBe('none');
      expect(result.updates).toEqual([]);
    });
  });

  describe('precedence', () => {
    it('prefers fan-out over bypass when both apply', () => {
      // A connects to B both directly AND through an intermediate.
      const nodes = [
        makeNode('A', 0, 0),
        makeNode('M', 0, 200),
        makeNode('B', 0, 400),
      ];
      const direct = makeEdge('e1', 'A', 'B');
      const intermediate1 = makeEdge('e2', 'A', 'M');
      const intermediate2 = makeEdge('e3', 'M', 'B');
      const newEdge = makeEdge('e4', 'A', 'B');

      const result = routeParallelEdge({
        newEdge,
        edges: [direct, intermediate1, intermediate2, newEdge],
        nodes,
      });
      // Direct parallel takes precedence: fan-out, not bypass.
      expect(result.routing).toBe('fan-out');
    });
  });
});

// ── applyParallelEdgeRouting ────────────────────────────────────────────────

describe('applyParallelEdgeRouting', () => {
  it('returns edges unchanged when updates array is empty', () => {
    const edges = [
      makeEdge('e1', 'n1', 'n2'),
      makeEdge('e2', 'n1', 'n2'),
    ];
    const result = applyParallelEdgeRouting(edges, []);
    expect(result).toBe(edges); // Same reference
  });

  it('applies control offset updates to specified edges', () => {
    const edges = [
      makeEdge('e1', 'n1', 'n2'),
      makeEdge('e2', 'n1', 'n2'),
      makeEdge('e3', 'n3', 'n4'),
    ];
    const updates = [
      { edgeId: 'e1', controlOffset: { x: -80, y: 0 } },
      { edgeId: 'e2', controlOffset: { x: 80, y: 0 } },
    ];

    const result = applyParallelEdgeRouting(edges, updates);

    expect(result[0].data?.controlOffset).toEqual({ x: -80, y: 0 });
    expect(result[1].data?.controlOffset).toEqual({ x: 80, y: 0 });
    expect(result[2].data?.controlOffset).toBeUndefined(); // Unchanged
  });

  it('preserves existing edge data while applying offsets', () => {
    const edges = [
      {
        ...makeEdge('e1', 'n1', 'n2'),
        data: { condition: 'my_condition', customField: 'value' },
      },
    ];
    const updates = [{ edgeId: 'e1', controlOffset: { x: 100, y: 0 } }];

    const result = applyParallelEdgeRouting(edges, updates);

    expect(result[0].data).toEqual({
      condition: 'my_condition',
      customField: 'value',
      controlOffset: { x: 100, y: 0 },
    });
  });

  it('overwrites existing control offset when applying new one', () => {
    const edges = [
      makeEdge('e1', 'n1', 'n2', { x: 50, y: 0 }),
    ];
    const updates = [{ edgeId: 'e1', controlOffset: { x: -80, y: 0 } }];

    const result = applyParallelEdgeRouting(edges, updates);

    expect(result[0].data?.controlOffset).toEqual({ x: -80, y: 0 });
  });

  it('handles multiple updates efficiently', () => {
    const edges = Array.from({ length: 10 }, (_, i) =>
      makeEdge(`e${i}`, 'n1', 'n2'),
    );
    const updates = [
      { edgeId: 'e0', controlOffset: { x: -120, y: 0 } },
      { edgeId: 'e5', controlOffset: { x: 0, y: 0 } },
      { edgeId: 'e9', controlOffset: { x: 120, y: 0 } },
    ];

    const result = applyParallelEdgeRouting(edges, updates);

    expect(result[0].data?.controlOffset).toEqual({ x: -120, y: 0 });
    expect(result[5].data?.controlOffset).toEqual({ x: 0, y: 0 });
    expect(result[9].data?.controlOffset).toEqual({ x: 120, y: 0 });
    // Other edges should remain unchanged
    expect(result[1].data?.controlOffset).toBeUndefined();
    expect(result[4].data?.controlOffset).toBeUndefined();
  });
});

// ── routeAllParallelEdges ───────────────────────────────────────────────────

describe('routeAllParallelEdges', () => {
  it('returns edges unchanged when no parallel groups exist', () => {
    const edges = [
      makeEdge('e1', 'A', 'B'),
      makeEdge('e2', 'B', 'C'),
      makeEdge('e3', 'C', 'D'),
    ];
    const result = routeAllParallelEdges(edges);
    // No group has 2+ edges, so nothing changes.
    expect(result).toBe(edges);
  });

  it('spreads two plain parallel edges symmetrically', () => {
    const edges = [
      makeEdge('e1', 'A', 'B'),
      makeEdge('e2', 'A', 'B'),
    ];
    const result = routeAllParallelEdges(edges);
    const e1Offset = result.find(e => e.id === 'e1')!.data?.controlOffset;
    const e2Offset = result.find(e => e.id === 'e2')!.data?.controlOffset;
    expect(e1Offset).toEqual({ x: -PARALLEL_EDGE_FAN_STEP / 2, y: 0 });
    expect(e2Offset).toEqual({ x: PARALLEL_EDGE_FAN_STEP / 2, y: 0 });
  });

  it('routes multiple independent parallel groups', () => {
    const edges = [
      makeEdge('e1', 'A', 'B'),
      makeEdge('e2', 'A', 'B'),
      makeEdge('e3', 'C', 'D'),
      makeEdge('e4', 'C', 'D'),
      makeEdge('e5', 'X', 'Y'), // No parallel — should stay untouched.
    ];
    const result = routeAllParallelEdges(edges);

    // Group A→B
    expect(result.find(e => e.id === 'e1')!.data?.controlOffset).toEqual({
      x: -PARALLEL_EDGE_FAN_STEP / 2, y: 0,
    });
    expect(result.find(e => e.id === 'e2')!.data?.controlOffset).toEqual({
      x: PARALLEL_EDGE_FAN_STEP / 2, y: 0,
    });
    // Group C→D
    expect(result.find(e => e.id === 'e3')!.data?.controlOffset).toEqual({
      x: -PARALLEL_EDGE_FAN_STEP / 2, y: 0,
    });
    expect(result.find(e => e.id === 'e4')!.data?.controlOffset).toEqual({
      x: PARALLEL_EDGE_FAN_STEP / 2, y: 0,
    });
    // Singleton X→Y should be unchanged.
    expect(result.find(e => e.id === 'e5')!.data?.controlOffset).toBeUndefined();
  });

  it('fans out three parallel edges with the middle at zero', () => {
    const edges = [
      makeEdge('e1', 'A', 'B'),
      makeEdge('e2', 'A', 'B'),
      makeEdge('e3', 'A', 'B'),
    ];
    const result = routeAllParallelEdges(edges);
    expect(result.find(e => e.id === 'e1')!.data?.controlOffset).toEqual({
      x: -PARALLEL_EDGE_FAN_STEP, y: 0,
    });
    expect(result.find(e => e.id === 'e2')!.data?.controlOffset).toEqual({
      x: 0, y: 0,
    });
    expect(result.find(e => e.id === 'e3')!.data?.controlOffset).toEqual({
      x: PARALLEL_EDGE_FAN_STEP, y: 0,
    });
  });

  it('returns same reference for empty edge array', () => {
    const edges: Edge[] = [];
    const result = routeAllParallelEdges(edges);
    expect(result).toBe(edges);
  });

  it('does not treat reverse-direction edges as parallels', () => {
    const edges = [
      makeEdge('e1', 'A', 'B'),
      makeEdge('e2', 'B', 'A'), // Reverse direction, not parallel.
    ];
    const result = routeAllParallelEdges(edges);
    // No group has 2+ edges in the same direction, so nothing changes.
    expect(result).toBe(edges);
  });
});
