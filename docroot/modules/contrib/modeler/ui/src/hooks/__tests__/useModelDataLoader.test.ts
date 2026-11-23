import { renderHook } from '@testing-library/react';
import { useModelDataLoader } from '../useModelDataLoader';

// Mock store state
const mockSetNodes = jest.fn();
const mockSetEdges = jest.fn();
const mockSetComponents = jest.fn();
const mockSetFavoriteComponents = jest.fn();
const mockSetContexts = jest.fn();
const mockSetSelectedContextId = jest.fn();
const mockSetContextConfig = jest.fn();
const mockSetModelData = jest.fn();
const mockSetSelectedNode = jest.fn();
const mockSetDependencies = jest.fn();
const mockSetVisibleStartNodeIds = jest.fn();

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

jest.mock('../../store/useGraphStore', () => ({
  useGraphStore: jest.fn((selector) => {
    const state = {
      setNodes: mockSetNodes,
      setEdges: mockSetEdges,
    };
    return selector(state);
  }),
}));

jest.mock('../../store/useComponentStore', () => ({
  useComponentStore: jest.fn((selector) => {
    const state = {
      setComponents: mockSetComponents,
      setFavoriteComponents: mockSetFavoriteComponents,
    };
    return selector(state);
  }),
}));

jest.mock('../../store/useContextStore', () => ({
  useContextStore: jest.fn((selector) => {
    const state = {
      setContexts: mockSetContexts,
      setDependencies: mockSetDependencies,
      setSelectedContextId: mockSetSelectedContextId,
      setContextConfig: mockSetContextConfig,
    };
    return selector(state);
  }),
}));

jest.mock('../../store/useModelStore', () => ({
  useModelStore: jest.fn((selector) => {
    const state = {
      setModelData: mockSetModelData,
    };
    return selector(state);
  }),
}));

jest.mock('../../store/useSelectionStore', () => ({
  useSelectionStore: jest.fn((selector) => {
    const state = {
      setSelectedNode: mockSetSelectedNode,
    };
    return selector(state);
  }),
}));

jest.mock('../../store/useFilterStore', () => ({
  useFilterStore: jest.fn((selector) => {
    const state = {
      setVisibleStartNodeIds: mockSetVisibleStartNodeIds,
    };
    return selector(state);
  }),
}));

jest.mock('../../store/useLabelStore', () => ({
  useLabelStore: jest.fn((selector) => {
    const state = {
      setComponentLabels: jest.fn(),
    };
    return selector(state);
  }),
}));

// Mock parseModelData (and isConditionReuseEnabled, which the loader calls to
// decide whether to enable condition-reuse grouping — issue #3589093).
jest.mock('../../utils/modelUtils', () => ({
  parseModelData: jest.fn((data) => ({
    modelData: data,
    nodes: data.nodes || [],
    edges: data.edges || [],
  })),
  isConditionReuseEnabled: jest.fn(() => false),
}));

describe('useModelDataLoader', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockSetFavoriteComponents.mockClear();
    // Reset document query selector mock
    document.querySelector = jest.fn(() => null);
  });

  const renderUseModelDataLoader = (settings: any = {}) => {
    const hookResult = renderHook(() =>
      useModelDataLoader({
        settings,
        viewportActions: mockViewportActions,
      })
    );
    return {
      ...hookResult,
      // Convenience rerender that keeps the same settings
      rerender: () => hookResult.rerender(),
    };
  };

  describe('replayData processing', () => {
    it('should return empty array when no replayData in settings', () => {
      const { result } = renderUseModelDataLoader({});
      expect(result.current.replayData).toEqual([]);
    });

    it('should return replayData from settings when provided', () => {
      const replayData = [{ step: 1 }, { step: 2 }];
      const { result } = renderUseModelDataLoader({
        modeler: { replayData },
      });
      expect(result.current.replayData).toEqual(replayData);
    });
  });

  describe('component loading', () => {
    it('should load components from settings.modeler.components', () => {
      const components = [
        { plugin: 'action1', label: 'Action 1', componentType: 4 },
        { plugin: 'event1', label: 'Event 1', componentType: 1 },
      ];

      renderUseModelDataLoader({
        modeler: { components },
      });

      expect(mockSetComponents).toHaveBeenCalled();
      const loadedComponents = mockSetComponents.mock.calls[0][0];
      expect(loadedComponents.length).toBeGreaterThanOrEqual(2);
      // Verify that type was resolved from componentType via the default typeMap
      const action1 = loadedComponents.find((c: any) => c.plugin === 'action1');
      expect(action1.type).toBe('element');
      const event1 = loadedComponents.find((c: any) => c.plugin === 'event1');
      expect(event1.type).toBe('start');
    });

    it('should add gateway component if not present', () => {
      const components = [
        { plugin: 'action1', label: 'Action 1', componentType: 4 },
      ];

      renderUseModelDataLoader({
        modeler: { components },
      });

      const loadedComponents = mockSetComponents.mock.calls[0][0];
      const gateway = loadedComponents.find((c: any) => c.plugin === 'gateway');
      expect(gateway).toBeDefined();
      expect(gateway.type).toBe('gateway');
    });

    it('should not duplicate gateway if already present', () => {
      const components = [
        { plugin: 'gateway', label: 'Gateway', componentType: 6 },
        { plugin: 'action1', label: 'Action 1', componentType: 4 },
      ];

      renderUseModelDataLoader({
        modeler: { components },
      });

      const loadedComponents = mockSetComponents.mock.calls[0][0];
      const gateways = loadedComponents.filter((c: any) => c.plugin === 'gateway');
      expect(gateways.length).toBe(1);
    });

    it('should not add synthetic gateway when backend provides a gateway component with a different plugin id', () => {
      const components = [
        { plugin: 'migrate_plus_field', label: 'Field Mapping', componentType: 6 },
        { plugin: 'action1', label: 'Action 1', componentType: 4 },
      ];

      renderUseModelDataLoader({
        modeler: { components },
      });

      const loadedComponents = mockSetComponents.mock.calls[0][0];
      const gateways = loadedComponents.filter(
        (c: any) => c.type === 'gateway' || c.componentType === 6,
      );
      expect(gateways.length).toBe(1);
      expect(gateways[0].plugin).toBe('migrate_plus_field');
    });

    it('should convert ownerComponents to flat array when components not provided', () => {
      const ownerComponents = {
        element: [
          { plugin: 'action1', label: 'Action 1' },
          { plugin: 'action2', label: 'Action 2' },
        ],
        start: [
          { plugin: 'event1', label: 'Event 1' },
        ],
      };

      renderUseModelDataLoader({
        ownerComponents,
      });

      expect(mockSetComponents).toHaveBeenCalled();
      const loadedComponents = mockSetComponents.mock.calls[0][0];

      // Should include components from both types plus gateway
      const action1 = loadedComponents.find((c: any) => c.plugin === 'action1');
      expect(action1).toBeDefined();
      expect(action1.type).toBe('element');

      const event1 = loadedComponents.find((c: any) => c.plugin === 'event1');
      expect(event1).toBeDefined();
      expect(event1.type).toBe('start');
    });

    it('should add gateway when using ownerComponents', () => {
      const ownerComponents = {
        element: [{ plugin: 'action1', label: 'Action 1' }],
      };

      renderUseModelDataLoader({
        ownerComponents,
      });

      const loadedComponents = mockSetComponents.mock.calls[0][0];
      const gateway = loadedComponents.find((c: any) => c.plugin === 'gateway');
      expect(gateway).toBeDefined();
    });

    it('should set only gateway when no components provided', () => {
      renderUseModelDataLoader({});

      expect(mockSetComponents).toHaveBeenCalled();
      const loadedComponents = mockSetComponents.mock.calls[0][0];
      expect(loadedComponents.length).toBe(1);
      expect(loadedComponents[0].plugin).toBe('gateway');
    });
  });

  describe('favorite components loading', () => {
    it('should load favorite components from settings', () => {
      const favoriteComponents = {
        1: ['test_base:test_custom', 'content_entity:update'],
        4: ['action_message_action', 'test_token_set_value'],
        5: ['test_scalar', 'test_count'],
      };

      renderUseModelDataLoader({
        modeler_api: { favorite_components: favoriteComponents },
      });

      expect(mockSetFavoriteComponents).toHaveBeenCalledWith(favoriteComponents);
    });

    it('should not call setFavoriteComponents when not provided', () => {
      renderUseModelDataLoader({});

      expect(mockSetFavoriteComponents).not.toHaveBeenCalled();
    });
  });

  describe('model data loading', () => {
    it('should parse and load model data from settings', () => {
      const modelData = JSON.stringify({
        id: 'model-1',
        nodes: [
          { id: 'node-1', position: { x: 100, y: 100 }, data: { label: 'Node 1' } },
        ],
        edges: [],
      });

      renderUseModelDataLoader({
        modeler: { modelData },
      });

      expect(mockSetModelData).toHaveBeenCalled();
      expect(mockSetNodes).toHaveBeenCalled();
      expect(mockSetEdges).toHaveBeenCalled();
    });

    it('should ensure all nodes have data property', () => {
      const modelData = JSON.stringify({
        nodes: [
          { id: 'node-1', position: { x: 100, y: 100 } }, // No data property
        ],
        edges: [],
      });

      renderUseModelDataLoader({
        modeler: { modelData },
      });

      const setNodesCall = mockSetNodes.mock.calls[0][0];
      expect(setNodesCall[0].data).toBeDefined();
    });

    it('should handle model data parsing errors gracefully', () => {
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation();

      renderUseModelDataLoader({
        modeler: { modelData: 'invalid json' },
      });

      expect(consoleSpy).toHaveBeenCalledWith(
        'Failed to parse model data from settings:',
        expect.any(Error)
      );
      consoleSpy.mockRestore();
    });

    it('should try loading from hidden field when no modelData in settings', () => {
      const mockHiddenField = {
        value: JSON.stringify({
          nodes: [{ id: 'node-1', position: { x: 0, y: 0 } }],
          edges: [],
        }),
      };
      document.querySelector = jest.fn(() => mockHiddenField);

      renderUseModelDataLoader({});

      expect(document.querySelector).toHaveBeenCalledWith('[name="modeler_api_data"]');
      expect(mockSetNodes).toHaveBeenCalled();
    });

    it('should initialize default model when no data found anywhere', () => {
      document.querySelector = jest.fn(() => null);

      renderUseModelDataLoader({});

      expect(mockSetModelData).toHaveBeenCalled();
      const defaultModel = mockSetModelData.mock.calls[0][0];
      expect(defaultModel.version).toBe('1.0.0');
      expect(defaultModel.metadata.label).toBe('New Workflow');
    });

    it('should use modelId from settings.modeler.modelId', () => {
      const modelData = JSON.stringify({
        id: 'original-id',
        nodes: [],
        edges: [],
        metadata: { label: 'Test Model' },
      });

      renderUseModelDataLoader({
        modeler: { modelData, modelId: 'new-model-id' },
      });

      expect(mockSetModelData).toHaveBeenCalled();
      const loadedModel = mockSetModelData.mock.calls[0][0];
      expect(loadedModel.id).toBe('new-model-id');
    });

    it('should fall back to model data id when modelId not provided', () => {
      const modelData = JSON.stringify({
        id: 'original-id',
        nodes: [],
        edges: [],
        metadata: { label: 'Test Model' },
      });

      renderUseModelDataLoader({
        modeler: { modelData },
      });

      expect(mockSetModelData).toHaveBeenCalled();
      const loadedModel = mockSetModelData.mock.calls[0][0];
      expect(loadedModel.id).toBe('original-id');
    });

    it('should merge metadata from settings.modeler_api.metadata', () => {
      const modelData = JSON.stringify({
        id: 'model-1',
        nodes: [],
        edges: [],
        metadata: { label: 'Original Label', documentation: 'Original Description' },
      });

      renderUseModelDataLoader({
        modeler: { modelData },
        modeler_api: {
          metadata: {
            label: 'API Label',
            tags: ['tag1', 'tag2'],
          },
        },
      });

      expect(mockSetModelData).toHaveBeenCalled();
      const loadedModel = mockSetModelData.mock.calls[0][0];
      // API metadata should override
      expect(loadedModel.metadata.label).toBe('API Label');
      expect(loadedModel.metadata.tags).toEqual(['tag1', 'tag2']);
      // Original values not in API should be preserved (if parseModelData returned them)
    });

    it('should use modelId in default model when no data found', () => {
      document.querySelector = jest.fn(() => null);

      renderUseModelDataLoader({
        modeler: { modelId: 'preset-model-id' },
      });

      expect(mockSetModelData).toHaveBeenCalled();
      const defaultModel = mockSetModelData.mock.calls[0][0];
      expect(defaultModel.id).toBe('preset-model-id');
    });

    it('should use api metadata in default model when no data found', () => {
      document.querySelector = jest.fn(() => null);

      renderUseModelDataLoader({
        modeler_api: {
          metadata: {
            label: 'Custom Default Label',
            documentation: 'Custom Description',
            executable: false,
            tags: ['default-tag'],
          },
        },
      });

      expect(mockSetModelData).toHaveBeenCalled();
      const defaultModel = mockSetModelData.mock.calls[0][0];
      expect(defaultModel.metadata.label).toBe('Custom Default Label');
      expect(defaultModel.metadata.documentation).toBe('Custom Description');
      expect(defaultModel.metadata.executable).toBe(false);
      expect(defaultModel.metadata.tags).toEqual(['default-tag']);
    });
  });

  describe('auto-selection', () => {
    it('should auto-select node when selectComponentId is specified', () => {
      const modelData = JSON.stringify({
        nodes: [
          { id: 'node-1', position: { x: 100, y: 100 }, data: { label: 'Node 1' } },
          { id: 'node-2', position: { x: 200, y: 100 }, data: { label: 'Node 2' } },
        ],
        edges: [],
      });

      renderUseModelDataLoader({
        modeler: { modelData, selectComponentId: 'node-1' },
      });

      // Selection is now done through viewportActions.selectAndFocus()
      expect(mockViewportActions.selectAndFocus).toHaveBeenCalledWith(
        expect.objectContaining({ id: 'node-1' }),
        'focusNode',
      );
    });

    it('should call selectAndFocus with topAlignNode for event nodes', () => {
      const modelData = JSON.stringify({
        nodes: [
          { id: 'event-1', type: 'start', position: { x: 100, y: 100 }, data: { label: 'Event' } },
        ],
        edges: [],
      });

      renderUseModelDataLoader({
        modeler: { modelData, selectComponentId: 'event-1' },
      });

      expect(mockViewportActions.selectAndFocus).toHaveBeenCalledWith(
        expect.objectContaining({ id: 'event-1' }),
        'topAlignNode',
      );
    });

    it('should call selectAndFocus with focusNode for non-event nodes', () => {
      const modelData = JSON.stringify({
        nodes: [
          { id: 'action-1', position: { x: 100, y: 100 }, data: { label: 'Action' } },
        ],
        edges: [],
      });

      renderUseModelDataLoader({
        modeler: { modelData, selectComponentId: 'action-1' },
      });

      expect(mockViewportActions.selectAndFocus).toHaveBeenCalledWith(
        expect.objectContaining({ id: 'action-1' }),
        'focusNode',
      );
    });

    it('should fit view when no selectComponentId but nodes exist', () => {
      const modelData = JSON.stringify({
        nodes: [
          { id: 'node-1', position: { x: 100, y: 100 }, data: { label: 'Node 1' } },
        ],
        edges: [],
      });

      renderUseModelDataLoader({
        modeler: { modelData },
      });

      expect(mockViewportActions.fitToNodes).toHaveBeenCalled();
    });

    it('should not select node when selectComponentId does not match any node', () => {
      const modelData = JSON.stringify({
        nodes: [
          { id: 'node-1', position: { x: 100, y: 100 }, data: { label: 'Node 1' } },
        ],
        edges: [],
      });

      renderUseModelDataLoader({
        modeler: { modelData, selectComponentId: 'non-existent' },
      });

      // Should not call setSelectedNode for non-existent node
      // It will still try to set nodes with selection marker but won't find the node
      expect(mockSetSelectedNode).not.toHaveBeenCalled();
    });

    it('should set visibleStartNodeIds when selecting a start node with multiple start nodes', () => {
      const modelData = JSON.stringify({
        nodes: [
          { id: 'event-1', type: 'start', position: { x: 0, y: 0 }, data: { label: 'Event 1' } },
          { id: 'event-2', type: 'start', position: { x: 200, y: 0 }, data: { label: 'Event 2' } },
        ],
        edges: [],
      });

      renderUseModelDataLoader({
        modeler: { modelData, selectComponentId: 'event-1' },
      });

      expect(mockSetVisibleStartNodeIds).toHaveBeenCalledWith(['event-1']);
    });

    it('should not set visibleStartNodeIds when selecting a non-start node', () => {
      const modelData = JSON.stringify({
        nodes: [
          { id: 'event-1', type: 'start', position: { x: 0, y: 0 }, data: { label: 'Event 1' } },
          { id: 'action-1', position: { x: 200, y: 0 }, data: { label: 'Action 1' } },
        ],
        edges: [],
      });

      renderUseModelDataLoader({
        modeler: { modelData, selectComponentId: 'action-1' },
      });

      expect(mockSetVisibleStartNodeIds).not.toHaveBeenCalled();
    });

    it('should not set visibleStartNodeIds when there is only one start node', () => {
      const modelData = JSON.stringify({
        nodes: [
          { id: 'event-1', type: 'start', position: { x: 0, y: 0 }, data: { label: 'Event 1' } },
          { id: 'action-1', position: { x: 200, y: 0 }, data: { label: 'Action 1' } },
        ],
        edges: [],
      });

      renderUseModelDataLoader({
        modeler: { modelData, selectComponentId: 'event-1' },
      });

      expect(mockSetVisibleStartNodeIds).not.toHaveBeenCalled();
    });
  });

  describe('context config loading', () => {
    it('should load context config from settings', () => {
      const contextConfig = { event: 'entity:insert', type: 'node' };

      renderUseModelDataLoader({
        modeler: { setContextConfig: contextConfig },
      });

      expect(mockSetContextConfig).toHaveBeenCalledWith(contextConfig);
    });

    it('should not call setContextConfig when not provided', () => {
      renderUseModelDataLoader({});

      expect(mockSetContextConfig).not.toHaveBeenCalled();
    });

    it('should not call setContextConfig when value is not an object', () => {
      renderUseModelDataLoader({
        modeler: { setContextConfig: 'invalid' as any },
      });

      expect(mockSetContextConfig).not.toHaveBeenCalled();
    });

    it('should handle empty context config object', () => {
      renderUseModelDataLoader({
        modeler: { setContextConfig: {} },
      });

      expect(mockSetContextConfig).toHaveBeenCalledWith({});
    });

    it('should load context config alongside other settings', () => {
      const contextConfig = { field1: 'value1' };
      const favoriteComponents = { 1: ['plugin_a'] };

      renderUseModelDataLoader({
        modeler: {
          setContextConfig: contextConfig,
        },
        modeler_api: {
          favorite_components: favoriteComponents,
        },
      });

      expect(mockSetContextConfig).toHaveBeenCalledWith(contextConfig);
      expect(mockSetFavoriteComponents).toHaveBeenCalledWith(favoriteComponents);
    });
  });

  describe('context auto-selection', () => {
    // Contexts need at least one plugin matching an available component
    // to pass filtering.  The gateway plugin is always available.
    it('should auto-select context when selectContextId matches an available context', () => {
      renderUseModelDataLoader({
        modeler: {
          selectContextId: 'ctx_1',
          components: [{ plugin: 'action1', label: 'Action 1', componentType: 4 }],
        },
        modeler_api: {
          contexts: [
            { id: 'ctx_1', topic: 'Content', model_owner: 'test_owner', components: { element: { plugins: ['action1'] } } },
            { id: 'ctx_2', topic: 'User', model_owner: 'test_owner', components: { gateway: { plugins: ['gateway'] } } },
          ],
        },
      });

      expect(mockSetContexts).toHaveBeenCalledWith([
        { id: 'ctx_1', topic: 'Content', model_owner: 'test_owner', components: { element: { plugins: ['action1'] } } },
        { id: 'ctx_2', topic: 'User', model_owner: 'test_owner', components: { gateway: { plugins: ['gateway'] } } },
      ]);
      expect(mockSetSelectedContextId).toHaveBeenCalledWith('ctx_1');
    });

    it('should not auto-select context when selectContextId does not match any context', () => {
      renderUseModelDataLoader({
        modeler: {
          selectContextId: 'ctx_nonexistent',
        },
        modeler_api: {
          contexts: [
            { id: 'ctx_1', topic: 'Content', model_owner: 'test_owner', components: { gateway: { plugins: ['gateway'] } } },
          ],
        },
      });

      expect(mockSetContexts).toHaveBeenCalled();
      expect(mockSetSelectedContextId).not.toHaveBeenCalled();
    });

    it('should not auto-select context when selectContextId is not provided', () => {
      renderUseModelDataLoader({
        modeler_api: {
          contexts: [
            { id: 'ctx_1', topic: 'Content', model_owner: 'test_owner', components: { gateway: { plugins: ['gateway'] } } },
          ],
        },
      });

      expect(mockSetContexts).toHaveBeenCalled();
      expect(mockSetSelectedContextId).not.toHaveBeenCalled();
    });

    it('should not auto-select context when no contexts are available', () => {
      renderUseModelDataLoader({
        modeler: {
          selectContextId: 'ctx_1',
        },
      });

      expect(mockSetSelectedContextId).not.toHaveBeenCalled();
    });

    it('should not auto-select context when it gets filtered out due to no matching plugins', () => {
      renderUseModelDataLoader({
        modeler: {
          selectContextId: 'ctx_no_match',
          components: [{ plugin: 'action1', label: 'Action 1', componentType: 4 }],
        },
        modeler_api: {
          contexts: [
            { id: 'ctx_no_match', topic: 'No Match', model_owner: 'test_owner', components: { element: { plugins: ['nonexistent_plugin'] } } },
            { id: 'ctx_valid', topic: 'Valid', model_owner: 'test_owner', components: { element: { plugins: ['action1'] } } },
          ],
        },
      });

      // Only the valid context should be stored
      expect(mockSetContexts).toHaveBeenCalledWith([
        { id: 'ctx_valid', topic: 'Valid', model_owner: 'test_owner', components: { element: { plugins: ['action1'] } } },
      ]);
      // Auto-select should not fire because the target context was filtered out
      expect(mockSetSelectedContextId).not.toHaveBeenCalled();
    });
  });

  describe('context filtering by available plugins', () => {
    it('should filter out contexts that have no matching plugins', () => {
      renderUseModelDataLoader({
        modeler: {
          components: [{ plugin: 'action1', label: 'Action 1', componentType: 4 }],
        },
        modeler_api: {
          contexts: [
            { id: 'ctx_valid', topic: 'Valid', model_owner: 'test_owner', components: { element: { plugins: ['action1'] } } },
            { id: 'ctx_invalid', topic: 'Invalid', model_owner: 'test_owner', components: { element: { plugins: ['nonexistent'] } } },
          ],
        },
      });

      expect(mockSetContexts).toHaveBeenCalledWith([
        { id: 'ctx_valid', topic: 'Valid', model_owner: 'test_owner', components: { element: { plugins: ['action1'] } } },
      ]);
    });

    it('should keep a context if at least one plugin matches', () => {
      renderUseModelDataLoader({
        modeler: {
          components: [{ plugin: 'action1', label: 'Action 1', componentType: 4 }],
        },
        modeler_api: {
          contexts: [
            {
              id: 'ctx_partial',
              topic: 'Partial Match',
              model_owner: 'test_owner',
              components: {
                element: { plugins: ['nonexistent', 'action1'] },
              },
            },
          ],
        },
      });

      expect(mockSetContexts).toHaveBeenCalledWith([
        {
          id: 'ctx_partial',
          topic: 'Partial Match',
          model_owner: 'test_owner',
          components: {
            element: { plugins: ['nonexistent', 'action1'] },
          },
        },
      ]);
    });

    it('should filter out contexts with empty components', () => {
      renderUseModelDataLoader({
        modeler: {
          components: [{ plugin: 'action1', label: 'Action 1', componentType: 4 }],
        },
        modeler_api: {
          contexts: [
            { id: 'ctx_empty', topic: 'Empty', model_owner: 'test_owner', components: {} },
            { id: 'ctx_valid', topic: 'Valid', model_owner: 'test_owner', components: { element: { plugins: ['action1'] } } },
          ],
        },
      });

      expect(mockSetContexts).toHaveBeenCalledWith([
        { id: 'ctx_valid', topic: 'Valid', model_owner: 'test_owner', components: { element: { plugins: ['action1'] } } },
      ]);
    });

    it('should result in empty contexts when none have matching plugins', () => {
      renderUseModelDataLoader({
        modeler: {
          components: [{ plugin: 'action1', label: 'Action 1', componentType: 4 }],
        },
        modeler_api: {
          contexts: [
            { id: 'ctx_1', topic: 'One', model_owner: 'test_owner', components: { element: { plugins: ['nonexistent1'] } } },
            { id: 'ctx_2', topic: 'Two', model_owner: 'test_owner', components: { element: { plugins: ['nonexistent2'] } } },
          ],
        },
      });

      expect(mockSetContexts).toHaveBeenCalledWith([]);
    });

    it('should match plugins across multiple component types in a context', () => {
      renderUseModelDataLoader({
        modeler: {
          components: [
            { plugin: 'event1', label: 'Event 1', componentType: 1 },
            { plugin: 'action1', label: 'Action 1', componentType: 4 },
          ],
        },
        modeler_api: {
          contexts: [
            {
              id: 'ctx_start_only',
              topic: 'Start Only',
              model_owner: 'test_owner',
              components: {
                start: { plugins: ['event1'] },
                element: { plugins: ['nonexistent'] },
              },
            },
          ],
        },
      });

      // Context has event1 in start plugins which matches, so it should be kept
      expect(mockSetContexts).toHaveBeenCalledWith([
        {
          id: 'ctx_start_only',
          topic: 'Start Only',
          model_owner: 'test_owner',
          components: {
            start: { plugins: ['event1'] },
            element: { plugins: ['nonexistent'] },
          },
        },
      ]);
    });

    it('should always match the gateway plugin (always available)', () => {
      // No explicit components provided — only the gateway is injected
      renderUseModelDataLoader({
        modeler_api: {
          contexts: [
            { id: 'ctx_gw', topic: 'Gateway Only', model_owner: 'test_owner', components: { gateway: { plugins: ['gateway'] } } },
            { id: 'ctx_none', topic: 'No Match', model_owner: 'test_owner', components: { element: { plugins: ['nonexistent'] } } },
          ],
        },
      });

      expect(mockSetContexts).toHaveBeenCalledWith([
        { id: 'ctx_gw', topic: 'Gateway Only', model_owner: 'test_owner', components: { gateway: { plugins: ['gateway'] } } },
      ]);
    });
  });

  describe('dependency loading', () => {
    it('should load dependencies from settings.modeler_api.dependencies', () => {
      const dependencies = {
        link: {
          'test_route_match': [{ type: 'start' as const, id: 'kernel:controller' }],
        },
        element: {
          'test_form_add_textfield': [{ type: 'start' as const, id: 'form:form_build' }],
        },
      };

      renderUseModelDataLoader({
        modeler_api: { dependencies },
      });

      expect(mockSetDependencies).toHaveBeenCalledWith(dependencies);
    });

    it('should not call setDependencies when not provided', () => {
      renderUseModelDataLoader({});

      expect(mockSetDependencies).not.toHaveBeenCalled();
    });

    it('should not call setDependencies when dependencies is not an object', () => {
      renderUseModelDataLoader({
        modeler_api: { dependencies: 'invalid' },
      });

      expect(mockSetDependencies).not.toHaveBeenCalled();
    });
  });
});
