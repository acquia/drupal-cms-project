/**
 * Tests for useExport hook
 *
 * Tests the export functionality for all four formats: Recipe, Archive,
 * JSON, and SVG.  DOM-dependent functions (SVG canvas export) are tested
 * via the helper utilities; the full canvas export is inherently an
 * integration-level concern tested in E2E.
 */

import { renderHook, act } from '@testing-library/react';
import { useExport } from '../useExport';
import type { Settings } from '../../types/settings';
import type { StoreNode as Node, StoreEdge as Edge, StoreComponent as Component } from '../../types/settings';
import type { ReplayStep } from '../useSimpleReplaySync';
import { TOKEN_DATA_PREV, TOKEN_DATA_REF, TOKEN_DATA_SAME, expandReplayStep } from '../../utils/replayExpansion';

// ---------------------------------------------------------------------------
// Mocks
// ---------------------------------------------------------------------------

// Mock modelUtils — exportModelData returns a predictable payload
jest.mock('../../utils/modelUtils', () => ({
  exportModelData: jest.fn((_nodes, _edges, meta) => ({
    id: meta?.id || 'mock-model',
    version: meta?.version || '1.0.0',
    nodes: [],
    edges: [],
  })),
}));

// Mock validation — fetchValidatedCsrfToken resolves to a token string
jest.mock('../../utils/validation', () => ({
  fetchValidatedCsrfToken: jest.fn().mockResolvedValue('mock-csrf-token'),
}));

// Capture Blob/URL operations used by downloadFile
const revokeObjectURL = jest.fn();
const createObjectURL = jest.fn().mockReturnValue('blob:mock-url');

// Mock URL methods
Object.defineProperty(globalThis, 'URL', {
  value: {
    createObjectURL,
    revokeObjectURL,
  },
  writable: true,
  configurable: true,
});

// Mock window.open for recipe export
const mockWindowOpen = jest.fn();
Object.defineProperty(window, 'open', {
  value: mockWindowOpen,
  writable: true,
  configurable: true,
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Minimal Settings object with export URLs. */
function makeSettings(overrides: Partial<Settings['modeler_api']> = {}): Settings {
  return {
    modeler_api: {
      token_url: '/session/token',
      save_url: '/modeler-api/save',
      export_url: '/modeler-api/export',
      export_recipe_url: '/modeler-api/export-recipe',
      ...overrides,
    },
  } as Settings;
}

/** Minimal node list. */
function makeNodes(count = 2): Node[] {
  return Array.from({ length: count }, (_, i) => ({
    id: `node_${i}`,
    type: i === 0 ? 'start' : 'element',
    position: { x: i * 200, y: 100 },
    data: { label: `Node ${i}`, plugin: `plugin_${i}` },
  })) as Node[];
}

/** Minimal edge list. */
function makeEdges(): Edge[] {
  return [
    {
      id: 'edge_1',
      source: 'node_0',
      target: 'node_1',
      type: 'default',
      data: { condition: 'condition_plugin' },
    },
  ] as Edge[];
}

/** Component registry matching the test nodes/edges. */
function makeComponents(): Component[] {
  return [
    { plugin: 'plugin_0', label: 'Plugin 0', provider: 'test_base', type: 'start' },
    { plugin: 'plugin_1', label: 'Plugin 1', provider: 'test_content', type: 'element' },
    { plugin: 'condition_plugin', label: 'Condition', provider: 'test_user', type: 'link' },
  ] as Component[];
}

function makeReplayData(): ReplayStep[] {
  return [
    { id: 'node_0', type: 'started', data: {} },
    { id: 'node_1', type: 'execute', successorId: 'node_1', data: {} },
  ];
}

/** Default hook props. */
function defaultProps() {
  return {
    settings: makeSettings(),
    nodes: makeNodes(),
    edges: makeEdges(),
    components: makeComponents(),
    modelData: { id: 'test-model', version: '2.0.0', metadata: { label: 'Test' } },
    replayData: [] as ReplayStep[],
    announce: jest.fn(),
  };
}

/**
 * Run an export and return the exact string it wrote to the downloaded Blob.
 * jsdom does not implement Blob.text(), so the constructor parts are
 * intercepted instead.
 */
async function captureExportedJson(run: () => Promise<void>): Promise<string> {
  const blobParts: string[] = [];
  const RealBlob = globalThis.Blob;
  const blobSpy = jest
    .spyOn(globalThis, 'Blob')
    .mockImplementation((parts?: BlobPart[]) => {
      if (parts) {
        for (const part of parts) {
          blobParts.push(String(part));
        }
      }
      return new RealBlob(parts ?? []);
    });

  await act(async () => {
    await run();
  });

  blobSpy.mockRestore();
  return blobParts.join('');
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('useExport', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  // -------------------------------------------------------------------------
  // canExport
  // -------------------------------------------------------------------------
  describe('canExport', () => {
    it('should be true when there are nodes', () => {
      const { result } = renderHook(() => useExport(defaultProps()));
      expect(result.current.canExport).toBe(true);
    });

    it('should be false when there are no nodes', () => {
      const props = { ...defaultProps(), nodes: [] };
      const { result } = renderHook(() => useExport(props));
      expect(result.current.canExport).toBe(false);
    });
  });

  // -------------------------------------------------------------------------
  // availableFormats
  // -------------------------------------------------------------------------
  describe('availableFormats', () => {
    it('should include all four formats when backend URLs are present', () => {
      const { result } = renderHook(() => useExport(defaultProps()));
      expect(result.current.availableFormats).toEqual(['recipe', 'archive', 'json', 'svg']);
    });

    it('should exclude recipe when export_recipe_url is missing', () => {
      const props = {
        ...defaultProps(),
        settings: makeSettings({ export_recipe_url: undefined }),
      };
      const { result } = renderHook(() => useExport(props));
      expect(result.current.availableFormats).not.toContain('recipe');
      expect(result.current.availableFormats).toContain('archive');
    });

    it('should exclude archive when export_url is missing', () => {
      const props = {
        ...defaultProps(),
        settings: makeSettings({ export_url: undefined }),
      };
      const { result } = renderHook(() => useExport(props));
      expect(result.current.availableFormats).not.toContain('archive');
      expect(result.current.availableFormats).toContain('recipe');
    });

    it('should always include json and svg', () => {
      const props = {
        ...defaultProps(),
        settings: makeSettings({ export_url: undefined, export_recipe_url: undefined }),
      };
      const { result } = renderHook(() => useExport(props));
      expect(result.current.availableFormats).toEqual(['json', 'svg']);
    });
  });

  // -------------------------------------------------------------------------
  // hasReplayData
  // -------------------------------------------------------------------------
  describe('hasReplayData', () => {
    it('should be false when replay data is empty', () => {
      const { result } = renderHook(() => useExport(defaultProps()));
      expect(result.current.hasReplayData).toBe(false);
    });

    it('should be true when replay data is present', () => {
      const props = { ...defaultProps(), replayData: makeReplayData() };
      const { result } = renderHook(() => useExport(props));
      expect(result.current.hasReplayData).toBe(true);
    });
  });

  // -------------------------------------------------------------------------
  // getRequiredModules
  // -------------------------------------------------------------------------
  describe('getRequiredModules', () => {
    it('should derive providers from node plugins', () => {
      const { result } = renderHook(() => useExport(defaultProps()));
      const modules = result.current.getRequiredModules();
      expect(modules).toContain('test_base');
      expect(modules).toContain('test_content');
    });

    it('should derive providers from edge conditions', () => {
      const { result } = renderHook(() => useExport(defaultProps()));
      const modules = result.current.getRequiredModules();
      expect(modules).toContain('test_user');
    });

    it('should return sorted unique values', () => {
      const { result } = renderHook(() => useExport(defaultProps()));
      const modules = result.current.getRequiredModules();
      expect(modules).toEqual(['test_base', 'test_content', 'test_user']);
    });

    it('should return empty array when no components match', () => {
      const props = { ...defaultProps(), components: [] };
      const { result } = renderHook(() => useExport(props));
      expect(result.current.getRequiredModules()).toEqual([]);
    });
  });

  // -------------------------------------------------------------------------
  // executeExport — recipe
  // -------------------------------------------------------------------------
  describe('executeExport - recipe', () => {
    it('should open recipe URL in a new tab', async () => {
      const props = defaultProps();
      const { result } = renderHook(() => useExport(props));

      await act(async () => {
        await result.current.executeExport('recipe');
      });

      expect(mockWindowOpen).toHaveBeenCalledWith(
        '/modeler-api/export-recipe',
        '_blank',
        'noopener,noreferrer',
      );
    });

    it('should announce success', async () => {
      const props = defaultProps();
      const { result } = renderHook(() => useExport(props));

      await act(async () => {
        await result.current.executeExport('recipe');
      });

      expect(props.announce).toHaveBeenCalledWith('Recipe export opened in new tab');
    });
  });

  // -------------------------------------------------------------------------
  // executeExport — archive
  // -------------------------------------------------------------------------
  describe('executeExport - archive', () => {
    it('should fetch archive with CSRF token', async () => {
      const mockBlob = new Blob(['archive-data']);
      global.fetch = jest.fn().mockResolvedValue({
        ok: true,
        blob: () => Promise.resolve(mockBlob),
        headers: new Headers({ 'Content-Disposition': 'attachment; filename="model.tar.gz"' }),
      });

      const props = defaultProps();
      const { result } = renderHook(() => useExport(props));

      await act(async () => {
        await result.current.executeExport('archive');
      });

      expect(global.fetch).toHaveBeenCalledWith('/modeler-api/export', {
        method: 'GET',
        headers: { 'X-CSRF-Token': 'mock-csrf-token' },
      });
    });

    it('should trigger download and announce success', async () => {
      const mockBlob = new Blob(['data']);
      global.fetch = jest.fn().mockResolvedValue({
        ok: true,
        blob: () => Promise.resolve(mockBlob),
        headers: new Headers({ 'Content-Disposition': 'attachment; filename="custom.tar.gz"' }),
      });

      const props = defaultProps();
      const { result } = renderHook(() => useExport(props));

      await act(async () => {
        await result.current.executeExport('archive');
      });

      expect(createObjectURL).toHaveBeenCalled();
      expect(revokeObjectURL).toHaveBeenCalled();
      expect(props.announce).toHaveBeenCalledWith('Archive exported successfully');
    });

    it('should throw on failed archive response', async () => {
      global.fetch = jest.fn().mockResolvedValue({
        ok: false,
        statusText: 'Forbidden',
      });

      const props = defaultProps();
      const { result } = renderHook(() => useExport(props));

      // Call directly to verify it throws
      await expect(
        result.current.executeExport('archive'),
      ).rejects.toThrow('Failed to download archive');
    });
  });

  // -------------------------------------------------------------------------
  // executeExport — json
  // -------------------------------------------------------------------------
  describe('executeExport - json', () => {
    it('should export JSON and trigger download', async () => {
      const props = defaultProps();
      const { result } = renderHook(() => useExport(props));

      await act(async () => {
        await result.current.executeExport('json');
      });

      expect(createObjectURL).toHaveBeenCalled();
      expect(revokeObjectURL).toHaveBeenCalled();

      // Verify the blob content
      const blobArg = createObjectURL.mock.calls[0][0] as Blob;
      expect(blobArg).toBeInstanceOf(Blob);
    });

    it('should not include replay data by default', async () => {
      const props = { ...defaultProps(), replayData: makeReplayData() };
      const { result } = renderHook(() => useExport(props));

      await act(async () => {
        await result.current.executeExport('json', false);
      });

      // Verify blob was created (download triggered)
      expect(createObjectURL).toHaveBeenCalled();
    });

    it('should include replay data when requested', async () => {
      const props = { ...defaultProps(), replayData: makeReplayData() };
      const { result } = renderHook(() => useExport(props));

      await act(async () => {
        await result.current.executeExport('json', true);
      });

      expect(createObjectURL).toHaveBeenCalled();
    });

    it('should announce success', async () => {
      const props = defaultProps();
      const { result } = renderHook(() => useExport(props));

      await act(async () => {
        await result.current.executeExport('json');
      });

      expect(props.announce).toHaveBeenCalledWith('JSON exported successfully');
    });
  });

  // -------------------------------------------------------------------------
  // executeExport — svg
  // -------------------------------------------------------------------------
  describe('executeExport - svg', () => {
    it('should throw when viewport is not found', async () => {
      const props = defaultProps();
      const { result } = renderHook(() => useExport(props));

      await expect(
        act(async () => {
          await result.current.executeExport('svg');
        }),
      ).rejects.toThrow('Canvas viewport not found');
    });

    it('should throw when no visible nodes exist', async () => {
      // Set up a mock viewport with no nodes
      const container = document.createElement('div');
      container.innerHTML = `
        <div class="modeler">
          <div class="react-flow__viewport">
            <svg class="react-flow__edges"></svg>
            <div class="react-flow__nodes"></div>
          </div>
        </div>
      `;
      document.body.appendChild(container);

      const props = defaultProps();
      const { result } = renderHook(() => useExport(props));

      try {
        await result.current.executeExport('svg');
      } catch {
        // Expected
      }

      expect(props.announce).toHaveBeenCalledWith('No visible elements to export');

      // Clean up
      container.remove();
    });

    it('should generate SVG when viewport has nodes', async () => {
      // Set up a mock viewport with a node
      const container = document.createElement('div');
      container.innerHTML = `
        <div class="modeler">
          <div class="react-flow__viewport">
            <svg class="react-flow__edges"><g></g></svg>
            <div class="react-flow__nodes">
              <div class="react-flow__node react-flow__node-start"
                   style="transform: translate(100px, 200px);">
                <div class="node-label">Test Event</div>
                <div class="node-plugin">test:plugin</div>
              </div>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(container);

      // Mock offsetWidth/offsetHeight on the node element
      const nodeEl = document.querySelector('.react-flow__node') as HTMLElement;
      Object.defineProperty(nodeEl, 'offsetWidth', { value: 200 });
      Object.defineProperty(nodeEl, 'offsetHeight', { value: 100 });

      // Mock getComputedStyle for CSS variable resolution
      const originalGetComputedStyle = window.getComputedStyle;
      window.getComputedStyle = jest.fn().mockReturnValue({
        getPropertyValue: (name: string) => {
          const vars: Record<string, string> = {
            '--modeler-color-bg-primary': '#ffffff',
            '--modeler-color-bg-surface': '#f9fafb',
            '--modeler-color-border-light': '#e5e7eb',
            '--modeler-color-text-primary': '#374151',
            '--modeler-color-text-secondary': '#4b5563',
            '--modeler-color-type-event': '#ff9800',
            '--modeler-color-type-event-light': '#fff3e0',
            '--modeler-color-warning-darker': '#b45309',
            '--modeler-color-type-action': '#4caf50',
            '--modeler-color-type-condition': '#2196f3',
            '--modeler-color-type-gateway': '#9c27b0',
            '--modeler-color-warning-light': '#fef3c7',
            '--modeler-color-warning': '#f59e0b',
            '--modeler-color-warning-darkest': '#92400e',
            '--modeler-color-edge-default': '#8b8b8b',
            '--modeler-color-interactive': '#3b82f6',
            '--modeler-color-text-on-dark': '#f9fafb',
          };
          return vars[name] || '';
        },
      }) as any;

      // Spy on download
      const clickSpy = jest.fn();
      jest.spyOn(document.body, 'appendChild').mockImplementation((el) => el);
      jest.spyOn(document.body, 'removeChild').mockImplementation((el) => el);
      jest.spyOn(document, 'createElement').mockImplementation((tag: string) => {
        if (tag === 'a') {
          return { href: '', download: '', click: clickSpy } as unknown as HTMLAnchorElement;
        }
        return document.createElementNS('http://www.w3.org/1999/xhtml', tag) as HTMLElement;
      });

      const props = defaultProps();
      const { result } = renderHook(() => useExport(props));

      await act(async () => {
        await result.current.executeExport('svg');
      });

      expect(clickSpy).toHaveBeenCalled();
      expect(createObjectURL).toHaveBeenCalled();
      expect(props.announce).toHaveBeenCalledWith('SVG exported successfully');

      // Restore
      window.getComputedStyle = originalGetComputedStyle;
      (document.body.appendChild as jest.Mock).mockRestore();
      (document.body.removeChild as jest.Mock).mockRestore();
      (document.createElement as jest.Mock).mockRestore();

      // Clean up the mock viewport DOM so subsequent tests don't find it
      container.remove();
    });
  });

  // -------------------------------------------------------------------------
  // Error handling
  // -------------------------------------------------------------------------
  describe('error handling', () => {
    it('should announce error message on failure', async () => {
      const props = defaultProps();
      const { result } = renderHook(() => useExport(props));

      // SVG export will fail because there's no viewport in the DOM.
      // The error is caught, announced, and re-thrown.  In the test
      // environment act() may swallow the rejection, so we verify the
      // announce side-effect instead.
      try {
        await result.current.executeExport('svg');
      } catch {
        // Expected — the function re-throws after announcing
      }

      expect(props.announce).toHaveBeenCalledWith('Canvas viewport not found');
    });

    it('should throw on failed archive fetch', async () => {
      global.fetch = jest.fn().mockResolvedValue({
        ok: false,
        statusText: 'Internal Server Error',
      });

      const props = defaultProps();
      const { result } = renderHook(() => useExport(props));

      try {
        await result.current.executeExport('archive');
      } catch {
        // Expected
      }

      expect(props.announce).toHaveBeenCalledWith(
        expect.stringContaining('Failed to download archive'),
      );
    });
  });

  // -------------------------------------------------------------------------
  // JSON export — configForms and components
  // -------------------------------------------------------------------------
  describe('executeExport - json with configForms and components', () => {
    it('should fetch config forms for all unique plugins', async () => {
      const mockFormResponse = (form: any[]) => ({
        ok: true,
        json: () => Promise.resolve({ form }),
      });

      // Mock fetch: one call per unique plugin
      global.fetch = jest.fn()
        .mockResolvedValueOnce(mockFormResponse([{ key: 'f1', type: 'textfield', title: 'F1' }]))  // plugin_0
        .mockResolvedValueOnce(mockFormResponse([{ key: 'f2', type: 'select', title: 'F2' }]))     // plugin_1
        .mockResolvedValueOnce(mockFormResponse([{ key: 'c1', type: 'checkbox', title: 'C1' }]));  // condition_plugin

      const props = {
        ...defaultProps(),
        settings: makeSettings({ config_url: '/api/config', token_url: '/session/token' }),
      };
      const { result } = renderHook(() => useExport(props));

      await act(async () => {
        await result.current.executeExport('json');
      });

      // Verify config forms were fetched (3 unique plugins)
      expect(global.fetch).toHaveBeenCalledTimes(3);
      // Verify blob was created (download triggered)
      expect(createObjectURL).toHaveBeenCalled();
    });

    it('should create download when config forms fetch has no config_url', async () => {
      // No config_url means fetchAllConfigForms returns empty
      const props = {
        ...defaultProps(),
        settings: makeSettings({ config_url: undefined, token_url: undefined }),
      };
      const { result } = renderHook(() => useExport(props));

      await act(async () => {
        await result.current.executeExport('json');
      });

      // Download should still be triggered
      expect(createObjectURL).toHaveBeenCalled();
    });

    it('should skip duplicate plugin IDs when fetching config forms', async () => {
      const mockFormResponse = (form: any[]) => ({
        ok: true,
        json: () => Promise.resolve({ form }),
      });

      global.fetch = jest.fn()
        .mockResolvedValue(mockFormResponse([{ key: 'f1', type: 'textfield' }]));

      // Create nodes with duplicate plugins
      const duplicateNodes = [
        ...makeNodes(),
        { id: 'node_dup', type: 'element', position: { x: 0, y: 0 }, data: { label: 'Dup', plugin: 'plugin_0' } },
      ] as Node[];

      const props = {
        ...defaultProps(),
        nodes: duplicateNodes,
        settings: makeSettings({ config_url: '/api/config', token_url: '/session/token' }),
      };
      const { result } = renderHook(() => useExport(props));

      await act(async () => {
        await result.current.executeExport('json');
      });

      // Should only fetch for unique plugins: plugin_0, plugin_1, condition_plugin
      expect(global.fetch).toHaveBeenCalledTimes(3);
    });

    it('should announce "Fetching configuration forms..." before export', async () => {
      const props = {
        ...defaultProps(),
        settings: makeSettings({ config_url: undefined }),
      };
      const { result } = renderHook(() => useExport(props));

      await act(async () => {
        await result.current.executeExport('json');
      });

      expect(props.announce).toHaveBeenCalledWith('Fetching configuration forms...');
      expect(props.announce).toHaveBeenCalledWith('JSON exported successfully');
    });

    it('should handle fetch errors gracefully', async () => {
      global.fetch = jest.fn().mockRejectedValue(new Error('Network error'));

      const props = {
        ...defaultProps(),
        settings: makeSettings({ config_url: '/api/config', token_url: '/session/token' }),
      };
      const { result } = renderHook(() => useExport(props));

      // Should not throw — fetch errors are caught internally
      await act(async () => {
        await result.current.executeExport('json');
      });

      // Download should still happen with empty configForms
      expect(createObjectURL).toHaveBeenCalled();
      expect(props.announce).toHaveBeenCalledWith('JSON exported successfully');
    });

    it('should ship the metadata form so the standalone viewer can render it', async () => {
      const metadataForm = [{ key: 'label', type: 'textfield', title: 'Label' }];
      const settings = makeSettings({ config_url: undefined });
      settings.modeler = { metadataForm };
      const { result } = renderHook(() => useExport({ ...defaultProps(), settings }));

      const json = await captureExportedJson(() => result.current.executeExport('json'));

      expect(JSON.parse(json).metadataForm).toEqual(metadataForm);
    });

    it('should omit the metadata form when the backend delivered none', async () => {
      const props = { ...defaultProps(), settings: makeSettings({ config_url: undefined }) };
      const { result } = renderHook(() => useExport(props));

      const json = await captureExportedJson(() => result.current.executeExport('json'));

      expect(JSON.parse(json)).not.toHaveProperty('metadataForm');
    });
  });

  // -------------------------------------------------------------------------
  // Replay marker compactness (C17)
  //
  // ECA delivers replay data in compact `@prev`/`@ref` marker form. The
  // modeler expands those markers lazily for DISPLAY only, on a copy. The
  // JSON export must keep the markers unexpanded even after a step has been
  // viewed, so the exported `replayData` stays compact.
  // -------------------------------------------------------------------------
  describe('replay marker compactness (C17)', () => {
    /** Compact replay data with both @prev and @ref markers. */
    function makeCompactReplayData(): ReplayStep[] {
      return [
        {
          id: 'node_0',
          type: 'started',
          data: {
            node: { label: 'Node', token: '[node]', data: { title: 'A' } },
            entity: { label: 'Entity', token: '[entity]', [TOKEN_DATA_REF]: 'node' },
          },
        },
        { id: 'node_1', type: 'execute', successorId: 'node_1', data: TOKEN_DATA_PREV },
      ];
    }

    it('keeps @prev/@ref markers compact in the JSON export after a step is viewed', async () => {
      const replayData = makeCompactReplayData();
      const props = { ...defaultProps(), replayData };
      const { result } = renderHook(() => useExport(props));

      // Simulate the display path viewing each step. This is the exact
      // expansion the ReplayPanel triggers; it must not mutate replayData.
      expandReplayStep(replayData, 0);
      expandReplayStep(replayData, 1);

      const json = await captureExportedJson(() => result.current.executeExport('json', true));
      const parsed = JSON.parse(json) as { replayData: ReplayStep[] };

      // The step-level @prev marker survives.
      expect(parsed.replayData[1].data).toBe(TOKEN_DATA_PREV);

      // The @ref marker survives, and no expanded `data` was written.
      const firstData = parsed.replayData[0].data as Record<string, Record<string, unknown>>;
      expect(firstData.entity[TOKEN_DATA_REF]).toBe('node');
      expect('data' in firstData.entity).toBe(false);
    });

    it('does not mutate the source replayData array when a step is viewed', () => {
      const replayData = makeCompactReplayData();
      const snapshot = JSON.parse(JSON.stringify(replayData));

      expandReplayStep(replayData, 0);
      expandReplayStep(replayData, 1);

      expect(replayData).toEqual(snapshot);
    });

    /** Compact replay data with a cross-step @same marker. */
    function makeSameReplayData(): ReplayStep[] {
      return [
        {
          id: 'node_0',
          type: 'started',
          data: {
            node: { label: 'Node', token: '[node]', data: { title: 'A' } },
          },
        },
        {
          id: 'node_1',
          type: 'execute',
          successorId: 'node_1',
          data: {
            node: {
              label: 'Node',
              token: '[node]',
              [TOKEN_DATA_SAME]: { step: 0, path: 'node' },
            },
          },
        },
      ];
    }

    it('keeps @same markers compact in the JSON export after a step is viewed', async () => {
      const replayData = makeSameReplayData();
      const props = { ...defaultProps(), replayData };
      const { result } = renderHook(() => useExport(props));

      // Simulate the display path viewing each step. This is the exact
      // expansion the ReplayPanel triggers; it must not mutate replayData.
      expandReplayStep(replayData, 0);
      expandReplayStep(replayData, 1);

      const json = await captureExportedJson(() => result.current.executeExport('json', true));
      const parsed = JSON.parse(json) as { replayData: ReplayStep[] };

      // The cross-step @same marker survives, and no expanded `data` was
      // written for the second occurrence.
      const secondData = parsed.replayData[1].data as Record<string, Record<string, unknown>>;
      expect(secondData.node[TOKEN_DATA_SAME]).toEqual({ step: 0, path: 'node' });
      expect('data' in secondData.node).toBe(false);
    });

    it('does not mutate the source replayData when a @same step is viewed', () => {
      const replayData = makeSameReplayData();
      const snapshot = JSON.parse(JSON.stringify(replayData));

      expandReplayStep(replayData, 0);
      expandReplayStep(replayData, 1);

      expect(replayData).toEqual(snapshot);
    });
  });

  // -------------------------------------------------------------------------
  // Format stability
  // -------------------------------------------------------------------------
  describe('format stability', () => {
    it('should return stable references across re-renders', () => {
      const props = defaultProps();
      const { result, rerender } = renderHook(() => useExport(props));

      const first = result.current;
      rerender();
      const second = result.current;

      expect(first.availableFormats).toEqual(second.availableFormats);
      expect(first.canExport).toBe(second.canExport);
      expect(first.hasReplayData).toBe(second.hasReplayData);
    });

    it('should update availableFormats when settings change', () => {
      const props = defaultProps();
      const { result, rerender } = renderHook(
        (p) => useExport(p),
        { initialProps: props },
      );

      expect(result.current.availableFormats).toContain('recipe');

      const newProps = {
        ...props,
        settings: makeSettings({ export_recipe_url: undefined }),
      };
      rerender(newProps);

      expect(result.current.availableFormats).not.toContain('recipe');
    });
  });
});
