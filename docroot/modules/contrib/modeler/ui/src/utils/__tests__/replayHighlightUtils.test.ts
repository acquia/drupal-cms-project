/**
 * Tests for replayHighlightUtils
 */

import { Node, Edge } from 'reactflow';
import {
  clearNodeHighlights,
  clearEdgeHighlights,
  applyNodeHighlight,
  applyEdgeHighlight,
  highlightNodesForStep,
  highlightEdgesForStep,
} from '../replayHighlightUtils';
import { ReplayStep } from '../replayStepUtils';

// ============ Test Fixtures ============

const createNode = (id: string, overrides: Partial<Node> = {}): Node => ({
  id,
  position: { x: 0, y: 0 },
  data: { label: id, highlighted: false },
  selected: false,
  ...overrides,
});

const createEdge = (id: string, source: string, target: string, overrides: Partial<Edge> = {}): Edge => ({
  id,
  source,
  target,
  data: {},
  selected: false,
  animated: false,
  ...overrides,
});

// ============ Tests ============

describe('replayHighlightUtils', () => {
  describe('clearNodeHighlights', () => {
    it('should deselect all nodes and clear highlighted flag', () => {
      const nodes = [
        createNode('n1', { selected: true, data: { label: 'N1', highlighted: true } }),
        createNode('n2', { selected: false, data: { label: 'N2', highlighted: false } }),
      ];
      const result = clearNodeHighlights(nodes);
      
      expect(result[0].selected).toBe(false);
      expect(result[0].data.highlighted).toBe(false);
      expect(result[1].selected).toBe(false);
      expect(result[1].data.highlighted).toBe(false);
    });

    it('should return a new array (immutable)', () => {
      const nodes = [createNode('n1')];
      const result = clearNodeHighlights(nodes);
      expect(result).not.toBe(nodes);
      expect(result[0]).not.toBe(nodes[0]);
    });

    it('should handle empty array', () => {
      expect(clearNodeHighlights([])).toEqual([]);
    });
  });

  describe('clearEdgeHighlights', () => {
    it('should clear all replay-related properties from edges', () => {
      const edges = [
        createEdge('e1', 'n1', 'n2', {
          selected: true,
          animated: true,
          className: 'replay-highlighted replay-add some-class',
          data: { highlighted: true, replayHighlight: 'green', fromReplay: true },
          style: { stroke: 'green', strokeWidth: 3 },
        }),
      ];
      const result = clearEdgeHighlights(edges);
      
      expect(result[0].selected).toBe(false);
      expect(result[0].animated).toBe(false);
      expect(result[0].data.highlighted).toBe(false);
      expect(result[0].data.replayHighlight).toBeUndefined();
      expect(result[0].data.fromReplay).toBe(false);
      expect(result[0].className).toBe('some-class');
      expect(result[0].style?.stroke).toBeUndefined();
      expect(result[0].style?.strokeWidth).toBeUndefined();
    });

    it('should handle edges with no style', () => {
      const edges = [createEdge('e1', 'n1', 'n2')];
      const result = clearEdgeHighlights(edges);
      expect(result[0].style).toBeUndefined();
    });

    it('should strip replay class names completely when no others remain', () => {
      const edges = [
        createEdge('e1', 'n1', 'n2', {
          className: 'replay-highlighted replay-add',
        }),
      ];
      const result = clearEdgeHighlights(edges);
      expect(result[0].className).toBeUndefined();
    });

    it('should handle empty array', () => {
      expect(clearEdgeHighlights([])).toEqual([]);
    });
  });

  describe('applyNodeHighlight', () => {
    it('should select and highlight the target node', () => {
      const nodes = [createNode('n1'), createNode('n2')];
      const result = applyNodeHighlight(nodes, 'n1', false);
      
      expect(result[0].selected).toBe(true);
      expect(result[0].data.highlighted).toBe(true);
      expect(result[0].data.fromReplay).toBe(true);
      expect(result[1].selected).toBe(false);
    });

    it('should apply access denied styling when isAccessDenied is true', () => {
      const nodes = [createNode('n1')];
      const result = applyNodeHighlight(nodes, 'n1', true);
      
      expect(result[0].style?.border).toBe('2px solid var(--modeler-color-danger-soft)');
      expect(result[0].style?.boxShadow).toBe('var(--modeler-shadow-danger-glow)');
    });

    it('should not add access denied styling when isAccessDenied is false', () => {
      const nodes = [createNode('n1')];
      const result = applyNodeHighlight(nodes, 'n1', false);
      
      // Style should remain as original (undefined in this case)
      expect(result[0].style).toBeUndefined();
    });

    it('should not modify other nodes', () => {
      const nodes = [createNode('n1'), createNode('n2')];
      const result = applyNodeHighlight(nodes, 'n1', false);
      
      expect(result[1].data.highlighted).toBeFalsy();
      expect(result[1].data.fromReplay).toBeUndefined();
    });
  });

  describe('applyEdgeHighlight', () => {
    it('should highlight edge as add successor (green)', () => {
      const edges = [createEdge('e1', 'n1', 'n2')];
      const result = applyEdgeHighlight(edges, 'e1', true);
      
      expect(result[0].data.highlighted).toBe(true);
      expect(result[0].data.fromReplay).toBe(true);
      expect(result[0].data.replayType).toBe('add');
      expect(result[0].style?.stroke).toBe('var(--modeler-color-success)');
      expect(result[0].animated).toBe(true);
      expect(result[0].className).toBe('replay-highlighted replay-add');
    });

    it('should preserve the incoming selected state from the caller', () => {
      const edges = [{ ...createEdge('e1', 'n1', 'n2'), selected: true }];
      const result = applyEdgeHighlight(edges, 'e1', true);
      expect(result[0].selected).toBe(true);
    });

    it('should highlight edge as ignore successor (red)', () => {
      const edges = [createEdge('e1', 'n1', 'n2')];
      const result = applyEdgeHighlight(edges, 'e1', false);
      
      expect(result[0].data.replayType).toBe('ignore');
      expect(result[0].style?.stroke).toBe('var(--modeler-color-danger-soft)');
      expect(result[0].className).toBe('replay-highlighted replay-ignore');
    });

    it('should not modify other edges', () => {
      const edges = [createEdge('e1', 'n1', 'n2'), createEdge('e2', 'n2', 'n3')];
      const result = applyEdgeHighlight(edges, 'e1', true);
      
      expect(result[1].data.highlighted).toBeFalsy();
    });
  });

  describe('highlightNodesForStep', () => {
    const nodes = [createNode('n1'), createNode('n2')];

    it('should highlight node for "started" step', () => {
      const step: ReplayStep = { type: 'started', id: 'n1' };
      const result = highlightNodesForStep(nodes, step);
      
      expect(result[0].selected).toBe(true);
      expect(result[0].data.highlighted).toBe(true);
      expect(result[1].selected).toBe(false);
    });

    it('should highlight node for "execute" step', () => {
      const step: ReplayStep = { type: 'execute', id: 'n1' };
      const result = highlightNodesForStep(nodes, step);
      
      expect(result[0].data.highlighted).toBe(true);
    });

    it('should apply access denied styling for "access denied" step', () => {
      const step: ReplayStep = { type: 'access denied', id: 'n1' };
      const result = highlightNodesForStep(nodes, step);
      
      expect(result[0].data.highlighted).toBe(true);
      expect(result[0].style?.border).toContain('danger');
    });

    it('should highlight node for successor step without conditionId', () => {
      const step: ReplayStep = { type: 'add successor', id: 'n1', successorId: 'n2' };
      const result = highlightNodesForStep(nodes, step);
      
      expect(result[0].data.highlighted).toBe(true);
    });

    // CHANGED (node model, issue #3589093): conditions are NODES now, so a
    // condition step highlights the matched condition NODE rather than an edge.
    it('should highlight the condition node for a condition step (match by plugin)', () => {
      // The step's conditionId historically matched edge.data.condition, which
      // carried the condition *plugin* id — so it matches the node's data.plugin.
      const conditionNodes: Node[] = [
        createNode('n1'),
        createNode('cond-node', {
          type: 'condition',
          data: { label: 'Check', plugin: 'cond1', conditionId: 'rt-1', __isConditionNode: true },
        }),
        createNode('n2'),
      ];
      const step: ReplayStep = { type: 'add successor', id: 'n1', conditionId: 'cond1' };
      const result = highlightNodesForStep(conditionNodes, step);

      const condResult = result.find(n => n.id === 'cond-node')!;
      expect(condResult.data.highlighted).toBe(true);
      expect(condResult.selected).toBe(true);
      expect(result.find(n => n.id === 'n1')!.data.highlighted).toBe(false);
      expect(result.find(n => n.id === 'n2')!.data.highlighted).toBe(false);
    });

    it('should fall back to data.conditionId when plugin does not match', () => {
      const conditionNodes: Node[] = [
        createNode('cond-node', {
          type: 'condition',
          data: { label: 'Check', plugin: 'other_plugin', conditionId: 'rt-1', __isConditionNode: true },
        }),
      ];
      const step: ReplayStep = { type: 'add successor', id: 'n1', conditionId: 'rt-1' };
      const result = highlightNodesForStep(conditionNodes, step);

      expect(result.find(n => n.id === 'cond-node')!.data.highlighted).toBe(true);
    });

    it('should prefer data.conditionId over data.plugin when both match different nodes', () => {
      // Issue #3589108: step.conditionId is ECA's condition CONFIG id, so the
      // node whose data.conditionId matches must win over a plugin-id collision.
      const conditionNodes: Node[] = [
        createNode('cond-by-plugin', {
          type: 'condition',
          data: { label: 'Wrong', plugin: 'shared', conditionId: 'other', __isConditionNode: true },
        }),
        createNode('cond-by-config-id', {
          type: 'condition',
          data: { label: 'Right', plugin: 'eca_scalar', conditionId: 'shared', __isConditionNode: true },
        }),
      ];
      const step: ReplayStep = { type: 'ignore successor', id: 'n1', conditionId: 'shared' };
      const result = highlightNodesForStep(conditionNodes, step);

      expect(result.find(n => n.id === 'cond-by-config-id')!.data.highlighted).toBe(true);
      expect(result.find(n => n.id === 'cond-by-plugin')!.data.highlighted).toBe(false);
    });

    it('should clear highlights for a condition step with no matching condition node', () => {
      const step: ReplayStep = { type: 'add successor', id: 'n1', conditionId: 'cond1' };
      const result = highlightNodesForStep(nodes, step);

      // No condition node present — nothing highlighted.
      expect(result[0].data.highlighted).toBe(false);
      expect(result[1].data.highlighted).toBe(false);
    });

    it('should only clear when step has no id', () => {
      const step: ReplayStep = { type: 'execute' };
      const result = highlightNodesForStep(nodes, step);
      
      expect(result.every(n => !n.data.highlighted)).toBe(true);
    });
  });

  // CHANGED (node model, issue #3589093): conditions are NODES now, so
  // highlightEdgesForStep no longer highlights a condition edge — condition
  // highlighting moved to highlightNodesForStep.  This function now only
  // clears edge highlights for every step type (no edge ever carries a
  // condition at runtime).
  describe('highlightEdgesForStep', () => {
    const edges = [
      createEdge('e1', 'n1', 'n2', { data: { highlighted: true, replayHighlight: 'green', fromReplay: true } }),
      createEdge('e2', 'n2', 'n3', { data: {} }),
    ];

    it('should clear edge highlights for a condition step (add successor)', () => {
      const step: ReplayStep = { type: 'add successor', id: 'n1', conditionId: 'cond1' };
      const result = highlightEdgesForStep(edges, step);

      expect(result.every(e => !e.data.highlighted)).toBe(true);
    });

    it('should clear edge highlights for a condition step (ignore successor)', () => {
      const step: ReplayStep = { type: 'ignore successor', id: 'n1', conditionId: 'cond1' };
      const result = highlightEdgesForStep(edges, step);

      expect(result.every(e => !e.data.highlighted)).toBe(true);
    });

    it('should clear for non-condition steps', () => {
      const step: ReplayStep = { type: 'execute', id: 'n1' };
      const result = highlightEdgesForStep(edges, step);
      
      expect(result.every(e => !e.data.highlighted)).toBe(true);
    });

    it('should clear for successor steps without conditionId', () => {
      const step: ReplayStep = { type: 'add successor', id: 'n1', successorId: 'n2' };
      const result = highlightEdgesForStep(edges, step);
      
      expect(result.every(e => !e.data.highlighted)).toBe(true);
    });
  });

});
