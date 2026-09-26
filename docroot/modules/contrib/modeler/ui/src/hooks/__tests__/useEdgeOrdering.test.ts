import { renderHook, act } from '@testing-library/react';
import { useEdgeOrdering } from '../useEdgeOrdering';
import { Edge, Node } from 'reactflow';

describe('useEdgeOrdering', () => {
  const createMockNode = (id: string, x: number, y: number): Node => ({
    id,
    position: { x, y },
    data: {},
    width: 200,
    height: 100,
  });

  const createMockEdge = (id: string, source: string, target: string): Edge => ({
    id,
    source,
    target,
    data: {},
  });

  const defaultNodes: Node[] = [
    createMockNode('node1', 0, 0),
    createMockNode('node2', 300, 0),
    createMockNode('node3', 300, 200),
    createMockNode('node4', 300, 400),
  ];

  const defaultEdges: Edge[] = [
    createMockEdge('node1_to_node2', 'node1', 'node2'),
    createMockEdge('node1_to_node3', 'node1', 'node3'),
    createMockEdge('node1_to_node4', 'node1', 'node4'),
  ];

  describe('getEdgeOrderInfo', () => {
    it('returns null for single edge from source', () => {
      const singleEdge = [createMockEdge('node1_to_node2', 'node1', 'node2')];
      const setEdges = jest.fn();
      const setHasUnsavedChanges = jest.fn();

      const { result } = renderHook(() =>
        useEdgeOrdering({
          edges: singleEdge,
          nodes: defaultNodes,
          setEdges,
          setHasUnsavedChanges,
        })
      );

      const orderInfo = result.current.getEdgeOrderInfo(singleEdge[0], singleEdge);
      expect(orderInfo).toBeNull();
    });

    it('returns correct order info for multiple edges from same source', () => {
      const setEdges = jest.fn();
      const setHasUnsavedChanges = jest.fn();

      const { result } = renderHook(() =>
        useEdgeOrdering({
          edges: defaultEdges,
          nodes: defaultNodes,
          setEdges,
          setHasUnsavedChanges,
        })
      );

      const orderInfo1 = result.current.getEdgeOrderInfo(defaultEdges[0], defaultEdges);
      const orderInfo2 = result.current.getEdgeOrderInfo(defaultEdges[1], defaultEdges);
      const orderInfo3 = result.current.getEdgeOrderInfo(defaultEdges[2], defaultEdges);

      expect(orderInfo1).toMatchObject({ order: 1, totalEdges: 3 });
      expect(orderInfo2).toMatchObject({ order: 2, totalEdges: 3 });
      expect(orderInfo3).toMatchObject({ order: 3, totalEdges: 3 });
    });



    it('calculates correct path coordinates', () => {
      const setEdges = jest.fn();
      const setHasUnsavedChanges = jest.fn();

      const { result } = renderHook(() =>
        useEdgeOrdering({
          edges: defaultEdges,
          nodes: defaultNodes,
          setEdges,
          setHasUnsavedChanges,
        })
      );

      const orderInfo = result.current.getEdgeOrderInfo(defaultEdges[0], defaultEdges);

      // node1 center: (100, 50), node2 center: (400, 50)
      // Edge center: (250, 50)
      expect(orderInfo?.pathX).toBe(250);
      expect(orderInfo?.pathY).toBe(50);
    });

    it('includes control point offset in path coordinates', () => {
      const edgeWithOffset: Edge[] = [
        {
          ...createMockEdge('node1_to_node2', 'node1', 'node2'),
          data: { controlOffset: { x: 50, y: -30 } },
        },
        createMockEdge('node1_to_node3', 'node1', 'node3'),
      ];
      const setEdges = jest.fn();
      const setHasUnsavedChanges = jest.fn();

      const { result } = renderHook(() =>
        useEdgeOrdering({
          edges: edgeWithOffset,
          nodes: defaultNodes,
          setEdges,
          setHasUnsavedChanges,
        })
      );

      const orderInfo = result.current.getEdgeOrderInfo(edgeWithOffset[0], edgeWithOffset);

      // Edge center (250, 50) + offset (50, -30) = (300, 20)
      expect(orderInfo?.pathX).toBe(300);
      expect(orderInfo?.pathY).toBe(20);
    });

    it('returns fallback coordinates when nodes not found', () => {
      const edgeWithMissingNode: Edge[] = [
        createMockEdge('missing_to_node2', 'missing', 'node2'),
        createMockEdge('missing_to_node3', 'missing', 'node3'),
      ];
      const setEdges = jest.fn();
      const setHasUnsavedChanges = jest.fn();

      const { result } = renderHook(() =>
        useEdgeOrdering({
          edges: edgeWithMissingNode,
          nodes: defaultNodes,
          setEdges,
          setHasUnsavedChanges,
        })
      );

      const orderInfo = result.current.getEdgeOrderInfo(edgeWithMissingNode[0], edgeWithMissingNode);

      expect(orderInfo?.pathX).toBe(100);
      expect(orderInfo?.pathY).toBe(100);
    });
  });

  describe('handleReorderEdge', () => {
    it('swaps edges when reordering by order numbers', () => {
      const setEdges = jest.fn();
      const setHasUnsavedChanges = jest.fn();

      const { result } = renderHook(() =>
        useEdgeOrdering({
          edges: defaultEdges,
          nodes: defaultNodes,
          setEdges,
          setHasUnsavedChanges,
        })
      );

      act(() => {
        result.current.handleReorderEdge('node1', 1, 3);
      });

      expect(setEdges).toHaveBeenCalled();
      expect(setHasUnsavedChanges).toHaveBeenCalledWith(true);

      // Verify the updater function produces correct result
      const updaterFn = setEdges.mock.calls[0][0];
      const newEdges = updaterFn(defaultEdges);
      const reorderedIds = newEdges.map((e: Edge) => e.id);
      expect(reorderedIds).toEqual([
        'node1_to_node4', // was 3, now 1
        'node1_to_node3', // unchanged at 2
        'node1_to_node2', // was 1, now 3
      ]);

      // Verify that data.order is updated to persist the new positions
      expect(newEdges[0].data.order).toBe(0);
      expect(newEdges[1].data.order).toBe(1);
      expect(newEdges[2].data.order).toBe(2);
    });

    it('does nothing for invalid order numbers', () => {
      const setEdges = jest.fn();
      const setHasUnsavedChanges = jest.fn();

      const { result } = renderHook(() =>
        useEdgeOrdering({
          edges: defaultEdges,
          nodes: defaultNodes,
          setEdges,
          setHasUnsavedChanges,
        })
      );

      act(() => {
        result.current.handleReorderEdge('node1', 0, 3); // 0 is invalid (1-based)
      });

      expect(setEdges).not.toHaveBeenCalled();

      act(() => {
        result.current.handleReorderEdge('node1', 1, 5); // 5 is out of range
      });

      expect(setEdges).not.toHaveBeenCalled();
    });

    it('sorts edges by order property when available', () => {
      const edgesWithOrder: Edge[] = [
        { ...createMockEdge('node1_to_node4', 'node1', 'node4'), data: { order: 3 } },
        { ...createMockEdge('node1_to_node2', 'node1', 'node2'), data: { order: 1 } },
        { ...createMockEdge('node1_to_node3', 'node1', 'node3'), data: { order: 2 } },
      ];
      const setEdges = jest.fn();
      const setHasUnsavedChanges = jest.fn();

      const { result } = renderHook(() =>
        useEdgeOrdering({
          edges: edgesWithOrder,
          nodes: defaultNodes,
          setEdges,
          setHasUnsavedChanges,
        })
      );

      // Order info should reflect the order property, not array position
      const orderInfo1 = result.current.getEdgeOrderInfo(edgesWithOrder[1], edgesWithOrder); // node1_to_node2 with order: 1
      expect(orderInfo1).toMatchObject({ order: 1, totalEdges: 3 });
    });
  });

  describe('handleDragStart and handleDragEnd', () => {
    it('sets up drag data transfer on drag start', () => {
      const setEdges = jest.fn();
      const setHasUnsavedChanges = jest.fn();
      const mockDataTransfer = {
        effectAllowed: '',
        setData: jest.fn(),
      };
      const mockEvent = {
        dataTransfer: mockDataTransfer,
      } as unknown as React.DragEvent;

      const { result } = renderHook(() =>
        useEdgeOrdering({
          edges: defaultEdges,
          nodes: defaultNodes,
          setEdges,
          setHasUnsavedChanges,
        })
      );

      act(() => {
        result.current.handleDragStart(mockEvent, 'node1_to_node2');
      });

      expect(mockDataTransfer.effectAllowed).toBe('move');
      expect(mockDataTransfer.setData).toHaveBeenCalledWith('text/plain', 'node1_to_node2');
    });

    it('handleDragEnd is callable without error', () => {
      const setEdges = jest.fn();
      const setHasUnsavedChanges = jest.fn();

      const { result } = renderHook(() =>
        useEdgeOrdering({
          edges: defaultEdges,
          nodes: defaultNodes,
          setEdges,
          setHasUnsavedChanges,
        })
      );

      expect(() => {
        act(() => {
          result.current.handleDragEnd();
        });
      }).not.toThrow();
    });
  });

  describe('handleEdgeOrderDrop', () => {
    it('reorders edges on valid drop', () => {
      const setEdges = jest.fn();
      const setHasUnsavedChanges = jest.fn();
      const mockDataTransfer = {
        effectAllowed: '',
        setData: jest.fn(),
      };
      const mockDragEvent = {
        dataTransfer: mockDataTransfer,
      } as unknown as React.DragEvent;
      const mockDropEvent = {
        preventDefault: jest.fn(),
      } as unknown as React.DragEvent;

      const { result } = renderHook(() =>
        useEdgeOrdering({
          edges: defaultEdges,
          nodes: defaultNodes,
          setEdges,
          setHasUnsavedChanges,
        })
      );

      // Start drag on first edge
      act(() => {
        result.current.handleDragStart(mockDragEvent, 'node1_to_node2');
      });

      // Drop on third edge
      act(() => {
        result.current.handleEdgeOrderDrop(mockDropEvent, 'node1_to_node4');
      });

      expect(mockDropEvent.preventDefault).toHaveBeenCalled();
      expect(setEdges).toHaveBeenCalled();
      expect(setHasUnsavedChanges).toHaveBeenCalledWith(true);

      // Verify the updater produces correct result
      const updaterFn = setEdges.mock.calls[0][0];
      const newEdges = updaterFn(defaultEdges);
      const reorderedIds = newEdges.map((e: Edge) => e.id);
      expect(reorderedIds).toEqual([
        'node1_to_node3',
        'node1_to_node4',
        'node1_to_node2',
      ]);

      // Verify that data.order is updated to persist the new positions
      expect(newEdges[0].data.order).toBe(0);
      expect(newEdges[1].data.order).toBe(1);
      expect(newEdges[2].data.order).toBe(2);
    });

    it('does nothing when dropping on same edge', () => {
      const setEdges = jest.fn();
      const setHasUnsavedChanges = jest.fn();
      const mockDataTransfer = {
        effectAllowed: '',
        setData: jest.fn(),
      };
      const mockDragEvent = {
        dataTransfer: mockDataTransfer,
      } as unknown as React.DragEvent;
      const mockDropEvent = {
        preventDefault: jest.fn(),
      } as unknown as React.DragEvent;

      const { result } = renderHook(() =>
        useEdgeOrdering({
          edges: defaultEdges,
          nodes: defaultNodes,
          setEdges,
          setHasUnsavedChanges,
        })
      );

      act(() => {
        result.current.handleDragStart(mockDragEvent, 'node1_to_node2');
      });

      act(() => {
        result.current.handleEdgeOrderDrop(mockDropEvent, 'node1_to_node2');
      });

      expect(setEdges).not.toHaveBeenCalled();
    });

    it('does nothing when dropping on edge from different source', () => {
      const edgesFromDifferentSources: Edge[] = [
        createMockEdge('node1_to_node2', 'node1', 'node2'),
        createMockEdge('node3_to_node4', 'node3', 'node4'),
      ];
      const setEdges = jest.fn();
      const setHasUnsavedChanges = jest.fn();
      const mockDataTransfer = {
        effectAllowed: '',
        setData: jest.fn(),
      };
      const mockDragEvent = {
        dataTransfer: mockDataTransfer,
      } as unknown as React.DragEvent;
      const mockDropEvent = {
        preventDefault: jest.fn(),
      } as unknown as React.DragEvent;

      const { result } = renderHook(() =>
        useEdgeOrdering({
          edges: edgesFromDifferentSources,
          nodes: defaultNodes,
          setEdges,
          setHasUnsavedChanges,
        })
      );

      act(() => {
        result.current.handleDragStart(mockDragEvent, 'node1_to_node2');
      });

      act(() => {
        result.current.handleEdgeOrderDrop(mockDropEvent, 'node3_to_node4');
      });

      expect(setEdges).not.toHaveBeenCalled();
    });
  });
});
