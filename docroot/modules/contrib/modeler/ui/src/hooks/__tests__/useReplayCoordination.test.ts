import { renderHook, act } from '@testing-library/react';
import { useReplayCoordination } from '../useReplayCoordination';
import { Node, Edge } from 'reactflow';

describe('useReplayCoordination', () => {
  let mockSetIsReplayMode: jest.Mock;
  let mockSetCurrentReplayStep: jest.Mock;
  let mockHandleReplayStepSelect: jest.Mock;

  const mockNodes: Node[] = [
    { id: 'node-1', position: { x: 0, y: 0 }, data: { label: 'Node 1' } },
    { id: 'node-2', position: { x: 100, y: 0 }, data: { label: 'Node 2' } },
  ];

  const mockEdges: Edge[] = [
    { id: 'edge-1', source: 'node-1', target: 'node-2', data: { condition: 'condition-1' } },
  ];

  const mockReplayData = [
    { type: 'started', id: 'node-1' },
    { type: 'execute', id: 'node-1' },
    { type: 'add successor', id: 'node-1', successorId: 'node-2', conditionId: 'condition-1' },
    { type: 'execute', id: 'node-2' },
  ];

  beforeEach(() => {
    jest.useFakeTimers();
    mockSetIsReplayMode = jest.fn();
    mockSetCurrentReplayStep = jest.fn();
    mockHandleReplayStepSelect = jest.fn();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  const renderUseReplayCoordination = (props = {}) => {
    return renderHook(() =>
      useReplayCoordination({
        replayData: mockReplayData,
        nodes: mockNodes,
        edges: mockEdges,
        isReplayMode: false,
        currentReplayStep: -1,
        setIsReplayMode: mockSetIsReplayMode,
        setCurrentReplayStep: mockSetCurrentReplayStep,
        selectedNode: null,
        selectedEdge: null,
        isSyncing: false,
        handleReplayStepSelect: mockHandleReplayStepSelect,
        ...props,
      })
    );
  };

  describe('return values', () => {
    it('should return all required functions and values', () => {
      const { result } = renderUseReplayCoordination();

      expect(typeof result.current.toggleReplayMode).toBe('function');
      expect(typeof result.current.autoSyncToReplay).toBe('function');
      expect(typeof result.current.hasReplayData).toBe('boolean');
    });
  });

  describe('hasReplayData', () => {
    it('should return true when replay data exists', () => {
      const { result } = renderUseReplayCoordination();

      expect(result.current.hasReplayData).toBe(true);
    });

    it('should return false when replay data is empty', () => {
      const { result } = renderUseReplayCoordination({ replayData: [] });

      expect(result.current.hasReplayData).toBe(false);
    });

    it('should return falsy when replay data is null', () => {
      const { result } = renderUseReplayCoordination({ replayData: null as any });

      expect(result.current.hasReplayData).toBeFalsy();
    });
  });

  describe('toggleReplayMode', () => {
    it('should exit replay mode when currently in replay mode', () => {
      const { result } = renderUseReplayCoordination({
        isReplayMode: true,
        currentReplayStep: 2,
      });

      act(() => {
        result.current.toggleReplayMode();
      });

      expect(mockSetIsReplayMode).toHaveBeenCalledWith(false);
      expect(mockSetCurrentReplayStep).toHaveBeenCalledWith(-1);
      expect(mockHandleReplayStepSelect).toHaveBeenCalledWith(-1);
    });

    it('should enter replay mode when not in replay mode', () => {
      const { result } = renderUseReplayCoordination({ isReplayMode: false });

      act(() => {
        result.current.toggleReplayMode();
      });

      expect(mockSetIsReplayMode).toHaveBeenCalledWith(true);
    });

    it('should find matching step for selected node when entering replay mode', () => {
      const selectedNode = { id: 'node-1', position: { x: 0, y: 0 }, data: {} };
      const { result } = renderUseReplayCoordination({
        isReplayMode: false,
        selectedNode,
      });

      act(() => {
        result.current.toggleReplayMode();
      });

      expect(mockSetCurrentReplayStep).toHaveBeenCalledWith(0);
      expect(mockHandleReplayStepSelect).toHaveBeenCalledWith(0);
    });

    it('should find matching step for selected edge when entering replay mode', () => {
      const selectedEdge = { id: 'edge-1', source: 'node-1', target: 'node-2', data: { condition: 'condition-1' } };
      const { result } = renderUseReplayCoordination({
        isReplayMode: false,
        selectedEdge,
      });

      act(() => {
        result.current.toggleReplayMode();
      });

      expect(mockSetCurrentReplayStep).toHaveBeenCalledWith(2);
    });
  });

  describe('autoSyncToReplay', () => {
    it('should sync node and enter replay mode when not in replay mode', () => {
      const { result } = renderUseReplayCoordination({
        isReplayMode: false,
        isSyncing: false,
      });
      const node = { id: 'node-1', position: { x: 0, y: 0 }, data: {} };

      act(() => {
        result.current.autoSyncToReplay(node);
        jest.runAllTimers();
      });

      expect(mockSetIsReplayMode).toHaveBeenCalledWith(true);
      expect(mockSetCurrentReplayStep).toHaveBeenCalled();
    });

    it('should not sync when isSyncing is true', () => {
      const { result } = renderUseReplayCoordination({
        isSyncing: true,
      });
      const node = { id: 'node-1', position: { x: 0, y: 0 }, data: {} };

      act(() => {
        result.current.autoSyncToReplay(node);
        jest.runAllTimers();
      });

      expect(mockSetCurrentReplayStep).not.toHaveBeenCalled();
    });

    it('should not sync when no replay data', () => {
      const { result } = renderUseReplayCoordination({
        replayData: [],
      });
      const node = { id: 'node-1', position: { x: 0, y: 0 }, data: {} };

      act(() => {
        result.current.autoSyncToReplay(node);
        jest.runAllTimers();
      });

      expect(mockSetCurrentReplayStep).not.toHaveBeenCalled();
    });

    it('should sync when in replay mode but step is -1', () => {
      const { result } = renderUseReplayCoordination({
        isReplayMode: true,
        currentReplayStep: -1,
        isSyncing: false,
      });
      const node = { id: 'node-1', position: { x: 0, y: 0 }, data: {} };

      act(() => {
        result.current.autoSyncToReplay(node);
        jest.runAllTimers();
      });

      expect(mockSetCurrentReplayStep).toHaveBeenCalled();
    });

    it('should not sync when already in replay mode with valid step', () => {
      const { result } = renderUseReplayCoordination({
        isReplayMode: true,
        currentReplayStep: 2,
        isSyncing: false,
      });
      const node = { id: 'node-1', position: { x: 0, y: 0 }, data: {} };

      act(() => {
        result.current.autoSyncToReplay(node);
        jest.runAllTimers();
      });

      expect(mockSetCurrentReplayStep).not.toHaveBeenCalled();
    });
  });
});
