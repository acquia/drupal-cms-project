import { renderHook, act } from '@testing-library/react';
import { useClipboard } from '../useClipboard';
import * as clipboardUtils from '../../utils/clipboardUtils';
import type { StoreNode as Node, StoreEdge as Edge } from '../../types/settings';

// Mock the clipboard utilities
jest.mock('../../utils/clipboardUtils', () => ({
  copyElements: jest.fn(),
  pasteElements: jest.fn(),
}));

describe('useClipboard', () => {
  const mockSetNodes = jest.fn();
  const mockSetEdges = jest.fn();
  const mockSetSelectedNode = jest.fn();
  const mockSetSelectedEdge = jest.fn();
  const mockSetSelectedNodes = jest.fn();
  const mockSetSelectedEdges = jest.fn();
  const mockSetHasUnsavedChanges = jest.fn();

  const createMockNode = (id: string, overrides = {}): Node => ({
    id,
    type: 'element',
    position: { x: 0, y: 0 },
    data: { label: `Node ${id}` },
    ...overrides,
  });

  const createMockEdge = (id: string, source: string, target: string, overrides = {}): Edge => ({
    id,
    source,
    target,
    ...overrides,
  });

  const defaultProps = {
    selectedNode: null as Node | null,
    selectedEdge: null as Edge | null,
    selectedNodeIds: [] as string[],
    selectedEdgeIds: [] as string[],
    nodes: [] as Node[],
    edges: [] as Edge[],
    setNodes: mockSetNodes,
    setEdges: mockSetEdges,
    setSelectedNode: mockSetSelectedNode,
    setSelectedEdge: mockSetSelectedEdge,
    setSelectedNodes: mockSetSelectedNodes,
    setSelectedEdges: mockSetSelectedEdges,
    setHasUnsavedChanges: mockSetHasUnsavedChanges,
  };

  beforeEach(() => {
    jest.clearAllMocks();
    (clipboardUtils.pasteElements as jest.Mock).mockResolvedValue({
      nodes: [],
      edges: [],
    });
  });

  describe('canCopy', () => {
    it('should return false when nothing is selected', () => {
      const { result } = renderHook(() => useClipboard(defaultProps));
      expect(result.current.canCopy).toBe(false);
    });

    it('should return true when a node is selected', () => {
      const selectedNode = createMockNode('node1');
      const { result } = renderHook(() =>
        useClipboard({ ...defaultProps, selectedNode })
      );
      expect(result.current.canCopy).toBe(true);
    });

    it('should return true when an edge is selected', () => {
      const selectedEdge = createMockEdge('edge1', 'node1', 'node2');
      const { result } = renderHook(() =>
        useClipboard({ ...defaultProps, selectedEdge })
      );
      expect(result.current.canCopy).toBe(true);
    });

    it('should return true when both node and edge are selected', () => {
      const selectedNode = createMockNode('node1');
      const selectedEdge = createMockEdge('edge1', 'node1', 'node2');
      const { result } = renderHook(() =>
        useClipboard({ ...defaultProps, selectedNode, selectedEdge })
      );
      expect(result.current.canCopy).toBe(true);
    });

    it('should return true when multiple nodes are selected via multi-select', () => {
      const { result } = renderHook(() =>
        useClipboard({ ...defaultProps, selectedNodeIds: ['node1', 'node2'] })
      );
      expect(result.current.canCopy).toBe(true);
    });

    it('should return true when multiple edges are selected via multi-select', () => {
      const { result } = renderHook(() =>
        useClipboard({ ...defaultProps, selectedEdgeIds: ['edge1', 'edge2'] })
      );
      expect(result.current.canCopy).toBe(true);
    });
  });

  describe('canPaste', () => {
    it('should always return true (paste validation happens in pasteElements)', () => {
      const { result } = renderHook(() => useClipboard(defaultProps));
      expect(result.current.canPaste).toBe(true);
    });
  });

  describe('handleCopy', () => {
    it('should not call copyElements when nothing is selected', () => {
      const { result } = renderHook(() => useClipboard(defaultProps));

      act(() => {
        result.current.handleCopy();
      });

      expect(clipboardUtils.copyElements).not.toHaveBeenCalled();
    });

    it('should call copyElements with selected node', () => {
      const selectedNode = createMockNode('node1');
      const { result } = renderHook(() =>
        useClipboard({ ...defaultProps, selectedNode })
      );

      act(() => {
        result.current.handleCopy();
      });

      expect(clipboardUtils.copyElements).toHaveBeenCalledWith(
        [selectedNode],
        []
      );
    });

    it('should call copyElements with selected edge', () => {
      const selectedEdge = createMockEdge('edge1', 'node1', 'node2');
      const { result } = renderHook(() =>
        useClipboard({ ...defaultProps, selectedEdge })
      );

      act(() => {
        result.current.handleCopy();
      });

      expect(clipboardUtils.copyElements).toHaveBeenCalledWith(
        [],
        [selectedEdge]
      );
    });

    it('should call copyElements with both selected node and edge', () => {
      const selectedNode = createMockNode('node1');
      const selectedEdge = createMockEdge('edge1', 'node1', 'node2');
      const { result } = renderHook(() =>
        useClipboard({ ...defaultProps, selectedNode, selectedEdge })
      );

      act(() => {
        result.current.handleCopy();
      });

      expect(clipboardUtils.copyElements).toHaveBeenCalledWith(
        [selectedNode],
        [selectedEdge]
      );
    });

    it('should announce copy to screen readers', () => {
      const mockAnnounce = jest.fn();
      const selectedNode = createMockNode('node1');
      const { result } = renderHook(() =>
        useClipboard({ ...defaultProps, selectedNode, announce: mockAnnounce })
      );

      act(() => {
        result.current.handleCopy();
      });

      expect(mockAnnounce).toHaveBeenCalledWith('1 elements copied.');
    });

    it('should announce copy count for multiple elements', () => {
      const mockAnnounce = jest.fn();
      const selectedNode = createMockNode('node1');
      const selectedEdge = createMockEdge('edge1', 'node1', 'node2');
      const { result } = renderHook(() =>
        useClipboard({ ...defaultProps, selectedNode, selectedEdge, announce: mockAnnounce })
      );

      act(() => {
        result.current.handleCopy();
      });

      expect(mockAnnounce).toHaveBeenCalledWith('2 elements copied.');
    });

    it('should not announce when nothing is copied', () => {
      const mockAnnounce = jest.fn();
      const { result } = renderHook(() =>
        useClipboard({ ...defaultProps, announce: mockAnnounce })
      );

      act(() => {
        result.current.handleCopy();
      });

      expect(mockAnnounce).not.toHaveBeenCalled();
    });

    it('should copy all multi-selected nodes', () => {
      const node1 = createMockNode('node1');
      const node2 = createMockNode('node2');
      const node3 = createMockNode('node3');
      const { result } = renderHook(() =>
        useClipboard({
          ...defaultProps,
          selectedNode: node1,
          selectedNodeIds: ['node1', 'node2', 'node3'],
          nodes: [node1, node2, node3],
        })
      );

      act(() => {
        result.current.handleCopy();
      });

      expect(clipboardUtils.copyElements).toHaveBeenCalledWith(
        [node1, node2, node3],
        []
      );
    });

    it('should copy all multi-selected edges', () => {
      const node1 = createMockNode('node1');
      const edge1 = createMockEdge('edge1', 'node1', 'node2');
      const edge2 = createMockEdge('edge2', 'node2', 'node3');
      const { result } = renderHook(() =>
        useClipboard({
          ...defaultProps,
          selectedNode: node1,
          selectedNodeIds: ['node1'],
          selectedEdgeIds: ['edge1', 'edge2'],
          nodes: [node1],
          edges: [edge1, edge2],
        })
      );

      act(() => {
        result.current.handleCopy();
      });

      expect(clipboardUtils.copyElements).toHaveBeenCalledWith(
        [node1],
        [edge1, edge2]
      );
    });

    it('should announce correct count for multi-select copy', () => {
      const mockAnnounce = jest.fn();
      const node1 = createMockNode('node1');
      const node2 = createMockNode('node2');
      const edge1 = createMockEdge('edge1', 'node1', 'node2');
      const { result } = renderHook(() =>
        useClipboard({
          ...defaultProps,
          selectedNode: node1,
          selectedNodeIds: ['node1', 'node2'],
          selectedEdgeIds: ['edge1'],
          nodes: [node1, node2],
          edges: [edge1],
          announce: mockAnnounce,
        })
      );

      act(() => {
        result.current.handleCopy();
      });

      expect(mockAnnounce).toHaveBeenCalledWith('3 elements copied.');
    });
  });

  describe('handlePaste', () => {
    it('should call pasteElements with current nodes and edges', async () => {
      const nodes = [createMockNode('node1')];
      const edges = [createMockEdge('edge1', 'node1', 'node2')];

      const { result } = renderHook(() =>
        useClipboard({ ...defaultProps, nodes, edges })
      );

      await act(async () => {
        await result.current.handlePaste();
      });

      expect(clipboardUtils.pasteElements).toHaveBeenCalledWith(
        nodes,
        edges,
        null
      );
    });

    it('should merge pasted nodes with existing nodes and deselect existing', async () => {
      const existingNodes = [createMockNode('existing1', { selected: true })];
      const pastedNodes = [createMockNode('pasted1')];

      (clipboardUtils.pasteElements as jest.Mock).mockResolvedValue({
        nodes: pastedNodes,
        edges: [],
      });

      const { result } = renderHook(() =>
        useClipboard({ ...defaultProps, nodes: existingNodes })
      );

      await act(async () => {
        await result.current.handlePaste();
      });

      expect(mockSetNodes).toHaveBeenCalled();
      const setNodesCall = mockSetNodes.mock.calls[0][0];
      // Call the function with existing nodes to verify the merge
      const mergedNodes = setNodesCall(existingNodes);
      expect(mergedNodes).toHaveLength(2);
      // Existing node should be deselected
      expect(mergedNodes[0].selected).toBe(false);
      // Pasted node should be selected
      expect(mergedNodes[1].selected).toBe(true);
    });

    it('should mark model as having unsaved changes after paste', async () => {
      const pastedNodes = [createMockNode('pasted1')];

      (clipboardUtils.pasteElements as jest.Mock).mockResolvedValue({
        nodes: pastedNodes,
        edges: [],
      });

      const { result } = renderHook(() => useClipboard(defaultProps));

      await act(async () => {
        await result.current.handlePaste();
      });

      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should merge pasted edges with existing edges and deselect existing', async () => {
      const existingEdges = [createMockEdge('existing1', 'n1', 'n2', { selected: true })];
      const pastedEdges = [createMockEdge('pasted1', 'n3', 'n4')];
      const pastedNodes = [createMockNode('pasted1')];

      (clipboardUtils.pasteElements as jest.Mock).mockResolvedValue({
        nodes: pastedNodes,
        edges: pastedEdges,
      });

      const { result } = renderHook(() =>
        useClipboard({ ...defaultProps, edges: existingEdges })
      );

      await act(async () => {
        await result.current.handlePaste();
      });

      expect(mockSetEdges).toHaveBeenCalled();
      const setEdgesCall = mockSetEdges.mock.calls[0][0];
      const mergedEdges = setEdgesCall(existingEdges);
      expect(mergedEdges).toHaveLength(2);
      // Existing edge should be deselected
      expect(mergedEdges[0].selected).toBe(false);
      // Pasted edge should be selected
      expect(mergedEdges[1].selected).toBe(true);
    });

    it('should select the first pasted node', async () => {
      const pastedNodes = [
        createMockNode('pasted1'),
        createMockNode('pasted2'),
      ];

      (clipboardUtils.pasteElements as jest.Mock).mockResolvedValue({
        nodes: pastedNodes,
        edges: [],
      });

      const { result } = renderHook(() => useClipboard(defaultProps));

      await act(async () => {
        await result.current.handlePaste();
      });

      expect(mockSetSelectedNode).toHaveBeenCalledWith(pastedNodes[0]);
    });

    it('should clear edge selection after paste', async () => {
      const pastedNodes = [createMockNode('pasted1')];

      (clipboardUtils.pasteElements as jest.Mock).mockResolvedValue({
        nodes: pastedNodes,
        edges: [],
      });

      const { result } = renderHook(() => useClipboard(defaultProps));

      await act(async () => {
        await result.current.handlePaste();
      });

      expect(mockSetSelectedEdge).toHaveBeenCalledWith(null);
    });

    it('should update multi-selection arrays to pasted element IDs', async () => {
      const pastedNodes = [createMockNode('pasted1'), createMockNode('pasted2')];
      const pastedEdges = [createMockEdge('pasted-e1', 'pasted1', 'pasted2')];

      (clipboardUtils.pasteElements as jest.Mock).mockResolvedValue({
        nodes: pastedNodes,
        edges: pastedEdges,
      });

      const { result } = renderHook(() => useClipboard(defaultProps));

      await act(async () => {
        await result.current.handlePaste();
      });

      expect(mockSetSelectedNodes).toHaveBeenCalledWith(['pasted1', 'pasted2']);
      expect(mockSetSelectedEdges).toHaveBeenCalledWith(['pasted-e1']);
    });

    it('should not update state when paste returns no nodes', async () => {
      (clipboardUtils.pasteElements as jest.Mock).mockResolvedValue({
        nodes: [],
        edges: [],
      });

      const { result } = renderHook(() => useClipboard(defaultProps));

      await act(async () => {
        await result.current.handlePaste();
      });

      expect(mockSetNodes).not.toHaveBeenCalled();
      expect(mockSetEdges).not.toHaveBeenCalled();
      expect(mockSetSelectedNode).not.toHaveBeenCalled();
      expect(mockSetHasUnsavedChanges).not.toHaveBeenCalled();
    });

    it('should not update state when paste returns null', async () => {
      (clipboardUtils.pasteElements as jest.Mock).mockResolvedValue(null);

      const { result } = renderHook(() => useClipboard(defaultProps));

      await act(async () => {
        await result.current.handlePaste();
      });

      expect(mockSetNodes).not.toHaveBeenCalled();
      expect(mockSetEdges).not.toHaveBeenCalled();
      expect(mockSetHasUnsavedChanges).not.toHaveBeenCalled();
    });

    it('should announce paste to screen readers', async () => {
      const mockAnnounce = jest.fn();
      const pastedNodes = [createMockNode('pasted1'), createMockNode('pasted2')];
      const pastedEdges = [createMockEdge('pasted-e1', 'pasted1', 'pasted2')];

      (clipboardUtils.pasteElements as jest.Mock).mockResolvedValue({
        nodes: pastedNodes,
        edges: pastedEdges,
      });

      const { result } = renderHook(() =>
        useClipboard({ ...defaultProps, announce: mockAnnounce })
      );

      await act(async () => {
        await result.current.handlePaste();
      });

      expect(mockAnnounce).toHaveBeenCalledWith('3 elements pasted.');
    });

    it('should not announce when paste returns no nodes', async () => {
      const mockAnnounce = jest.fn();
      (clipboardUtils.pasteElements as jest.Mock).mockResolvedValue({
        nodes: [],
        edges: [],
      });

      const { result } = renderHook(() =>
        useClipboard({ ...defaultProps, announce: mockAnnounce })
      );

      await act(async () => {
        await result.current.handlePaste();
      });

      expect(mockAnnounce).not.toHaveBeenCalled();
    });
  });

  describe('memoization', () => {
    it('should maintain stable function references', () => {
      const { result, rerender } = renderHook(() => useClipboard(defaultProps));

      const handleCopy1 = result.current.handleCopy;
      const handlePaste1 = result.current.handlePaste;

      rerender();

      // Functions should be memoized and stable
      expect(result.current.handleCopy).toBe(handleCopy1);
      expect(result.current.handlePaste).toBe(handlePaste1);
    });

    it('should update handleCopy when selectedNode changes', () => {
      const { result, rerender } = renderHook(
        (props) => useClipboard(props),
        { initialProps: defaultProps }
      );

      const handleCopy1 = result.current.handleCopy;

      rerender({
        ...defaultProps,
        selectedNode: createMockNode('node1'),
      });

      expect(result.current.handleCopy).not.toBe(handleCopy1);
    });

    it('should update handleCopy when selectedEdge changes', () => {
      const { result, rerender } = renderHook(
        (props) => useClipboard(props),
        { initialProps: defaultProps }
      );

      const handleCopy1 = result.current.handleCopy;

      rerender({
        ...defaultProps,
        selectedEdge: createMockEdge('edge1', 'n1', 'n2'),
      });

      expect(result.current.handleCopy).not.toBe(handleCopy1);
    });

    it('should update handleCopy when selectedNodeIds changes', () => {
      const { result, rerender } = renderHook(
        (props) => useClipboard(props),
        { initialProps: defaultProps }
      );

      const handleCopy1 = result.current.handleCopy;

      rerender({
        ...defaultProps,
        selectedNodeIds: ['node1', 'node2'],
      });

      expect(result.current.handleCopy).not.toBe(handleCopy1);
    });
  });

  describe('return value structure', () => {
    it('should return all expected properties', () => {
      const { result } = renderHook(() => useClipboard(defaultProps));

      expect(result.current).toHaveProperty('handleCopy');
      expect(result.current).toHaveProperty('handlePaste');
      expect(result.current).toHaveProperty('canCopy');
      expect(result.current).toHaveProperty('canPaste');

      expect(typeof result.current.handleCopy).toBe('function');
      expect(typeof result.current.handlePaste).toBe('function');
      expect(typeof result.current.canCopy).toBe('boolean');
      expect(typeof result.current.canPaste).toBe('boolean');
    });
  });
});
