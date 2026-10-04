/**
 * Regression tests for issue #3589109.
 *
 * The plugin API mutates useGraphStore synchronously, so a plugin can add
 * nodes and then call api.autoLayout() inside a single tick — before React
 * has re-rendered. handleAutoLayout used to close over the render-time
 * `nodes`/`edges` snapshot and hand a plain array to setNodes(), which
 * replaces the whole array. The result was silent data loss: every node
 * added in that tick was erased and its edges were left orphaned.
 *
 * These tests deliberately use the REAL useGraphStore and the REAL plugin
 * API rather than mocks, because the defect lives in the seam between them.
 * The mutation hook is registered exactly the way Flow.tsx registers it
 * (capturing the callback from the last committed render), so the stale
 * closure is reproduced faithfully.
 */

import { renderHook, act } from '@testing-library/react';
import { useGraphStore } from '../../store/useGraphStore';
import { useSelectionStore } from '../../store/useSelectionStore';
import { useConfiguration } from '../useConfiguration';
import {
  createPluginApi,
  setMutationHooks,
  clearMutationHooks,
  setApiReadOnly,
} from '../../plugins/pluginApi';
import type { ModelerPluginApi } from '../../types/pluginApi';
import type { StoreNode } from '../../types/settings';

const startNode: StoreNode = {
  id: 'event_one',
  type: 'start',
  position: { x: 0, y: 0 },
  data: {
    label: 'Event One',
    plugin: 'example.event_one',
    componentType: 1,
    configuration: {},
  },
};

/**
 * Render useConfiguration and wire its handleAutoLayout into the plugin API
 * the same way Flow.tsx does — the API holds the closure produced by the
 * last committed render.
 */
function renderModeler(): {
  api: ModelerPluginApi;
  setHasUnsavedChanges: jest.Mock;
  saveHistory: jest.Mock;
} {
  const setHasUnsavedChanges = jest.fn();
  const saveHistory = jest.fn();

  const view = renderHook(() => useConfiguration({ setHasUnsavedChanges, saveHistory }));

  const handleAutoLayout = view.result.current.handleAutoLayout;
  setMutationHooks({
    saveHistory: () => saveHistory(),
    markUnsaved: () => setHasUnsavedChanges(true),
    autoLayout: () => handleAutoLayout(),
  });

  return { api: createPluginApi(), setHasUnsavedChanges, saveHistory };
}

describe('handleAutoLayout live-state reads (issue #3589109)', () => {
  beforeEach(() => {
    useGraphStore.setState({ nodes: [{ ...startNode }], edges: [] });
    useSelectionStore.getState().clearSelection();
    setApiReadOnly(false);
  });

  afterEach(() => {
    clearMutationHooks();
    useGraphStore.setState({ nodes: [], edges: [] });
  });

  it('keeps a node added in the same tick as the autoLayout() call', () => {
    const { api } = renderModeler();
    let newNodeId: string | null = null;

    act(() => {
      newNodeId = api.addNode({
        plugin: 'example.event_two',
        componentType: 1,
        label: 'Event Two',
      });
      api.autoLayout();
    });

    expect(newNodeId).not.toBeNull();
    expect(api.getNodeById(newNodeId as unknown as string)).not.toBeNull();

    const ids = useGraphStore.getState().nodes.map((n) => n.id);
    expect(ids).toContain(startNode.id);
    expect(ids).toContain(newNodeId);
    expect(ids).toHaveLength(2);
  });

  it('keeps every node of a multi-node plan and leaves no orphaned edge', () => {
    const { api } = renderModeler();
    let eventId: string | null = null;
    let actionId: string | null = null;
    let edgeId: string | null = null;

    // A plugin "plan" applied in one synchronous batch, mirroring the
    // reproduction recorded on the issue.
    act(() => {
      eventId = api.addNode({
        plugin: 'example.event_two',
        componentType: 1,
        label: 'Event Two',
      });
      actionId = api.addNode({
        plugin: 'example.action_one',
        componentType: 4,
        label: 'Action One',
      });
      edgeId = api.addEdge(eventId as unknown as string, actionId as unknown as string);
      api.autoLayout();
    });

    const { nodes, edges } = useGraphStore.getState();
    const ids = nodes.map((n) => n.id);

    expect(ids).toEqual(
      expect.arrayContaining([startNode.id, eventId, actionId]),
    );
    expect(ids).toHaveLength(3);

    // The edge added in the same batch must still connect two live nodes.
    expect(edgeId).not.toBeNull();
    const liveIds = new Set(ids);
    const orphanedEdges = edges.filter(
      (e) => !liveIds.has(e.source) || !liveIds.has(e.target),
    );
    expect(orphanedEdges).toEqual([]);
    expect(edges.map((e) => e.id)).toContain(edgeId);
  });

  it('lays out against edges added in the same tick', () => {
    const { api } = renderModeler();

    act(() => {
      const actionId = api.addNode({
        plugin: 'example.action_one',
        componentType: 4,
        label: 'Action One',
      });
      api.addEdge(startNode.id, actionId as unknown as string);
      api.autoLayout();
    });

    const { nodes } = useGraphStore.getState();
    const start = nodes.find((n) => n.id === startNode.id);
    const successor = nodes.find((n) => n.id !== startNode.id);

    // The successor was connected before layout ran, so it must be placed
    // below the start node rather than treated as an unreachable island.
    expect(start).toBeDefined();
    expect(successor).toBeDefined();
    expect(successor!.position.y).toBeGreaterThan(start!.position.y);
  });
});
