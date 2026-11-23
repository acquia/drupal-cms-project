import { renderHook, act } from '@testing-library/react';
import { useSimpleReplaySync, ReplayStep } from '../useSimpleReplaySync';
import { Node, Edge } from 'reactflow';

// Mock requestAnimationFrame
const mockRAF = jest.fn((callback: FrameRequestCallback) => {
  callback(0);
  return 0;
});
global.requestAnimationFrame = mockRAF;

describe('useSimpleReplaySync', () => {
  let mockSetNodes: jest.Mock;
  let mockSetEdges: jest.Mock;
  let mockSetSelectedNode: jest.Mock;
  let mockSetSelectedEdge: jest.Mock;
  let mockSetCurrentStep: jest.Mock;

  const mockNodes: Node[] = [
    { id: 'node-1', position: { x: 0, y: 0 }, data: { label: 'Node 1' } },
    { id: 'node-2', position: { x: 100, y: 0 }, data: { label: 'Node 2' } },
    { id: 'node-3', position: { x: 200, y: 0 }, data: { label: 'Node 3' } },
  ];

  const mockEdges: Edge[] = [
    { id: 'edge-1', source: 'node-1', target: 'node-2', data: { condition: 'condition-1' } },
    { id: 'edge-2', source: 'node-2', target: 'node-3', data: {} },
  ];

  const mockReplayData: ReplayStep[] = [
    { id: 'node-1', type: 'started' },
    { id: 'node-1', type: 'execute' },
    { id: 'node-1', type: 'add successor', successorId: 'node-2', conditionId: 'condition-1' },
    { id: 'node-2', type: 'execute' },
    { id: 'node-2', type: 'add successor', successorId: 'node-3' },
    { id: 'node-3', type: 'access denied' },
  ];

  beforeEach(() => {
    jest.useFakeTimers();
    mockSetNodes = jest.fn();
    mockSetEdges = jest.fn();
    mockSetSelectedNode = jest.fn();
    mockSetSelectedEdge = jest.fn();
    mockSetCurrentStep = jest.fn();
    mockRAF.mockClear();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  const renderUseSimpleReplaySync = (props = {}) => {
    return renderHook(() =>
      useSimpleReplaySync({
        replayData: mockReplayData,
        nodes: mockNodes,
        edges: mockEdges,
        setNodes: mockSetNodes,
        setEdges: mockSetEdges,
        setSelectedNode: mockSetSelectedNode,
        setSelectedEdge: mockSetSelectedEdge,
        currentStep: -1,
        setCurrentStep: mockSetCurrentStep,
        ...props,
      })
    );
  };

  describe('return values', () => {
    it('should return required handlers', () => {
      const { result } = renderUseSimpleReplaySync();

      expect(typeof result.current.handleCanvasNodeClick).toBe('function');
      expect(typeof result.current.handleCanvasEdgeClick).toBe('function');
      expect(typeof result.current.handleReplayStepSelect).toBe('function');
      expect(typeof result.current.isSyncing).toBe('boolean');
      expect(result.current.isSyncingRef).toBeDefined();
      expect(typeof result.current.isSyncingRef).toBe('object');
      expect(result.current.isReplaySyncingRef).toBeDefined();
      expect(typeof result.current.isReplaySyncingRef).toBe('object');
    });

    it('should return isSyncing as false initially', () => {
      const { result } = renderUseSimpleReplaySync();

      expect(result.current.isSyncing).toBe(false);
      expect(result.current.isSyncingRef.current).toBe(false);
      expect(result.current.isReplaySyncingRef.current).toBe(false);
    });
  });

  describe('handleCanvasNodeClick', () => {
    it('should find and set replay step for clicked node', () => {
      const { result } = renderUseSimpleReplaySync();
      const node = { id: 'node-1', position: { x: 0, y: 0 }, data: {} };

      act(() => {
        result.current.handleCanvasNodeClick(node);
      });

      expect(mockSetCurrentStep).toHaveBeenCalledWith(0);
    });

    it('should clear current step when no matching replay step', () => {
      const { result } = renderUseSimpleReplaySync();
      const node = { id: 'non-existent', position: { x: 0, y: 0 }, data: {} };

      act(() => {
        result.current.handleCanvasNodeClick(node);
      });

      expect(mockSetCurrentStep).toHaveBeenCalledWith(-1);
    });

    it('should not sync if already on correct step', () => {
      const { result } = renderUseSimpleReplaySync({
        currentStep: 0, // Already on node-1's started step
      });
      const node = { id: 'node-1', position: { x: 0, y: 0 }, data: {} };

      act(() => {
        result.current.handleCanvasNodeClick(node);
      });

      // Should not call setCurrentStep because we're already on the right step
      expect(mockSetCurrentStep).not.toHaveBeenCalled();
    });

    it('should find execute step for node', () => {
      const { result } = renderUseSimpleReplaySync();
      const node = { id: 'node-2', position: { x: 0, y: 0 }, data: {} };

      act(() => {
        result.current.handleCanvasNodeClick(node);
      });

      expect(mockSetCurrentStep).toHaveBeenCalledWith(3); // node-2's execute step
    });
  });

  describe('handleCanvasEdgeClick', () => {
    it('should find replay step for edge with condition', () => {
      const { result } = renderUseSimpleReplaySync();
      const edge = { id: 'edge-1', source: 'node-1', target: 'node-2', data: { condition: 'condition-1' } };

      act(() => {
        result.current.handleCanvasEdgeClick(edge);
      });

      expect(mockSetCurrentStep).toHaveBeenCalledWith(2); // add successor with condition-1
    });

    it('should find replay step for edge without condition by source/target', () => {
      const { result } = renderUseSimpleReplaySync();
      const edge = { id: 'edge-2', source: 'node-2', target: 'node-3', data: {} };

      act(() => {
        result.current.handleCanvasEdgeClick(edge);
      });

      expect(mockSetCurrentStep).toHaveBeenCalledWith(4); // add successor from node-2 to node-3
    });

    it('should not sync if already on correct step for edge with condition', () => {
      const { result } = renderUseSimpleReplaySync({
        currentStep: 2, // Already on the condition-1 step
      });
      const edge = { id: 'edge-1', source: 'node-1', target: 'node-2', data: { condition: 'condition-1' } };

      act(() => {
        result.current.handleCanvasEdgeClick(edge);
      });

      expect(mockSetCurrentStep).not.toHaveBeenCalled();
    });
  });

  describe('handleReplayStepSelect', () => {
    it('should clear selections when selecting step', () => {
      const { result } = renderUseSimpleReplaySync();

      act(() => {
        result.current.handleReplayStepSelect(0);
        jest.runAllTimers();
      });

      expect(mockSetNodes).toHaveBeenCalled();
      expect(mockSetEdges).toHaveBeenCalled();
      expect(mockSetSelectedNode).toHaveBeenCalledWith(null);
      expect(mockSetSelectedEdge).toHaveBeenCalledWith(null);
    });

    it('should select node for started step', () => {
      const { result } = renderUseSimpleReplaySync();

      act(() => {
        result.current.handleReplayStepSelect(0);
        jest.runAllTimers();
      });

      // Should call setNodes to mark node-1 as selected
      expect(mockSetNodes).toHaveBeenCalled();
      // Should call setSelectedNode with node-1
      expect(mockSetSelectedNode).toHaveBeenCalled();
    });

    it('should select node for execute step', () => {
      const { result } = renderUseSimpleReplaySync();

      act(() => {
        result.current.handleReplayStepSelect(1);
        jest.runAllTimers();
      });

      expect(mockSetNodes).toHaveBeenCalled();
      expect(mockSetCurrentStep).toHaveBeenCalledWith(1);
    });

    it('should select edge for add successor step with condition', () => {
      const { result } = renderUseSimpleReplaySync();

      act(() => {
        result.current.handleReplayStepSelect(2);
        jest.runAllTimers();
      });

      expect(mockSetEdges).toHaveBeenCalled();
      expect(mockSetSelectedEdge).toHaveBeenCalled();
    });

    it('should apply special styling for access denied step', () => {
      const { result } = renderUseSimpleReplaySync();

      act(() => {
        result.current.handleReplayStepSelect(5); // access denied
        jest.runAllTimers();
      });

      // Should call setNodes multiple times - once to clear, once to select, once for styling
      expect(mockSetNodes).toHaveBeenCalled();
    });

    it('should skip if already synced to same step', () => {
      const { result } = renderUseSimpleReplaySync();

      // First call
      act(() => {
        result.current.handleReplayStepSelect(0);
        jest.runAllTimers();
      });

      const callCount = mockSetNodes.mock.calls.length;

      // Second call to same step - should be skipped due to lastSyncedStep ref
      act(() => {
        result.current.handleReplayStepSelect(0);
        jest.runAllTimers();
      });

      // Should not have additional calls
      expect(mockSetNodes.mock.calls.length).toBe(callCount);
    });

    it('should handle step -1 (clear all) after selecting a valid step', () => {
      const { result } = renderUseSimpleReplaySync();

      // First select a valid step
      act(() => {
        result.current.handleReplayStepSelect(0);
        jest.runAllTimers();
      });

      mockSetSelectedNode.mockClear();
      mockSetSelectedEdge.mockClear();
      mockSetCurrentStep.mockClear();

      // Now select -1 to clear
      act(() => {
        result.current.handleReplayStepSelect(-1);
        jest.runAllTimers();
      });

      expect(mockSetSelectedNode).toHaveBeenCalledWith(null);
      expect(mockSetSelectedEdge).toHaveBeenCalledWith(null);
      expect(mockSetCurrentStep).toHaveBeenCalledWith(-1);
    });

    it('should handle step beyond replay data length', () => {
      const { result } = renderUseSimpleReplaySync();

      act(() => {
        result.current.handleReplayStepSelect(100);
        jest.runAllTimers();
      });

      // Should still update current step
      expect(mockSetCurrentStep).toHaveBeenCalledWith(100);
    });
  });

  describe('findReplayStepForElement (via handleCanvasNodeClick)', () => {
    it('should return -1 for empty replay data', () => {
      const { result } = renderUseSimpleReplaySync({ replayData: [] });
      const node = { id: 'node-1', position: { x: 0, y: 0 }, data: {} };

      act(() => {
        result.current.handleCanvasNodeClick(node);
      });

      expect(mockSetCurrentStep).toHaveBeenCalledWith(-1);
    });

    it('should prioritize started/execute/access denied steps for nodes', () => {
      const replayDataWithMultipleTypes: ReplayStep[] = [
        { id: 'node-1', type: 'add successor', successorId: 'node-2' },
        { id: 'node-1', type: 'started' },
        { id: 'node-1', type: 'execute' },
      ];

      const { result } = renderUseSimpleReplaySync({
        replayData: replayDataWithMultipleTypes,
      });
      const node = { id: 'node-1', position: { x: 0, y: 0 }, data: {} };

      act(() => {
        result.current.handleCanvasNodeClick(node);
      });

      // Should find 'started' step (index 1), not 'add successor' (index 0)
      expect(mockSetCurrentStep).toHaveBeenCalledWith(1);
    });
  });

  describe('findElementForReplayStep (via handleReplayStepSelect)', () => {
    it('should find edge by condition ID', () => {
      const { result } = renderUseSimpleReplaySync();

      act(() => {
        result.current.handleReplayStepSelect(2); // add successor with conditionId
        jest.runAllTimers();
      });

      expect(mockSetEdges).toHaveBeenCalled();
      expect(mockSetSelectedEdge).toHaveBeenCalled();
    });

    it('should find edge by source/target when no condition match', () => {
      const { result } = renderUseSimpleReplaySync();

      act(() => {
        result.current.handleReplayStepSelect(4); // add successor without conditionId
        jest.runAllTimers();
      });

      expect(mockSetEdges).toHaveBeenCalled();
    });

    it('should fallback to node when edge not found', () => {
      const replayDataNodeOnly: ReplayStep[] = [
        { id: 'node-1', type: 'execute' },
      ];

      const { result } = renderUseSimpleReplaySync({
        replayData: replayDataNodeOnly,
      });

      act(() => {
        result.current.handleReplayStepSelect(0);
        jest.runAllTimers();
      });

      expect(mockSetSelectedNode).toHaveBeenCalled();
    });
  });

  describe('syncing flag', () => {
    it('should clear syncing flag after delay', () => {
      const { result } = renderUseSimpleReplaySync();

      act(() => {
        result.current.handleReplayStepSelect(0);
        jest.runAllTimers();
      });

      // Fast-forward timers to clear the syncing flag
      act(() => {
        jest.advanceTimersByTime(200);
      });

      // isSyncing should be false again (ref value not directly testable, but behavior is)
      // Test by trying another sync
      mockSetCurrentStep.mockClear();

      act(() => {
        result.current.handleCanvasNodeClick({ id: 'node-2', position: { x: 0, y: 0 }, data: {} });
        jest.runAllTimers();
      });

      expect(mockSetCurrentStep).toHaveBeenCalled();
    });
  });

  describe('edge cases', () => {
    it('should handle null/undefined in replay data gracefully', () => {
      const { result } = renderUseSimpleReplaySync({ replayData: null as any });

      expect(() => {
        act(() => {
          result.current.handleCanvasNodeClick({ id: 'node-1', position: { x: 0, y: 0 }, data: {} });
        });
      }).not.toThrow();
    });

    it('should handle edge without data property', () => {
      const edgesWithoutData: Edge[] = [
        { id: 'edge-1', source: 'node-1', target: 'node-2' },
      ];

      const { result } = renderUseSimpleReplaySync({ edges: edgesWithoutData });

      expect(() => {
        act(() => {
          result.current.handleCanvasEdgeClick(edgesWithoutData[0]);
        });
      }).not.toThrow();
    });

    it('should handle step with only successorId (no conditionId)', () => {
      const replayDataWithSuccessor: ReplayStep[] = [
        { id: 'node-1', type: 'add successor', successorId: 'node-2' },
      ];

      const { result } = renderUseSimpleReplaySync({
        replayData: replayDataWithSuccessor,
      });

      act(() => {
        result.current.handleReplayStepSelect(0);
        jest.runAllTimers();
      });

      // Should find edge by source/target - setEdges is called in requestAnimationFrame
      expect(mockSetEdges).toHaveBeenCalled();
    });

    it('should warn when element not found for important step types', () => {
      const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();

      const replayDataNoMatch: ReplayStep[] = [
        { id: 'non-existent', type: 'add successor', successorId: 'also-non-existent', conditionId: 'no-match' },
      ];

      const { result } = renderUseSimpleReplaySync({
        replayData: replayDataNoMatch,
        nodes: [],
        edges: [],
      });

      act(() => {
        result.current.handleReplayStepSelect(0);
        jest.runAllTimers();
      });

      // The warning is called inside requestAnimationFrame callback
      expect(consoleSpy).toHaveBeenCalled();
      consoleSpy.mockRestore();
    });
  });

  describe('bidirectional sync prevention', () => {
    it('should not cause feedback loop when selecting from canvas then replay', () => {
      const { result } = renderUseSimpleReplaySync();

      // Simulate canvas click
      act(() => {
        result.current.handleCanvasNodeClick({ id: 'node-1', position: { x: 0, y: 0 }, data: {} });
        // Don't run timers yet - syncing flag should still be set
      });

      const callsAfterCanvasClick = mockSetCurrentStep.mock.calls.length;

      // Immediately try to select from replay (simulating potential feedback)
      // This should be blocked by isSyncing flag
      act(() => {
        result.current.handleReplayStepSelect(1);
        jest.runAllTimers();
      });

      // The important thing is no infinite loop - we should have limited calls
      expect(mockSetCurrentStep.mock.calls.length).toBeLessThanOrEqual(callsAfterCanvasClick + 2);
    });
  });
});
