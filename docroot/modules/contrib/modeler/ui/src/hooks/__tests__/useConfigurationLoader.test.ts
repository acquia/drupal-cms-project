/**
 * Tests for useConfigurationLoader hook
 */

import { renderHook, act, waitFor } from '@testing-library/react';
import { useConfigurationLoader } from '../useConfigurationLoader';

// Mock fetch
const mockFetch = jest.fn();
global.fetch = mockFetch;

// Mock Drupal.Message for showDrupalMessage
const mockMessageAdd = jest.fn();
(global as any).Drupal = {
  Message: jest.fn(() => ({ add: mockMessageAdd })),
};

// Mock store
const mockSetNodes = jest.fn();
const mockSetEdges = jest.fn();
let mockContextConfig: Record<string, string> = {};

jest.mock('../../store/useContextStore', () => ({
  useContextStore: jest.fn((selector) => {
    const state = {
      contextConfig: mockContextConfig,
    };
    return selector(state);
  }),
}));

jest.mock('../../store/useGraphStore', () => ({
  useGraphStore: jest.fn((selector) => {
    const state = {
      setNodes: mockSetNodes,
      setEdges: mockSetEdges,
    };
    return selector(state);
  }),
}));

/** Helper: create a mock CSRF token response with validation-compatible shape */
const mockTokenResponse = (token = 'csrf-token') => ({
  ok: true,
  status: 200,
  statusText: 'OK',
  text: () => Promise.resolve(token),
});

describe('useConfigurationLoader', () => {
  const defaultSettings = {
    modeler_api: {
      config_url: '/api/config',
      token_url: '/api/token',
    },
  };

  const mockNode = {
    id: 'node-1',
    type: 'element',
    position: { x: 0, y: 0 },
    data: {
      label: 'Test Node',
      plugin: 'test_plugin',
      componentType: '4',
      configuration: { key: 'value' },
    },
  };

  // After the P5 refactor, conditions are authored as condition NODES
  // (type 'condition', componentType 5, plugin = the condition plugin). They
  // flow through the SAME node loading path that previously served edge
  // conditions, sending an identical request payload to the backend.
  const mockConditionNode = {
    id: 'condition-1',
    type: 'condition',
    position: { x: 0, y: 0 },
    data: {
      label: 'Test Condition',
      plugin: 'test_condition',
      componentType: '5', // Api::COMPONENT_TYPE_LINK (condition)
      configuration: { condKey: 'condValue' },
      __isConditionNode: true,
    },
  };

  const mockConfigFormResponse = {
    form: [
      { key: 'field1', type: 'textfield', title: 'Field 1' },
    ],
  };

  beforeEach(() => {
    jest.clearAllMocks();
    mockFetch.mockReset();
    mockSetNodes.mockReset();
    mockSetEdges.mockReset();
    mockMessageAdd.mockReset();
    mockContextConfig = {};
  });

  describe('initialization', () => {
    it('should initialize with null configurationForm and false loading', () => {
      const { result } = renderHook(() => useConfigurationLoader({
        node: null,
        settings: defaultSettings,
      }));
      
      expect(result.current.configurationForm).toBeNull();
      expect(result.current.loading).toBe(false);
    });

    it('should return loadConfiguration function', () => {
      const { result } = renderHook(() => useConfigurationLoader({
        node: null,
        settings: defaultSettings,
      }));
      
      expect(result.current.loadConfiguration).toBeDefined();
      expect(typeof result.current.loadConfiguration).toBe('function');
    });
  });

  describe('loading node configuration', () => {
    it('should load configuration for a node with plugin', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse()) // Token fetch
        .mockResolvedValueOnce({ 
          ok: true, 
          json: () => Promise.resolve(mockConfigFormResponse) 
        }); // Config fetch

      const { result } = renderHook(() => useConfigurationLoader({
        node: mockNode,
        settings: defaultSettings,
      }));

      await waitFor(() => {
        expect(result.current.configurationForm).toEqual(mockConfigFormResponse.form);
      });
    });

    it('should send correct payload for node configuration', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({ 
          ok: true, 
          json: () => Promise.resolve(mockConfigFormResponse) 
        });

      renderHook(() => useConfigurationLoader({
        node: mockNode,
        settings: defaultSettings,
      }));

      await waitFor(() => {
        expect(mockFetch).toHaveBeenCalledTimes(2);
      });

      const configCall = mockFetch.mock.calls[1];
      expect(configCall[0]).toBe('/api/config');
      expect(configCall[1].method).toBe('POST');
      
      const body = JSON.parse(configCall[1].body);
      expect(body.component_type).toBe('4');
      expect(body.component_id).toBe('node-1');
      expect(body.model_id).toBe('');
      expect(body.is_new).toBe(false);
      expect(body.plugin_id).toBe('test_plugin');
      expect(body.configuration).toEqual({ key: 'value' });
    });

    it('should send model_id and is_new from settings', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve(mockConfigFormResponse),
        });

      const settingsWithModel = {
        ...defaultSettings,
        modeler: { modelId: 'my-model-42' },
        modeler_api: { ...defaultSettings.modeler_api, isNew: true },
      };

      renderHook(() => useConfigurationLoader({
        node: mockNode,
        settings: settingsWithModel,
      }));

      await waitFor(() => {
        expect(mockFetch).toHaveBeenCalledTimes(2);
      });

      const configCall = mockFetch.mock.calls[1];
      const body = JSON.parse(configCall[1].body);
      expect(body.model_id).toBe('my-model-42');
      expect(body.is_new).toBe(true);
    });

    it('should skip loading for gateway plugin', async () => {
      const gatewayNode = {
        ...mockNode,
        data: { ...mockNode.data, plugin: 'gateway' },
      };

      const { result } = renderHook(() => useConfigurationLoader({
        node: gatewayNode,
        settings: defaultSettings,
      }));

      // Give it time to potentially load
      await act(async () => {
        await new Promise(resolve => setTimeout(resolve, 100));
      });

      expect(mockFetch).not.toHaveBeenCalled();
      expect(result.current.configurationForm).toBeNull();
    });

    it('should skip loading for node without plugin', async () => {
      const nodeWithoutPlugin = {
        ...mockNode,
        data: { ...mockNode.data, plugin: undefined },
      };

      renderHook(() => useConfigurationLoader({
        node: nodeWithoutPlugin,
        settings: defaultSettings,
      }));

      await act(async () => {
        await new Promise(resolve => setTimeout(resolve, 100));
      });

      expect(mockFetch).not.toHaveBeenCalled();
    });
  });

  // Conditions are now authored as condition NODES and load their config form
  // through the node path. These tests assert the SAME request payload that the
  // old edge-condition path produced (component_type 5, the condition plugin)
  // now arrives via the condition node.
  describe('loading condition node configuration', () => {
    it('should load configuration for a condition node', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({ 
          ok: true, 
          json: () => Promise.resolve(mockConfigFormResponse) 
        });

      const { result } = renderHook(() => useConfigurationLoader({
        node: mockConditionNode,
        settings: defaultSettings,
      }));

      await waitFor(() => {
        expect(result.current.configurationForm).toEqual(mockConfigFormResponse.form);
      });
    });

    it('should send a condition payload (component_type 5, condition plugin) via the node path', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({ 
          ok: true, 
          json: () => Promise.resolve(mockConfigFormResponse) 
        });

      renderHook(() => useConfigurationLoader({
        node: mockConditionNode,
        settings: defaultSettings,
      }));

      await waitFor(() => {
        expect(mockFetch).toHaveBeenCalledTimes(2);
      });

      const configCall = mockFetch.mock.calls[1];
      const body = JSON.parse(configCall[1].body);
      expect(body.component_type).toBe('5'); // COMPONENT_TYPE_LINK for conditions
      expect(body.component_id).toBe('condition-1');
      expect(body.model_id).toBe('');
      expect(body.is_new).toBe(false);
      expect(body.plugin_id).toBe('test_condition');
      expect(body.configuration).toEqual({ condKey: 'condValue' });
    });

    it('should skip loading for a condition node without a plugin', async () => {
      const conditionNodeWithoutPlugin = {
        ...mockConditionNode,
        data: { ...mockConditionNode.data, plugin: undefined },
      };

      const { result } = renderHook(() => useConfigurationLoader({
        node: conditionNodeWithoutPlugin,
        settings: defaultSettings,
      }));

      await act(async () => {
        await new Promise(resolve => setTimeout(resolve, 100));
      });

      expect(mockFetch).not.toHaveBeenCalled();
      expect(result.current.configurationForm).toBeNull();
    });
  });

  describe('loading states', () => {
    it('should set loading to true during fetch', async () => {
      let resolveConfig: (value: any) => void;
      const configPromise = new Promise(resolve => { resolveConfig = resolve; });

      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockReturnValueOnce(configPromise);

      const { result } = renderHook(() => useConfigurationLoader({
        node: mockNode,
        settings: defaultSettings,
      }));

      await waitFor(() => {
        expect(result.current.loading).toBe(true);
      });

      await act(async () => {
        resolveConfig!({ ok: true, json: () => Promise.resolve(mockConfigFormResponse) });
      });

      await waitFor(() => {
        expect(result.current.loading).toBe(false);
      });
    });
  });

  describe('error handling', () => {
    it('should handle missing config URL', async () => {
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      renderHook(() => useConfigurationLoader({
        node: mockNode,
        settings: { modeler_api: { token_url: '/api/token' } },
      }));

      await act(async () => {
        await new Promise(resolve => setTimeout(resolve, 100));
      });

      expect(consoleSpy).toHaveBeenCalledWith('Configuration URL not found in settings');
      consoleSpy.mockRestore();
    });

    it('should handle missing token URL', async () => {
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      mockFetch.mockResolvedValueOnce(mockTokenResponse());

      renderHook(() => useConfigurationLoader({
        node: mockNode,
        settings: { modeler_api: { config_url: '/api/config' } },
      }));

      await act(async () => {
        await new Promise(resolve => setTimeout(resolve, 100));
      });

      expect(consoleSpy).toHaveBeenCalledWith('Token URL not found in settings');
      consoleSpy.mockRestore();
    });

    it('should show Drupal message on failed response', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({ ok: false, status: 500, statusText: 'Internal Server Error' });

      renderHook(() => useConfigurationLoader({
        node: mockNode,
        settings: defaultSettings,
      }));

      await waitFor(() => {
        expect(mockMessageAdd).toHaveBeenCalledWith(
          expect.stringContaining('500'),
          { type: 'error' },
        );
      });
    });

    it('should show Drupal message on fetch errors', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockRejectedValueOnce(new Error('Network error'));

      renderHook(() => useConfigurationLoader({
        node: mockNode,
        settings: defaultSettings,
      }));

      await waitFor(() => {
        expect(mockMessageAdd).toHaveBeenCalledWith(
          expect.stringContaining('Network error'),
          { type: 'error' },
        );
      });
    });
  });

  describe('abort handling', () => {
    it('should create an AbortController for requests', async () => {
      // Test that the hook properly uses AbortController by checking the signal is passed
      let receivedSignal: AbortSignal | null | undefined;

      mockFetch.mockImplementation((url: string, options?: RequestInit) => {
        if (url === '/api/token') {
          return Promise.resolve(mockTokenResponse());
        }
        receivedSignal = options?.signal;
        return Promise.resolve({ 
          ok: true, 
          json: () => Promise.resolve(mockConfigFormResponse) 
        });
      });

      renderHook(() => useConfigurationLoader({
        node: mockNode,
        settings: defaultSettings,
      }));

      await waitFor(() => {
        expect(receivedSignal).toBeDefined();
        expect(receivedSignal instanceof AbortSignal).toBe(true);
      });
    });

    it('should ignore AbortError', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockRejectedValueOnce(new DOMException('Aborted', 'AbortError'));

      renderHook(() => useConfigurationLoader({
        node: mockNode,
        settings: defaultSettings,
      }));

      await act(async () => {
        await new Promise(resolve => setTimeout(resolve, 100));
      });

      // Should not show a Drupal message for AbortError
      expect(mockMessageAdd).not.toHaveBeenCalled();
    });
  });

  describe('caching behavior', () => {
    it('should not reload if plugin has not changed', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({ 
          ok: true, 
          json: () => Promise.resolve(mockConfigFormResponse) 
        });

      const { result, rerender } = renderHook(
        ({ node }) => useConfigurationLoader({
          node,
          settings: defaultSettings,
        }),
        { initialProps: { node: mockNode } }
      );

      await waitFor(() => {
        expect(result.current.configurationForm).toBeTruthy();
      });

      const callCount = mockFetch.mock.calls.length;

      // Rerender with same node
      rerender({ node: mockNode });

      await act(async () => {
        await new Promise(resolve => setTimeout(resolve, 100));
      });

      // Should not have made new requests
      expect(mockFetch.mock.calls.length).toBe(callCount);
    });

    it('should reload when plugin changes', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({ 
          ok: true, 
          json: () => Promise.resolve(mockConfigFormResponse) 
        })
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({ 
          ok: true, 
          json: () => Promise.resolve(mockConfigFormResponse) 
        });

      const { result, rerender } = renderHook(
        ({ node }) => useConfigurationLoader({
          node,
          settings: defaultSettings,
        }),
        { initialProps: { node: mockNode } }
      );

      await waitFor(() => {
        expect(result.current.configurationForm).toBeTruthy();
      });

      const callCountAfterFirst = mockFetch.mock.calls.length;

      // Change to a different plugin - should trigger reload
      const newNode = { ...mockNode, id: 'node-2', data: { ...mockNode.data, plugin: 'different_plugin' } };
      rerender({ node: newNode });

      await waitFor(() => {
        // Should have made new requests due to different plugin
        expect(mockFetch.mock.calls.length).toBeGreaterThan(callCountAfterFirst);
      });
    });
  });

  describe('selection changes', () => {
    it('should clear loading state when no target', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({ 
          ok: true, 
          json: () => Promise.resolve(mockConfigFormResponse) 
        });

      type Props = { node: typeof mockNode | null };
      const { result, rerender } = renderHook(
        ({ node }: Props) => useConfigurationLoader({
          node,
          settings: defaultSettings,
        }),
        { initialProps: { node: mockNode } as Props }
      );

      // Wait for initial load
      await waitFor(() => {
        expect(result.current.configurationForm).toBeTruthy();
      });

      // Clear node
      rerender({ node: null });

      await waitFor(() => {
        expect(result.current.loading).toBe(false);
        expect(result.current.configurationForm).toBeNull();
      });
    });
  });

  describe('empty response handling', () => {
    it('should handle empty response object', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({ 
          ok: true, 
          json: () => Promise.resolve({}) 
        });

      const { result } = renderHook(() => useConfigurationLoader({
        node: mockNode,
        settings: defaultSettings,
      }));

      await waitFor(() => {
        expect(result.current.loading).toBe(false);
        expect(result.current.configurationForm).toBeNull();
      });
    });

    it('should show Drupal message for response with only error', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({ 
          ok: true, 
          json: () => Promise.resolve({ error: 'Plugin not editable.' }) 
        });

      const { result } = renderHook(() => useConfigurationLoader({
        node: mockNode,
        settings: defaultSettings,
      }));

      await waitFor(() => {
        expect(result.current.loading).toBe(false);
        expect(result.current.configurationForm).toBeNull();
      });

      expect(mockMessageAdd).toHaveBeenCalledWith('Plugin not editable.', { type: 'error' });
    });
  });

  describe('contextConfig integration', () => {
    const newNode = {
      id: 'new-node-1',
      type: 'element',
      position: { x: 0, y: 0 },
      data: {
        label: 'New Node',
        plugin: 'test_plugin',
        componentType: '4',
        configuration: {}, // Empty = new component
      },
    };

    const newConditionNode = {
      id: 'new-condition-1',
      type: 'condition',
      position: { x: 0, y: 0 },
      data: {
        label: 'New Condition',
        plugin: 'test_condition',
        componentType: '5', // Condition node
        configuration: {}, // Empty = new condition
        __isConditionNode: true,
      },
    };

    it('should merge contextConfig into configuration for new nodes', async () => {
      mockContextConfig = { event: 'entity:insert', type: 'node' };

      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve(mockConfigFormResponse),
        });

      renderHook(() => useConfigurationLoader({
        node: newNode,
        settings: defaultSettings,
      }));

      await waitFor(() => {
        expect(mockFetch).toHaveBeenCalledTimes(2);
      });

      // Check the configuration sent in the request body
      const configCall = mockFetch.mock.calls[1];
      const body = JSON.parse(configCall[1].body);
      expect(body.configuration).toEqual({ event: 'entity:insert', type: 'node' });
    });

    it('should persist contextConfig values into node data', async () => {
      mockContextConfig = { event: 'entity:insert' };

      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve(mockConfigFormResponse),
        });

      renderHook(() => useConfigurationLoader({
        node: newNode,
        settings: defaultSettings,
      }));

      await waitFor(() => {
        expect(mockSetNodes).toHaveBeenCalled();
      });

      // The setNodes function should be called with a mapper that updates the node
      const setNodesFn = mockSetNodes.mock.calls[0][0];
      const updatedNodes = setNodesFn([newNode]);
      expect(updatedNodes[0].data.configuration).toEqual({ event: 'entity:insert' });
    });

    it('should not merge contextConfig for nodes with existing configuration', async () => {
      mockContextConfig = { event: 'entity:insert' };

      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve(mockConfigFormResponse),
        });

      renderHook(() => useConfigurationLoader({
        node: mockNode, // Has existing configuration { key: 'value' }
        settings: defaultSettings,
      }));

      await waitFor(() => {
        expect(mockFetch).toHaveBeenCalledTimes(2);
      });

      // Existing configuration should be sent as-is
      const configCall = mockFetch.mock.calls[1];
      const body = JSON.parse(configCall[1].body);
      expect(body.configuration).toEqual({ key: 'value' });

      // setNodes should NOT have been called for contextConfig merging
      expect(mockSetNodes).not.toHaveBeenCalled();
    });

    it('should not merge contextConfig when contextConfig is empty', async () => {
      mockContextConfig = {};

      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve(mockConfigFormResponse),
        });

      renderHook(() => useConfigurationLoader({
        node: newNode,
        settings: defaultSettings,
      }));

      await waitFor(() => {
        expect(mockFetch).toHaveBeenCalledTimes(2);
      });

      // Empty configuration should be sent
      const configCall = mockFetch.mock.calls[1];
      const body = JSON.parse(configCall[1].body);
      expect(body.configuration).toEqual({});

      // setNodes should NOT have been called
      expect(mockSetNodes).not.toHaveBeenCalled();
    });

    it('should merge contextConfig into configuration for new condition nodes', async () => {
      mockContextConfig = { event: 'entity:insert', type: 'node' };

      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve(mockConfigFormResponse),
        });

      renderHook(() => useConfigurationLoader({
        node: newConditionNode,
        settings: defaultSettings,
      }));

      await waitFor(() => {
        expect(mockFetch).toHaveBeenCalledTimes(2);
      });

      const configCall = mockFetch.mock.calls[1];
      const body = JSON.parse(configCall[1].body);
      expect(body.configuration).toEqual({ event: 'entity:insert', type: 'node' });
    });

    it('should persist contextConfig values into condition node data', async () => {
      mockContextConfig = { field1: 'val1' };

      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve(mockConfigFormResponse),
        });

      renderHook(() => useConfigurationLoader({
        node: newConditionNode,
        settings: defaultSettings,
      }));

      await waitFor(() => {
        expect(mockSetNodes).toHaveBeenCalled();
      });

      // The setNodes function should be called with a mapper that updates the node
      const setNodesFn = mockSetNodes.mock.calls[0][0];
      const updatedNodes = setNodesFn([newConditionNode]);
      expect(updatedNodes[0].data.configuration).toEqual({ field1: 'val1' });
    });

    it('should not merge contextConfig for condition nodes with existing configuration', async () => {
      mockContextConfig = { event: 'entity:insert' };

      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve(mockConfigFormResponse),
        });

      renderHook(() => useConfigurationLoader({
        node: mockConditionNode, // Has existing configuration { condKey: 'condValue' }
        settings: defaultSettings,
      }));

      await waitFor(() => {
        expect(mockFetch).toHaveBeenCalledTimes(2);
      });

      // Existing condition configuration should be sent as-is
      const configCall = mockFetch.mock.calls[1];
      const body = JSON.parse(configCall[1].body);
      expect(body.configuration).toEqual({ condKey: 'condValue' });

      // setNodes should NOT have been called for contextConfig merging
      expect(mockSetNodes).not.toHaveBeenCalled();
    });

    it('should merge multiple contextConfig keys into new node configuration', async () => {
      mockContextConfig = { field_a: 'alpha', field_b: 'beta', field_c: 'gamma' };

      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve(mockConfigFormResponse),
        });

      renderHook(() => useConfigurationLoader({
        node: newNode,
        settings: defaultSettings,
      }));

      await waitFor(() => {
        expect(mockFetch).toHaveBeenCalledTimes(2);
      });

      const configCall = mockFetch.mock.calls[1];
      const body = JSON.parse(configCall[1].body);
      expect(body.configuration).toEqual({
        field_a: 'alpha',
        field_b: 'beta',
        field_c: 'gamma',
      });
    });
  });

  describe('standalone mode', () => {
    const standaloneSettings = {
      modeler: {
        standalone: true,
        configForms: {
          test_plugin: [
            { key: 'field1', type: 'textfield', title: 'Field 1' },
            { key: 'field2', type: 'select', title: 'Field 2', options: { a: 'A', b: 'B' } },
          ],
          test_condition: [
            { key: 'cond_field', type: 'checkbox', title: 'Condition Field' },
          ],
        },
      },
      modeler_api: {},
    };

    it('returns pre-baked form for a node plugin without fetching', async () => {
      const { result } = renderHook(() => useConfigurationLoader({
        node: mockNode,
        settings: standaloneSettings,
      }));

      // Trigger the load
      await act(async () => {
        await result.current.loadConfiguration();
      });

      expect(result.current.configurationForm).toEqual(standaloneSettings.modeler.configForms.test_plugin);
      expect(mockFetch).not.toHaveBeenCalled();
    });

    it('returns pre-baked form for a condition node plugin without fetching', async () => {
      const { result } = renderHook(() => useConfigurationLoader({
        node: mockConditionNode,
        settings: standaloneSettings,
      }));

      await act(async () => {
        await result.current.loadConfiguration();
      });

      expect(result.current.configurationForm).toEqual(standaloneSettings.modeler.configForms.test_condition);
      expect(mockFetch).not.toHaveBeenCalled();
    });

    it('returns null when plugin has no pre-baked form', async () => {
      const unknownNode = {
        ...mockNode,
        data: { ...mockNode.data, plugin: 'unknown_plugin' },
      };

      const { result } = renderHook(() => useConfigurationLoader({
        node: unknownNode,
        settings: standaloneSettings,
      }));

      await act(async () => {
        await result.current.loadConfiguration();
      });

      expect(result.current.configurationForm).toBeNull();
      expect(mockFetch).not.toHaveBeenCalled();
    });

    it('returns null when configForms is not provided', async () => {
      const noFormsSettings = {
        modeler: { standalone: true },
        modeler_api: {},
      };

      const { result } = renderHook(() => useConfigurationLoader({
        node: mockNode,
        settings: noFormsSettings,
      }));

      await act(async () => {
        await result.current.loadConfiguration();
      });

      expect(result.current.configurationForm).toBeNull();
      expect(mockFetch).not.toHaveBeenCalled();
    });
  });
});
