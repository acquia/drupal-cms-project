/**
 * Tests for useReplayStepFilter hook
 */

import { renderHook } from '@testing-library/react';
import { useReplayStepFilter } from '../useReplayStepFilter';
import { ReplayStep } from '../../utils/replayStepUtils';
import type { StoreNode as Node, StoreEdge as Edge } from '../../types/settings';

describe('useReplayStepFilter', () => {
  // Helper to create mock nodes
  const createNode = (id: string, type: string = 'element', nodeType?: string): Node => ({
    id,
    type,
    position: { x: 0, y: 0 },
    data: { 
      label: `Node ${id}`,
      nodeType: nodeType || type,
    },
  });

  // Helper to create mock edges
  const createEdge = (id: string, source: string, target: string, condition?: string): Edge => ({
    id,
    source,
    target,
    type: 'default',
    data: condition ? { condition, conditionLabel: `Condition: ${condition}` } : {},
  });

  // Helper to create replay steps
  const createStep = (type: string, props: Partial<ReplayStep> = {}): ReplayStep => ({
    type,
    ...props,
  });

  describe('filteredReplayData', () => {
    it('should return empty array when replayData is null', () => {
      const { result } = renderHook(() =>
        useReplayStepFilter({
          replayData: null,
          nodes: [],
          edges: [],
        })
      );

      expect(result.current.filteredReplayData).toEqual([]);
    });

    it('should return empty array when replayData is undefined', () => {
      const { result } = renderHook(() =>
        useReplayStepFilter({
          replayData: undefined,
          nodes: [],
          edges: [],
        })
      );

      expect(result.current.filteredReplayData).toEqual([]);
    });

    it('should include all basic step types', () => {
      const replayData: ReplayStep[] = [
        createStep('started', { id: 'node1' }),
        createStep('execute', { id: 'node2' }),
        createStep('access denied', { id: 'node3' }),
      ];

      const { result } = renderHook(() =>
        useReplayStepFilter({
          replayData,
          nodes: [],
          edges: [],
        })
      );

      expect(result.current.filteredReplayData).toHaveLength(3);
      expect(result.current.filteredReplayData).toEqual(replayData);
    });

    it('should filter out "add successor" steps without conditionId', () => {
      const replayData: ReplayStep[] = [
        createStep('started', { id: 'node1' }),
        createStep('add successor', { id: 'node1', successorId: 'node2' }), // No conditionId - filtered
        createStep('execute', { id: 'node2' }),
      ];

      const { result } = renderHook(() =>
        useReplayStepFilter({
          replayData,
          nodes: [],
          edges: [],
        })
      );

      expect(result.current.filteredReplayData).toHaveLength(2);
      expect(result.current.filteredReplayData[0].type).toBe('started');
      expect(result.current.filteredReplayData[1].type).toBe('execute');
    });

    it('should include "add successor" steps with conditionId', () => {
      const replayData: ReplayStep[] = [
        createStep('started', { id: 'node1' }),
        createStep('add successor', { id: 'node1', successorId: 'node2', conditionId: 'edge1' }),
        createStep('execute', { id: 'node2' }),
      ];

      const { result } = renderHook(() =>
        useReplayStepFilter({
          replayData,
          nodes: [],
          edges: [],
        })
      );

      expect(result.current.filteredReplayData).toHaveLength(3);
      expect(result.current.filteredReplayData[1].type).toBe('add successor');
    });

    it('should include "ignore successor" steps for gateway nodes', () => {
      const nodes = [
        createNode('node1', 'element'),
        createNode('gateway1', 'gateway'),
      ];

      const replayData: ReplayStep[] = [
        createStep('started', { id: 'node1' }),
        createStep('ignore successor', { id: 'node1', successorId: 'gateway1' }),
      ];

      const { result } = renderHook(() =>
        useReplayStepFilter({
          replayData,
          nodes,
          edges: [],
        })
      );

      expect(result.current.filteredReplayData).toHaveLength(2);
      expect(result.current.filteredReplayData[1].type).toBe('ignore successor');
    });

    it('should include "ignore successor" steps when edge has condition', () => {
      const nodes = [
        createNode('node1', 'element'),
        createNode('node2', 'element'),
      ];
      const edges = [
        createEdge('edge1', 'node1', 'node2', 'some_condition'),
      ];

      const replayData: ReplayStep[] = [
        createStep('started', { id: 'node1' }),
        createStep('ignore successor', { id: 'node1', successorId: 'node2' }),
      ];

      const { result } = renderHook(() =>
        useReplayStepFilter({
          replayData,
          nodes,
          edges,
        })
      );

      expect(result.current.filteredReplayData).toHaveLength(2);
    });

    it('should filter out "ignore successor" steps when no condition edge exists', () => {
      const nodes = [
        createNode('node1', 'element'),
        createNode('node2', 'element'),
      ];
      const edges = [
        createEdge('edge1', 'node1', 'node2'), // No condition
      ];

      const replayData: ReplayStep[] = [
        createStep('started', { id: 'node1' }),
        createStep('ignore successor', { id: 'node1', successorId: 'node2' }),
        createStep('execute', { id: 'node2' }),
      ];

      const { result } = renderHook(() =>
        useReplayStepFilter({
          replayData,
          nodes,
          edges,
        })
      );

      expect(result.current.filteredReplayData).toHaveLength(2);
      expect(result.current.filteredReplayData[0].type).toBe('started');
      expect(result.current.filteredReplayData[1].type).toBe('execute');
    });

    it('should handle nodes with nodeType in data property', () => {
      const nodes = [
        createNode('node1', 'element'),
        { 
          id: 'gateway1', 
          type: 'custom', 
          position: { x: 0, y: 0 },
          data: { label: 'Gateway', nodeType: 'gateway' }
        } as Node,
      ];

      const replayData: ReplayStep[] = [
        createStep('ignore successor', { id: 'node1', successorId: 'gateway1' }),
      ];

      const { result } = renderHook(() =>
        useReplayStepFilter({
          replayData,
          nodes,
          edges: [],
        })
      );

      expect(result.current.filteredReplayData).toHaveLength(1);
    });

    // ---- Promoted condition NODES (issues #3589093 / #3589108) ------------
    // After promoteConditionEdges() the direct source -> target edge is gone
    // (replaced by source -> condition -> target) and no edge carries
    // `data.condition`. FALSE conditions were therefore filtered out of the
    // step list entirely, which also suppressed the red replay indicator.

    const createConditionNode = (id: string, conditionId: string, plugin: string): Node => ({
      id,
      type: 'condition',
      position: { x: 0, y: 0 },
      data: { label: `Condition ${id}`, conditionId, plugin, __isConditionNode: true },
    });

    it('should include "ignore successor" steps that carry a conditionId (promoted condition node)', () => {
      const nodes = [
        createNode('node1', 'element'),
        createConditionNode('cond1', 'cfg-1', 'eca_scalar'),
        createNode('node2', 'element'),
      ];
      // Post-promotion edges: no direct node1 -> node2 edge, no data.condition.
      const edges = [
        createEdge('e-in', 'node1', 'cond1'),
        createEdge('e-out', 'cond1', 'node2'),
      ];

      const replayData: ReplayStep[] = [
        createStep('started', { id: 'node1' }),
        createStep('ignore successor', { id: 'node1', successorId: 'node2', conditionId: 'cfg-1' }),
      ];

      const { result } = renderHook(() =>
        useReplayStepFilter({ replayData, nodes, edges })
      );

      expect(result.current.filteredReplayData).toHaveLength(2);
      expect(result.current.filteredReplayData[1].type).toBe('ignore successor');
      expect(result.current.filteredReplayData[1].conditionId).toBe('cfg-1');
    });

    it('should include "ignore successor" steps with a conditionId even without any edges', () => {
      const nodes = [createNode('node1', 'element'), createNode('node2', 'element')];

      const replayData: ReplayStep[] = [
        createStep('ignore successor', { id: 'node1', successorId: 'node2', conditionId: 'cfg-1' }),
      ];

      const { result } = renderHook(() =>
        useReplayStepFilter({ replayData, nodes, edges: [] })
      );

      expect(result.current.filteredReplayData).toHaveLength(1);
    });

    it('should still exclude "ignore successor" steps with no conditionId and a non-gateway successor', () => {
      const nodes = [createNode('node1', 'element'), createNode('node2', 'element')];
      const edges = [createEdge('e1', 'node1', 'node2')]; // plain edge, no condition

      const replayData: ReplayStep[] = [
        createStep('ignore successor', { id: 'node1', successorId: 'node2' }),
      ];

      const { result } = renderHook(() =>
        useReplayStepFilter({ replayData, nodes, edges })
      );

      expect(result.current.filteredReplayData).toHaveLength(0);
    });

    it('should still include gateway "ignore successor" steps with no conditionId', () => {
      const nodes = [createNode('node1', 'element'), createNode('gateway1', 'gateway')];

      const replayData: ReplayStep[] = [
        createStep('ignore successor', { id: 'node1', successorId: 'gateway1' }),
      ];

      const { result } = renderHook(() =>
        useReplayStepFilter({ replayData, nodes, edges: [] })
      );

      expect(result.current.filteredReplayData).toHaveLength(1);
    });

    it('should keep both TRUE and FALSE condition steps in the same replay', () => {
      const nodes = [
        createNode('node1', 'element'),
        createConditionNode('condTrue', 'cfg-true', 'eca_scalar'),
        createConditionNode('condFalse', 'cfg-false', 'eca_scalar'),
        createNode('node2', 'element'),
        createNode('node3', 'element'),
      ];

      const replayData: ReplayStep[] = [
        createStep('started', { id: 'node1' }),
        createStep('add successor', { id: 'node1', successorId: 'node2', conditionId: 'cfg-true' }),
        createStep('ignore successor', { id: 'node1', successorId: 'node3', conditionId: 'cfg-false' }),
      ];

      const { result } = renderHook(() =>
        useReplayStepFilter({ replayData, nodes, edges: [] })
      );

      expect(result.current.filteredReplayData).toHaveLength(3);
      expect(result.current.filteredReplayData.map(s => s.type)).toEqual([
        'started',
        'add successor',
        'ignore successor',
      ]);
    });

    it('should handle edge direction from target to source', () => {
      const nodes = [
        createNode('node1', 'element'),
        createNode('node2', 'element'),
      ];
      const edges = [
        createEdge('edge1', 'node2', 'node1', 'reverse_condition'), // Reversed direction
      ];

      const replayData: ReplayStep[] = [
        createStep('ignore successor', { id: 'node1', successorId: 'node2' }),
      ];

      const { result } = renderHook(() =>
        useReplayStepFilter({
          replayData,
          nodes,
          edges,
        })
      );

      expect(result.current.filteredReplayData).toHaveLength(1);
    });
  });

  describe('getFilteredIndex', () => {
    it('should return -1 for negative index', () => {
      const { result } = renderHook(() =>
        useReplayStepFilter({
          replayData: [createStep('started')],
          nodes: [],
          edges: [],
        })
      );

      expect(result.current.getFilteredIndex(-1)).toBe(-1);
    });

    it('should return -1 when replayData is null', () => {
      const { result } = renderHook(() =>
        useReplayStepFilter({
          replayData: null,
          nodes: [],
          edges: [],
        })
      );

      expect(result.current.getFilteredIndex(0)).toBe(-1);
    });

    it('should return correct filtered index for included steps', () => {
      const replayData: ReplayStep[] = [
        createStep('started', { id: 'node1' }),
        createStep('execute', { id: 'node2' }),
        createStep('execute', { id: 'node3' }),
      ];

      const { result } = renderHook(() =>
        useReplayStepFilter({
          replayData,
          nodes: [],
          edges: [],
        })
      );

      expect(result.current.getFilteredIndex(0)).toBe(0);
      expect(result.current.getFilteredIndex(1)).toBe(1);
      expect(result.current.getFilteredIndex(2)).toBe(2);
    });

    it('should return -1 for filtered out steps', () => {
      const replayData: ReplayStep[] = [
        createStep('started', { id: 'node1' }),
        createStep('add successor', { id: 'node1', successorId: 'node2' }), // Filtered out
        createStep('execute', { id: 'node2' }),
      ];

      const { result } = renderHook(() =>
        useReplayStepFilter({
          replayData,
          nodes: [],
          edges: [],
        })
      );

      expect(result.current.getFilteredIndex(0)).toBe(0);
      expect(result.current.getFilteredIndex(1)).toBe(-1); // Filtered step
      expect(result.current.getFilteredIndex(2)).toBe(1);
    });

    it('should handle multiple filtered steps correctly', () => {
      const replayData: ReplayStep[] = [
        createStep('started', { id: 'node1' }),
        createStep('add successor', { id: 'node1', successorId: 'node2' }), // Filtered
        createStep('add successor', { id: 'node2', successorId: 'node3' }), // Filtered
        createStep('execute', { id: 'node2' }),
        createStep('add successor', { id: 'node2', successorId: 'node4' }), // Filtered
        createStep('execute', { id: 'node3' }),
      ];

      const { result } = renderHook(() =>
        useReplayStepFilter({
          replayData,
          nodes: [],
          edges: [],
        })
      );

      expect(result.current.getFilteredIndex(0)).toBe(0); // started
      expect(result.current.getFilteredIndex(1)).toBe(-1); // filtered
      expect(result.current.getFilteredIndex(2)).toBe(-1); // filtered
      expect(result.current.getFilteredIndex(3)).toBe(1); // execute
      expect(result.current.getFilteredIndex(4)).toBe(-1); // filtered
      expect(result.current.getFilteredIndex(5)).toBe(2); // execute
    });
  });

  describe('getOriginalIndex', () => {
    it('should return -1 for negative index', () => {
      const { result } = renderHook(() =>
        useReplayStepFilter({
          replayData: [createStep('started')],
          nodes: [],
          edges: [],
        })
      );

      expect(result.current.getOriginalIndex(-1)).toBe(-1);
    });

    it('should return -1 when replayData is null', () => {
      const { result } = renderHook(() =>
        useReplayStepFilter({
          replayData: null,
          nodes: [],
          edges: [],
        })
      );

      expect(result.current.getOriginalIndex(0)).toBe(-1);
    });

    it('should return correct original index when no steps are filtered', () => {
      const replayData: ReplayStep[] = [
        createStep('started', { id: 'node1' }),
        createStep('execute', { id: 'node2' }),
        createStep('execute', { id: 'node3' }),
      ];

      const { result } = renderHook(() =>
        useReplayStepFilter({
          replayData,
          nodes: [],
          edges: [],
        })
      );

      expect(result.current.getOriginalIndex(0)).toBe(0);
      expect(result.current.getOriginalIndex(1)).toBe(1);
      expect(result.current.getOriginalIndex(2)).toBe(2);
    });

    it('should skip filtered steps when mapping back', () => {
      const replayData: ReplayStep[] = [
        createStep('started', { id: 'node1' }),           // Original: 0, Filtered: 0
        createStep('add successor', { id: 'node1', successorId: 'node2' }), // Filtered out
        createStep('execute', { id: 'node2' }),           // Original: 2, Filtered: 1
      ];

      const { result } = renderHook(() =>
        useReplayStepFilter({
          replayData,
          nodes: [],
          edges: [],
        })
      );

      expect(result.current.getOriginalIndex(0)).toBe(0);
      expect(result.current.getOriginalIndex(1)).toBe(2);
    });

    it('should handle complex filtering scenarios', () => {
      const replayData: ReplayStep[] = [
        createStep('started', { id: 'node1' }),           // O:0, F:0
        createStep('add successor', { id: 'n', successorId: 'n2' }), // Filtered
        createStep('execute', { id: 'node2' }),           // O:2, F:1
        createStep('add successor', { id: 'n', successorId: 'n3' }), // Filtered
        createStep('add successor', { id: 'n', successorId: 'n4', conditionId: 'e1' }), // O:4, F:2 (has conditionId)
        createStep('execute', { id: 'node3' }),           // O:5, F:3
      ];

      const { result } = renderHook(() =>
        useReplayStepFilter({
          replayData,
          nodes: [],
          edges: [],
        })
      );

      expect(result.current.getOriginalIndex(0)).toBe(0);
      expect(result.current.getOriginalIndex(1)).toBe(2);
      expect(result.current.getOriginalIndex(2)).toBe(4);
      expect(result.current.getOriginalIndex(3)).toBe(5);
    });

    it('should return last index for out-of-bounds filtered index', () => {
      const replayData: ReplayStep[] = [
        createStep('started', { id: 'node1' }),
        createStep('execute', { id: 'node2' }),
      ];

      const { result } = renderHook(() =>
        useReplayStepFilter({
          replayData,
          nodes: [],
          edges: [],
        })
      );

      expect(result.current.getOriginalIndex(10)).toBe(1); // Returns last index
    });
  });

  describe('bidirectional mapping consistency', () => {
    it('should maintain consistency between getFilteredIndex and getOriginalIndex', () => {
      const replayData: ReplayStep[] = [
        createStep('started', { id: 'node1' }),
        createStep('add successor', { id: 'n1', successorId: 'n2' }), // Filtered
        createStep('execute', { id: 'node2' }),
        createStep('add successor', { id: 'n2', successorId: 'n3', conditionId: 'e1' }), // Included
        createStep('execute', { id: 'node3' }),
      ];

      const { result } = renderHook(() =>
        useReplayStepFilter({
          replayData,
          nodes: [],
          edges: [],
        })
      );

      // For each original index that maps to a valid filtered index,
      // mapping back should return the same original index
      for (let i = 0; i < replayData.length; i++) {
        const filteredIndex = result.current.getFilteredIndex(i);
        if (filteredIndex >= 0) {
          expect(result.current.getOriginalIndex(filteredIndex)).toBe(i);
        }
      }
    });
  });

  describe('memoization', () => {
    it('should return same filtered array reference when inputs do not change', () => {
      const replayData: ReplayStep[] = [
        createStep('started', { id: 'node1' }),
        createStep('execute', { id: 'node2' }),
      ];
      const nodes: Node[] = [];
      const edges: Edge[] = [];

      const { result, rerender } = renderHook(
        ({ replayData, nodes, edges }) =>
          useReplayStepFilter({ replayData, nodes, edges }),
        { initialProps: { replayData, nodes, edges } }
      );

      const firstResult = result.current.filteredReplayData;

      // Rerender with same props
      rerender({ replayData, nodes, edges });

      expect(result.current.filteredReplayData).toBe(firstResult);
    });

    it('should return new filtered array when replayData changes', () => {
      const replayData1: ReplayStep[] = [
        createStep('started', { id: 'node1' }),
      ];
      const replayData2: ReplayStep[] = [
        createStep('started', { id: 'node1' }),
        createStep('execute', { id: 'node2' }),
      ];
      const nodes: Node[] = [];
      const edges: Edge[] = [];

      const { result, rerender } = renderHook(
        ({ replayData, nodes, edges }) =>
          useReplayStepFilter({ replayData, nodes, edges }),
        { initialProps: { replayData: replayData1, nodes, edges } }
      );

      const firstResult = result.current.filteredReplayData;

      // Rerender with different replayData
      rerender({ replayData: replayData2, nodes, edges });

      expect(result.current.filteredReplayData).not.toBe(firstResult);
      expect(result.current.filteredReplayData).toHaveLength(2);
    });
  });
});
