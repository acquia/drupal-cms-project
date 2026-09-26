/**
 * Tests for pluginApi.ts — the public plugin API surface.
 *
 * Every Zustand store is mocked with a minimal state shape and a _notify()
 * helper that lets tests trigger store subscriptions imperatively.
 */

import type { StoreNode, StoreEdge } from '../../types/settings';

// ── Store mock factory ──────────────────────────────────────────────────

function createStoreMock<S extends Record<string, any>>(initial: S) {
  const state = { ...initial };
  const subscribers = new Set<(s: S) => void>();
  const store = Object.assign(
    jest.fn((selector?: (s: S) => any) =>
      typeof selector === 'function' ? selector(state) : state,
    ),
    {
      getState: () => state,
      setState: (patch: Partial<S>) => Object.assign(state, patch),
      subscribe: (fn: (s: S) => void) => {
        subscribers.add(fn);
        return () => { subscribers.delete(fn); };
      },
      /** Imperatively fire all subscribers with the current state. */
      _notify: () => subscribers.forEach((fn) => fn({ ...state })),
      _state: state,
      _subscribers: subscribers,
    },
  );
  return store;
}

// ── Build mock stores ───────────────────────────────────────────────────

const mockNode1: StoreNode = {
  id: 'node-1',
  type: 'element',
  position: { x: 100, y: 200 },
  data: {
    label: 'Node One',
    plugin: 'example.node_one',
    configuration: { key: 'value' },
    componentType: 4,
    description: 'A test node',
    documentationUrl: 'https://example.com/docs',
  },
};

const mockNode2: StoreNode = {
  id: 'node-2',
  type: 'start',
  position: { x: 300, y: 400 },
  data: {
    label: 'Node Two',
    plugin: 'example.node_two',
    componentType: 1,
  },
};

const mockEdge1: StoreEdge = {
  id: 'edge-1',
  source: 'node-1',
  target: 'node-2',
  type: 'default',
  data: { condition: null },
};

const mockEdge2: StoreEdge = {
  id: 'edge-2',
  source: 'node-2',
  target: 'node-1',
  type: 'default',
  data: { condition: null },
};

const graphStore = createStoreMock({
  nodes: [mockNode1, mockNode2] as StoreNode[],
  edges: [mockEdge1] as StoreEdge[],
  addNode: jest.fn(),
  addEdge: jest.fn(),
  removeNode: jest.fn(),
  removeEdge: jest.fn(),
  updateNode: jest.fn(),
  updateEdge: jest.fn(),
  setNodes: jest.fn(),
  setEdges: jest.fn(),
});

const selectionStore = createStoreMock({
  selectedNode: null as StoreNode | null,
  selectedEdge: null as StoreEdge | null,
  selectedNodes: [] as string[],
  selectedEdges: [] as string[],
  selectNode: jest.fn(),
  selectEdge: jest.fn(),
  clearSelection: jest.fn(),
});

const modelStore = createStoreMock({
  modelData: {
    id: 'model-1',
    version: '1.0.0',
    metadata: {
      label: 'Test Model',
      documentation: 'A test workflow model',
      executable: true,
      tags: ['test'],
      changelog: 'Initial',
    },
  } as any,
});

const uiSettingsStore = createStoreMock({
  darkMode: false,
  toggleDarkMode: jest.fn(),
});

const componentStore = createStoreMock({
  components: [
    {
      plugin: 'eca_content:create',
      label: 'Create Content',
      type: 'element',
      provider: 'eca_content',
      description: 'Creates content',
      documentationUrl: 'https://example.com',
      componentType: 4,
    },
  ],
});

const contextStore = createStoreMock({
  contexts: [
    { id: 'ctx-1', topic: 'Content', model_owner: 'eca' },
    { id: 'ctx-2', topic: 'User', model_owner: 'eca' },
  ],
  selectedContextId: 'ctx-1' as string | null,
});

const filterStore = createStoreMock({
  visibleStartNodeIds: null as string[] | null,
  setVisibleStartNodeIds: jest.fn(),
});

const labelStore = createStoreMock({
  componentLabels: {
    start: 'Event',
    element: 'Action',
    link: 'Condition',
    gateway: 'Gateway',
    subprocess: 'Subprocess',
  },
});

const historyStore = createStoreMock({
  canUndo: jest.fn(() => true),
  canRedo: jest.fn(() => false),
});

const errorStore = createStoreMock({
  errorLog: [
    { id: 'err-1', message: 'Something went wrong', dismissed: false },
    { id: 'err-2', message: 'Another error', dismissed: true },
  ],
});

// ── jest.mock calls (must be at top level before imports) ───────────────

jest.mock('../../store/useGraphStore', () => ({
  useGraphStore: graphStore,
}));

jest.mock('../../store/useSelectionStore', () => ({
  useSelectionStore: selectionStore,
}));

jest.mock('../../store/useModelStore', () => ({
  useModelStore: modelStore,
}));

jest.mock('../../store/useUISettingsStore', () => ({
  useUISettingsStore: uiSettingsStore,
}));

jest.mock('../../store/useComponentStore', () => ({
  useComponentStore: componentStore,
}));

jest.mock('../../store/useContextStore', () => ({
  useContextStore: contextStore,
}));

jest.mock('../../store/useFilterStore', () => ({
  useFilterStore: filterStore,
}));

jest.mock('../../store/useLabelStore', () => ({
  useLabelStore: labelStore,
}));

jest.mock('../../store/useHistoryStore', () => ({
  useHistoryStore: historyStore,
}));

jest.mock('../../store/useErrorStore', () => ({
  useErrorStore: errorStore,
}));

jest.mock('../../utils/componentUtils', () => ({
  resolveNodeType: jest.fn((componentType: number) => {
    const map: Record<number, string> = { 1: 'start', 4: 'element', 6: 'gateway', 2: 'subprocess' };
    return map[componentType] || 'element';
  }),
}));

jest.mock('../../utils/clipboardUtils', () => ({
  generateNodeId: jest.fn((label: string, type: string) => `${type}_${label}_123`),
  generateEdgeId: jest.fn((source: string, target: string) => `edge_${source}_${target}`),
}));

jest.mock('../../utils/positionUtils', () => ({
  // Keep the real pure helpers (requiredVerticalGap, findFlowAwarePosition)
  // so incrementalLayout.placeNodeOnEdge — used by setCondition — works,
  // while still stubbing findFreePosition for the addNode tests.
  ...jest.requireActual('../../utils/positionUtils'),
  findFreePosition: jest.fn((candidate: { x: number; y: number }) => candidate),
}));

// ── Import module under test (after mocks) ──────────────────────────────

import {
  setApiReadOnly,
  setMutationHooks,
  clearMutationHooks,
  setViewportHooks,
  clearViewportHooks,
  createPluginApi,
} from '../pluginApi';

import { resolveNodeType } from '../../utils/componentUtils';
import { generateNodeId, generateEdgeId } from '../../utils/clipboardUtils';
import { findFreePosition } from '../../utils/positionUtils';
// The real (unmocked) placement primitives, so the tests below can assert
// that a plugin-added node lands on exactly the coordinates the modeler's
// own UI paths would have chosen (issue #3589110).
import {
  computeNewEventPosition,
  computeSuccessorPosition,
} from '../../utils/incrementalLayout';

// ── Helpers ─────────────────────────────────────────────────────────────

/** Reset all store states and mocks to baseline. */
function resetStores() {
  graphStore.setState({
    nodes: [mockNode1, mockNode2],
    edges: [mockEdge1],
  });
  (graphStore.getState().addNode as jest.Mock).mockClear();
  (graphStore.getState().addEdge as jest.Mock).mockClear();
  (graphStore.getState().removeNode as jest.Mock).mockClear();
  (graphStore.getState().removeEdge as jest.Mock).mockClear();
  (graphStore.getState().updateNode as jest.Mock).mockClear();
  (graphStore.getState().updateEdge as jest.Mock).mockClear();
  (graphStore.getState().setNodes as jest.Mock).mockClear();
  (graphStore.getState().setEdges as jest.Mock).mockClear();

  selectionStore.setState({
    selectedNode: null,
    selectedEdge: null,
    selectedNodes: [],
    selectedEdges: [],
  });
  (selectionStore.getState().selectNode as jest.Mock).mockClear();
  (selectionStore.getState().selectEdge as jest.Mock).mockClear();
  (selectionStore.getState().clearSelection as jest.Mock).mockClear();

  modelStore.setState({
    modelData: {
      id: 'model-1',
      version: '1.0.0',
      metadata: { label: 'Test Model', documentation: 'A test workflow model', executable: true, tags: ['test'], changelog: 'Initial' },
    },
  });

  uiSettingsStore.setState({ darkMode: false });
  (uiSettingsStore.getState().toggleDarkMode as jest.Mock).mockClear();

  componentStore.setState({
    components: [
      {
        plugin: 'eca_content:create',
        label: 'Create Content',
        type: 'element',
        provider: 'eca_content',
        description: 'Creates content',
        documentationUrl: 'https://example.com',
        componentType: 4,
      },
    ],
  });

  contextStore.setState({
    contexts: [
      { id: 'ctx-1', topic: 'Content', model_owner: 'eca' },
      { id: 'ctx-2', topic: 'User', model_owner: 'eca' },
    ],
    selectedContextId: 'ctx-1',
  });

  filterStore.setState({ visibleStartNodeIds: null });
  (filterStore.getState().setVisibleStartNodeIds as jest.Mock).mockClear();

  labelStore.setState({
    componentLabels: { start: 'Event', element: 'Action', link: 'Condition', gateway: 'Gateway', subprocess: 'Subprocess' },
  });

  historyStore.setState({
    canUndo: jest.fn(() => true),
    canRedo: jest.fn(() => false),
  });

  errorStore.setState({
    errorLog: [
      { id: 'err-1', message: 'Something went wrong', dismissed: false },
      { id: 'err-2', message: 'Another error', dismissed: true },
    ],
  });

  (resolveNodeType as jest.Mock).mockClear();
  // Restore the default deterministic implementations (individual tests may
  // override with mockReturnValue; reset so leftover overrides don't bleed).
  (generateNodeId as jest.Mock).mockReset();
  (generateNodeId as jest.Mock).mockImplementation((label: string, type: string) => `${type}_${label}_123`);
  (generateEdgeId as jest.Mock).mockReset();
  (generateEdgeId as jest.Mock).mockImplementation((source: string, target: string) => `edge_${source}_${target}`);
  (findFreePosition as jest.Mock).mockClear();

  // Reset read-only to false
  setApiReadOnly(false);
  clearMutationHooks();
  clearViewportHooks();
}

// ═════════════════════════════════════════════════════════════════════════
// Tests
// ═════════════════════════════════════════════════════════════════════════

describe('pluginApi', () => {
  let api: ReturnType<typeof createPluginApi>;

  beforeEach(() => {
    resetStores();
    api = createPluginApi();
  });

  // ── Getters ───────────────────────────────────────────────────────────

  describe('getters', () => {
    describe('getNodes', () => {
      it('returns deep-cloned nodes', () => {
        const nodes = api.getNodes();
        expect(nodes).toHaveLength(2);
        expect(nodes[0].id).toBe('node-1');
        expect(nodes[0].type).toBe('element');
        expect(nodes[0].position).toEqual({ x: 100, y: 200 });
        expect(nodes[0].data).toEqual(expect.objectContaining({ label: 'Node One' }));
      });

      it('returns a new array instance (deep clone)', () => {
        const a = api.getNodes();
        const b = api.getNodes();
        expect(a).not.toBe(b);
        expect(a[0]).not.toBe(b[0]);
      });

      it('returns empty array when no nodes', () => {
        graphStore.setState({ nodes: [] });
        expect(api.getNodes()).toEqual([]);
      });
    });

    describe('getEdges', () => {
      it('returns deep-cloned edges', () => {
        const edges = api.getEdges();
        expect(edges).toHaveLength(1);
        expect(edges[0].id).toBe('edge-1');
        expect(edges[0].source).toBe('node-1');
        expect(edges[0].target).toBe('node-2');
      });

      it('returns empty array when no edges', () => {
        graphStore.setState({ edges: [] });
        expect(api.getEdges()).toEqual([]);
      });
    });

    describe('getNodeById', () => {
      it('returns the node when it exists', () => {
        const node = api.getNodeById('node-1');
        expect(node).not.toBeNull();
        expect(node!.id).toBe('node-1');
      });

      it('returns null when node does not exist', () => {
        expect(api.getNodeById('nonexistent')).toBeNull();
      });
    });

    describe('getEdgeById', () => {
      it('returns the edge when it exists', () => {
        const edge = api.getEdgeById('edge-1');
        expect(edge).not.toBeNull();
        expect(edge!.id).toBe('edge-1');
      });

      it('returns null when edge does not exist', () => {
        expect(api.getEdgeById('nonexistent')).toBeNull();
      });
    });

    describe('getSelectedNode', () => {
      it('returns null when nothing selected', () => {
        expect(api.getSelectedNode()).toBeNull();
      });

      it('returns the selected node', () => {
        selectionStore.setState({ selectedNode: mockNode1 });
        const node = api.getSelectedNode();
        expect(node).not.toBeNull();
        expect(node!.id).toBe('node-1');
      });
    });

    describe('getSelectedNodes', () => {
      it('returns an empty array when nothing is selected', () => {
        expect(api.getSelectedNodes()).toEqual([]);
      });

      it('returns one node for a single selection', () => {
        selectionStore.setState({ selectedNodes: ['node-1'] });

        expect(api.getSelectedNodes()).toEqual([
          expect.objectContaining({ id: 'node-1' }),
        ]);
      });

      it('returns all selected nodes in selection-store order', () => {
        selectionStore.setState({ selectedNodes: ['node-2', 'node-1'] });

        expect(api.getSelectedNodes().map((node) => node.id)).toEqual([
          'node-2',
          'node-1',
        ]);
      });

      it('skips selected node IDs missing from the graph store', () => {
        selectionStore.setState({
          selectedNodes: ['node-2', 'missing-node', 'node-1'],
        });

        expect(api.getSelectedNodes().map((node) => node.id)).toEqual([
          'node-2',
          'node-1',
        ]);
      });
    });

    describe('getSelectedEdge', () => {
      it('returns null when nothing selected', () => {
        expect(api.getSelectedEdge()).toBeNull();
      });

      it('returns the selected edge', () => {
        selectionStore.setState({ selectedEdge: mockEdge1 });
        const edge = api.getSelectedEdge();
        expect(edge).not.toBeNull();
        expect(edge!.id).toBe('edge-1');
      });
    });

    describe('getSelectedEdges', () => {
      beforeEach(() => {
        graphStore.setState({ edges: [mockEdge1, mockEdge2] });
      });

      it('returns an empty array when nothing is selected', () => {
        expect(api.getSelectedEdges()).toEqual([]);
      });

      it('returns one edge for a single selection', () => {
        selectionStore.setState({ selectedEdges: ['edge-1'] });

        expect(api.getSelectedEdges()).toEqual([
          expect.objectContaining({ id: 'edge-1' }),
        ]);
      });

      it('returns all selected edges in selection-store order', () => {
        selectionStore.setState({ selectedEdges: ['edge-2', 'edge-1'] });

        expect(api.getSelectedEdges().map((edge) => edge.id)).toEqual([
          'edge-2',
          'edge-1',
        ]);
      });

      it('skips selected edge IDs missing from the graph store', () => {
        selectionStore.setState({
          selectedEdges: ['edge-2', 'missing-edge', 'edge-1'],
        });

        expect(api.getSelectedEdges().map((edge) => edge.id)).toEqual([
          'edge-2',
          'edge-1',
        ]);
      });
    });

    describe('getModelData', () => {
      it('returns deep-cloned model data', () => {
        const data = api.getModelData();
        expect(data).not.toBeNull();
        expect(data!.id).toBe('model-1');
        expect(data!.version).toBe('1.0.0');
        expect(data!.metadata).toEqual(
          expect.objectContaining({ label: 'Test Model' }),
        );
      });

      it('returns null when modelData is null', () => {
        modelStore.setState({ modelData: null });
        expect(api.getModelData()).toBeNull();
      });
    });

    describe('isReadOnly', () => {
      it('returns false by default', () => {
        expect(api.isReadOnly()).toBe(false);
      });

      it('returns true after setApiReadOnly(true)', () => {
        setApiReadOnly(true);
        expect(api.isReadOnly()).toBe(true);
      });
    });

    describe('isDarkMode', () => {
      it('returns the current dark mode state', () => {
        expect(api.isDarkMode()).toBe(false);
        uiSettingsStore.setState({ darkMode: true });
        expect(api.isDarkMode()).toBe(true);
      });
    });

    describe('getComponents', () => {
      it('returns deep-cloned components', () => {
        const comps = api.getComponents();
        expect(comps).toHaveLength(1);
        expect(comps[0].plugin).toBe('eca_content:create');
        expect(comps[0].label).toBe('Create Content');
      });

      it('returns empty array when no components', () => {
        componentStore.setState({ components: [] });
        expect(api.getComponents()).toEqual([]);
      });
    });

    describe('getComponentLabels', () => {
      it('returns deep-cloned component labels', () => {
        const labels = api.getComponentLabels();
        expect(labels.start).toBe('Event');
        expect(labels.element).toBe('Action');
      });
    });

    describe('getContexts', () => {
      it('returns deep-cloned contexts', () => {
        const contexts = api.getContexts();
        expect(contexts).toHaveLength(2);
        expect(contexts[0].id).toBe('ctx-1');
        expect(contexts[0].topic).toBe('Content');
        expect(contexts[0].model_owner).toBe('eca');
      });
    });

    describe('getSelectedContextId', () => {
      it('returns the selected context id', () => {
        expect(api.getSelectedContextId()).toBe('ctx-1');
      });

      it('returns null when no context is selected', () => {
        contextStore.setState({ selectedContextId: null });
        expect(api.getSelectedContextId()).toBeNull();
      });
    });

    describe('getFilteredNodeIds', () => {
      it('returns null when no filter active', () => {
        expect(api.getFilteredNodeIds()).toBeNull();
      });

      it('returns a copy of visible start node ids', () => {
        filterStore.setState({ visibleStartNodeIds: ['node-1', 'node-2'] });
        const ids = api.getFilteredNodeIds();
        expect(ids).toEqual(['node-1', 'node-2']);
        // Should be a copy, not the same reference
        expect(ids).not.toBe(filterStore.getState().visibleStartNodeIds);
      });
    });

    describe('getHistoryState', () => {
      it('returns canUndo/canRedo booleans', () => {
        const state = api.getHistoryState();
        expect(state.canUndo).toBe(true);
        expect(state.canRedo).toBe(false);
      });
    });

    describe('getErrors', () => {
      it('returns mapped error log', () => {
        const errors = api.getErrors();
        expect(errors).toHaveLength(2);
        expect(errors[0]).toEqual({ id: 'err-1', message: 'Something went wrong', dismissed: false });
        expect(errors[1]).toEqual({ id: 'err-2', message: 'Another error', dismissed: true });
      });

      it('maps dismissed correctly for falsy values', () => {
        errorStore.setState({
          errorLog: [
            { id: 'err-3', message: 'Undismissed', dismissed: false },
          ],
        });
        const errors = api.getErrors();
        expect(errors[0].dismissed).toBe(false);
      });
    });
  });

  // ── setApiReadOnly ────────────────────────────────────────────────────

  describe('setApiReadOnly', () => {
    it('updates the read-only flag', () => {
      setApiReadOnly(true);
      expect(api.isReadOnly()).toBe(true);
      setApiReadOnly(false);
      expect(api.isReadOnly()).toBe(false);
    });

    it('notifies listeners when the value changes', () => {
      const listener = jest.fn();
      api.onReadOnlyChange(listener);

      setApiReadOnly(true);
      expect(listener).toHaveBeenCalledWith(true);
      expect(listener).toHaveBeenCalledTimes(1);

      setApiReadOnly(false);
      expect(listener).toHaveBeenCalledWith(false);
      expect(listener).toHaveBeenCalledTimes(2);
    });

    it('does not notify when value is the same', () => {
      const listener = jest.fn();
      api.onReadOnlyChange(listener);

      setApiReadOnly(false); // already false
      expect(listener).not.toHaveBeenCalled();
    });

    it('handles callback errors gracefully', () => {
      const errorSpy = jest.spyOn(console, 'error').mockImplementation();
      const badListener = jest.fn(() => {
        throw new Error('boom');
      });
      const goodListener = jest.fn();

      const unsubBad = api.onReadOnlyChange(badListener);
      const unsubGood = api.onReadOnlyChange(goodListener);

      setApiReadOnly(true);

      expect(badListener).toHaveBeenCalled();
      expect(goodListener).toHaveBeenCalled();
      expect(errorSpy).toHaveBeenCalledWith(
        'Plugin onReadOnlyChange callback error:',
        expect.any(Error),
      );

      // Clean up to prevent leaking into other tests
      unsubBad();
      unsubGood();
      errorSpy.mockRestore();
    });
  });

  // ── setMutationHooks / clearMutationHooks ─────────────────────────────

  describe('setMutationHooks / clearMutationHooks', () => {
    it('registers hooks that are called during mutations', () => {
      const saveHistory = jest.fn();
      const markUnsaved = jest.fn();
      const autoLayoutFn = jest.fn();

      setMutationHooks({ saveHistory, markUnsaved, autoLayout: autoLayoutFn });

      // addNode triggers beforeMutation (saveHistory) and afterMutation (markUnsaved)
      api.addNode({
        plugin: 'example.test',
        componentType: 4,
        label: 'Test',
        position: { x: 0, y: 0 },
      });

      expect(saveHistory).toHaveBeenCalledTimes(1);
      expect(markUnsaved).toHaveBeenCalledTimes(1);
    });

    it('clearMutationHooks prevents hooks from firing', () => {
      const saveHistory = jest.fn();
      const markUnsaved = jest.fn();
      const autoLayoutFn = jest.fn();

      setMutationHooks({ saveHistory, markUnsaved, autoLayout: autoLayoutFn });
      clearMutationHooks();

      api.addNode({
        plugin: 'example.test',
        componentType: 4,
        label: 'Test',
        position: { x: 0, y: 0 },
      });

      expect(saveHistory).not.toHaveBeenCalled();
      expect(markUnsaved).not.toHaveBeenCalled();
    });
  });

  // ── Event subscriptions ───────────────────────────────────────────────

  describe('event subscriptions', () => {
    describe('onSelectionChange', () => {
      it('fires callback when selected node changes', () => {
        const cb = jest.fn();
        api.onSelectionChange(cb);

        selectionStore.setState({ selectedNode: mockNode1, selectedEdge: null });
        selectionStore._notify();

        expect(cb).toHaveBeenCalledTimes(1);
        expect(cb).toHaveBeenCalledWith(
          expect.objectContaining({ id: 'node-1' }),
          null,
        );
      });

      it('fires callback when selected edge changes', () => {
        const cb = jest.fn();
        api.onSelectionChange(cb);

        selectionStore.setState({ selectedNode: null, selectedEdge: mockEdge1 });
        selectionStore._notify();

        expect(cb).toHaveBeenCalledTimes(1);
        expect(cb).toHaveBeenCalledWith(
          null,
          expect.objectContaining({ id: 'edge-1' }),
        );
      });

      it('does not fire if selection did not actually change', () => {
        const cb = jest.fn();
        api.onSelectionChange(cb);

        // First notification with node-1
        selectionStore.setState({ selectedNode: mockNode1 });
        selectionStore._notify();
        expect(cb).toHaveBeenCalledTimes(1);

        // Same node again - no change
        selectionStore._notify();
        expect(cb).toHaveBeenCalledTimes(1);
      });

      it('fires when the multi-selection grows without changing the primary node', () => {
        const cb = jest.fn();
        api.onSelectionChange(cb);

        selectionStore.setState({
          selectedNode: mockNode1,
          selectedNodes: ['node-1'],
        });
        selectionStore._notify();
        cb.mockClear();

        selectionStore.setState({ selectedNodes: ['node-1', 'node-2'] });
        selectionStore._notify();

        expect(cb).toHaveBeenCalledTimes(1);
        expect(cb).toHaveBeenCalledWith(
          expect.objectContaining({ id: 'node-1' }),
          null,
        );
      });

      it('unsubscribe stops future callbacks', () => {
        const cb = jest.fn();
        const unsub = api.onSelectionChange(cb);

        selectionStore.setState({ selectedNode: mockNode1 });
        selectionStore._notify();
        expect(cb).toHaveBeenCalledTimes(1);

        unsub();
        selectionStore.setState({ selectedNode: mockNode2 });
        selectionStore._notify();
        expect(cb).toHaveBeenCalledTimes(1); // still 1
      });

      it('handles callback errors gracefully', () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation();
        const bad = jest.fn(() => { throw new Error('selection boom'); });
        api.onSelectionChange(bad);

        selectionStore.setState({ selectedNode: mockNode1 });
        selectionStore._notify();

        expect(errorSpy).toHaveBeenCalledWith(
          'Plugin onSelectionChange callback error:',
          expect.any(Error),
        );
        errorSpy.mockRestore();
      });
    });

    describe('onNodesChange', () => {
      it('fires when node count changes', () => {
        const cb = jest.fn();
        api.onNodesChange(cb);

        graphStore.setState({ nodes: [mockNode1] });
        graphStore._notify();

        expect(cb).toHaveBeenCalledTimes(1);
        expect(cb).toHaveBeenCalledWith([expect.objectContaining({ id: 'node-1' })]);
      });

      it('fires when first node id changes', () => {
        const cb = jest.fn();
        api.onNodesChange(cb);

        // Initial call sets prevLength/prevFirstId
        graphStore._notify();
        expect(cb).toHaveBeenCalledTimes(1);

        // Swap first node
        graphStore.setState({ nodes: [mockNode2, mockNode1] });
        graphStore._notify();
        expect(cb).toHaveBeenCalledTimes(2);
      });

      it('does not fire when nothing changed', () => {
        const cb = jest.fn();
        api.onNodesChange(cb);

        // First call establishes baseline
        graphStore._notify();
        expect(cb).toHaveBeenCalledTimes(1);

        // Same state again
        graphStore._notify();
        expect(cb).toHaveBeenCalledTimes(1);
      });

      it('unsubscribe stops future callbacks', () => {
        const cb = jest.fn();
        const unsub = api.onNodesChange(cb);

        graphStore._notify();
        expect(cb).toHaveBeenCalledTimes(1);

        unsub();
        graphStore.setState({ nodes: [] });
        graphStore._notify();
        expect(cb).toHaveBeenCalledTimes(1);
      });

      it('handles callback errors gracefully', () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation();
        const bad = jest.fn(() => { throw new Error('nodes boom'); });
        api.onNodesChange(bad);

        graphStore._notify();

        expect(errorSpy).toHaveBeenCalledWith(
          'Plugin onNodesChange callback error:',
          expect.any(Error),
        );
        errorSpy.mockRestore();
      });
    });

    describe('onEdgesChange', () => {
      it('fires when edge count changes', () => {
        const cb = jest.fn();
        api.onEdgesChange(cb);

        graphStore.setState({ edges: [] });
        graphStore._notify();

        expect(cb).toHaveBeenCalledTimes(1);
        expect(cb).toHaveBeenCalledWith([]);
      });

      it('fires when first edge id changes', () => {
        const cb = jest.fn();
        api.onEdgesChange(cb);

        // Initial call
        graphStore._notify();
        expect(cb).toHaveBeenCalledTimes(1);

        const mockEdge2: StoreEdge = {
          id: 'edge-2',
          source: 'node-2',
          target: 'node-1',
          type: 'default',
        };
        graphStore.setState({ edges: [mockEdge2, mockEdge1] });
        graphStore._notify();
        expect(cb).toHaveBeenCalledTimes(2);
      });

      it('does not fire when nothing changed', () => {
        const cb = jest.fn();
        api.onEdgesChange(cb);

        graphStore._notify();
        expect(cb).toHaveBeenCalledTimes(1);

        // Same state again
        graphStore._notify();
        expect(cb).toHaveBeenCalledTimes(1);
      });

      it('handles callback errors gracefully', () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation();
        api.onEdgesChange(jest.fn(() => { throw new Error('edges boom'); }));
        graphStore._notify();
        expect(errorSpy).toHaveBeenCalledWith(
          'Plugin onEdgesChange callback error:',
          expect.any(Error),
        );
        errorSpy.mockRestore();
      });
    });

    describe('onModelDataChange', () => {
      it('fires when model id changes', () => {
        const cb = jest.fn();
        api.onModelDataChange(cb);

        modelStore.setState({
          modelData: { id: 'model-2', version: '1.0.0', metadata: {} },
        });
        modelStore._notify();

        expect(cb).toHaveBeenCalledTimes(1);
        expect(cb).toHaveBeenCalledWith(
          expect.objectContaining({ id: 'model-2' }),
        );
      });

      it('fires when model version changes', () => {
        const cb = jest.fn();
        api.onModelDataChange(cb);

        modelStore.setState({
          modelData: { id: 'model-1', version: '2.0.0', metadata: {} },
        });
        modelStore._notify();

        expect(cb).toHaveBeenCalledTimes(1);
      });

      it('does not fire when id and version are unchanged', () => {
        const cb = jest.fn();
        api.onModelDataChange(cb);

        // First call sets baseline
        modelStore._notify();
        expect(cb).toHaveBeenCalledTimes(1);

        // Same id and version
        modelStore._notify();
        expect(cb).toHaveBeenCalledTimes(1);
      });

      it('fires with null when modelData becomes null', () => {
        const cb = jest.fn();
        api.onModelDataChange(cb);

        // First notify establishes baseline (prevId = 'model-1')
        modelStore._notify();
        expect(cb).toHaveBeenCalledTimes(1);

        // Now set modelData to null — id changes from 'model-1' to undefined
        modelStore.setState({ modelData: null });
        modelStore._notify();

        expect(cb).toHaveBeenCalledTimes(2);
        expect(cb).toHaveBeenLastCalledWith(null);
      });

      it('handles callback errors gracefully', () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation();
        api.onModelDataChange(jest.fn(() => { throw new Error('model boom'); }));
        modelStore.setState({ modelData: { id: 'changed', version: '9.9' } });
        modelStore._notify();
        expect(errorSpy).toHaveBeenCalledWith(
          'Plugin onModelDataChange callback error:',
          expect.any(Error),
        );
        errorSpy.mockRestore();
      });
    });

    describe('onDarkModeChange', () => {
      it('fires when dark mode changes', () => {
        const cb = jest.fn();
        api.onDarkModeChange(cb);

        uiSettingsStore.setState({ darkMode: true });
        uiSettingsStore._notify();

        expect(cb).toHaveBeenCalledWith(true);
      });

      it('does not fire when value is unchanged', () => {
        const cb = jest.fn();
        api.onDarkModeChange(cb);

        // First call sets baseline
        uiSettingsStore._notify();
        expect(cb).toHaveBeenCalledTimes(1);

        // Same value
        uiSettingsStore._notify();
        expect(cb).toHaveBeenCalledTimes(1);
      });

      it('handles callback errors gracefully', () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation();
        api.onDarkModeChange(jest.fn(() => { throw new Error('dark boom'); }));
        uiSettingsStore.setState({ darkMode: true });
        uiSettingsStore._notify();
        expect(errorSpy).toHaveBeenCalledWith(
          'Plugin onDarkModeChange callback error:',
          expect.any(Error),
        );
        errorSpy.mockRestore();
      });
    });

    describe('onContextChange', () => {
      it('fires when selected context changes', () => {
        const cb = jest.fn();
        api.onContextChange(cb);

        contextStore.setState({ selectedContextId: 'ctx-2' });
        contextStore._notify();

        expect(cb).toHaveBeenCalledWith('ctx-2');
      });

      it('does not fire when unchanged', () => {
        const cb = jest.fn();
        api.onContextChange(cb);

        contextStore._notify();
        expect(cb).toHaveBeenCalledTimes(1);

        contextStore._notify();
        expect(cb).toHaveBeenCalledTimes(1);
      });

      it('handles callback errors gracefully', () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation();
        api.onContextChange(jest.fn(() => { throw new Error('ctx boom'); }));
        contextStore.setState({ selectedContextId: 'ctx-2' });
        contextStore._notify();
        expect(errorSpy).toHaveBeenCalledWith(
          'Plugin onContextChange callback error:',
          expect.any(Error),
        );
        errorSpy.mockRestore();
      });
    });

    describe('onComponentsChange', () => {
      it('fires when components list length changes', () => {
        const cb = jest.fn();
        api.onComponentsChange(cb);

        componentStore.setState({ components: [] });
        componentStore._notify();

        expect(cb).toHaveBeenCalledTimes(1);
        expect(cb).toHaveBeenCalledWith([]);
      });

      it('does not fire when list length unchanged', () => {
        const cb = jest.fn();
        api.onComponentsChange(cb);

        componentStore._notify();
        expect(cb).toHaveBeenCalledTimes(1);

        componentStore._notify();
        expect(cb).toHaveBeenCalledTimes(1);
      });

      it('handles callback errors gracefully', () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation();
        api.onComponentsChange(jest.fn(() => { throw new Error('comp boom'); }));
        componentStore.setState({ components: [] });
        componentStore._notify();
        expect(errorSpy).toHaveBeenCalledWith(
          'Plugin onComponentsChange callback error:',
          expect.any(Error),
        );
        errorSpy.mockRestore();
      });
    });

    describe('onReadOnlyChange', () => {
      it('fires when read-only state changes', () => {
        const cb = jest.fn();
        api.onReadOnlyChange(cb);

        setApiReadOnly(true);
        expect(cb).toHaveBeenCalledWith(true);

        setApiReadOnly(false);
        expect(cb).toHaveBeenCalledWith(false);
      });

      it('unsubscribe stops future callbacks', () => {
        const cb = jest.fn();
        const unsub = api.onReadOnlyChange(cb);

        setApiReadOnly(true);
        expect(cb).toHaveBeenCalledTimes(1);

        unsub();
        setApiReadOnly(false);
        expect(cb).toHaveBeenCalledTimes(1);
      });
    });
  });

  // ── Selection & viewport actions ──────────────────────────────────────

  describe('selection & viewport actions', () => {
    describe('selectNode', () => {
      it('selects an existing node', () => {
        api.selectNode('node-1');
        expect(selectionStore.getState().selectNode).toHaveBeenCalledWith(mockNode1);
      });

      it('does nothing when node does not exist', () => {
        api.selectNode('nonexistent');
        expect(selectionStore.getState().selectNode).not.toHaveBeenCalled();
      });

      it('clears selection when null is passed', () => {
        api.selectNode(null);
        expect(selectionStore.getState().clearSelection).toHaveBeenCalled();
      });

      it('is a no-op in read-only mode', () => {
        setApiReadOnly(true);
        api.selectNode('node-1');
        expect(selectionStore.getState().selectNode).not.toHaveBeenCalled();
        expect(selectionStore.getState().clearSelection).not.toHaveBeenCalled();
      });
    });

    describe('selectEdge', () => {
      it('selects an existing edge', () => {
        api.selectEdge('edge-1');
        expect(selectionStore.getState().selectEdge).toHaveBeenCalledWith(mockEdge1);
      });

      it('does nothing when edge does not exist', () => {
        api.selectEdge('nonexistent');
        expect(selectionStore.getState().selectEdge).not.toHaveBeenCalled();
      });

      it('clears selection when null is passed', () => {
        api.selectEdge(null);
        expect(selectionStore.getState().clearSelection).toHaveBeenCalled();
      });

      it('is a no-op in read-only mode', () => {
        setApiReadOnly(true);
        api.selectEdge('edge-1');
        expect(selectionStore.getState().selectEdge).not.toHaveBeenCalled();
      });
    });

    describe('clearSelection', () => {
      it('clears the selection', () => {
        api.clearSelection();
        expect(selectionStore.getState().clearSelection).toHaveBeenCalled();
      });
    });

    describe('focusNode', () => {
      it('calls the viewport focusNode hook for existing node', () => {
        const mockFocusNode = jest.fn();
        setViewportHooks({ focusNode: mockFocusNode, fitView: jest.fn() });

        api.focusNode('node-1');
        expect(mockFocusNode).toHaveBeenCalledWith('node-1');
      });

      it('does nothing when node does not exist', () => {
        const mockFocusNode = jest.fn();
        setViewportHooks({ focusNode: mockFocusNode, fitView: jest.fn() });

        api.focusNode('nonexistent');
        expect(mockFocusNode).not.toHaveBeenCalled();
      });

      it('does nothing when no viewport hooks are registered', () => {
        clearViewportHooks();
        // Should not throw
        expect(() => api.focusNode('node-1')).not.toThrow();
      });
    });

    describe('fitView', () => {
      it('calls the viewport fitView hook', () => {
        const mockFitView = jest.fn();
        setViewportHooks({ focusNode: jest.fn(), fitView: mockFitView });

        api.fitView();
        expect(mockFitView).toHaveBeenCalledTimes(1);
      });

      it('does nothing when no viewport hooks are registered', () => {
        clearViewportHooks();
        // Should not throw
        expect(() => api.fitView()).not.toThrow();
      });
    });
  });

  // ── Node mutations ────────────────────────────────────────────────────

  describe('node mutations', () => {
    const saveHistory = jest.fn();
    const markUnsaved = jest.fn();
    const autoLayoutFn = jest.fn();

    beforeEach(() => {
      saveHistory.mockClear();
      markUnsaved.mockClear();
      autoLayoutFn.mockClear();
      setMutationHooks({ saveHistory, markUnsaved, autoLayout: autoLayoutFn });
    });

    describe('addNode', () => {
      it('creates a node with explicit position', () => {
        (generateNodeId as jest.Mock).mockReturnValue('element_Test_123');

        const result = api.addNode({
          plugin: 'example.test',
          componentType: 4,
          label: 'Test',
          position: { x: 50, y: 60 },
        });

        expect(result).toBe('element_Test_123');
        expect(resolveNodeType).toHaveBeenCalledWith(4);
        expect(generateNodeId).toHaveBeenCalledWith('Test', 'element');
        expect(graphStore.getState().addNode).toHaveBeenCalledWith(
          expect.objectContaining({
            id: 'element_Test_123',
            type: 'element',
            position: { x: 50, y: 60 },
            data: expect.objectContaining({
              plugin: 'example.test',
              label: 'Test',
              componentType: 4,
              configuration: {},
            }),
          }),
        );
        expect(saveHistory).toHaveBeenCalledTimes(1);
        expect(markUnsaved).toHaveBeenCalledTimes(1);
      });

      it('derives label from plugin when label is omitted', () => {
        (generateNodeId as jest.Mock).mockReturnValue('element_test_123');

        api.addNode({
          plugin: 'example.test',
          componentType: 4,
        });

        expect(graphStore.getState().addNode).toHaveBeenCalledWith(
          expect.objectContaining({
            data: expect.objectContaining({ label: 'test' }),
          }),
        );
      });

      it('falls back to "New Node" when plugin has no last segment', () => {
        (generateNodeId as jest.Mock).mockReturnValue('element_NewNode_123');

        // empty string plugin split results in [''] where pop() gives ''
        api.addNode({
          plugin: '',
          componentType: 4,
          label: '',
        });

        expect(graphStore.getState().addNode).toHaveBeenCalledWith(
          expect.objectContaining({
            data: expect.objectContaining({ label: 'New Node' }),
          }),
        );
      });

      it('auto-positions when position is omitted and nodes exist', () => {
        (generateNodeId as jest.Mock).mockReturnValue('element_Test_123');

        api.addNode({
          plugin: 'example.test',
          componentType: 4,
          label: 'Test',
        });

        expect(findFreePosition).toHaveBeenCalledWith(
          expect.objectContaining({ x: expect.any(Number), y: expect.any(Number) }),
          expect.any(Array),
          expect.any(Number),
          expect.any(Number),
        );
      });

      it('includes configuration, description, documentationUrl', () => {
        (generateNodeId as jest.Mock).mockReturnValue('element_Test_123');

        api.addNode({
          plugin: 'example.test',
          componentType: 4,
          label: 'Test',
          position: { x: 0, y: 0 },
          configuration: { foo: 'bar' },
          description: 'A description',
          documentationUrl: 'https://example.com/docs',
        });

        expect(graphStore.getState().addNode).toHaveBeenCalledWith(
          expect.objectContaining({
            data: expect.objectContaining({
              configuration: { foo: 'bar' },
              description: 'A description',
              documentationUrl: 'https://example.com/docs',
            }),
          }),
        );
      });

      it('returns null in read-only mode', () => {
        setApiReadOnly(true);
        const result = api.addNode({
          plugin: 'example.test',
          componentType: 4,
          position: { x: 0, y: 0 },
        });
        expect(result).toBeNull();
        expect(graphStore.getState().addNode).not.toHaveBeenCalled();
        expect(saveHistory).not.toHaveBeenCalled();
      });

      // ── Shared placement primitives (issue #3589110) ─────────────────
      //
      // addNode used to hand-roll its own placement instead of calling the
      // primitives in utils/incrementalLayout.ts, so a plugin-added node
      // could land somewhere the UI would never have put it.  These tests
      // pin the node to the SAME coordinates the equivalent UI path
      // produces, which is the actual point of the issue.
      describe('placement via shared layout primitives', () => {
        /** Read the node handed to graphStore.addNode by the last call. */
        function addedNode(): StoreNode {
          const calls = (graphStore.getState().addNode as jest.Mock).mock.calls;
          return calls[calls.length - 1][0] as StoreNode;
        }

        it('uses an explicit position verbatim, ignoring sourceNodeId', () => {
          // Explicit caller intent always wins — unchanged contract.
          api.addNode({
            plugin: 'example.test',
            componentType: 4,
            label: 'Test',
            position: { x: 50, y: 60 },
            sourceNodeId: 'node-1',
          });

          expect(addedNode().position).toEqual({ x: 50, y: 60 });
          // No successor placement ran, so no flow shifts were applied.
          expect(graphStore.getState().setNodes).not.toHaveBeenCalled();
        });

        it('places the first node on an empty canvas where the UI would', () => {
          // Regression: addNode used to return DEFAULT_POSITION (100,100)
          // while every UI path anchors the first flow at LAYOUT_START
          // (400,100).  Both must now agree.
          graphStore.setState({ nodes: [], edges: [] });

          api.addNode({ plugin: 'example.test', componentType: 1, label: 'Test' });

          const expected = computeNewEventPosition({ nodes: [], width: 180, height: 120 });
          expect(expected).toEqual({ x: 400, y: 100 });
          expect(addedNode().position).toEqual(expected);
        });

        it('places a new event flow to the right of existing flows', () => {
          api.addNode({ plugin: 'example.test', componentType: 4, label: 'Test' });

          const expected = computeNewEventPosition({
            nodes: [mockNode1, mockNode2],
            width: 180,
            height: 120,
          });
          expect(addedNode().position).toEqual(expected);
        });

        it('uses the compact gateway footprint for gateway nodes', () => {
          // Gateways are 60px tall, not 120 — feeding the card height into
          // the primitives would reserve room the node does not occupy.
          const findFree = findFreePosition as jest.Mock;
          api.addNode({ plugin: 'example.gateway', componentType: 6, label: 'GW' });

          expect(findFree).toHaveBeenCalledWith(
            expect.anything(),
            expect.any(Array),
            180, // NODE_DIMENSIONS.CARD_WIDTH
            60,  // NODE_DIMENSIONS.GATEWAY_HEIGHT
          );
        });

        it('places a successor exactly where quick-add would place it', () => {
          // useQuickAdd.addSuccessorNode calls computeSuccessorPosition with
          // precisely these arguments, so matching it proves the two paths
          // now agree coordinate-for-coordinate.
          api.addNode({
            plugin: 'example.test',
            componentType: 4,
            label: 'Test',
            sourceNodeId: 'node-1',
          });

          const expected = computeSuccessorPosition({
            nodes: [mockNode1, mockNode2],
            edges: [mockEdge1],
            sourceNodeId: 'node-1',
          }).position;

          expect(addedNode().position).toEqual(expected);
          // Below the parent, and column-aligned with it.
          expect(addedNode().position.y).toBeGreaterThan(mockNode1.position.y);
          expect(addedNode().position.x).toBe(mockNode1.position.x);
        });

        it('returns null and does not mutate for an unknown sourceNodeId', () => {
          // Consistent with addEdge(), which rejects unknown endpoints.
          const result = api.addNode({
            plugin: 'example.test',
            componentType: 4,
            label: 'Test',
            sourceNodeId: 'does-not-exist',
          });

          expect(result).toBeNull();
          expect(graphStore.getState().addNode).not.toHaveBeenCalled();
          expect(graphStore.getState().setNodes).not.toHaveBeenCalled();
          // Crucially: no history entry for an operation that never happened.
          expect(saveHistory).not.toHaveBeenCalled();
          expect(markUnsaved).not.toHaveBeenCalled();
        });

        it('applies flow shifts, without clobbering the selection', () => {
          // Source flow at x=100; a second, unconnected flow sits directly in
          // the successor slot and close enough on the right that the
          // primitive must push it aside to make room.
          const source: StoreNode = {
            id: 'src',
            type: 'element',
            position: { x: 100, y: 100 },
            data: { label: 'Src', plugin: 'p.src', componentType: 4 },
          };
          const neighbor: StoreNode = {
            id: 'blk',
            type: 'element',
            position: { x: 150, y: 320 },
            selected: true,
            data: { label: 'Blk', plugin: 'p.blk', componentType: 4 },
          };
          graphStore.setState({ nodes: [source, neighbor], edges: [] });

          const expected = computeSuccessorPosition({
            nodes: [source, neighbor],
            edges: [],
            sourceNodeId: 'src',
          });
          // Guard the fixture: this scenario is only meaningful if the
          // primitive actually asks for a shift.
          expect(expected.shiftAmount).toBeGreaterThan(0);
          expect(expected.shiftNodeIds.has('blk')).toBe(true);

          api.addNode({
            plugin: 'example.test',
            componentType: 4,
            label: 'Test',
            sourceNodeId: 'src',
          });

          expect(addedNode().position).toEqual(expected.position);

          // The shift is applied through setNodes, as an updater function.
          const setNodes = graphStore.getState().setNodes as jest.Mock;
          expect(setNodes).toHaveBeenCalledTimes(1);
          const updater = setNodes.mock.calls[0][0] as (n: StoreNode[]) => StoreNode[];
          const shifted = updater([source, neighbor]);

          const movedNeighbor = shifted.find((n) => n.id === 'blk')!;
          expect(movedNeighbor.position.x).toBe(150 + expected.shiftAmount);
          // The source flow is untouched.
          expect(shifted.find((n) => n.id === 'src')!.position).toEqual({ x: 100, y: 100 });
          // A background plugin call must NOT deselect the user's selection
          // (quick-add deselects because it selects the new node instead).
          expect(movedNeighbor.selected).toBe(true);
        });
      });
    });

    describe('updateNode', () => {
      it('updates label', () => {
        const result = api.updateNode('node-1', { label: 'Updated' });
        expect(result).toBe(true);
        expect(graphStore.getState().updateNode).toHaveBeenCalledWith(
          'node-1',
          expect.objectContaining({
            data: expect.objectContaining({ label: 'Updated' }),
          }),
        );
        expect(saveHistory).toHaveBeenCalledTimes(1);
        expect(markUnsaved).toHaveBeenCalledTimes(1);
      });

      it('updates position', () => {
        api.updateNode('node-1', { position: { x: 500, y: 600 } });
        expect(graphStore.getState().updateNode).toHaveBeenCalledWith(
          'node-1',
          expect.objectContaining({
            position: { x: 500, y: 600 },
          }),
        );
      });

      it('merges configuration with existing', () => {
        api.updateNode('node-1', { configuration: { newKey: 'newValue' } });
        expect(graphStore.getState().updateNode).toHaveBeenCalledWith(
          'node-1',
          expect.objectContaining({
            data: expect.objectContaining({
              configuration: { key: 'value', newKey: 'newValue' },
            }),
          }),
        );
      });

      it('updates annotation', () => {
        api.updateNode('node-1', { annotation: 'A note' });
        expect(graphStore.getState().updateNode).toHaveBeenCalledWith(
          'node-1',
          expect.objectContaining({
            data: expect.objectContaining({ annotation: 'A note' }),
          }),
        );
      });

      it('handles update with no data changes (position only)', () => {
        api.updateNode('node-1', { position: { x: 10, y: 20 } });
        // Should NOT include data key in storeUpdates if no data fields changed
        const call = (graphStore.getState().updateNode as jest.Mock).mock.calls[0];
        expect(call[1].position).toEqual({ x: 10, y: 20 });
        // data should not be set since no data fields were specified
        expect(call[1].data).toBeUndefined();
      });

      it('returns false when node does not exist', () => {
        const result = api.updateNode('nonexistent', { label: 'X' });
        expect(result).toBe(false);
        expect(graphStore.getState().updateNode).not.toHaveBeenCalled();
      });

      it('returns false in read-only mode', () => {
        setApiReadOnly(true);
        const result = api.updateNode('node-1', { label: 'X' });
        expect(result).toBe(false);
        expect(graphStore.getState().updateNode).not.toHaveBeenCalled();
      });

      it('merges configuration with empty existing config', () => {
        // Node with no configuration
        const nodeNoConfig: StoreNode = {
          id: 'node-noconfig',
          type: 'element',
          position: { x: 0, y: 0 },
          data: { label: 'No Config' },
        };
        graphStore.setState({ nodes: [nodeNoConfig] });

        api.updateNode('node-noconfig', { configuration: { a: 1 } });
        expect(graphStore.getState().updateNode).toHaveBeenCalledWith(
          'node-noconfig',
          expect.objectContaining({
            data: expect.objectContaining({
              configuration: { a: 1 },
            }),
          }),
        );
      });
    });

    describe('removeNode', () => {
      it('removes an existing node', () => {
        const result = api.removeNode('node-1');
        expect(result).toBe(true);
        expect(graphStore.getState().removeNode).toHaveBeenCalledWith('node-1');
        expect(saveHistory).toHaveBeenCalledTimes(1);
        expect(markUnsaved).toHaveBeenCalledTimes(1);
      });

      it('clears selection if the removed node was selected', () => {
        selectionStore.setState({ selectedNode: mockNode1 });

        api.removeNode('node-1');
        expect(selectionStore.getState().clearSelection).toHaveBeenCalled();
      });

      it('does not clear selection if a different node was selected', () => {
        selectionStore.setState({ selectedNode: mockNode2 });

        api.removeNode('node-1');
        expect(selectionStore.getState().clearSelection).not.toHaveBeenCalled();
      });

      it('returns false when node does not exist', () => {
        const result = api.removeNode('nonexistent');
        expect(result).toBe(false);
        expect(graphStore.getState().removeNode).not.toHaveBeenCalled();
      });

      it('returns false in read-only mode', () => {
        setApiReadOnly(true);
        const result = api.removeNode('node-1');
        expect(result).toBe(false);
        expect(graphStore.getState().removeNode).not.toHaveBeenCalled();
      });
    });
  });

  // ── Edge mutations ────────────────────────────────────────────────────

  describe('edge mutations', () => {
    const saveHistory = jest.fn();
    const markUnsaved = jest.fn();
    const autoLayoutFn = jest.fn();

    beforeEach(() => {
      saveHistory.mockClear();
      markUnsaved.mockClear();
      autoLayoutFn.mockClear();
      setMutationHooks({ saveHistory, markUnsaved, autoLayout: autoLayoutFn });
    });

    describe('addEdge', () => {
      it('creates an edge between two existing nodes', () => {
        (generateEdgeId as jest.Mock).mockReturnValue('edge_node-1_node-2');
        // Start with a clean edge list so the new edge is the only one and
        // does not collide with any existing parallel connection.
        graphStore.setState({ edges: [] });

        const result = api.addEdge('node-1', 'node-2');
        expect(result).toBe('edge_node-1_node-2');
        expect(graphStore.getState().addEdge).toHaveBeenCalledWith(
          expect.objectContaining({
            id: 'edge_node-1_node-2',
            source: 'node-1',
            target: 'node-2',
            type: 'default',
            data: {},
          }),
        );
        expect(saveHistory).toHaveBeenCalledTimes(1);
        expect(markUnsaved).toHaveBeenCalledTimes(1);
      });

      it('routes a parallel edge with a sideways controlOffset', () => {
        (generateEdgeId as jest.Mock).mockReturnValue('edge_node-1_node-2_b');
        // Fixture already contains mockEdge1 (node-1 → node-2), so adding a
        // second edge between the same endpoints triggers fan-out routing.
        graphStore.setState({
          edges: [
            { id: 'edge-1', source: 'node-1', target: 'node-2', type: 'default', data: {} },
          ] as StoreEdge[],
        });

        const result = api.addEdge('node-1', 'node-2');
        expect(result).toBe('edge_node-1_node-2_b');

        // The new edge must arrive with a non-zero controlOffset.x, distinct
        // from the existing edge (which has been rebalanced to the other side).
        const addEdgeCalls = (graphStore.getState().addEdge as jest.Mock).mock.calls;
        const newEdgeArg = addEdgeCalls[addEdgeCalls.length - 1][0];
        expect(newEdgeArg.id).toBe('edge_node-1_node-2_b');
        expect(newEdgeArg.data.controlOffset).toBeDefined();
        expect(newEdgeArg.data.controlOffset.x).not.toBe(0);

        // The router must also rebalance the existing sibling via setEdges.
        expect(graphStore.getState().setEdges).toHaveBeenCalled();
      });

      it('returns null when source node does not exist', () => {
        const result = api.addEdge('nonexistent', 'node-2');
        expect(result).toBeNull();
        expect(graphStore.getState().addEdge).not.toHaveBeenCalled();
      });

      it('returns null when target node does not exist', () => {
        const result = api.addEdge('node-1', 'nonexistent');
        expect(result).toBeNull();
        expect(graphStore.getState().addEdge).not.toHaveBeenCalled();
      });

      it('returns null when both nodes do not exist', () => {
        const result = api.addEdge('fake-1', 'fake-2');
        expect(result).toBeNull();
      });

      it('returns null in read-only mode', () => {
        setApiReadOnly(true);
        const result = api.addEdge('node-1', 'node-2');
        expect(result).toBeNull();
        expect(graphStore.getState().addEdge).not.toHaveBeenCalled();
      });
    });

    describe('updateEdge', () => {
      it('updates annotation', () => {
        api.updateEdge('edge-1', { annotation: 'Edge note' });
        expect(graphStore.getState().updateEdge).toHaveBeenCalledWith(
          'edge-1',
          expect.objectContaining({
            data: expect.objectContaining({ annotation: 'Edge note' }),
          }),
        );
        expect(saveHistory).toHaveBeenCalledTimes(1);
        expect(markUnsaved).toHaveBeenCalledTimes(1);
      });

      it('handles empty updates (no data changes)', () => {
        api.updateEdge('edge-1', {});
        // updateEdge is still called but with empty storeUpdates
        expect(graphStore.getState().updateEdge).toHaveBeenCalledWith('edge-1', {});
      });

      it('returns false when edge does not exist', () => {
        const result = api.updateEdge('nonexistent', { annotation: 'note' });
        expect(result).toBe(false);
      });

      it('returns false in read-only mode', () => {
        setApiReadOnly(true);
        const result = api.updateEdge('edge-1', { annotation: 'note' });
        expect(result).toBe(false);
      });

      it('merges with existing edge data', () => {
        api.updateEdge('edge-1', { annotation: 'note' });
        const call = (graphStore.getState().updateEdge as jest.Mock).mock.calls[0];
        // data should include existing condition: null plus new annotation
        expect(call[1].data).toEqual({ condition: null, annotation: 'note' });
      });
    });

    describe('removeEdge', () => {
      it('removes an existing edge', () => {
        const result = api.removeEdge('edge-1');
        expect(result).toBe(true);
        expect(graphStore.getState().removeEdge).toHaveBeenCalledWith('edge-1');
        expect(saveHistory).toHaveBeenCalledTimes(1);
        expect(markUnsaved).toHaveBeenCalledTimes(1);
      });

      it('clears selection if the removed edge was selected', () => {
        selectionStore.setState({ selectedEdge: mockEdge1 });
        api.removeEdge('edge-1');
        expect(selectionStore.getState().clearSelection).toHaveBeenCalled();
      });

      it('does not clear selection if a different edge was selected', () => {
        const otherEdge: StoreEdge = { id: 'edge-other', source: 'a', target: 'b', type: 'default' };
        selectionStore.setState({ selectedEdge: otherEdge });
        api.removeEdge('edge-1');
        expect(selectionStore.getState().clearSelection).not.toHaveBeenCalled();
      });

      it('returns false when edge does not exist', () => {
        const result = api.removeEdge('nonexistent');
        expect(result).toBe(false);
      });

      it('returns false in read-only mode', () => {
        setApiReadOnly(true);
        const result = api.removeEdge('edge-1');
        expect(result).toBe(false);
      });
    });

    describe('setCondition', () => {
      // Conditions are first-class nodes now (issue #3589093): setCondition
      // INSERTS a condition node on the edge (mirroring handleAddCondition)
      // instead of mutating the edge.  It replaces the target edge with two
      // plain edges around a new `type: 'condition'` node.

      it('inserts a condition node on an existing edge', () => {
        const result = api.setCondition('edge-1', {
          plugin: 'eca_condition:entity_type',
          label: 'Entity Type',
          configuration: { type: 'node' },
        });

        expect(result).toBe(true);

        // A condition node is created via setNodes.
        const setNodesArg = (graphStore.getState().setNodes as jest.Mock).mock.calls[0][0] as StoreNode[];
        const condNode = setNodesArg.find((n) => n.type === 'condition');
        expect(condNode).toBeDefined();
        expect(condNode).toEqual(
          expect.objectContaining({
            id: 'condition_Entity Type_123',
            type: 'condition',
            data: expect.objectContaining({
              label: 'Entity Type',
              plugin: 'eca_condition:entity_type',
              configuration: { type: 'node' },
              conditionId: '',
              componentType: 5,
              __isConditionNode: true,
            }),
          }),
        );

        // The original edge is replaced by two plain edges around the node.
        const setEdgesArg = (graphStore.getState().setEdges as jest.Mock).mock.calls[0][0] as StoreEdge[];
        expect(setEdgesArg.some((e) => e.id === 'edge-1')).toBe(false);
        const toNew = setEdgesArg.find((e) => e.target === 'condition_Entity Type_123');
        const fromNew = setEdgesArg.find((e) => e.source === 'condition_Entity Type_123');
        expect(toNew).toEqual(
          expect.objectContaining({ source: 'node-1', target: 'condition_Entity Type_123', type: 'default' }),
        );
        expect(fromNew).toEqual(
          expect.objectContaining({ source: 'condition_Entity Type_123', target: 'node-2', type: 'default' }),
        );

        // No edge carries a condition anymore.
        for (const e of setEdgesArg) {
          expect(e.type).not.toBe('condition');
          expect(e.data?.condition).toBeUndefined();
        }

        // The old edge-mutation path is no longer used.
        expect(graphStore.getState().updateEdge).not.toHaveBeenCalled();
        expect(saveHistory).toHaveBeenCalledTimes(1);
        expect(markUnsaved).toHaveBeenCalledTimes(1);
      });

      it('derives label from plugin when label is omitted', () => {
        api.setCondition('edge-1', {
          plugin: 'eca_condition.entity_type',
        });

        const setNodesArg = (graphStore.getState().setNodes as jest.Mock).mock.calls[0][0] as StoreNode[];
        const condNode = setNodesArg.find((n) => n.type === 'condition');
        expect(condNode?.data).toEqual(expect.objectContaining({ label: 'entity_type' }));
      });

      it('falls back to full plugin name when split yields nothing', () => {
        api.setCondition('edge-1', {
          plugin: 'myplugin',
        });

        const setNodesArg = (graphStore.getState().setNodes as jest.Mock).mock.calls[0][0] as StoreNode[];
        const condNode = setNodesArg.find((n) => n.type === 'condition');
        expect(condNode?.data).toEqual(expect.objectContaining({ label: 'myplugin' }));
      });

      it('uses empty object for missing configuration', () => {
        api.setCondition('edge-1', {
          plugin: 'eca_condition:test',
          label: 'Test',
        });

        const setNodesArg = (graphStore.getState().setNodes as jest.Mock).mock.calls[0][0] as StoreNode[];
        const condNode = setNodesArg.find((n) => n.type === 'condition');
        expect(condNode?.data).toEqual(expect.objectContaining({ configuration: {} }));
      });

      it('inserts a gateway when the edge source is a condition node (no condition->condition edge)', () => {
        // Invariant (issue #3589093): inserting a condition on an edge whose
        // source is already a condition must route through a gateway:
        //   existingCond -> gateway -> newCond -> target.
        graphStore.setState({
          nodes: [
            { id: 'cond-src', type: 'condition', position: { x: 0, y: 0 }, data: { __isConditionNode: true, label: 'Existing' } },
            mockNode2,
          ],
          edges: [
            { id: 'edge-cc', source: 'cond-src', target: 'node-2', type: 'default', data: {} },
          ],
        });

        const result = api.setCondition('edge-cc', {
          plugin: 'eca_condition:test',
          label: 'New Cond',
        });
        expect(result).toBe(true);

        const setNodesArg = (graphStore.getState().setNodes as jest.Mock).mock.calls[0][0] as StoreNode[];
        const gateway = setNodesArg.find((n) => n.type === 'gateway');
        const newCond = setNodesArg.find((n) => n.type === 'condition' && n.id !== 'cond-src');
        expect(gateway).toBeDefined();
        expect(newCond).toBeDefined();
        expect(gateway!.data?.componentType).toBe(6);
        expect(gateway!.data?.plugin).toBe('gateway');

        const setEdgesArg = (graphStore.getState().setEdges as jest.Mock).mock.calls[0][0] as StoreEdge[];
        // Original edge replaced; chain is cond-src -> gateway -> newCond -> node-2.
        expect(setEdgesArg.some((e) => e.id === 'edge-cc')).toBe(false);
        expect(setEdgesArg.find((e) => e.source === 'cond-src' && e.target === gateway!.id)).toBeDefined();
        expect(setEdgesArg.find((e) => e.source === gateway!.id && e.target === newCond!.id)).toBeDefined();
        expect(setEdgesArg.find((e) => e.source === newCond!.id && e.target === 'node-2')).toBeDefined();

        // No condition -> condition edge exists.
        const condIds = new Set(['cond-src', newCond!.id]);
        for (const e of setEdgesArg) {
          expect(condIds.has(e.source) && condIds.has(e.target)).toBe(false);
        }
      });

      it('returns false when edge does not exist', () => {
        const result = api.setCondition('nonexistent', { plugin: 'x' });
        expect(result).toBe(false);
        expect(graphStore.getState().setNodes).not.toHaveBeenCalled();
      });

      it('returns false in read-only mode', () => {
        setApiReadOnly(true);
        const result = api.setCondition('edge-1', { plugin: 'x' });
        expect(result).toBe(false);
        expect(graphStore.getState().setNodes).not.toHaveBeenCalled();
      });
    });

    describe('removeCondition', () => {
      // Conditions are nodes now: removeCondition collapses the condition
      // node back into a single plain edge (the inverse of setCondition).
      // `edgeId` resolves to a condition node when it is the condition node
      // id itself OR an edge adjacent to a condition node.

      // Build a graph with a condition node inserted between node-1 and
      // node-2:  node-1 --in--> cond-node --out--> node-2.
      const condNode: StoreNode = {
        id: 'cond-node',
        type: 'condition',
        position: { x: 0, y: 0 },
        data: { label: 'Cond', plugin: 'p', componentType: 5, __isConditionNode: true },
      };
      const inEdge: StoreEdge = { id: 'edge-in', source: 'node-1', target: 'cond-node', type: 'default', data: {} };
      const outEdge: StoreEdge = { id: 'edge-out', source: 'cond-node', target: 'node-2', type: 'default', data: {} };

      function seedConditionGraph() {
        graphStore.setState({
          nodes: [mockNode1, mockNode2, condNode],
          edges: [inEdge, outEdge],
        });
      }

      it('collapses the condition node when given the inbound edge id', () => {
        seedConditionGraph();
        const result = api.removeCondition('edge-in');

        expect(result).toBe(true);

        // The condition node is removed.
        const setNodesFn = (graphStore.getState().setNodes as jest.Mock).mock.calls[0][0] as (n: StoreNode[]) => StoreNode[];
        const remainingNodes = setNodesFn([mockNode1, mockNode2, condNode]);
        expect(remainingNodes.some((n) => n.id === 'cond-node')).toBe(false);

        // Its two edges are replaced by a single plain edge node-1 -> node-2.
        const setEdgesFn = (graphStore.getState().setEdges as jest.Mock).mock.calls[0][0] as (e: StoreEdge[]) => StoreEdge[];
        const remainingEdges = setEdgesFn([inEdge, outEdge]);
        expect(remainingEdges).toHaveLength(1);
        expect(remainingEdges[0]).toEqual(
          expect.objectContaining({ source: 'node-1', target: 'node-2', type: 'default' }),
        );

        expect(graphStore.getState().updateEdge).not.toHaveBeenCalled();
        expect(saveHistory).toHaveBeenCalledTimes(1);
        expect(markUnsaved).toHaveBeenCalledTimes(1);
      });

      it('collapses the condition node when given the outbound edge id', () => {
        seedConditionGraph();
        const result = api.removeCondition('edge-out');
        expect(result).toBe(true);

        const setEdgesFn = (graphStore.getState().setEdges as jest.Mock).mock.calls[0][0] as (e: StoreEdge[]) => StoreEdge[];
        const remainingEdges = setEdgesFn([inEdge, outEdge]);
        expect(remainingEdges).toHaveLength(1);
        expect(remainingEdges[0]).toEqual(
          expect.objectContaining({ source: 'node-1', target: 'node-2' }),
        );
      });

      it('collapses the condition node when given the condition node id directly', () => {
        seedConditionGraph();
        const result = api.removeCondition('cond-node');
        expect(result).toBe(true);

        const setNodesFn = (graphStore.getState().setNodes as jest.Mock).mock.calls[0][0] as (n: StoreNode[]) => StoreNode[];
        const remainingNodes = setNodesFn([mockNode1, mockNode2, condNode]);
        expect(remainingNodes.some((n) => n.id === 'cond-node')).toBe(false);
      });

      it('returns false when the id matches neither a condition node nor an adjacent edge', () => {
        // Baseline graph (no condition node) — edge-1 has no condition node.
        const result = api.removeCondition('edge-1');
        expect(result).toBe(false);
        expect(graphStore.getState().setNodes).not.toHaveBeenCalled();
      });

      it('returns false when id does not exist', () => {
        const result = api.removeCondition('nonexistent');
        expect(result).toBe(false);
      });

      it('returns false in read-only mode', () => {
        seedConditionGraph();
        setApiReadOnly(true);
        const result = api.removeCondition('edge-in');
        expect(result).toBe(false);
      });
    });
  });

  // ── Canvas actions ────────────────────────────────────────────────────

  describe('canvas actions', () => {
    describe('autoLayout', () => {
      it('calls the autoLayout hook', () => {
        const autoLayoutFn = jest.fn();
        setMutationHooks({
          saveHistory: jest.fn(),
          markUnsaved: jest.fn(),
          autoLayout: autoLayoutFn,
        });

        api.autoLayout();
        expect(autoLayoutFn).toHaveBeenCalledTimes(1);
      });

      it('is a no-op in read-only mode', () => {
        const autoLayoutFn = jest.fn();
        setMutationHooks({
          saveHistory: jest.fn(),
          markUnsaved: jest.fn(),
          autoLayout: autoLayoutFn,
        });

        setApiReadOnly(true);
        api.autoLayout();
        expect(autoLayoutFn).not.toHaveBeenCalled();
      });

      it('does nothing when no autoLayout hook is registered', () => {
        clearMutationHooks();
        // Should not throw
        expect(() => api.autoLayout()).not.toThrow();
      });
    });

    describe('setDarkMode', () => {
      it('toggles dark mode when value differs', () => {
        uiSettingsStore.setState({ darkMode: false });
        api.setDarkMode(true);
        expect(uiSettingsStore.getState().toggleDarkMode).toHaveBeenCalledTimes(1);
      });

      it('does not toggle when value is already the same', () => {
        uiSettingsStore.setState({ darkMode: true });
        api.setDarkMode(true);
        expect(uiSettingsStore.getState().toggleDarkMode).not.toHaveBeenCalled();
      });

      it('toggles when disabling dark mode', () => {
        uiSettingsStore.setState({ darkMode: true });
        api.setDarkMode(false);
        expect(uiSettingsStore.getState().toggleDarkMode).toHaveBeenCalledTimes(1);
      });
    });

    describe('setFlowFilter', () => {
      it('sets visible start node ids', () => {
        api.setFlowFilter(['node-1', 'node-2']);
        expect(filterStore.getState().setVisibleStartNodeIds).toHaveBeenCalledWith(['node-1', 'node-2']);
      });

      it('passes null to clear filter', () => {
        api.setFlowFilter(null);
        expect(filterStore.getState().setVisibleStartNodeIds).toHaveBeenCalledWith(null);
      });

      it('creates a copy of the provided array', () => {
        const ids = ['node-1'];
        api.setFlowFilter(ids);
        const calledWith = (filterStore.getState().setVisibleStartNodeIds as jest.Mock).mock.calls[0][0];
        expect(calledWith).toEqual(ids);
        expect(calledWith).not.toBe(ids); // must be a copy
      });
    });
  });

  // ── deepClone edge cases ──────────────────────────────────────────────

  describe('deepClone (via getters)', () => {
    it('handles null and undefined values', () => {
      modelStore.setState({ modelData: null });
      expect(api.getModelData()).toBeNull();

      selectionStore.setState({ selectedNode: null });
      expect(api.getSelectedNode()).toBeNull();
    });

    it('falls back to JSON round-trip when structuredClone fails', () => {
      const originalStructuredClone = globalThis.structuredClone;
      // Force structuredClone to throw
      globalThis.structuredClone = () => {
        throw new Error('structuredClone not available');
      };

      // Re-import would be ideal but the function is already captured.
      // Instead, test via a getter that uses deepClone.
      // Since deepClone is module-scoped and already imported, we need
      // to test at the integration level. The getNodes path goes through
      // toPluginNode -> deepClone.
      const nodes = api.getNodes();
      expect(nodes).toHaveLength(2);
      expect(nodes[0].id).toBe('node-1');

      globalThis.structuredClone = originalStructuredClone;
    });
  });

  // ── Mutations without hooks ───────────────────────────────────────────

  describe('mutations without hooks registered', () => {
    beforeEach(() => {
      clearMutationHooks();
    });

    it('addNode works without hooks', () => {
      (generateNodeId as jest.Mock).mockReturnValue('element_Test_123');
      const result = api.addNode({
        plugin: 'example.test',
        componentType: 4,
        label: 'Test',
        position: { x: 0, y: 0 },
      });
      expect(result).toBe('element_Test_123');
      expect(graphStore.getState().addNode).toHaveBeenCalled();
    });

    it('updateNode works without hooks', () => {
      const result = api.updateNode('node-1', { label: 'Updated' });
      expect(result).toBe(true);
    });

    it('removeNode works without hooks', () => {
      const result = api.removeNode('node-1');
      expect(result).toBe(true);
    });

    it('addEdge works without hooks', () => {
      (generateEdgeId as jest.Mock).mockReturnValue('edge_node-1_node-2');
      const result = api.addEdge('node-1', 'node-2');
      expect(result).toBe('edge_node-1_node-2');
    });

    it('updateEdge works without hooks', () => {
      const result = api.updateEdge('edge-1', { annotation: 'note' });
      expect(result).toBe(true);
    });

    it('removeEdge works without hooks', () => {
      const result = api.removeEdge('edge-1');
      expect(result).toBe(true);
    });

    it('setCondition works without hooks', () => {
      const result = api.setCondition('edge-1', { plugin: 'x', label: 'X' });
      expect(result).toBe(true);
    });

    it('removeCondition works without hooks', () => {
      // Insert a condition node, then collapse it — both succeed without
      // mutation hooks registered.
      graphStore.setState({
        nodes: [mockNode1, mockNode2, { id: 'c', type: 'condition', position: { x: 0, y: 0 }, data: { __isConditionNode: true } }],
        edges: [
          { id: 'e-in', source: 'node-1', target: 'c', type: 'default', data: {} },
          { id: 'e-out', source: 'c', target: 'node-2', type: 'default', data: {} },
        ],
      });
      const result = api.removeCondition('c');
      expect(result).toBe(true);
    });
  });

  // ── API stability ─────────────────────────────────────────────────────

  describe('API stability', () => {
    it('createPluginApi returns an object with all expected methods', () => {
      const methodNames = [
        'getNodes', 'getEdges', 'getNodeById', 'getEdgeById',
        'getSelectedNode', 'getSelectedEdge', 'getSelectedNodes',
        'getSelectedEdges', 'getModelData',
        'isReadOnly', 'isDarkMode', 'getComponents', 'getComponentLabels',
        'getContexts', 'getSelectedContextId', 'getFilteredNodeIds',
        'getHistoryState', 'getErrors',
        'onSelectionChange', 'onNodesChange', 'onEdgesChange',
        'onModelDataChange', 'onDarkModeChange', 'onContextChange',
        'onComponentsChange', 'onReadOnlyChange',
        'selectNode', 'selectEdge', 'clearSelection', 'focusNode', 'fitView',
        'addNode', 'updateNode', 'removeNode',
        'addEdge', 'updateEdge', 'removeEdge', 'setCondition', 'removeCondition',
        'autoLayout', 'setDarkMode', 'setFlowFilter',
      ];

      for (const name of methodNames) {
        expect(typeof (api as any)[name]).toBe('function');
      }
    });

    it('multiple createPluginApi calls return independent instances', () => {
      const api2 = createPluginApi();
      expect(api).not.toBe(api2);
      // But they read from the same stores
      expect(api.getNodes()).toEqual(api2.getNodes());
    });
  });
});
