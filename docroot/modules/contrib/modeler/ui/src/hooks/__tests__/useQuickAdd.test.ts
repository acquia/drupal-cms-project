import { renderHook, act } from '@testing-library/react';
import { useQuickAdd } from '../useQuickAdd';

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
const mockFitView = jest.fn();
jest.mock('reactflow', () => ({
  ...jest.requireActual('reactflow'),
  useReactFlow: () => ({
    setCenter: mockSetCenter,
    fitView: mockFitView,
    getZoom: jest.fn(() => 1),
    getViewport: jest.fn(() => ({ x: 0, y: 0, zoom: 1 })),
  }),
}));

// Mock utility functions
jest.mock('../../utils/clipboardUtils', () => ({
  generateNodeId: jest.fn((label, type) => `${type}_${label.toLowerCase().replace(/\s+/g, '_')}_1`),
  generateEdgeId: jest.fn((sourceId, targetId) => `${sourceId}_to_${targetId}`),
}));

jest.mock('../../utils/modelUtils', () => ({
  autoLayout: jest.fn((nodes) => nodes),
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

describe('useQuickAdd', () => {
  let mockSetHasUnsavedChanges: jest.Mock;

  beforeEach(() => {
    jest.clearAllMocks();
    mockSetHasUnsavedChanges = jest.fn();
    mockSetCenter.mockClear();
    mockFitView.mockClear();
    mockNodes = [
      {
        id: 'event_1',
        type: 'start',
        position: { x: 100, y: 100 },
        width: 200,
        height: 80,
        data: { label: 'Start Event' },
      },
    ];
    mockEdges = [];
  });

  const renderUseQuickAdd = () => {
    return renderHook(() =>
      useQuickAdd({
        setHasUnsavedChanges: mockSetHasUnsavedChanges,
        viewportActions: mockViewportActions,
      })
    );
  };

  describe('addSuccessorNode', () => {
    it('should return addSuccessorNode function', () => {
      const { result } = renderUseQuickAdd();
      
      expect(typeof result.current.addSuccessorNode).toBe('function');
    });

    it('should create a new node when addSuccessorNode is called', () => {
      const { result } = renderUseQuickAdd();
      
      const component = {
        plugin: 'action:save',
        label: 'Save Entity',
      };
      
      act(() => {
        result.current.addSuccessorNode(component, 'event_1');
      });
      
      expect(mockSetNodes).toHaveBeenCalled();
      expect(mockNodes.length).toBe(2);
      expect(mockNodes[1].data.label).toBe('Save Entity');
      expect(mockNodes[1].data.plugin).toBe('action:save');
    });

    it('should create an edge connecting source to new node', () => {
      const { result } = renderUseQuickAdd();
      
      const component = {
        plugin: 'action:save',
        label: 'Save Entity',
      };
      
      act(() => {
        result.current.addSuccessorNode(component, 'event_1');
      });
      
      expect(mockSetEdges).toHaveBeenCalled();
      expect(mockEdges.length).toBe(1);
      expect(mockEdges[0].source).toBe('event_1');
    });

    it('should position new node below source node', () => {
      const { result } = renderUseQuickAdd();
      
      const component = {
        plugin: 'action:save',
        label: 'Save Entity',
      };
      
      act(() => {
        result.current.addSuccessorNode(component, 'event_1');
      });
      
      const newNode = mockNodes[1];
      const sourceNode = mockNodes[0];
      
      // New node should be below source
      expect(newNode.position.y).toBeGreaterThan(sourceNode.position.y);
    });

    it('should set correct node type for gateway components', () => {
      const { result } = renderUseQuickAdd();
      
      const component = {
        plugin: 'gateway:exclusive',
        label: 'Exclusive Gateway',
        type: 'gateway',
      };
      
      act(() => {
        result.current.addSuccessorNode(component, 'event_1');
      });
      
      expect(mockNodes[1].type).toBe('gateway');
    });

    it('should set correct node type for action components', () => {
      const { result } = renderUseQuickAdd();
      
      const component = {
        plugin: 'action:save',
        label: 'Save Entity',
      };
      
      act(() => {
        result.current.addSuccessorNode(component, 'event_1');
      });
      
      expect(mockNodes[1].type).toBe('element');
    });

    it('should mark model as having unsaved changes', () => {
      const { result } = renderUseQuickAdd();
      
      const component = {
        plugin: 'action:save',
        label: 'Save Entity',
      };
      
      act(() => {
        result.current.addSuccessorNode(component, 'event_1');
      });
      
      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should mark the new node as selected and deselect others', () => {
      // Start with a selected node
      mockNodes[0].selected = true;
      const { result } = renderUseQuickAdd();
      
      const component = {
        plugin: 'action:save',
        label: 'Save Entity',
      };
      
      act(() => {
        result.current.addSuccessorNode(component, 'event_1');
      });
      
      // The new node should be selected
      const newNode = mockNodes[mockNodes.length - 1];
      expect(newNode.selected).toBe(true);
      expect(newNode.data.plugin).toBe('action:save');
      expect(newNode.data.label).toBe('Save Entity');
      
      // The original node should be deselected
      expect(mockNodes[0].selected).toBe(false);
    });

    it('should not create node if source node not found', () => {
      const { result } = renderUseQuickAdd();
      
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
      
      const component = {
        plugin: 'action:save',
        label: 'Save Entity',
      };
      
      act(() => {
        result.current.addSuccessorNode(component, 'nonexistent_node');
      });
      
      expect(consoleSpy).toHaveBeenCalled();
      expect(mockSetNodes).not.toHaveBeenCalled();
      
      consoleSpy.mockRestore();
    });

    it('should offset position if node already exists at target position', () => {
      // The source node is at position (100, 100) with height 80
      // New node would be placed at approximately (100, 100 + 80 + 150) = (100, 330)
      // Add a node at that exact position to trigger collision detection
      const expectedY = 100 + 80 + 150; // source.y + source.height + NODE_SPACING_Y (150)
      
      mockNodes.push({
        id: 'existing_node',
        type: 'element',
        position: { x: 100, y: expectedY }, // At the expected new node position
        width: 200,
        height: 80,
        data: { label: 'Existing Node' },
      });

      // Connect nodes so they form a flow (edges needed for flow-aware positioning)
      mockEdges = [{ id: 'edge-1', source: 'event_1', target: 'existing_node', data: {} }];
      
      const { result } = renderUseQuickAdd();
      
      const component = {
        plugin: 'action:save',
        label: 'Save Entity',
      };
      
      act(() => {
        result.current.addSuccessorNode(component, 'event_1');
      });
      
      const newNode = mockNodes[mockNodes.length - 1];
      
      // New node should be offset (moved away from the collision).
      // Flow-aware positioning tries right first (no neighbor flow to block),
      // then down.  Either way it must not sit at the blocked position.
      expect(
        newNode.position.x !== 100 || newNode.position.y !== expectedY
      ).toBe(true);
    });

    it('should use plugin name as label if label not provided', () => {
      const { result } = renderUseQuickAdd();
      
      const component = {
        plugin: 'some_module.some_action',
        label: '', // Empty label to test fallback to plugin ID extraction
      };
      
      act(() => {
        result.current.addSuccessorNode(component, 'event_1');
      });
      
      // Should extract last part of plugin ID when label is empty
      expect(mockNodes[1].data.label).toBe('some_action');
    });

    it('should deselect existing edges when adding successor node', () => {
      // Start with a selected edge
      mockEdges = [{ id: 'edge-0', source: 'event_1', target: 'other', selected: true, data: {} }];
      const { result } = renderUseQuickAdd();
      
      const component = {
        plugin: 'action:save',
        label: 'Save Entity',
      };
      
      act(() => {
        result.current.addSuccessorNode(component, 'event_1');
      });
      
      // The previously selected edge should be deselected
      const previousEdge = mockEdges.find(e => e.id === 'edge-0');
      expect(previousEdge!.selected).toBe(false);
      
      // The new edge should not be selected (only the new node is selected)
      const newEdge = mockEdges.find(e => e.id !== 'edge-0');
      expect(newEdge).toBeDefined();
      expect(newEdge!.selected).toBeUndefined();
    });

    it('should shift neighboring flows right when new node would intrude', () => {
      // Two flows side by side with tight spacing
      mockNodes = [
        // Left flow
        { id: 'event_L',  type: 'start', position: { x: 100, y: 100 }, width: 200, height: 80, data: { label: 'Left Event' } },
        { id: 'action_L', type: 'element', position: { x: 100, y: 330 }, width: 200, height: 100, data: { label: 'Left Action' } },
        // Right flow, very close (starts at x=350)
        { id: 'event_R',  type: 'start', position: { x: 350, y: 100 }, width: 200, height: 80, data: { label: 'Right Event' } },
        { id: 'action_R', type: 'element', position: { x: 350, y: 330 }, width: 200, height: 100, data: { label: 'Right Action' } },
      ];
      mockEdges = [
        { id: 'e1', source: 'event_L', target: 'action_L', data: {} },
        { id: 'e2', source: 'event_R', target: 'action_R', data: {} },
      ];

      // Fill the left flow's column so all downward positions are blocked
      const stepY = 100 + 150; // DEFAULT_HEIGHT + NODE_SPACING_Y
      for (let i = 0; i < 55; i++) {
        const id = `filler_${i}`;
        mockNodes.push({
          id,
          type: 'element',
          position: { x: 100, y: 330 + stepY * (i + 1) },
          width: 200,
          height: 100,
          data: { label: `Filler ${i}` },
        });
        mockEdges.push({ id: `ef_${i}`, source: 'action_L', target: id, data: {} });
      }

      const { result } = renderUseQuickAdd();

      const component = {
        plugin: 'action:save',
        label: 'Save Entity',
      };

      const originalRightEventX = mockNodes.find(n => n.id === 'event_R')!.position.x;

      act(() => {
        result.current.addSuccessorNode(component, 'event_L');
      });

      const rightEventAfter = mockNodes.find(n => n.id === 'event_R')!;
      const rightActionAfter = mockNodes.find(n => n.id === 'action_R')!;

      // The right flow should have been shifted further right
      expect(rightEventAfter.position.x).toBeGreaterThan(originalRightEventX);
      expect(rightActionAfter.position.x).toBeGreaterThan(originalRightEventX);
    });

    it('should include component description and documentationUrl in node data', () => {
      const { result } = renderUseQuickAdd();
      
      const component = {
        plugin: 'action:save',
        label: 'Save Entity',
        description: 'Saves the entity to the database',
        documentationUrl: 'https://docs.example.com/save',
      };
      
      act(() => {
        result.current.addSuccessorNode(component, 'event_1');
      });
      
      expect(mockNodes[1].data.description).toBe('Saves the entity to the database');
      expect(mockNodes[1].data.documentationUrl).toBe('https://docs.example.com/save');
    });
  });

  describe('addConditionWithPlaceholder', () => {
    it('should return addConditionWithPlaceholder function', () => {
      const { result } = renderUseQuickAdd();
      
      expect(typeof result.current.addConditionWithPlaceholder).toBe('function');
    });

    // Conditions are first-class nodes now (issue #3589093).  Condition-first
    // authoring creates a chain: source -> conditionNode -> placeholder, wired
    // with two plain 'default' edges.  No edge of type 'condition' is created.

    it('should create a placeholder node with type placeholder and label Select action...', () => {
      const { result } = renderUseQuickAdd();
      
      const component = {
        plugin: 'condition:entity_is_new',
        label: 'Entity is new',
      };
      
      act(() => {
        result.current.addConditionWithPlaceholder(component, 'event_1');
      });
      
      // The placeholder is the last node added (condition node precedes it).
      const placeholderNode = mockNodes[mockNodes.length - 1];
      expect(placeholderNode.type).toBe('placeholder');
      expect(placeholderNode.data.label).toBe('Select action...');
    });

    it('should create a condition NODE (not edge) with first-class condition data', () => {
      const { result } = renderUseQuickAdd();
      
      const component = {
        plugin: 'condition:entity_is_new',
        label: 'Entity is new',
      };
      
      act(() => {
        result.current.addConditionWithPlaceholder(component, 'event_1');
      });
      
      // A real condition node is created with the canonical first-class shape.
      const conditionNode = mockNodes.find(n => n.type === 'condition');
      expect(conditionNode).toBeDefined();
      expect(conditionNode!.data.__isConditionNode).toBe(true);
      expect(conditionNode!.data.componentType).toBe(5);
      expect(conditionNode!.data.plugin).toBe('condition:entity_is_new');
      expect(conditionNode!.data.label).toBe('Entity is new');
      expect(conditionNode!.data.conditionId).toBe('');
      expect(conditionNode!.data.configuration).toEqual({});
    });

    it('should wire source -> condition -> placeholder with two plain default edges and NO condition edge', () => {
      const { result } = renderUseQuickAdd();
      
      const component = {
        plugin: 'condition:entity_is_new',
        label: 'Entity is new',
      };
      
      act(() => {
        result.current.addConditionWithPlaceholder(component, 'event_1');
      });
      
      const conditionNode = mockNodes.find(n => n.type === 'condition')!;
      const placeholderNode = mockNodes.find(n => n.type === 'placeholder')!;

      // Exactly two new edges, both plain 'default' type — never 'condition'.
      expect(mockEdges.every(e => e.type !== 'condition')).toBe(true);

      const edgeToCondition = mockEdges.find(
        e => e.source === 'event_1' && e.target === conditionNode.id,
      );
      const edgeToPlaceholder = mockEdges.find(
        e => e.source === conditionNode.id && e.target === placeholderNode.id,
      );
      expect(edgeToCondition).toBeDefined();
      expect(edgeToCondition!.type).toBe('default');
      expect(edgeToPlaceholder).toBeDefined();
      expect(edgeToPlaceholder!.type).toBe('default');
    });

    it('should select the condition node (so its config panel opens) and not the placeholder', () => {
      const { result } = renderUseQuickAdd();
      
      const component = {
        plugin: 'condition:entity_is_new',
        label: 'Entity is new',
      };
      
      act(() => {
        result.current.addConditionWithPlaceholder(component, 'event_1');
      });
      
      const conditionNode = mockNodes.find(n => n.type === 'condition')!;
      const placeholderNode = mockNodes.find(n => n.type === 'placeholder')!;
      expect(conditionNode.selected).toBe(true);
      expect(placeholderNode.selected).toBe(false);
    });

    it('should have the placeholder node with selected false', () => {
      const { result } = renderUseQuickAdd();
      
      const component = {
        plugin: 'condition:entity_is_new',
        label: 'Entity is new',
      };
      
      act(() => {
        result.current.addConditionWithPlaceholder(component, 'event_1');
      });
      
      const placeholderNode = mockNodes[mockNodes.length - 1];
      expect(placeholderNode.selected).toBe(false);
    });

    it('should deselect all existing nodes and edges', () => {
      mockNodes[0].selected = true;
      mockEdges = [{ id: 'edge-0', source: 'event_1', target: 'other', selected: true, data: {} }];
      const { result } = renderUseQuickAdd();
      
      const component = {
        plugin: 'condition:entity_is_new',
        label: 'Entity is new',
      };
      
      act(() => {
        result.current.addConditionWithPlaceholder(component, 'event_1');
      });
      
      // Original node should be deselected
      expect(mockNodes[0].selected).toBe(false);
      
      // Previously selected edge should be deselected
      const previousEdge = mockEdges.find(e => e.id === 'edge-0');
      expect(previousEdge!.selected).toBe(false);
    });

    it('should position placeholder node below source node', () => {
      const { result } = renderUseQuickAdd();
      
      const component = {
        plugin: 'condition:entity_is_new',
        label: 'Entity is new',
      };
      
      act(() => {
        result.current.addConditionWithPlaceholder(component, 'event_1');
      });
      
      const placeholderNode = mockNodes[mockNodes.length - 1];
      const sourceNode = mockNodes[0];
      
      // Placeholder node should be below source
      expect(placeholderNode.position.y).toBeGreaterThan(sourceNode.position.y);
    });

    it('should mark model as having unsaved changes', () => {
      const { result } = renderUseQuickAdd();
      
      const component = {
        plugin: 'condition:entity_is_new',
        label: 'Entity is new',
      };
      
      act(() => {
        result.current.addConditionWithPlaceholder(component, 'event_1');
      });
      
      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should call saveHistory when provided', () => {
      const mockSaveHistory = jest.fn();
      const { result } = renderHook(() =>
        useQuickAdd({
          setHasUnsavedChanges: mockSetHasUnsavedChanges,
          saveHistory: mockSaveHistory,
          viewportActions: mockViewportActions,
        })
      );
      
      const component = {
        plugin: 'condition:entity_is_new',
        label: 'Entity is new',
      };
      
      act(() => {
        result.current.addConditionWithPlaceholder(component, 'event_1');
      });
      
      expect(mockSaveHistory).toHaveBeenCalled();
    });

    it('should log error and not create nodes when source node not found', () => {
      const { result } = renderUseQuickAdd();
      
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
      
      const component = {
        plugin: 'condition:entity_is_new',
        label: 'Entity is new',
      };
      
      act(() => {
        result.current.addConditionWithPlaceholder(component, 'nonexistent_node');
      });
      
      expect(consoleSpy).toHaveBeenCalled();
      expect(mockSetNodes).not.toHaveBeenCalled();
      
      consoleSpy.mockRestore();
    });

    it('should use condition label from component on the condition node, falling back to plugin name', () => {
      const { result } = renderUseQuickAdd();
      
      const componentWithLabel = {
        plugin: 'some_module.some_condition',
        label: 'My Custom Condition',
      };
      
      act(() => {
        result.current.addConditionWithPlaceholder(componentWithLabel, 'event_1');
      });
      
      // The label now lives on the first-class condition node, not an edge.
      const conditionNodeWithLabel = mockNodes.find(n => n.type === 'condition')!;
      expect(conditionNodeWithLabel.data.label).toBe('My Custom Condition');
      
      // Reset for the fallback test
      mockNodes = [
        {
          id: 'event_1',
          type: 'start',
          position: { x: 100, y: 100 },
          width: 200,
          height: 80,
          data: { label: 'Start Event' },
        },
      ];
      mockEdges = [];
      
      const { result: result2 } = renderUseQuickAdd();
      
      const componentNoLabel = {
        plugin: 'some_module.some_condition',
        label: '',
      };
      
      act(() => {
        result2.current.addConditionWithPlaceholder(componentNoLabel, 'event_1');
      });
      
      // Empty label falls back to the last segment of the plugin id.
      const conditionNodeNoLabel = mockNodes.find(n => n.type === 'condition')!;
      expect(conditionNodeNoLabel.data.label).toBe('some_condition');
    });

    // ── No-two-adjacent-conditions invariant (issue #3589093) ───────────
    // The placeholder target is always fresh (never a condition), so only
    // the SOURCE side can be adjacent.  When the source is a condition node,
    // a gateway must separate it from the new condition:
    //   source(condition) -> gateway -> conditionNode -> placeholder.
    it('inserts a gateway when the source is a condition node (source->gateway->condition->placeholder)', () => {
      mockNodes = [
        {
          id: 'cond_source',
          type: 'condition',
          position: { x: 100, y: 100 },
          width: 200,
          height: 80,
          data: { label: 'Existing Condition', __isConditionNode: true },
        },
      ];
      mockEdges = [];
      const { result } = renderUseQuickAdd();

      const component = {
        plugin: 'condition:entity_is_new',
        label: 'Entity is new',
      };

      act(() => {
        result.current.addConditionWithPlaceholder(component, 'cond_source');
      });

      const gatewayNode = mockNodes.find(n => n.type === 'gateway');
      const newConditionNode = mockNodes.find(
        n => n.type === 'condition' && n.id !== 'cond_source',
      );
      const placeholderNode = mockNodes.find(n => n.type === 'placeholder');
      expect(gatewayNode).toBeDefined();
      expect(gatewayNode!.data.componentType).toBe(6);
      expect(gatewayNode!.data.plugin).toBe('gateway');
      expect(newConditionNode).toBeDefined();
      expect(placeholderNode).toBeDefined();

      // Edge wiring: cond_source -> gateway -> newCondition -> placeholder.
      expect(mockEdges.find(e => e.source === 'cond_source' && e.target === gatewayNode!.id)).toBeDefined();
      expect(mockEdges.find(e => e.source === gatewayNode!.id && e.target === newConditionNode!.id)).toBeDefined();
      expect(mockEdges.find(e => e.source === newConditionNode!.id && e.target === placeholderNode!.id)).toBeDefined();

      // No condition -> condition edge exists.
      const condIds = new Set(['cond_source', newConditionNode!.id]);
      for (const e of mockEdges) {
        expect(condIds.has(e.source) && condIds.has(e.target)).toBe(false);
      }
    });

    it('does NOT insert a gateway when the source is a normal (non-condition) node', () => {
      // Baseline: with a non-condition source, the original two-node chain is
      // produced with no gateway (source -> condition -> placeholder).
      const { result } = renderUseQuickAdd();

      const component = {
        plugin: 'condition:entity_is_new',
        label: 'Entity is new',
      };

      act(() => {
        result.current.addConditionWithPlaceholder(component, 'event_1');
      });

      expect(mockNodes.find(n => n.type === 'gateway')).toBeUndefined();
    });
  });
});
