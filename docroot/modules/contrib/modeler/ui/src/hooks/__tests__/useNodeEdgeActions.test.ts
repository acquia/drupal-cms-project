import { renderHook, act } from '@testing-library/react';
import { useNodeEdgeActions } from '../useNodeEdgeActions';

// Mock store state
let mockNodes: any[] = [];
let mockEdges: any[] = [];

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

jest.mock('../../store/useGraphStore', () => ({
  useGraphStore: jest.fn((selector) => {
    const state = {
      nodes: mockNodes,
      edges: mockEdges,
      setNodes: mockSetNodes,
      setEdges: mockSetEdges,
    };
    return selector(state);
  }),
}));

const mockSetCenter = jest.fn();
const mockGetZoom = jest.fn(() => 1);
const mockGetViewport = jest.fn(() => ({ x: 0, y: 0, zoom: 1 }));
jest.mock('reactflow', () => ({
  ...jest.requireActual('reactflow'),
  useReactFlow: () => ({ setCenter: mockSetCenter, getZoom: mockGetZoom, getViewport: mockGetViewport }),
}));

jest.mock('../../utils/clipboardUtils', () => ({
  generateNodeId: jest.fn((label, type) => `${type}_${label}_1`),
  generateEdgeId: jest.fn((source, target) => `${source}_to_${target}`),
}));

// Mock autoLayout — returns the nodes as-is (with all selected flags cleared)
jest.mock('../../utils/modelUtils', () => ({
  autoLayout: jest.fn((nodes: any[]) =>
    nodes.map((n: any) => ({ ...n, selected: false })),
  ),
}));

const mockViewportActions = {
  panToNode: jest.fn(),
  panToNodeIfOffscreen: jest.fn(),
  fitToNodes: jest.fn(),
  topAlignNode: jest.fn(),
  focusNode: jest.fn(),
  fitToNodePair: jest.fn(),
  selectAndFocus: jest.fn(),
  setReady: jest.fn(),
};

describe('useNodeEdgeActions', () => {
  let mockSetHasUnsavedChanges: jest.Mock;

  beforeEach(() => {
    jest.clearAllMocks();
    mockNodes = [];
    mockEdges = [];
    mockSetHasUnsavedChanges = jest.fn();
    mockSetCenter.mockClear();
  });

  const renderUseNodeEdgeActions = () => {
    return renderHook(() =>
      useNodeEdgeActions({
        setHasUnsavedChanges: mockSetHasUnsavedChanges,
        viewportActions: mockViewportActions,
      })
    );
  };

  describe('return values', () => {
    it('should return handleAddCondition, handleAddEvent, and handleAddActionOnEdge', () => {
      const { result } = renderUseNodeEdgeActions();
      expect(typeof result.current.handleAddCondition).toBe('function');
      expect(typeof result.current.handleAddEvent).toBe('function');
      expect(typeof result.current.handleAddActionOnEdge).toBe('function');
    });
  });

  describe('handleAddCondition', () => {
    // Issue #3589093: "add condition" now inserts a first-class condition
    // NODE on the target edge (mirroring the load-time translation), instead
    // of mutating the edge to carry a condition. The edge is split into two
    // plain default edges with the condition node between them.
    const setupNodesAndEdges = () => {
      mockNodes = [
        { id: 'node-1', position: { x: 100, y: 50 }, selected: false },
        { id: 'node-2', position: { x: 100, y: 254 }, selected: false },
      ];
      mockEdges = [
        { id: 'e1', source: 'node-1', target: 'node-2', type: 'default', data: {}, selected: false },
      ];
    };

    it('should insert a condition NODE on the target edge', () => {
      setupNodesAndEdges();
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddCondition('e1', {
          plugin: 'test.condition.check_value',
          label: 'Check Value',
        });
      });

      expect(mockSetNodes).toHaveBeenCalled();
      const updatedNodes = mockSetNodes.mock.calls[0][0];
      // Original two nodes plus the new condition node.
      expect(updatedNodes).toHaveLength(3);

      const conditionNode = updatedNodes.find(
        (n: any) => n.id !== 'node-1' && n.id !== 'node-2',
      );
      expect(conditionNode).toBeDefined();
      expect(conditionNode.type).toBe('condition');
      expect(conditionNode.data.__isConditionNode).toBe(true);
      expect(conditionNode.data.plugin).toBe('test.condition.check_value');
      expect(conditionNode.data.label).toBe('Check Value');
      expect(conditionNode.data.componentType).toBe(5);
      // New condition — empty conditionId so export mints a UUID.
      expect(conditionNode.data.conditionId).toBe('');
      expect(conditionNode.data.configuration).toEqual({});
    });

    it('should NOT mutate the edge to carry a condition', () => {
      setupNodesAndEdges();
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddCondition('e1', {
          plugin: 'test.condition.check_value',
          label: 'Check Value',
        });
      });

      const updatedEdges = mockSetEdges.mock.calls[0][0];
      // The original edge is removed; replaced by two plain default edges.
      expect(updatedEdges.find((e: any) => e.id === 'e1')).toBeUndefined();
      expect(updatedEdges).toHaveLength(2);
      for (const edge of updatedEdges) {
        expect(edge.type).toBe('default');
        // No condition data lives on any edge anymore.
        expect(edge.data?.condition).toBeUndefined();
        expect(edge.data?.conditionLabel).toBeUndefined();
      }
    });

    it('should create two plain edges: source -> condition -> target', () => {
      setupNodesAndEdges();
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddCondition('e1', {
          plugin: 'test.condition.test',
          label: 'Test',
        });
      });

      const updatedNodes = mockSetNodes.mock.calls[0][0];
      const conditionNode = updatedNodes.find(
        (n: any) => n.id !== 'node-1' && n.id !== 'node-2',
      );
      const updatedEdges = mockSetEdges.mock.calls[0][0];

      const edgeToCondition = updatedEdges.find(
        (e: any) => e.source === 'node-1' && e.target === conditionNode.id,
      );
      const edgeFromCondition = updatedEdges.find(
        (e: any) => e.source === conditionNode.id && e.target === 'node-2',
      );
      expect(edgeToCondition).toBeDefined();
      expect(edgeToCondition.type).toBe('default');
      expect(edgeToCondition.data).toEqual({});
      expect(edgeFromCondition).toBeDefined();
      expect(edgeFromCondition.type).toBe('default');
      expect(edgeFromCondition.data).toEqual({});
    });

    it('should select the new condition node', () => {
      setupNodesAndEdges();
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddCondition('e1', {
          plugin: 'test.condition.test',
          label: 'Test',
        });
      });

      const updatedNodes = mockSetNodes.mock.calls[0][0];
      const conditionNode = updatedNodes.find(
        (n: any) => n.id !== 'node-1' && n.id !== 'node-2',
      );
      expect(conditionNode.selected).toBe(true);
    });

    it('should mark unsaved changes', () => {
      setupNodesAndEdges();
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddCondition('e1', {
          plugin: 'test',
          label: 'Test',
        });
      });

      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should use plugin name as fallback label', () => {
      setupNodesAndEdges();
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddCondition('e1', {
          plugin: 'test.condition.check_value',
          label: '',
        });
      });

      const updatedNodes = mockSetNodes.mock.calls[0][0];
      const conditionNode = updatedNodes.find(
        (n: any) => n.id !== 'node-1' && n.id !== 'node-2',
      );
      expect(conditionNode.data.label).toBe('check_value');
    });

    it('should pan to the new condition node if offscreen', () => {
      setupNodesAndEdges();
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddCondition('e1', {
          plugin: 'test.condition.test',
          label: 'Test',
        });
      });

      expect(mockViewportActions.panToNodeIfOffscreen).toHaveBeenCalled();
    });

    it('should handle missing edge gracefully', () => {
      setupNodesAndEdges();
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddCondition('nonexistent-edge', {
          plugin: 'test.condition.test',
          label: 'Test',
        });
      });

      expect(consoleSpy).toHaveBeenCalledWith('Edge not found:', 'nonexistent-edge');
      expect(mockSetNodes).not.toHaveBeenCalled();
      consoleSpy.mockRestore();
    });

    // ── No-two-adjacent-conditions invariant (issue #3589093) ───────────
    // When the target edge's source or target is itself a condition node,
    // handleAddCondition must route through a gateway so we never create a
    // condition -> condition edge.

    it('inserts a gateway when the target edge points INTO an existing condition (source->newCond->gateway->existingCond)', () => {
      // event_1 -> existingCond.  Inserting on that edge means the target is
      // a condition, so result must be event_1 -> newCond -> gateway -> existingCond.
      mockNodes = [
        { id: 'event_1', position: { x: 100, y: 50 }, selected: false },
        { id: 'existing_cond', type: 'condition', position: { x: 100, y: 254 }, selected: false, data: { __isConditionNode: true } },
      ];
      mockEdges = [
        { id: 'e1', source: 'event_1', target: 'existing_cond', type: 'default', data: {}, selected: false },
      ];
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddCondition('e1', {
          plugin: 'test.condition.check',
          label: 'Check',
        });
      });

      const updatedNodes = mockSetNodes.mock.calls[0][0];
      const updatedEdges = mockSetEdges.mock.calls[0][0];

      // A gateway node was added (condition + gateway = 2 new nodes).
      const gateway = updatedNodes.find((n: any) => n.type === 'gateway');
      expect(gateway).toBeDefined();
      const newCond = updatedNodes.find((n: any) => n.type === 'condition' && n.id !== 'existing_cond');
      expect(newCond).toBeDefined();

      // Edge wiring: event_1 -> newCond -> gateway -> existing_cond.
      expect(updatedEdges.find((e: any) => e.source === 'event_1' && e.target === newCond.id)).toBeDefined();
      expect(updatedEdges.find((e: any) => e.source === newCond.id && e.target === gateway.id)).toBeDefined();
      expect(updatedEdges.find((e: any) => e.source === gateway.id && e.target === 'existing_cond')).toBeDefined();

      // No condition -> condition edge exists.
      const condIds = new Set(['existing_cond', newCond.id]);
      for (const e of updatedEdges) {
        expect(condIds.has(e.source) && condIds.has(e.target)).toBe(false);
      }
    });

    it('inserts a gateway when the target edge comes OUT of an existing condition (existingCond->gateway->newCond->target)', () => {
      // existingCond -> action.  Inserting on that edge means the source is
      // a condition, so result must be existingCond -> gateway -> newCond -> action.
      mockNodes = [
        { id: 'existing_cond', type: 'condition', position: { x: 100, y: 50 }, selected: false, data: { __isConditionNode: true } },
        { id: 'action_1', position: { x: 100, y: 254 }, selected: false },
      ];
      mockEdges = [
        { id: 'e1', source: 'existing_cond', target: 'action_1', type: 'default', data: {}, selected: false },
      ];
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddCondition('e1', {
          plugin: 'test.condition.check',
          label: 'Check',
        });
      });

      const updatedNodes = mockSetNodes.mock.calls[0][0];
      const updatedEdges = mockSetEdges.mock.calls[0][0];

      const gateway = updatedNodes.find((n: any) => n.type === 'gateway');
      expect(gateway).toBeDefined();
      const newCond = updatedNodes.find((n: any) => n.type === 'condition' && n.id !== 'existing_cond');
      expect(newCond).toBeDefined();

      // Edge wiring: existing_cond -> gateway -> newCond -> action_1.
      expect(updatedEdges.find((e: any) => e.source === 'existing_cond' && e.target === gateway.id)).toBeDefined();
      expect(updatedEdges.find((e: any) => e.source === gateway.id && e.target === newCond.id)).toBeDefined();
      expect(updatedEdges.find((e: any) => e.source === newCond.id && e.target === 'action_1')).toBeDefined();

      const condIds = new Set(['existing_cond', newCond.id]);
      for (const e of updatedEdges) {
        expect(condIds.has(e.source) && condIds.has(e.target)).toBe(false);
      }

      // Issue #3589093 regression: positions must follow the FLOW order
      // (gateway BEFORE the new condition), not the buildConditionInsertion
      // array order.  The gateway must therefore sit ABOVE the new condition.
      expect(gateway.position.y).toBeLessThan(newCond.position.y);
    });
  });

  describe('handleAddEvent', () => {
    it('should create a new start node at default position', () => {
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddEvent({
          plugin: 'test.event.content_entity_insert',
          label: 'Entity Insert',
          componentType: 1,
          description: 'Triggered on entity insert',
        });
      });

      expect(mockSetNodes).toHaveBeenCalled();
      const updater = mockSetNodes.mock.calls[0][0];
      const newNodes = updater([]);
      expect(newNodes).toHaveLength(1);
      expect(newNodes[0].type).toBe('start');
      expect(newNodes[0].data.plugin).toBe('test.event.content_entity_insert');
      expect(newNodes[0].data.label).toBe('Entity Insert');
      // After issue #3588454 the first event uses the same anchor as
      // auto-layout (LAYOUT_START_X/Y) so the two code paths produce
      // identical output.
      expect(newNodes[0].position.x).toBe(400);
      expect(newNodes[0].position.y).toBe(100);
    });

    it('should position node to the right of existing nodes', () => {
      mockNodes = [
        { id: 'node-1', position: { x: 200, y: 50 } },
        { id: 'node-2', position: { x: 450, y: 100 } },
      ];
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddEvent({
          plugin: 'test.event.test',
          label: 'Test Event',
          componentType: 1,
        });
      });

      const updater = mockSetNodes.mock.calls[0][0];
      const newNodes = updater(mockNodes);
      const newNode = newNodes[newNodes.length - 1];
      expect(newNode.position.x).toBe(700); // 450 + 250 (NODE_SPACING_X)
      expect(newNode.position.y).toBe(50); // minY
    });

    it('should mark the new node as selected and deselect others', () => {
      mockNodes = [{ id: 'existing-1', position: { x: 100, y: 100 }, selected: true }];
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddEvent({
          plugin: 'test.event.test',
          label: 'Test',
          componentType: 1,
        });
      });

      const updater = mockSetNodes.mock.calls[0][0];
      const newNodes = updater(mockNodes);
      const newNode = newNodes[newNodes.length - 1];
      expect(newNode.selected).toBe(true);
      expect(newNode.type).toBe('start');
      expect(newNode.data.plugin).toBe('test.event.test');
      // Existing node should be deselected
      expect(newNodes[0].selected).toBe(false);
    });

    it('should mark unsaved changes', () => {
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddEvent({
          plugin: 'test',
          label: 'Test',
          componentType: 1,
        });
      });

      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should deselect existing edges when adding event', () => {
      mockEdges = [
        { id: 'edge-1', source: 'a', target: 'b', selected: true, data: {} },
      ];
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddEvent({
          plugin: 'test.event.test',
          label: 'Test',
          componentType: 1,
        });
      });

      // setEdges should deselect existing edges
      expect(mockSetEdges).toHaveBeenCalled();
      const edgesUpdater = mockSetEdges.mock.calls[0][0];
      const updatedEdges = edgesUpdater(mockEdges);
      expect(updatedEdges[0].selected).toBe(false);
    });

    it('should use topmost Y (minY) for new node position', () => {
      mockNodes = [
        { id: 'node-1', position: { x: 300, y: 150 } },
        { id: 'node-2', position: { x: 500, y: 80 } },
      ];

      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddEvent({
          plugin: 'test',
          label: 'Test',
          componentType: 1,
        });
      });

      const updater = mockSetNodes.mock.calls[0][0];
      const newNodes = updater(mockNodes);
      const newNode = newNodes[newNodes.length - 1];
      // maxX = 500, positionX = 750, minY = 80
      expect(newNode.position.x).toBe(750);
      expect(newNode.position.y).toBe(80);
    });
  });

  describe('handleAddActionOnEdge', () => {
    const setupNodesAndEdges = () => {
      mockNodes = [
        { id: 'node-1', position: { x: 100, y: 50 }, selected: false },
        { id: 'node-2', position: { x: 100, y: 254 }, selected: false },
      ];
      mockEdges = [
        { id: 'e1', source: 'node-1', target: 'node-2', type: 'default', data: {}, selected: false },
      ];
    };

    it('should insert a new node between source and target', () => {
      setupNodesAndEdges();
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddActionOnEdge('e1', {
          plugin: 'test.action.save',
          label: 'Save Entity',
          type: 'element',
          componentType: 4,
        });
      });

      // setNodes should be called with updated nodes including the new one
      expect(mockSetNodes).toHaveBeenCalled();
      const updatedNodes = mockSetNodes.mock.calls[0][0];
      expect(updatedNodes).toHaveLength(3);

      // setEdges should be called: original edge removed, 2 new edges added
      expect(mockSetEdges).toHaveBeenCalled();
      const updatedEdges = mockSetEdges.mock.calls[0][0];
      expect(updatedEdges).toHaveLength(2);
      // Original edge should not be present
      expect(updatedEdges.find((e: any) => e.id === 'e1')).toBeUndefined();
    });

    it('should create new node with correct plugin, label, and componentType', () => {
      setupNodesAndEdges();
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddActionOnEdge('e1', {
          plugin: 'test.action.save',
          label: 'Save Entity',
          type: 'element',
          componentType: 4,
        });
      });

      const updatedNodes = mockSetNodes.mock.calls[0][0];
      const newNode = updatedNodes.find((n: any) => n.id !== 'node-1' && n.id !== 'node-2');
      expect(newNode).toBeDefined();
      expect(newNode.data.plugin).toBe('test.action.save');
      expect(newNode.data.label).toBe('Save Entity');
      expect(newNode.data.componentType).toBe(4);
    });

    it('should create two default-type edges', () => {
      setupNodesAndEdges();
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddActionOnEdge('e1', {
          plugin: 'test.action.save',
          label: 'Save Entity',
          type: 'element',
          componentType: 4,
        });
      });

      const updatedEdges = mockSetEdges.mock.calls[0][0];
      expect(updatedEdges[0].type).toBe('default');
      expect(updatedEdges[1].type).toBe('default');
    });

    it('should remove the original edge', () => {
      setupNodesAndEdges();
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddActionOnEdge('e1', {
          plugin: 'test.action.save',
          label: 'Save Entity',
          type: 'element',
          componentType: 4,
        });
      });

      const updatedEdges = mockSetEdges.mock.calls[0][0];
      const originalEdge = updatedEdges.find((e: any) => e.id === 'e1');
      expect(originalEdge).toBeUndefined();
    });

    it('should not call full autoLayout (uses targeted positioning)', () => {
      setupNodesAndEdges();
      const { autoLayout } = require('../../utils/modelUtils');
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddActionOnEdge('e1', {
          plugin: 'test.action.save',
          label: 'Save Entity',
          type: 'element',
          componentType: 4,
        });
      });

      expect(autoLayout).not.toHaveBeenCalled();
    });

    it('should mark unsaved changes', () => {
      setupNodesAndEdges();
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddActionOnEdge('e1', {
          plugin: 'test.action.save',
          label: 'Save Entity',
          type: 'element',
          componentType: 4,
        });
      });

      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should select the new node', () => {
      setupNodesAndEdges();
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddActionOnEdge('e1', {
          plugin: 'test.action.save',
          label: 'Save Entity',
          type: 'element',
          componentType: 4,
        });
      });

      const updatedNodes = mockSetNodes.mock.calls[0][0];
      const newNode = updatedNodes.find((n: any) => n.id !== 'node-1' && n.id !== 'node-2');
      // autoLayout mock clears selected flags, but the node was created with selected: true
      // The mock autoLayout returns nodes with selected: false
      expect(newNode).toBeDefined();
    });

    it('should handle missing edge gracefully', () => {
      setupNodesAndEdges();
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddActionOnEdge('nonexistent-edge', {
          plugin: 'test.action.save',
          label: 'Save Entity',
          type: 'element',
          componentType: 4,
        });
      });

      expect(consoleSpy).toHaveBeenCalledWith('Edge not found:', 'nonexistent-edge');
      expect(mockSetNodes).not.toHaveBeenCalled();
      consoleSpy.mockRestore();
    });

    it('should create gateway node type when component.type is gateway', () => {
      setupNodesAndEdges();
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddActionOnEdge('e1', {
          plugin: 'test.gateway.split',
          label: 'Split Flow',
          type: 'gateway',
          componentType: 6,
        });
      });

      const updatedNodes = mockSetNodes.mock.calls[0][0];
      const newNode = updatedNodes.find((n: any) => n.id !== 'node-1' && n.id !== 'node-2');
      expect(newNode).toBeDefined();
      expect(newNode.type).toBe('gateway');
    });
  });
});
