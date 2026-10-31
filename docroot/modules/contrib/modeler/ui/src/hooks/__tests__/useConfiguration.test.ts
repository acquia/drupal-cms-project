import { renderHook, act } from '@testing-library/react';
import { useConfiguration } from '../useConfiguration';

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

const mockSetSelectedNode = jest.fn((node) => {
  mockSelectedNode = node;
});

const mockSetSelectedEdge = jest.fn((edge) => {
  mockSelectedEdge = edge;
});

// The hook reads live state via useGraphStore.getState() (issue #3589109),
// so the mock exposes getState() alongside the selector call signature.
jest.mock('../../store/useGraphStore', () => {
  const getState = () => ({
    nodes: mockNodes,
    edges: mockEdges,
    setNodes: mockSetNodes,
    setEdges: mockSetEdges,
  });
  return {
    useGraphStore: Object.assign(
      jest.fn((selector) => selector(getState())),
      { getState },
    ),
  };
});

jest.mock('../../store/useSelectionStore', () => ({
  useSelectionStore: jest.fn((selector) => {
    const state = {
      selectedNode: mockSelectedNode,
      setSelectedNode: mockSetSelectedNode,
      selectedEdge: mockSelectedEdge,
      setSelectedEdge: mockSetSelectedEdge,
    };
    return selector(state);
  }),
}));

// Mock autoLayout
jest.mock('../../utils/modelUtils', () => ({
  autoLayout: jest.fn((nodes) => nodes.map((n: any) => ({ ...n, position: { x: 0, y: 0 } }))),
}));

describe('useConfiguration', () => {
  let mockSetHasUnsavedChanges: jest.Mock;

  beforeEach(() => {
    jest.clearAllMocks();
    mockSetHasUnsavedChanges = jest.fn();
    mockNodes = [
      {
        id: 'node-1',
        position: { x: 100, y: 100 },
        data: { label: 'Node 1', configuration: { field1: 'value1' } },
      },
      {
        id: 'node-2',
        position: { x: 300, y: 100 },
        data: { label: 'Node 2' },
      },
    ];
    mockEdges = [
      {
        id: 'edge-1',
        source: 'node-1',
        target: 'node-2',
        type: 'default',
        data: {},
      },
    ];
    mockSelectedNode = null;
    mockSelectedEdge = null;
  });

  const renderUseConfiguration = () => {
    return renderHook(() =>
      useConfiguration({
        setHasUnsavedChanges: mockSetHasUnsavedChanges,
      })
    );
  };

  describe('onConfigurationChange', () => {
    it('should update node configuration', () => {
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onConfigurationChange('node-1', { field2: 'value2' });
      });

      expect(mockSetNodes).toHaveBeenCalled();
      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should merge new configuration with existing', () => {
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onConfigurationChange('node-1', { field2: 'value2' });
      });

      const updatedNodes = mockNodes;
      const node1 = updatedNodes.find((n: any) => n.id === 'node-1');
      expect(node1.data.configuration.field1).toBe('value1');
      expect(node1.data.configuration.field2).toBe('value2');
    });

    it('should update label when _componentLabel is provided', () => {
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onConfigurationChange('node-1', { _componentLabel: 'New Label' });
      });

      const updatedNodes = mockNodes;
      const node1 = updatedNodes.find((n: any) => n.id === 'node-1');
      expect(node1.data.label).toBe('New Label');
    });

    it('should not fail for non-existent node', () => {
      const { result } = renderUseConfiguration();

      expect(() => {
        act(() => {
          result.current.onConfigurationChange('non-existent', { field: 'value' });
        });
      }).not.toThrow();
    });

    it('should handle node with undefined data', () => {
      mockNodes = [
        { id: 'node-1', position: { x: 0, y: 0 } },
      ];
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onConfigurationChange('node-1', { field: 'value' });
      });

      const updatedNodes = mockNodes;
      const node1 = updatedNodes.find((n: any) => n.id === 'node-1');
      expect(node1.data.configuration.field).toBe('value');
    });
  });

  describe('onNodeUpdate', () => {
    it('should update node data', () => {
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onNodeUpdate('node-1', { description: 'custom value' });
      });

      expect(mockSetNodes).toHaveBeenCalled();
      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should merge with existing data', () => {
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onNodeUpdate('node-1', { description: 'new value' });
      });

      const updatedNodes = mockNodes;
      const node1 = updatedNodes.find((n: Record<string, unknown>) => n.id === 'node-1');
      expect((node1 as any).data.label).toBe('Node 1');
      expect((node1 as any).data.description).toBe('new value');
    });
  });

  describe('onEdgeUpdate', () => {
    it('should update edge data', () => {
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onEdgeUpdate('edge-1', { annotation: 'custom value' });
      });

      expect(mockSetEdges).toHaveBeenCalled();
      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should merge with existing data', () => {
      mockEdges = [
        {
          id: 'edge-1',
          source: 'node-1',
          target: 'node-2',
          type: 'default',
          data: { existingField: 'existing' },
        },
      ];
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onEdgeUpdate('edge-1', { annotation: 'new' });
      });

      const updatedEdges = mockEdges;
      const edge1 = updatedEdges.find((e: Record<string, unknown>) => e.id === 'edge-1');
      expect((edge1 as any).data.existingField).toBe('existing');
      expect((edge1 as any).data.annotation).toBe('new');
    });

    it('should update edge type based on merged data', () => {
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onEdgeUpdate('edge-1', { condition: 'some_condition' });
      });

      const updatedEdges = mockEdges;
      const edge1 = updatedEdges.find((e: any) => e.id === 'edge-1');
      expect(edge1.type).toBe('condition');
    });
  });

  describe('onReconnectEdge (issue #3585553)', () => {
    it('should update top-level source/sourceHandle and save history', () => {
      const saveHistory = jest.fn();
      const { result } = renderHook(() =>
        useConfiguration({ setHasUnsavedChanges: mockSetHasUnsavedChanges, saveHistory }),
      );

      act(() => {
        result.current.onReconnectEdge('edge-1', { source: 'node-new', sourceHandle: 'output' });
      });

      expect(saveHistory).toHaveBeenCalled();
      const edge1 = mockEdges.find((e: any) => e.id === 'edge-1');
      expect(edge1.source).toBe('node-new');
      expect(edge1.sourceHandle).toBe('output');
      // Target is untouched.
      expect(edge1.target).toBe('node-2');
      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should update top-level target/targetHandle only', () => {
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onReconnectEdge('edge-1', { target: 'node-new', targetHandle: 'input' });
      });

      const edge1 = mockEdges.find((e: any) => e.id === 'edge-1');
      expect(edge1.target).toBe('node-new');
      expect(edge1.targetHandle).toBe('input');
      expect(edge1.source).toBe('node-1');
    });

    it('should not change edge data or type on a pure reconnect', () => {
      mockEdges = [
        { id: 'edge-1', source: 'node-1', target: 'node-2', type: 'default', data: { annotation: 'keep' } },
      ];
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onReconnectEdge('edge-1', { target: 'node-3' });
      });

      const edge1 = mockEdges.find((e: any) => e.id === 'edge-1');
      expect(edge1.type).toBe('default');
      expect(edge1.data.annotation).toBe('keep');
    });

    it('should not touch other edges', () => {
      mockEdges = [
        { id: 'edge-1', source: 'node-1', target: 'node-2', type: 'default', data: {} },
        { id: 'edge-2', source: 'node-2', target: 'node-3', type: 'default', data: {} },
      ];
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onReconnectEdge('edge-1', { target: 'node-3' });
      });

      const edge2 = mockEdges.find((e: any) => e.id === 'edge-2');
      expect(edge2.source).toBe('node-2');
      expect(edge2.target).toBe('node-3');
    });
  });

  describe('handleAutoLayout', () => {
    it('should call autoLayout with nodes and edges', () => {
      const { autoLayout } = require('../../utils/modelUtils');
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.handleAutoLayout();
      });

      // Verify autoLayout was called with arrays of nodes and edges
      expect(autoLayout).toHaveBeenCalled();
      const callArgs = autoLayout.mock.calls[0];
      expect(Array.isArray(callArgs[0])).toBe(true);
      expect(Array.isArray(callArgs[1])).toBe(true);
      expect(callArgs[0].length).toBe(2); // 2 nodes
      expect(callArgs[1].length).toBe(1); // 1 edge
    });

    it('should update nodes with layouted result', () => {
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.handleAutoLayout();
      });

      expect(mockSetNodes).toHaveBeenCalled();
    });

    it('should mark as unsaved changes', () => {
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.handleAutoLayout();
      });

      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should not update when autoLayout returns null', () => {
      const { autoLayout } = require('../../utils/modelUtils');
      autoLayout.mockReturnValueOnce(null);

      const { result } = renderUseConfiguration();

      act(() => {
        result.current.handleAutoLayout();
      });

      expect(mockSetNodes).not.toHaveBeenCalled();
    });
  });

  describe('selected node is not written by configuration changes', () => {
    // Regression guard for issue #3589111. onConfigurationChange used to
    // re-sync the selection store from a render-time snapshot behind a 10ms
    // setTimeout, which pushed the PRE-update node back into the property
    // panel. Refreshing the selected node object belongs to useSelectionSync
    // (see useSelectionSync.test.ts), which reads fresh state in an effect
    // keyed on the nodes array. This test fails if that duplicate path is
    // ever reinstated.
    it('should not write to the selection store when the selected node changes', async () => {
      mockSelectedNode = { id: 'node-1', position: { x: 100, y: 100 }, data: { label: 'Node 1' } };
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onConfigurationChange('node-1', { field: 'value' });
      });

      // Wait past the window the removed setTimeout used to fire in.
      await act(async () => {
        await new Promise(resolve => setTimeout(resolve, 20));
      });

      expect(mockSetNodes).toHaveBeenCalled();
      expect(mockSetSelectedNode).not.toHaveBeenCalled();
    });
  });

  describe('edge type determination with both condition and annotation', () => {
    it('should stay default type when only annotation exists via onEdgeUpdate', () => {
      mockEdges = [
        {
          id: 'edge-1',
          source: 'node-1',
          target: 'node-2',
          type: 'default',
          data: {},
        },
      ];
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onEdgeUpdate('edge-1', { annotation: 'Just a note' });
      });

      const updatedEdges = mockEdges;
      const edge1 = updatedEdges.find((e: any) => e.id === 'edge-1');
      // Annotations belong to conditions; an edge without a condition stays 'default'
      expect(edge1.type).toBe('default');
    });

    it('should set type to condition when both condition and annotation exist via onEdgeUpdate', () => {
      mockEdges = [
        {
          id: 'edge-1',
          source: 'node-1',
          target: 'node-2',
          type: 'default',
          data: { annotation: 'A note' },
        },
      ];
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onEdgeUpdate('edge-1', { condition: 'some_condition' });
      });

      const updatedEdges = mockEdges;
      const edge1 = updatedEdges.find((e: any) => e.id === 'edge-1');
      expect(edge1.type).toBe('condition');
    });
  });
});
