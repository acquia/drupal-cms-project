/**
 * Tests for constraintValidation utility.
 *
 * Covers model-level cardinality constraints including the new
 * requireConditionWhenParallel flag for parallel-successor validation.
 */

import { validateModelConstraints, validateNoAdjacentConditions, validateConditionOutdegree } from '../constraintValidation';
import type { StoreNode, StoreEdge, ModelConstraints } from '../../types/settings';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Create a minimal StoreNode for testing. */
function makeNode(id: string, type: string, label?: string): StoreNode {
  return {
    id,
    type,
    position: { x: 0, y: 0 },
    data: { label: label ?? id },
  };
}

/** Create a minimal StoreEdge (default / no condition). */
function makeEdge(id: string, source: string, target: string): StoreEdge {
  return { id, source, target, data: {} };
}

/**
 * Create a condition NODE (issue #3589093).  Conditions are no longer edge
 * properties — they are first-class nodes (type 'condition',
 * data.__isConditionNode === true).
 */
function makeConditionNode(id: string, label?: string): StoreNode {
  return {
    id,
    type: 'condition',
    position: { x: 0, y: 0 },
    data: {
      label: label ?? 'Check',
      plugin: 'some_condition_plugin',
      configuration: {},
      conditionId: id,
      __isConditionNode: true,
    },
  };
}

/**
 * Build a CONDITIONAL successor path: source -> conditionNode -> target.
 * Returns the condition node plus the two plain ("default") edges that route
 * through it.  This is how P2's translation layer represents a condition
 * between two nodes at runtime.
 */
function makeConditionalPath(
  prefix: string,
  source: string,
  condNodeId: string,
  target: string,
): { node: StoreNode; edges: StoreEdge[] } {
  return {
    node: makeConditionNode(condNodeId),
    edges: [
      makeEdge(`${prefix}__in`, source, condNodeId),
      makeEdge(`${prefix}__out`, condNodeId, target),
    ],
  };
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('validateModelConstraints', () => {
  // ── Basic cardinality (min/max component counts) ───────────────────────

  describe('component count constraints', () => {
    it('should return no errors when counts are within bounds', () => {
      const nodes = [makeNode('n1', 'start'), makeNode('n2', 'element')];
      const constraints: ModelConstraints = {
        start: { min: 1, max: 1 },
        element: { min: 1 },
      };
      expect(validateModelConstraints(nodes, [], constraints)).toEqual([]);
    });

    it('should report error when count is below minimum', () => {
      const constraints: ModelConstraints = { start: { min: 1 } };
      const errors = validateModelConstraints([], [], constraints);
      expect(errors.length).toBe(1);
      expect(errors[0]).toContain('at least one');
    });

    it('should report error when count exceeds maximum', () => {
      const nodes = [
        makeNode('n1', 'start'),
        makeNode('n2', 'start'),
      ];
      const constraints: ModelConstraints = { start: { max: 1 } };
      const errors = validateModelConstraints(nodes, [], constraints);
      expect(errors.length).toBe(1);
      expect(errors[0]).toContain('at most one');
    });
  });

  // ── Successor min/max constraints ──────────────────────────────────────

  describe('successor cardinality constraints', () => {
    it('should report error when a node has fewer successors than required', () => {
      const nodes = [makeNode('n1', 'start', 'My Event')];
      const constraints: ModelConstraints = {
        start: { successors: { min: 1 } },
      };
      const errors = validateModelConstraints(nodes, [], constraints);
      expect(errors.length).toBe(1);
      expect(errors[0]).toContain('My Event');
      expect(errors[0]).toContain('at least');
    });

    it('should report error when a node exceeds maximum successors', () => {
      const nodes = [makeNode('n1', 'start', 'My Event'), makeNode('n2', 'element'), makeNode('n3', 'element')];
      const edges = [makeEdge('e1', 'n1', 'n2'), makeEdge('e2', 'n1', 'n3')];
      const constraints: ModelConstraints = {
        start: { successors: { max: 1 } },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      expect(errors.length).toBe(1);
      expect(errors[0]).toContain('at most');
    });
  });

  // ── requireConditionWhenParallel ───────────────────────────────────────

  describe('requireConditionWhenParallel', () => {
    it('should not check parallel edges when flag is absent', () => {
      const nodes = [
        makeNode('n1', 'start', 'Event A'),
        makeNode('n2', 'element', 'Action B'),
      ];
      // Two default (unconditional) edges between same source and target.
      const edges = [
        makeEdge('e1', 'n1', 'n2'),
        makeEdge('e2', 'n1', 'n2'),
      ];
      const constraints: ModelConstraints = {
        start: { successors: {} },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      expect(errors).toEqual([]);
    });

    it('should not check parallel edges when flag is false', () => {
      const nodes = [
        makeNode('n1', 'start', 'Event A'),
        makeNode('n2', 'element', 'Action B'),
      ];
      const edges = [
        makeEdge('e1', 'n1', 'n2'),
        makeEdge('e2', 'n1', 'n2'),
      ];
      const constraints: ModelConstraints = {
        start: { successors: { requireConditionWhenParallel: false } },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      expect(errors).toEqual([]);
    });

    it('should allow parallel paths when all route through a condition node', () => {
      // CHANGED (node model): two CONDITIONAL paths to the same resolved
      // target n2, each modeled as n1 -> condNode -> n2.  All paths are
      // conditional, so no error.
      const p1 = makeConditionalPath('e1', 'n1', 'cond1', 'n2');
      const p2 = makeConditionalPath('e2', 'n1', 'cond2', 'n2');
      const nodes = [
        makeNode('n1', 'start', 'Event A'),
        makeNode('n2', 'element', 'Action B'),
        p1.node,
        p2.node,
      ];
      const edges = [...p1.edges, ...p2.edges];
      const constraints: ModelConstraints = {
        start: { successors: { requireConditionWhenParallel: true } },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      expect(errors).toEqual([]);
    });

    it('should block when any parallel path lacks a condition node', () => {
      // CHANGED (node model): one CONDITIONAL path (n1 -> condNode -> n2)
      // and one UNCONDITIONAL path (n1 -> n2 directly) resolve to the same
      // target n2.  The unconditional path triggers exactly one error.
      const p1 = makeConditionalPath('e1', 'n1', 'cond1', 'n2');
      const nodes = [
        makeNode('n1', 'start', 'Event A'),
        makeNode('n2', 'element', 'Action B'),
        p1.node,
      ];
      const edges = [
        ...p1.edges,
        makeEdge('e2', 'n1', 'n2'), // direct, unconditional
      ];
      const constraints: ModelConstraints = {
        start: { successors: { requireConditionWhenParallel: true } },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      expect(errors.length).toBe(1);
      expect(errors[0]).toContain('Event A');
      expect(errors[0]).toContain('Action B');
      expect(errors[0]).toContain('parallel');
      expect(errors[0]).toContain('condition');
    });

    it('should block when all parallel paths lack a condition node', () => {
      // CHANGED (node model): two direct UNCONDITIONAL edges to the same
      // target n2 — both are unconditional, so one error for the group.
      const nodes = [
        makeNode('n1', 'start', 'Event A'),
        makeNode('n2', 'element', 'Action B'),
      ];
      const edges = [
        makeEdge('e1', 'n1', 'n2'),
        makeEdge('e2', 'n1', 'n2'),
      ];
      const constraints: ModelConstraints = {
        start: { successors: { requireConditionWhenParallel: true } },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      expect(errors.length).toBe(1);
      expect(errors[0]).toContain('parallel');
    });

    it('should not affect single edges (no parallel group)', () => {
      const nodes = [
        makeNode('n1', 'start', 'Event A'),
        makeNode('n2', 'element', 'Action B'),
      ];
      // Only one edge from n1 to n2 — not a parallel group.
      const edges = [makeEdge('e1', 'n1', 'n2')];
      const constraints: ModelConstraints = {
        start: { successors: { requireConditionWhenParallel: true } },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      expect(errors).toEqual([]);
    });

    it('should not affect edges to different targets', () => {
      const nodes = [
        makeNode('n1', 'start', 'Event A'),
        makeNode('n2', 'element', 'Action B'),
        makeNode('n3', 'element', 'Action C'),
      ];
      // Two edges from same source but to different targets — not parallel.
      const edges = [
        makeEdge('e1', 'n1', 'n2'),
        makeEdge('e2', 'n1', 'n3'),
      ];
      const constraints: ModelConstraints = {
        start: { successors: { requireConditionWhenParallel: true } },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      expect(errors).toEqual([]);
    });

    it('should only apply to the constrained component type', () => {
      const nodes = [
        makeNode('n1', 'start', 'Event A'),
        makeNode('n2', 'element', 'Action B'),
        makeNode('n3', 'element', 'Action C'),
      ];
      // Parallel unconditional edges from an element node (not constrained).
      const edges = [
        makeEdge('e1', 'n2', 'n3'),
        makeEdge('e2', 'n2', 'n3'),
      ];
      // Constraint is on 'start', not 'element'.
      const constraints: ModelConstraints = {
        start: { successors: { requireConditionWhenParallel: true } },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      expect(errors).toEqual([]);
    });

    it('should report errors per parallel group', () => {
      const nodes = [
        makeNode('n1', 'start', 'Event A'),
        makeNode('n2', 'element', 'Action B'),
        makeNode('n3', 'element', 'Action C'),
      ];
      // Two parallel groups, both violating.
      const edges = [
        makeEdge('e1', 'n1', 'n2'),
        makeEdge('e2', 'n1', 'n2'),
        makeEdge('e3', 'n1', 'n3'),
        makeEdge('e4', 'n1', 'n3'),
      ];
      const constraints: ModelConstraints = {
        start: { successors: { requireConditionWhenParallel: true } },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      expect(errors.length).toBe(2);
      expect(errors[0]).toContain('Action B');
      expect(errors[1]).toContain('Action C');
    });

    it('should group by RESOLVED target across a condition node and a direct edge', () => {
      // NEW (node model): the conditional path n1 -> condNode -> n2 and the
      // direct path n1 -> n2 have DIFFERENT direct successors (condNode vs n2)
      // but the SAME resolved target (n2).  They must be grouped together; the
      // direct (unconditional) path triggers exactly one error.
      const p1 = makeConditionalPath('e1', 'n1', 'cond1', 'n2');
      const nodes = [
        makeNode('n1', 'start', 'Event A'),
        makeNode('n2', 'element', 'Action B'),
        p1.node,
      ];
      const edges = [
        ...p1.edges,
        makeEdge('e2', 'n1', 'n2'),
      ];
      const constraints: ModelConstraints = {
        start: { successors: { requireConditionWhenParallel: true } },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      expect(errors.length).toBe(1);
      expect(errors[0]).toContain('Action B');
    });

    it('should not flag two condition nodes resolving to different targets', () => {
      // NEW (node model): two conditional paths resolving to DIFFERENT targets
      // (n2 and n3).  Each resolved-target group has size 1 — no error.
      const p1 = makeConditionalPath('e1', 'n1', 'cond1', 'n2');
      const p2 = makeConditionalPath('e2', 'n1', 'cond2', 'n3');
      const nodes = [
        makeNode('n1', 'start', 'Event A'),
        makeNode('n2', 'element', 'Action B'),
        makeNode('n3', 'element', 'Action C'),
        p1.node,
        p2.node,
      ];
      const edges = [...p1.edges, ...p2.edges];
      const constraints: ModelConstraints = {
        start: { successors: { requireConditionWhenParallel: true } },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      expect(errors).toEqual([]);
    });

    it('should use target node ID as fallback when target has no label', () => {
      const targetNode: StoreNode = {
        id: 'target-42',
        type: 'element',
        position: { x: 0, y: 0 },
        data: {},
      };
      const nodes = [
        makeNode('n1', 'start', 'Event A'),
        targetNode,
      ];
      const edges = [
        makeEdge('e1', 'n1', 'target-42'),
        makeEdge('e2', 'n1', 'target-42'),
      ];
      const constraints: ModelConstraints = {
        start: { successors: { requireConditionWhenParallel: true } },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      expect(errors.length).toBe(1);
      expect(errors[0]).toContain('target-42');
    });

    it('should coexist with min/max successor constraints', () => {
      const nodes = [
        makeNode('n1', 'start', 'Event A'),
        makeNode('n2', 'element', 'Action B'),
      ];
      const edges = [
        makeEdge('e1', 'n1', 'n2'),
        makeEdge('e2', 'n1', 'n2'),
        makeEdge('e3', 'n1', 'n2'),
      ];
      // max:2 is violated (3 edges), AND parallel condition is violated.
      const constraints: ModelConstraints = {
        start: {
          successors: {
            max: 2,
            requireConditionWhenParallel: true,
          },
        },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      // Should have both a max-exceeded error and a parallel-condition error.
      expect(errors.length).toBe(2);
      expect(errors.some(e => e.includes('at most'))).toBe(true);
      expect(errors.some(e => e.includes('parallel'))).toBe(true);
    });
  });

  // ── Structural invariant: no two adjacent conditions (issue #3589093) ──
  // This invariant is a hard structural rule, NOT an owner-configurable
  // constraint, so it is exposed as the standalone validateNoAdjacentConditions
  // function (called UNCONDITIONALLY from validateBeforeSave).  It is no longer
  // run inside validateModelConstraints — see the explicit test below that
  // confirms validateModelConstraints does NOT report adjacency errors.
  describe('validateNoAdjacentConditions structural invariant', () => {
    it('emits an error for a condition -> condition edge', () => {
      const nodes = [
        makeConditionNode('cond_a', 'First Check'),
        makeConditionNode('cond_b', 'Second Check'),
      ];
      const edges = [makeEdge('e1', 'cond_a', 'cond_b')];
      const errors = validateNoAdjacentConditions(nodes, edges);
      expect(errors.length).toBe(1);
      expect(errors[0]).toContain('Two conditions cannot be directly connected');
      expect(errors[0]).toContain('First Check');
      expect(errors[0]).toContain('Second Check');
    });

    it('emits NO error when a gateway separates two conditions (condition -> gateway -> condition)', () => {
      const nodes = [
        makeConditionNode('cond_a', 'First Check'),
        makeNode('gw', 'gateway', 'Gateway'),
        makeConditionNode('cond_b', 'Second Check'),
      ];
      const edges = [
        makeEdge('e1', 'cond_a', 'gw'),
        makeEdge('e2', 'gw', 'cond_b'),
      ];
      const errors = validateNoAdjacentConditions(nodes, edges);
      expect(errors).toEqual([]);
    });

    it('recognizes conditions flagged only by __isConditionNode (no type) on both ends', () => {
      const nodes: StoreNode[] = [
        { id: 'a', position: { x: 0, y: 0 }, data: { label: 'A', __isConditionNode: true } },
        { id: 'b', position: { x: 0, y: 0 }, data: { label: 'B', __isConditionNode: true } },
      ];
      const edges = [makeEdge('e1', 'a', 'b')];
      const errors = validateNoAdjacentConditions(nodes, edges);
      expect(errors.length).toBe(1);
      expect(errors[0]).toContain('Two conditions cannot be directly connected');
    });

    it('dedupes duplicate edges between the same condition pair to a single error', () => {
      const nodes = [
        makeConditionNode('cond_a', 'A'),
        makeConditionNode('cond_b', 'B'),
      ];
      const edges = [
        makeEdge('e1', 'cond_a', 'cond_b'),
        makeEdge('e2', 'cond_a', 'cond_b'),
      ];
      const errors = validateNoAdjacentConditions(nodes, edges);
      expect(errors.length).toBe(1);
    });

    it('does not flag condition -> non-condition or non-condition -> condition edges', () => {
      const nodes = [
        makeConditionNode('cond_a', 'A'),
        makeNode('act', 'element', 'Action'),
      ];
      const edges = [
        makeEdge('e1', 'cond_a', 'act'),
        makeEdge('e2', 'act', 'cond_a'),
      ];
      const errors = validateNoAdjacentConditions(nodes, edges);
      expect(errors).toEqual([]);
    });

    it('validateModelConstraints does NOT report adjacency errors (moved to validateNoAdjacentConditions)', () => {
      // Regression guard for fix C3: the adjacency check must not run inside
      // validateModelConstraints, otherwise validateBeforeSave would report it
      // twice when constraints are present.
      const nodes = [
        makeConditionNode('cond_a', 'First Check'),
        makeConditionNode('cond_b', 'Second Check'),
      ];
      const edges = [makeEdge('e1', 'cond_a', 'cond_b')];
      const errors = validateModelConstraints(nodes, edges, {});
      expect(errors).toEqual([]);
    });
  });

  // ── 1-outbound structural invariant (issue #3589093, Task 1) ─────────────
  // A condition node may fan-in (multiple inbound edges) but have AT MOST ONE
  // outbound edge.  validateConditionOutdegree flags any condition node with
  // more than one outgoing edge; it is run UNCONDITIONALLY from
  // validateBeforeSave (a hard invariant, not an owner-configurable rule).
  describe('validateConditionOutdegree structural invariant', () => {
    it('returns no errors when there are no condition nodes', () => {
      const nodes = [makeNode('a', 'element'), makeNode('b', 'element')];
      const edges = [makeEdge('e1', 'a', 'b')];
      expect(validateConditionOutdegree(nodes, edges)).toEqual([]);
    });

    it('allows a condition node with one outbound edge (and many inbound)', () => {
      const nodes = [
        makeNode('a', 'element'),
        makeNode('b', 'element'),
        makeConditionNode('cond', 'Reused'),
        makeNode('z', 'element'),
      ];
      const edges = [
        makeEdge('in1', 'a', 'cond'),   // fan-in 1
        makeEdge('in2', 'b', 'cond'),   // fan-in 2 — allowed
        makeEdge('out', 'cond', 'z'),   // single outbound — allowed
      ];
      expect(validateConditionOutdegree(nodes, edges)).toEqual([]);
    });

    it('flags a condition node with more than one outbound edge', () => {
      const nodes = [
        makeNode('a', 'element'),
        makeConditionNode('cond', 'Bad'),
        makeNode('y', 'element'),
        makeNode('z', 'element'),
      ];
      const edges = [
        makeEdge('in', 'a', 'cond'),
        makeEdge('out1', 'cond', 'y'),
        makeEdge('out2', 'cond', 'z'),
      ];
      const errors = validateConditionOutdegree(nodes, edges);
      expect(errors).toHaveLength(1);
      expect(errors[0]).toContain('only one outgoing connection');
      expect(errors[0]).toContain('Bad');
      expect(errors[0]).toContain('2');
    });

    it('reports one error per offending condition node', () => {
      const nodes = [
        makeConditionNode('c1', 'C1'),
        makeConditionNode('c2', 'C2'),
        makeNode('x', 'element'),
        makeNode('y', 'element'),
        makeNode('z', 'element'),
      ];
      const edges = [
        makeEdge('c1o1', 'c1', 'x'),
        makeEdge('c1o2', 'c1', 'y'),
        makeEdge('c2o1', 'c2', 'y'),
        makeEdge('c2o2', 'c2', 'z'),
      ];
      const errors = validateConditionOutdegree(nodes, edges);
      expect(errors).toHaveLength(2);
    });
  });
});
