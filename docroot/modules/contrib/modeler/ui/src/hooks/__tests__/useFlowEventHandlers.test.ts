import React from 'react';
import { renderHook, act } from '@testing-library/react';
import { useFlowEventHandlers } from '../useFlowEventHandlers';

// Mock store state
let mockNodes: any[] = [];
let mockEdges: any[] = [];
let mockSelectedNode: any = null;
let mockSelectedEdge: any = null;

const mockSetNodes = jest.fn((callback) => {
  if (typeof callback === 'function') {
    mockNodes = callback(mockNodes);
  } else {
    mockNodes = callback;
  }
});

const mockSetEdges = jest.fn((callback) => {
  if (typeof callback === 'function') {
    mockEdges = callback(mockEdges);
  } else {
    mockEdges = callback;
  }
});

const mockApplyNodeChanges = jest.fn();
const mockApplyEdgeChanges = jest.fn();
const mockRemoveNode = jest.fn((nodeId: string) => {
  mockEdges = mockEdges.filter(e => e.source !== nodeId && e.target !== nodeId);
  mockNodes = mockNodes.filter(n => n.id !== nodeId);
  if (mockSelectedNode?.id === nodeId) {
    mockSelectedNode = null;
  }
});
const mockRemoveEdge = jest.fn((edgeId: string) => {
  mockEdges = mockEdges.filter(e => e.id !== edgeId);
});
const mockSetSelectedNode = jest.fn((node) => { mockSelectedNode = node; });
const mockSetSelectedEdge = jest.fn((edge) => { mockSelectedEdge = edge; });
const mockSetSelectedNodes = jest.fn();
const mockSetSelectedEdges = jest.fn();

jest.mock('../../store/useGraphStore', () => ({
  useGraphStore: jest.fn((selector) => {
    const state = {
      nodes: mockNodes,
      edges: mockEdges,
      setNodes: mockSetNodes,
      setEdges: mockSetEdges,
      removeNode: mockRemoveNode,
      removeEdge: mockRemoveEdge,
      applyNodeChanges: mockApplyNodeChanges,
      applyEdgeChanges: mockApplyEdgeChanges,
    };
    return selector(state);
  }),
}));

jest.mock('../../store/useSelectionStore', () => ({
  useSelectionStore: jest.fn((selector) => {
    const state = {
      selectedNode: mockSelectedNode,
      selectedEdge: mockSelectedEdge,
      setSelectedNode: mockSetSelectedNode,
      setSelectedEdge: mockSetSelectedEdge,
      setSelectedNodes: mockSetSelectedNodes,
      setSelectedEdges: mockSetSelectedEdges,
    };
    return selector(state);
  }),
}));

// Mock utility functions
jest.mock('../../utils/clipboardUtils', () => ({
  generateUniqueEdgeId: jest.fn(() => 'edge-new-123'),
  generateEdgeId: jest.fn((source: string, target: string) => `edge_${source}_to_${target}`),
}));

describe('useFlowEventHandlers', () => {
  let mockHandleCanvasNodeClick: jest.Mock;
  let mockHandleCanvasEdgeClick: jest.Mock;
  let mockSetHasUnsavedChanges: jest.Mock;
  let mockAutoSyncToReplay: jest.Mock;

  beforeEach(() => {
    jest.clearAllMocks();
    mockHandleCanvasNodeClick = jest.fn();
    mockHandleCanvasEdgeClick = jest.fn();
    mockSetHasUnsavedChanges = jest.fn();
    mockAutoSyncToReplay = jest.fn();

    mockNodes = [
      { id: 'node-1', position: { x: 100, y: 100 }, data: { label: 'Node 1' }, selected: false },
      { id: 'node-2', position: { x: 300, y: 100 }, data: { label: 'Node 2' }, selected: false },
    ];
    mockEdges = [
      { id: 'edge-1', source: 'node-1', target: 'node-2', selected: false },
    ];
    mockSelectedNode = null;
    mockSelectedEdge = null;
  });

  const createRef = (value: boolean) => ({ current: value });

  const renderUseFlowEventHandlers = (overrides: Record<string, unknown> = {}) => {
    const isSyncing = (overrides.isSyncing as boolean | undefined) ?? false;
    const { isSyncing: _, isReplaySyncingRef: __, ...rest } = overrides;
    return renderHook(() =>
      useFlowEventHandlers({
        handleCanvasNodeClick: mockHandleCanvasNodeClick,
        handleCanvasEdgeClick: mockHandleCanvasEdgeClick,
        setHasUnsavedChanges: mockSetHasUnsavedChanges,
        isSyncing,
        isReplaySyncingRef: (overrides.isReplaySyncingRef ?? createRef(false)) as React.RefObject<boolean>,
        hasReplayData: false,
        isReplayMode: false,
        currentReplayStep: -1,
        autoSyncToReplay: mockAutoSyncToReplay,
        ...rest,
      })
    );
  };

  describe('return values', () => {
    it('should return all required handlers', () => {
      const { result } = renderUseFlowEventHandlers();

      expect(typeof result.current.onNodesChange).toBe('function');
      expect(typeof result.current.onEdgesChange).toBe('function');
      expect(typeof result.current.onSelectionChange).toBe('function');
      expect(typeof result.current.onNodeClick).toBe('function');
      expect(typeof result.current.onEdgeClick).toBe('function');
      expect(typeof result.current.onDeleteNode).toBe('function');
      expect(typeof result.current.handleDeleteSelected).toBe('function');
      expect(typeof result.current.onConnect).toBe('function');
      expect(typeof result.current.onPaneClick).toBe('function');
      expect(typeof result.current.onSelectionStart).toBe('function');
      expect(typeof result.current.onNodeDragStart).toBe('function');
      expect(typeof result.current.onNodeDragStop).toBe('function');
    });
  });

  describe('onNodesChange', () => {
    it('should apply node changes to store', () => {
      const { result } = renderUseFlowEventHandlers();
      const changes = [{ id: 'node-1', type: 'position', position: { x: 200, y: 200 } }];

      act(() => {
        result.current.onNodesChange(changes);
      });

      expect(mockApplyNodeChanges).toHaveBeenCalledWith(changes);
    });

    it('should not mark unsaved changes for any node changes (handled by drag events)', () => {
      const { result } = renderUseFlowEventHandlers();
      const changes = [
        { id: 'node-1', type: 'position', position: { x: 200, y: 200 }, dragging: false },
        { id: 'node-1', type: 'select', selected: true },
      ];

      act(() => {
        result.current.onNodesChange(changes);
      });

      expect(mockSetHasUnsavedChanges).not.toHaveBeenCalled();
    });
  });

  describe('onNodeDragStart and onNodeDragStop', () => {
    it('should mark unsaved changes when a node is dragged to a new position', () => {
      const { result } = renderUseFlowEventHandlers();
      const event = {} as React.MouseEvent;
      const nodeAtStart = { id: 'node-1', position: { x: 100, y: 100 }, data: {} };
      const nodeAtEnd = { id: 'node-1', position: { x: 200, y: 200 }, data: {} };

      act(() => {
        result.current.onNodeDragStart(event, nodeAtStart);
      });
      expect(mockSetHasUnsavedChanges).not.toHaveBeenCalled();

      act(() => {
        result.current.onNodeDragStop(event, nodeAtEnd);
      });
      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should not mark unsaved changes when a node is clicked without moving (zero-movement drag)', () => {
      const { result } = renderUseFlowEventHandlers();
      const event = {} as React.MouseEvent;
      const node = { id: 'node-1', position: { x: 100, y: 100 }, data: {} };

      act(() => {
        result.current.onNodeDragStart(event, node);
      });
      act(() => {
        result.current.onNodeDragStop(event, node);
      });

      expect(mockSetHasUnsavedChanges).not.toHaveBeenCalled();
    });

    it('should not mark unsaved changes if only one axis moved to the same value', () => {
      const { result } = renderUseFlowEventHandlers();
      const event = {} as React.MouseEvent;
      const nodeAtStart = { id: 'node-1', position: { x: 100, y: 100 }, data: {} };
      const nodeAtEnd = { id: 'node-1', position: { x: 100, y: 200 }, data: {} };

      act(() => {
        result.current.onNodeDragStart(event, nodeAtStart);
      });
      act(() => {
        result.current.onNodeDragStop(event, nodeAtEnd);
      });

      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });
  });

  describe('onEdgesChange', () => {
    it('should apply edge changes to store', () => {
      const { result } = renderUseFlowEventHandlers();
      const changes = [{ id: 'edge-1', type: 'remove' }];

      act(() => {
        result.current.onEdgesChange(changes);
      });

      expect(mockApplyEdgeChanges).toHaveBeenCalledWith(changes);
    });
  });

  describe('onSelectionChange', () => {
    it('should set selected node and edge', () => {
      const { result } = renderUseFlowEventHandlers();
      const selectedNodes = [{ id: 'node-1', position: { x: 0, y: 0 }, data: {} }];
      const selectedEdges: any[] = [];

      act(() => {
        result.current.onSelectionChange({ nodes: selectedNodes, edges: selectedEdges });
      });

      expect(mockSetSelectedNode).toHaveBeenCalledWith(selectedNodes[0]);
      expect(mockSetSelectedEdge).toHaveBeenCalledWith(null);
    });

    it('should set selected nodes and edges arrays', () => {
      const { result } = renderUseFlowEventHandlers();
      const selectedNodes = [
        { id: 'node-1', position: { x: 0, y: 0 }, data: {} },
        { id: 'node-2', position: { x: 100, y: 0 }, data: {} }
      ];
      const selectedEdges = [{ id: 'edge-1', source: 'node-1', target: 'node-2' }];

      act(() => {
        result.current.onSelectionChange({ nodes: selectedNodes, edges: selectedEdges });
      });

      expect(mockSetSelectedNodes).toHaveBeenCalledWith(['node-1', 'node-2']);
      expect(mockSetSelectedEdges).toHaveBeenCalledWith(['edge-1']);
    });

    it('should auto-sync to replay when conditions are met', () => {
      const { result } = renderUseFlowEventHandlers({
        hasReplayData: true,
        isReplayMode: false,
        isSyncing: false,
      });
      const selectedNodes = [{ id: 'node-1', position: { x: 0, y: 0 }, data: {} }];

      act(() => {
        result.current.onSelectionChange({ nodes: selectedNodes, edges: [] });
      });

      expect(mockAutoSyncToReplay).toHaveBeenCalledWith(selectedNodes[0]);
    });

    it('should not auto-sync when isSyncing is true', () => {
      const { result } = renderUseFlowEventHandlers({
        hasReplayData: true,
        isSyncing: true,
      });

      act(() => {
        result.current.onSelectionChange({ nodes: [{ id: 'node-1', position: { x: 0, y: 0 }, data: {} }], edges: [] });
      });

      expect(mockAutoSyncToReplay).not.toHaveBeenCalled();
    });

    it('should not auto-sync when no replay data', () => {
      const { result } = renderUseFlowEventHandlers({
        hasReplayData: false,
      });

      act(() => {
        result.current.onSelectionChange({ nodes: [{ id: 'node-1', position: { x: 0, y: 0 }, data: {} }], edges: [] });
      });

      expect(mockAutoSyncToReplay).not.toHaveBeenCalled();
    });

    it('should not auto-sync when isReplayMode is true and currentReplayStep is not -1', () => {
      const { result } = renderUseFlowEventHandlers({
        hasReplayData: true,
        isReplayMode: true,
        currentReplayStep: 5,
        isSyncing: false,
      });

      act(() => {
        result.current.onSelectionChange({ nodes: [{ id: 'node-1', position: { x: 0, y: 0 }, data: {} }], edges: [] });
      });

      expect(mockAutoSyncToReplay).not.toHaveBeenCalled();
    });

    it('should auto-sync when isReplayMode is true but currentReplayStep is -1', () => {
      const { result } = renderUseFlowEventHandlers({
        hasReplayData: true,
        isReplayMode: true,
        currentReplayStep: -1,
        isSyncing: false,
      });

      act(() => {
        result.current.onSelectionChange({ nodes: [{ id: 'node-1', position: { x: 0, y: 0 }, data: {} }], edges: [] });
      });

      expect(mockAutoSyncToReplay).toHaveBeenCalled();
    });

    it('should handle empty selection', () => {
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.onSelectionChange({ nodes: [], edges: [] });
      });

      expect(mockSetSelectedNode).toHaveBeenCalledWith(null);
      expect(mockSetSelectedEdge).toHaveBeenCalledWith(null);
      expect(mockSetSelectedNodes).toHaveBeenCalledWith([]);
      expect(mockSetSelectedEdges).toHaveBeenCalledWith([]);
    });

    it('should select edge when only edge is selected', () => {
      const { result } = renderUseFlowEventHandlers();
      const selectedEdge = { id: 'edge-1', source: 'node-1', target: 'node-2' };

      act(() => {
        result.current.onSelectionChange({ nodes: [], edges: [selectedEdge] });
      });

      expect(mockSetSelectedNode).toHaveBeenCalledWith(null);
      expect(mockSetSelectedEdge).toHaveBeenCalledWith(selectedEdge);
    });

    it('should skip onSelectionChange entirely when isReplaySyncingRef is true', () => {
      const replaySyncingRef = createRef(true);
      const { result } = renderUseFlowEventHandlers({
        isReplaySyncingRef: replaySyncingRef,
      });

      act(() => {
        result.current.onSelectionChange({
          nodes: [{ id: 'node-1', position: { x: 0, y: 0 }, data: {} }],
          edges: [{ id: 'edge-1', source: 'node-1', target: 'node-2' }],
        });
      });

      // All store updates should be skipped during replay-to-canvas sync
      expect(mockSetSelectedNode).not.toHaveBeenCalled();
      expect(mockSetSelectedEdge).not.toHaveBeenCalled();
      expect(mockSetSelectedNodes).not.toHaveBeenCalled();
      expect(mockSetSelectedEdges).not.toHaveBeenCalled();
      expect(mockAutoSyncToReplay).not.toHaveBeenCalled();
    });

    it('should allow onSelectionChange when isReplaySyncingRef is false even if isSyncing is true', () => {
      const replaySyncingRef = createRef(false);
      const { result } = renderUseFlowEventHandlers({
        isSyncing: true,
        isReplaySyncingRef: replaySyncingRef,
      });

      act(() => {
        result.current.onSelectionChange({
          nodes: [{ id: 'node-1', position: { x: 0, y: 0 }, data: {} }],
          edges: [],
        });
      });

      // Selection should proceed (isReplaySyncingRef is false)
      expect(mockSetSelectedNode).toHaveBeenCalledWith(expect.objectContaining({ id: 'node-1' }));
      // But autoSyncToReplay should be blocked by the general isSyncing flag
      expect(mockAutoSyncToReplay).not.toHaveBeenCalled();
    });
  });

  describe('onNodeClick', () => {
    it('should call handleCanvasNodeClick with node', () => {
      const { result } = renderUseFlowEventHandlers();
      const node = { id: 'node-1', position: { x: 0, y: 0 }, data: {} };
      const event = {} as React.MouseEvent;

      act(() => {
        result.current.onNodeClick(event, node);
      });

      expect(mockHandleCanvasNodeClick).toHaveBeenCalledWith(node);
    });
  });

  describe('onEdgeClick', () => {
    it('should call handleCanvasEdgeClick with edge', () => {
      const { result } = renderUseFlowEventHandlers();
      const edge = { id: 'edge-1', source: 'node-1', target: 'node-2' };
      const event = {} as React.MouseEvent;

      act(() => {
        result.current.onEdgeClick(event, edge);
      });

      expect(mockHandleCanvasEdgeClick).toHaveBeenCalledWith(edge);
    });
  });

  describe('onDeleteNode', () => {
    it('should delegate to useGraphStore.removeNode', () => {
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.onDeleteNode('node-1');
      });

      expect(mockRemoveNode).toHaveBeenCalledWith('node-1');
    });

    it('should remove node and connected edges via removeNode', () => {
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.onDeleteNode('node-1');
      });

      expect(mockNodes.find(n => n.id === 'node-1')).toBeUndefined();
      expect(mockEdges.find(e => e.source === 'node-1' || e.target === 'node-1')).toBeUndefined();
    });

    it('should mark unsaved changes', () => {
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.onDeleteNode('node-1');
      });

      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should announce deletion to screen readers', () => {
      const mockAnnounce = jest.fn();
      const { result } = renderUseFlowEventHandlers({ announce: mockAnnounce });

      act(() => {
        result.current.onDeleteNode('node-1');
      });

      expect(mockAnnounce).toHaveBeenCalledWith('Element deleted.');
    });

    it('should not fail when announce is not provided', () => {
      const { result } = renderUseFlowEventHandlers();

      expect(() => {
        act(() => {
          result.current.onDeleteNode('node-1');
        });
      }).not.toThrow();
    });

    it('reconnects predecessor -> successor when deleting a 1-in/1-out condition node', () => {
      // Fix C4: single-node delete of a condition node must reconnect the graph.
      mockNodes = [
        { id: 'pred', data: {} },
        { id: 'cond', type: 'condition', data: { __isConditionNode: true } },
        { id: 'succ', data: {} },
      ];
      mockEdges = [
        { id: 'in', source: 'pred', target: 'cond' },
        { id: 'out', source: 'cond', target: 'succ' },
      ];
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.onDeleteNode('cond');
      });

      // removeNode is bypassed in favor of an explicit setNodes/setEdges pass.
      expect(mockRemoveNode).not.toHaveBeenCalled();
      expect(mockNodes.find(n => n.id === 'cond')).toBeUndefined();
      expect(mockEdges.find(e => e.id === 'in')).toBeUndefined();
      expect(mockEdges.find(e => e.id === 'out')).toBeUndefined();
      const reconnect = mockEdges.find(e => e.source === 'pred' && e.target === 'succ');
      expect(reconnect).toBeDefined();
      expect(reconnect.type).toBe('default');
    });
  });

  describe('handleDeleteSelected', () => {
    it('should remove selected unlocked nodes (single setNodes/setEdges pass)', () => {
      mockNodes = [
        { id: 'node-1', selected: true, data: {} },
        { id: 'node-2', selected: false, data: {} },
      ];
      mockEdges = [];
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.handleDeleteSelected();
      });

      expect(mockNodes.find(n => n.id === 'node-1')).toBeUndefined();
      expect(mockNodes.find(n => n.id === 'node-2')).toBeDefined();
      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should remove selected unlocked edges', () => {
      mockNodes = [];
      mockEdges = [
        { id: 'edge-1', source: 'a', target: 'b', selected: true },
        { id: 'edge-2', source: 'b', target: 'c', selected: false },
      ];
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.handleDeleteSelected();
      });

      expect(mockEdges.find(e => e.id === 'edge-1')).toBeUndefined();
      expect(mockEdges.find(e => e.id === 'edge-2')).toBeDefined();
    });

    it('should remove edges connected to deleted nodes without leaving stragglers', () => {
      mockNodes = [
        { id: 'node-1', selected: true, data: {} },
        { id: 'node-2', selected: false, data: {} },
      ];
      mockEdges = [
        { id: 'edge-1', source: 'node-1', target: 'node-2', selected: true },
      ];
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.handleDeleteSelected();
      });

      // node-1 and its connected edge are gone; node-2 survives.
      expect(mockNodes.find(n => n.id === 'node-1')).toBeUndefined();
      expect(mockEdges.find(e => e.id === 'edge-1')).toBeUndefined();
      expect(mockNodes.find(n => n.id === 'node-2')).toBeDefined();
    });

    it('reconnects predecessor -> successor when a selected condition node is deleted', () => {
      // Fix C4: deleting a 1-in/1-out condition node must reconnect its
      // predecessor directly to its successor with a plain default edge.
      mockNodes = [
        { id: 'pred', selected: false, data: {} },
        { id: 'cond', type: 'condition', selected: true, data: { __isConditionNode: true } },
        { id: 'succ', selected: false, data: {} },
      ];
      mockEdges = [
        { id: 'in', source: 'pred', target: 'cond' },
        { id: 'out', source: 'cond', target: 'succ' },
      ];
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.handleDeleteSelected();
      });

      expect(mockNodes.find(n => n.id === 'cond')).toBeUndefined();
      // The two split edges are gone, replaced by a single pred -> succ edge.
      expect(mockEdges.find(e => e.id === 'in')).toBeUndefined();
      expect(mockEdges.find(e => e.id === 'out')).toBeUndefined();
      const reconnect = mockEdges.find(e => e.source === 'pred' && e.target === 'succ');
      expect(reconnect).toBeDefined();
      expect(reconnect.type).toBe('default');
    });

    it('reconnects ALL N predecessors -> successor when a reused (fan-in) condition node is deleted', () => {
      // Issue #3589093 (Task 4): a condition node may have N inbound edges
      // (reuse).  Deleting it must reconnect EACH of the N predecessors to the
      // single successor — N plain edges, not just the first.
      mockNodes = [
        { id: 'p1', selected: false, data: {} },
        { id: 'p2', selected: false, data: {} },
        { id: 'cond', type: 'condition', selected: true, data: { __isConditionNode: true } },
        { id: 'succ', selected: false, data: {} },
      ];
      mockEdges = [
        { id: 'in1', source: 'p1', target: 'cond' },
        { id: 'in2', source: 'p2', target: 'cond' },
        { id: 'out', source: 'cond', target: 'succ' },
      ];
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.handleDeleteSelected();
      });

      expect(mockNodes.find(n => n.id === 'cond')).toBeUndefined();
      // All split edges gone.
      expect(mockEdges.find(e => e.id === 'in1')).toBeUndefined();
      expect(mockEdges.find(e => e.id === 'in2')).toBeUndefined();
      expect(mockEdges.find(e => e.id === 'out')).toBeUndefined();
      // BOTH predecessors reconnected to the single successor.
      const r1 = mockEdges.find(e => e.source === 'p1' && e.target === 'succ');
      const r2 = mockEdges.find(e => e.source === 'p2' && e.target === 'succ');
      expect(r1).toBeDefined();
      expect(r2).toBeDefined();
      expect(r1.type).toBe('default');
      expect(r2.type).toBe('default');
    });

    it('does NOT reconnect when the condition predecessor is also deleted', () => {
      // Edge case: reconnecting to a node that is itself being removed would
      // create a dangling edge — computeConditionReconnectEdges skips it.
      mockNodes = [
        { id: 'pred', selected: true, data: {} },
        { id: 'cond', type: 'condition', selected: true, data: { __isConditionNode: true } },
        { id: 'succ', selected: false, data: {} },
      ];
      mockEdges = [
        { id: 'in', source: 'pred', target: 'cond' },
        { id: 'out', source: 'cond', target: 'succ' },
      ];
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.handleDeleteSelected();
      });

      expect(mockNodes.find(n => n.id === 'pred')).toBeUndefined();
      expect(mockNodes.find(n => n.id === 'cond')).toBeUndefined();
      // No dangling edge to/from the removed predecessor.
      expect(mockEdges.find(e => e.source === 'pred')).toBeUndefined();
      expect(mockEdges.find(e => e.target === 'succ' && e.source === 'pred')).toBeUndefined();
    });

    it('should do nothing when nothing is selected', () => {
      mockNodes = [{ id: 'node-1', selected: false }];
      mockEdges = [{ id: 'edge-1', selected: false }];
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.handleDeleteSelected();
      });

      expect(mockRemoveNode).not.toHaveBeenCalled();
      expect(mockRemoveEdge).not.toHaveBeenCalled();
      expect(mockSetHasUnsavedChanges).not.toHaveBeenCalled();
    });

    it('should announce deletion count to screen readers', () => {
      mockNodes = [
        { id: 'node-1', selected: true, data: {} },
        { id: 'node-2', selected: true, data: {} },
      ];
      mockEdges = [
        { id: 'edge-1', source: 'node-1', target: 'node-2', selected: true },
      ];
      const mockAnnounce = jest.fn();
      const { result } = renderUseFlowEventHandlers({ announce: mockAnnounce });

      act(() => {
        result.current.handleDeleteSelected();
      });

      expect(mockAnnounce).toHaveBeenCalledWith('3 elements deleted.');
    });

    it('should not announce when nothing is deleted', () => {
      mockNodes = [{ id: 'node-1', selected: false }];
      mockEdges = [];
      const mockAnnounce = jest.fn();
      const { result } = renderUseFlowEventHandlers({ announce: mockAnnounce });

      act(() => {
        result.current.handleDeleteSelected();
      });

      expect(mockAnnounce).not.toHaveBeenCalled();
    });
  });

  describe('onConnect', () => {
    it('should create new edge between nodes', () => {
      const { result } = renderUseFlowEventHandlers();
      const connection = { source: 'node-1', target: 'node-2' };

      act(() => {
        result.current.onConnect(connection);
      });

      expect(mockSetEdges).toHaveBeenCalled();
      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should use default edge type', () => {
      mockEdges = [];
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.onConnect({ source: 'node-1', target: 'node-2' });
      });

      const newEdge = mockEdges[0];
      expect(newEdge.type).toBe('default');
      expect(newEdge.id).toBe('edge-new-123');
    });

    it('should not create edge when source is null', () => {
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.onConnect({ source: null, target: 'node-2' });
      });

      expect(mockSetEdges).not.toHaveBeenCalled();
    });

    it('should not create edge when target is null', () => {
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.onConnect({ source: 'node-1', target: null });
      });

      expect(mockSetEdges).not.toHaveBeenCalled();
    });

    describe('parallel-edge routing', () => {
      it('routes a new direct parallel edge with a sideways controlOffset', () => {
        // Fixture already contains edge-1 (node-1 → node-2). Adding a new
        // edge between the same endpoints must trigger fan-out routing.
        const { result } = renderUseFlowEventHandlers();

        act(() => {
          result.current.onConnect({ source: 'node-1', target: 'node-2' });
        });

        // Find the new edge in the resulting state.
        const newEdge = mockEdges.find(e => e.id === 'edge-new-123');
        expect(newEdge).toBeDefined();
        expect(newEdge.data?.controlOffset).toBeDefined();
        expect(newEdge.data.controlOffset.x).not.toBe(0);

        // The existing sibling must have been rebalanced to the other side.
        const existing = mockEdges.find(e => e.id === 'edge-1');
        expect(existing).toBeDefined();
        expect(existing.data?.controlOffset).toBeDefined();
        expect(existing.data.controlOffset.x).not.toBe(0);
        // Opposite signs — the two siblings sit on opposite sides.
        expect(
          Math.sign(newEdge.data.controlOffset.x) *
            Math.sign(existing.data.controlOffset.x),
        ).toBeLessThan(0);
      });

      it('does not add a controlOffset when no parallel collision exists', () => {
        // Empty edge list → first edge between node-1 and node-2 is plain.
        mockEdges = [];
        const { result } = renderUseFlowEventHandlers();

        act(() => {
          result.current.onConnect({ source: 'node-1', target: 'node-2' });
        });

        const newEdge = mockEdges[0];
        expect(newEdge.data?.controlOffset).toBeUndefined();
      });

      it('fans out parallel edges symmetrically (issue #3589093)', () => {
        // Conditions are first-class nodes now, so no rendered edge carries a
        // condition card and parallel edges simply fan out symmetrically
        // around zero — the condition-card overhang special case was removed.
        mockEdges = [
          {
            id: 'edge-1',
            source: 'node-1',
            target: 'node-2',
            type: 'default',
            data: {},
          },
        ];
        const { result } = renderUseFlowEventHandlers();

        act(() => {
          result.current.onConnect({ source: 'node-1', target: 'node-2' });
        });

        const existing = mockEdges.find(e => e.id === 'edge-1');
        const newEdge = mockEdges.find(e => e.id === 'edge-new-123');
        expect(existing).toBeDefined();
        expect(newEdge).toBeDefined();

        // Both must have non-zero offsets on opposite sides.
        const existingX = existing.data.controlOffset.x;
        const newX = newEdge.data.controlOffset.x;
        expect(existingX).not.toBe(0);
        expect(newX).not.toBe(0);
        expect(Math.sign(existingX) * Math.sign(newX)).toBeLessThan(0);
        // Symmetric fan-out: equal magnitude on both sides.
        expect(Math.abs(existingX)).toBe(Math.abs(newX));
      });

      it('routes a bypass curve when a chain connects source to target', () => {
        mockNodes = [
          { id: 'node-1', position: { x: 0, y: 0 }, data: {}, selected: false, width: 180, height: 120 },
          { id: 'node-mid', position: { x: 0, y: 200 }, data: {}, selected: false, width: 180, height: 120 },
          { id: 'node-2', position: { x: 0, y: 400 }, data: {}, selected: false, width: 180, height: 120 },
        ];
        // Existing chain: node-1 → node-mid → node-2.
        mockEdges = [
          { id: 'e1', source: 'node-1', target: 'node-mid', type: 'default', data: {} },
          { id: 'e2', source: 'node-mid', target: 'node-2', type: 'default', data: {} },
        ];
        const { result } = renderUseFlowEventHandlers();

        // Drag a new direct edge node-1 → node-2.
        act(() => {
          result.current.onConnect({ source: 'node-1', target: 'node-2' });
        });

        const newEdge = mockEdges.find(e => e.id === 'edge-new-123');
        expect(newEdge).toBeDefined();
        expect(newEdge.data?.controlOffset).toBeDefined();
        expect(newEdge.data.controlOffset.x).not.toBe(0);
        // The chain edges themselves must NOT be modified by a bypass.
        const chainE1 = mockEdges.find(e => e.id === 'e1');
        const chainE2 = mockEdges.find(e => e.id === 'e2');
        expect(chainE1.data?.controlOffset).toBeUndefined();
        expect(chainE2.data?.controlOffset).toBeUndefined();
      });
    });
  });

  // [C2] New-edge "drop onto node body" (issue #3585553 follow-on UX).
  // onConnect fires only on a HANDLE drop; onConnectEnd fires on ANY release.
  // When no handle was hit, onConnectEnd must resolve the node under the cursor
  // and create the edge — matching reconnect drop-onto-node behavior.
  describe('onConnectStart / onConnectEnd (drop on node body)', () => {
    // Build a fake DOM element that `el.closest('.react-flow__node[data-id]')`
    // resolves to the requested node id (and is NOT inside a grip), so
    // hitTestDropTarget returns that node.
    const fakeNodeElement = (nodeId: string): HTMLElement => {
      const nodeEl = document.createElement('div');
      nodeEl.className = 'react-flow__node';
      nodeEl.setAttribute('data-id', nodeId);
      const child = document.createElement('div');
      nodeEl.appendChild(child);
      return child; // elementFromPoint returns a child; .closest walks up.
    };

    const mouseUpAt = (clientX: number, clientY: number) =>
      ({ clientX, clientY } as unknown as MouseEvent);

    let originalElementFromPoint: typeof document.elementFromPoint;

    beforeEach(() => {
      mockEdges = [];
      originalElementFromPoint = document.elementFromPoint;
    });

    afterEach(() => {
      document.elementFromPoint = originalElementFromPoint;
    });

    it('creates an edge to the NODE under the cursor when no handle was hit', () => {
      const { result } = renderUseFlowEventHandlers();
      // elementFromPoint resolves to node-2's body.
      document.elementFromPoint = jest.fn(() => fakeNodeElement('node-2'));

      act(() => {
        result.current.onConnectStart(
          {} as React.MouseEvent,
          { nodeId: 'node-1', handleId: 'output', handleType: 'source' },
        );
      });
      act(() => {
        // No onConnect fired (off-handle drop) → onConnectEnd does the work.
        result.current.onConnectEnd(mouseUpAt(500, 200));
      });

      expect(mockSetEdges).toHaveBeenCalled();
      const newEdge = mockEdges.find(e => e.source === 'node-1' && e.target === 'node-2');
      expect(newEdge).toBeDefined();
      expect(newEdge.targetHandle).toBe('input');
      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('does NOT create a duplicate edge when onConnect already fired (handle hit)', () => {
      const { result } = renderUseFlowEventHandlers();
      document.elementFromPoint = jest.fn(() => fakeNodeElement('node-2'));

      act(() => {
        result.current.onConnectStart(
          {} as React.MouseEvent,
          { nodeId: 'node-1', handleId: 'output', handleType: 'source' },
        );
      });
      act(() => {
        // Handle WAS hit → React Flow fires onConnect first.
        result.current.onConnect({ source: 'node-1', target: 'node-2', targetHandle: 'input' });
      });
      const afterConnect = mockEdges.length;
      act(() => {
        result.current.onConnectEnd(mouseUpAt(500, 200));
      });

      // onConnectEnd must be a no-op since onConnect already created the edge.
      expect(mockEdges.length).toBe(afterConnect);
      expect(mockEdges.filter(e => e.source === 'node-1' && e.target === 'node-2').length).toBe(1);
    });

    it('creates nothing when released over empty canvas (no node)', () => {
      const { result } = renderUseFlowEventHandlers();
      document.elementFromPoint = jest.fn(() => null);

      act(() => {
        result.current.onConnectStart(
          {} as React.MouseEvent,
          { nodeId: 'node-1', handleId: 'output', handleType: 'source' },
        );
      });
      act(() => {
        result.current.onConnectEnd(mouseUpAt(9999, 9999));
      });

      expect(mockSetEdges).not.toHaveBeenCalled();
    });

    it('creates nothing when dropped back on the SOURCE node (no self-loop)', () => {
      const { result } = renderUseFlowEventHandlers();
      document.elementFromPoint = jest.fn(() => fakeNodeElement('node-1'));

      act(() => {
        result.current.onConnectStart(
          {} as React.MouseEvent,
          { nodeId: 'node-1', handleId: 'output', handleType: 'source' },
        );
      });
      act(() => {
        result.current.onConnectEnd(mouseUpAt(150, 150));
      });

      expect(mockSetEdges).not.toHaveBeenCalled();
    });

    it('respects successor cardinality — no edge when source is at max successors', () => {
      // node-1 is type "limited" with max 1 successor and already has one edge.
      mockNodes = [
        { id: 'node-1', type: 'limited', position: { x: 0, y: 0 }, data: {}, selected: false },
        { id: 'node-2', type: 'limited', position: { x: 300, y: 0 }, data: {}, selected: false },
        { id: 'node-3', type: 'limited', position: { x: 600, y: 0 }, data: {}, selected: false },
      ];
      mockEdges = [{ id: 'edge-1', source: 'node-1', target: 'node-2', type: 'default', data: {} }];
      const modelConstraints = { limited: { successors: { max: 1 } } } as any;
      const { result } = renderUseFlowEventHandlers({ modelConstraints });
      document.elementFromPoint = jest.fn(() => fakeNodeElement('node-3'));

      act(() => {
        result.current.onConnectStart(
          {} as React.MouseEvent,
          { nodeId: 'node-1', handleId: 'output', handleType: 'source' },
        );
      });
      act(() => {
        result.current.onConnectEnd(mouseUpAt(700, 0));
      });

      // node-1 already at its single allowed successor → no new edge.
      const created = mockEdges.find(e => e.source === 'node-1' && e.target === 'node-3');
      expect(created).toBeUndefined();
    });

    it('creates nothing when there was no connect start (defensive)', () => {
      const { result } = renderUseFlowEventHandlers();
      document.elementFromPoint = jest.fn(() => fakeNodeElement('node-2'));

      act(() => {
        // onConnectEnd without a preceding onConnectStart.
        result.current.onConnectEnd(mouseUpAt(500, 200));
      });

      expect(mockSetEdges).not.toHaveBeenCalled();
    });
  });

  describe('onPaneClick', () => {
    it('should clear node and edge selection', () => {
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.onPaneClick();
      });

      expect(mockSetSelectedNode).toHaveBeenCalledWith(null);
      expect(mockSetSelectedEdge).toHaveBeenCalledWith(null);
    });

    it('should cause onSelectionChange to ignore stale non-empty events', () => {
      const { result } = renderUseFlowEventHandlers();
      const staleNode = { id: 'node-1', position: { x: 0, y: 0 }, data: {} };

      // Simulate: pane click clears selection, then a stale onSelectionChange
      // fires with the previously selected node still present.
      act(() => {
        result.current.onPaneClick();
      });

      mockSetSelectedNode.mockClear();
      mockSetSelectedEdge.mockClear();
      mockSetSelectedNodes.mockClear();
      mockSetSelectedEdges.mockClear();

      act(() => {
        result.current.onSelectionChange({ nodes: [staleNode], edges: [] });
      });

      // The stale event should be ignored — no store updates.
      expect(mockSetSelectedNode).not.toHaveBeenCalled();
      expect(mockSetSelectedEdge).not.toHaveBeenCalled();
      expect(mockSetSelectedNodes).not.toHaveBeenCalled();
      expect(mockSetSelectedEdges).not.toHaveBeenCalled();
    });

    it('should allow the confirming empty onSelectionChange after pane click', () => {
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.onPaneClick();
      });

      mockSetSelectedNode.mockClear();
      mockSetSelectedEdge.mockClear();
      mockSetSelectedNodes.mockClear();
      mockSetSelectedEdges.mockClear();

      // The confirming empty selection event should proceed normally.
      act(() => {
        result.current.onSelectionChange({ nodes: [], edges: [] });
      });

      expect(mockSetSelectedNode).toHaveBeenCalledWith(null);
      expect(mockSetSelectedEdge).toHaveBeenCalledWith(null);
      expect(mockSetSelectedNodes).toHaveBeenCalledWith([]);
      expect(mockSetSelectedEdges).toHaveBeenCalledWith([]);
    });

    it('should not interfere with normal onSelectionChange without prior pane click', () => {
      const { result } = renderUseFlowEventHandlers();
      const selectedNode = { id: 'node-1', position: { x: 0, y: 0 }, data: {} };

      // Normal selection without pane click should work as usual.
      act(() => {
        result.current.onSelectionChange({ nodes: [selectedNode], edges: [] });
      });

      expect(mockSetSelectedNode).toHaveBeenCalledWith(selectedNode);
    });
  });

  // Issue #3589101 (follow-up): a drag-select that begins after a pane-click
  // deselect must NOT be swallowed by the post-pane-click stale-selection
  // guard. React Flow calls onSelectionStart when a rubber-band drag begins
  // (before the selection event), which clears the guard.
  describe('onSelectionStart (pane-click guard reset)', () => {
    const dragSelected = (ids: string[]) =>
      ids.map(id => ({ id, position: { x: 0, y: 0 }, data: {} }));

    it('clears the pane-click guard so a following drag-select is honored', () => {
      const { result } = renderUseFlowEventHandlers();

      // 1) Pane click to deselect — arms the stale-selection guard.
      act(() => {
        result.current.onPaneClick();
      });

      mockSetSelectedNode.mockClear();
      mockSetSelectedEdge.mockClear();
      mockSetSelectedNodes.mockClear();
      mockSetSelectedEdges.mockClear();

      // 2) A NEW rubber-band drag begins → onSelectionStart fires FIRST and
      //    clears the guard. (No confirming empty event fired in between,
      //    which is exactly the broken real-world sequence.)
      act(() => {
        result.current.onSelectionStart();
      });

      // 3) The drag-select's real, non-empty selection event must now be
      //    honored — the store receives the multi-selection.
      act(() => {
        result.current.onSelectionChange({ nodes: dragSelected(['node-1', 'node-2']), edges: [] });
      });

      expect(mockSetSelectedNodes).toHaveBeenCalledWith(['node-1', 'node-2']);
      expect(mockSetSelectedEdges).toHaveBeenCalledWith([]);
      expect(mockSetSelectedNode).toHaveBeenCalledWith(
        expect.objectContaining({ id: 'node-1' }),
      );
    });

    it('reproduces the full maintainer sequence: drag → pane-click → drag again', () => {
      const { result } = renderUseFlowEventHandlers();

      // First drag-select works (no prior pane click).
      act(() => {
        result.current.onSelectionStart();
      });
      act(() => {
        result.current.onSelectionChange({ nodes: dragSelected(['node-1', 'node-2']), edges: [] });
      });
      expect(mockSetSelectedNodes).toHaveBeenLastCalledWith(['node-1', 'node-2']);

      // Pane click clears everything (guard armed, then confirming empty event
      // disarms it and clears the store).
      act(() => {
        result.current.onPaneClick();
      });
      act(() => {
        result.current.onSelectionChange({ nodes: [], edges: [] });
      });
      expect(mockSetSelectedNodes).toHaveBeenLastCalledWith([]);

      mockSetSelectedNodes.mockClear();
      mockSetSelectedEdges.mockClear();

      // SECOND drag-select after the pane-click deselect — previously BROKEN.
      // onSelectionStart fires, then the selection event must reach the store.
      act(() => {
        result.current.onSelectionStart();
      });
      act(() => {
        result.current.onSelectionChange({ nodes: dragSelected(['node-1', 'node-2']), edges: [] });
      });

      expect(mockSetSelectedNodes).toHaveBeenCalledWith(['node-1', 'node-2']);
      expect(mockSetSelectedEdges).toHaveBeenCalledWith([]);
    });

    it('still suppresses a stale non-empty echo after a plain pane click (no drag start)', () => {
      // Regression guard: a plain pane CLICK (no rubber-band drag) does NOT
      // fire onSelectionStart, so the stale non-empty echo it produces must
      // still be ignored — preserving the original reason the guard exists.
      const { result } = renderUseFlowEventHandlers();
      const staleNode = { id: 'node-1', position: { x: 0, y: 0 }, data: {} };

      act(() => {
        result.current.onPaneClick();
      });

      mockSetSelectedNode.mockClear();
      mockSetSelectedEdge.mockClear();
      mockSetSelectedNodes.mockClear();
      mockSetSelectedEdges.mockClear();

      // No onSelectionStart here — this is a stale echo, not a new gesture.
      act(() => {
        result.current.onSelectionChange({ nodes: [staleNode], edges: [] });
      });

      expect(mockSetSelectedNode).not.toHaveBeenCalled();
      expect(mockSetSelectedEdge).not.toHaveBeenCalled();
      expect(mockSetSelectedNodes).not.toHaveBeenCalled();
      expect(mockSetSelectedEdges).not.toHaveBeenCalled();
    });
  });

  // Issue #3589101: rubber-band (mouse-drag) multi-select must drive the
  // selection store IDENTICALLY to Shift+click multi-select, so the property
  // panel switches to multi-selection mode and the Copy button enables.
  //
  // React Flow fires the SAME onSelectionChange for both gestures (the
  // SelectionListener derives its payload from each element's `selected`
  // flag, which both the rubber-band and the click path set). These tests
  // lock that equivalence in at the store-write boundary so a future change
  // cannot make drag-select diverge from Shift+click again.
  describe('drag-select parity with Shift+click (issue #3589101)', () => {
    const dragSelected = (ids: string[]) =>
      ids.map(id => ({ id, position: { x: 0, y: 0 }, data: {} }));

    it('writes the full multi-node selection to the store on a drag-select', () => {
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        // A rubber-band that captured three nodes fires onSelectionChange
        // with all three — exactly as three sequential Shift+clicks would.
        result.current.onSelectionChange({ nodes: dragSelected(['node-1', 'node-2']), edges: [] });
      });

      // Multi-selection arrays drive PropertyPanel.hasMultipleSelection and
      // useClipboard.canCopy — both must reflect the drag-selected nodes.
      expect(mockSetSelectedNodes).toHaveBeenCalledWith(['node-1', 'node-2']);
      expect(mockSetSelectedEdges).toHaveBeenCalledWith([]);
      // Primary selection (first element) is set for the single-element views.
      expect(mockSetSelectedNode).toHaveBeenCalledWith(
        expect.objectContaining({ id: 'node-1' }),
      );
    });

    it('produces the SAME store writes for a drag-select and an equivalent Shift+click set', () => {
      // Drag-select path.
      const drag = renderUseFlowEventHandlers();
      act(() => {
        drag.result.current.onSelectionChange({ nodes: dragSelected(['node-1', 'node-2']), edges: [] });
      });
      const lastCall = (calls: any[][]) => calls[calls.length - 1];
      const dragNodesArg = lastCall(mockSetSelectedNodes.mock.calls)?.[0];
      const dragEdgesArg = lastCall(mockSetSelectedEdges.mock.calls)?.[0];

      jest.clearAllMocks();

      // Shift+click building the same two-node selection (the final
      // onSelectionChange carries both selected nodes).
      const click = renderUseFlowEventHandlers();
      act(() => {
        click.result.current.onSelectionChange({ nodes: dragSelected(['node-1', 'node-2']), edges: [] });
      });
      const clickNodesArg = lastCall(mockSetSelectedNodes.mock.calls)?.[0];
      const clickEdgesArg = lastCall(mockSetSelectedEdges.mock.calls)?.[0];

      expect(dragNodesArg).toEqual(clickNodesArg);
      expect(dragEdgesArg).toEqual(clickEdgesArg);
    });

    it('includes edges connected within the drag-selected region', () => {
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.onSelectionChange({
          nodes: dragSelected(['node-1', 'node-2']),
          edges: [{ id: 'edge-1', source: 'node-1', target: 'node-2' }],
        });
      });

      expect(mockSetSelectedNodes).toHaveBeenCalledWith(['node-1', 'node-2']);
      expect(mockSetSelectedEdges).toHaveBeenCalledWith(['edge-1']);
    });
  });

});
