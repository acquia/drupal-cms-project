/**
 * Tests for standalone viewer entry point
 *
 * Tests the settings synthesis and init API for the standalone viewer
 * which embeds the modeler without a Drupal backend.
 */

// ---------------------------------------------------------------------------
// Mocks — must be declared BEFORE imports
// ---------------------------------------------------------------------------

const mockRender = jest.fn();
const mockUnmount = jest.fn();
const mockCreateRoot = jest.fn((_container: unknown) => ({
  render: mockRender,
  unmount: mockUnmount,
}));

jest.mock('react-dom/client', () => ({
  createRoot: mockCreateRoot,
}));

// Mock React to prevent JSX rendering issues
jest.mock('react', () => {
  const actual = jest.requireActual('react');
  return {
    ...actual,
    createElement: jest.fn((...args: any[]) => args),
  };
});

// Mock App component
jest.mock('../App', () => ({
  __esModule: true,
  default: jest.fn(() => null),
}));

// Mock CSS imports
jest.mock('../styles/modeler.css', () => ({}));
jest.mock('reactflow/dist/style.css', () => ({}));

// Mock global fetch
const mockFetch = jest.fn();
global.fetch = mockFetch;

// ---------------------------------------------------------------------------
// Import under test (after mocks)
// ---------------------------------------------------------------------------
import { init } from '../standalone';
import type { ReactElement } from 'react';
import type { Settings } from '../types/settings';

/**
 * The settings `init()` handed to `<App>`: it renders `<App>` wrapped in
 * `<StrictMode>`, so the App element is the wrapper's only child.
 */
function renderedSettings(): Settings {
  const tree = mockRender.mock.calls[0][0] as ReactElement<{ children: ReactElement<{ settings: Settings }> }>;
  return tree.props.children.props.settings;
}

describe('standalone viewer', () => {
  let container: HTMLElement;

  beforeEach(() => {
    jest.clearAllMocks();
    container = document.createElement('div');
    container.id = 'workflow-viewer';
    document.body.appendChild(container);
  });

  afterEach(() => {
    container.remove();
  });

  describe('init()', () => {
    it('should throw when container is not found', async () => {
      await expect(
        init('#nonexistent', { model: { id: 'test' } }),
      ).rejects.toThrow('container not found');
    });

    it('should throw when neither model nor modelUrl is provided', async () => {
      await expect(
        init('#workflow-viewer', {} as any),
      ).rejects.toThrow('either `model` or `modelUrl` must be provided');
    });

    it('should accept a DOM element as selector', async () => {
      await init(container, { model: { id: 'test', nodes: [], edges: [] } });

      expect(mockCreateRoot).toHaveBeenCalledWith(container);
      expect(mockRender).toHaveBeenCalled();
    });

    it('should mount App with inline model data', async () => {
      const model = {
        id: 'my-workflow',
        version: '1.0.0',
        metadata: { label: 'Test Workflow', documentation: 'A test' },
        nodes: [
          { id: 'n1', type: 'start', plugin: 'form:form_build', label: 'Event', position: { x: 100, y: 100 }, configuration: {} },
        ],
        edges: [],
        replayData: [{ type: 'started', id: 'n1', data: {} }],
        configForms: { 'form:form_build': [{ key: 'form_ids', type: 'textfield', title: 'Form IDs' }] },
        metadataForm: [{ key: 'label', type: 'textfield', title: 'Label' }],
        components: [{ plugin: 'form:form_build', label: 'Form Build', type: 'start', provider: 'example_form' }],
      };

      const result = await init('#workflow-viewer', { model });

      expect(mockCreateRoot).toHaveBeenCalledWith(container);
      expect(mockRender).toHaveBeenCalled();
      // The metadata dialog reads the form from the settings, so the export's
      // own form has to arrive there.
      expect(renderedSettings().modeler?.metadataForm).toEqual(model.metadataForm);

      // Verify destroy function works
      expect(typeof result.destroy).toBe('function');
      result.destroy();
      expect(mockUnmount).toHaveBeenCalled();
    });

    it('should mount App without a metadata form when the export has none', async () => {
      await init('#workflow-viewer', { model: { id: 'test', nodes: [], edges: [] } });

      expect(renderedSettings().modeler?.metadataForm).toBeUndefined();
    });

    it('should fetch model from URL when modelUrl is provided', async () => {
      const model = {
        id: 'remote-model',
        nodes: [],
        edges: [],
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve(model),
      });

      await init('#workflow-viewer', { modelUrl: '/api/model.json' });

      expect(mockFetch).toHaveBeenCalledWith('/api/model.json');
      expect(mockCreateRoot).toHaveBeenCalled();
      expect(mockRender).toHaveBeenCalled();
    });

    it('should throw when fetch fails', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: false,
        statusText: 'Not Found',
      });

      await expect(
        init('#workflow-viewer', { modelUrl: '/bad-url.json' }),
      ).rejects.toThrow('failed to fetch model');
    });

    it('should prefer inline model over modelUrl', async () => {
      const model = { id: 'inline', nodes: [], edges: [] };

      await init('#workflow-viewer', { model, modelUrl: '/should-not-fetch.json' });

      expect(mockFetch).not.toHaveBeenCalled();
      expect(mockRender).toHaveBeenCalled();
    });

    it('should render without errors for models with replay data', async () => {
      const model = {
        id: 'test',
        nodes: [],
        edges: [],
        replayData: [{ type: 'started', id: 'n1', data: {} }],
      };

      await init('#workflow-viewer', { model });

      expect(mockCreateRoot).toHaveBeenCalled();
      expect(mockRender).toHaveBeenCalled();
    });

    it('should clear container content before mounting', async () => {
      container.innerHTML = '<p>Old content</p>';

      await init('#workflow-viewer', { model: { id: 'test', nodes: [], edges: [] } });

      // innerHTML is cleared, then React mounts
      expect(container.innerHTML).toBe('');
    });
  });
});
