/**
 * Tests for useViewportActions — focused on the live-state reads introduced
 * for issue #3589109.
 *
 * The hook used to resolve node IDs against a render-time `nodes` snapshot.
 * Because the plugin API mutates the store synchronously, a plugin that
 * created a node and immediately asked to focus or frame it hit the snapshot
 * from the previous render, found nothing, and the call silently did nothing.
 *
 * These tests use the REAL useGraphStore and perform the store mutation and
 * the viewport call inside a single act() block, so no re-render occurs in
 * between — exactly the situation a plugin creates.
 */

import { renderHook, act } from '@testing-library/react';
import { useGraphStore } from '../../store/useGraphStore';
import { useViewportActions } from '../useViewportActions';
import { NODE_DIMENSIONS } from '../../constants/dimensions';
import type { StoreNode } from '../../types/settings';

const mockSetCenter = jest.fn();
const mockFitView = jest.fn();
const mockGetZoom = jest.fn(() => 1);
const mockGetViewport = jest.fn(() => ({ x: 0, y: 0, zoom: 1 }));

jest.mock('reactflow', () => ({
  ...jest.requireActual('reactflow'),
  useReactFlow: () => ({
    setCenter: mockSetCenter,
    fitView: mockFitView,
    getZoom: mockGetZoom,
    getViewport: mockGetViewport,
  }),
}));

const existingNode: StoreNode = {
  id: 'existing_node',
  type: 'start',
  position: { x: 0, y: 0 },
  data: { label: 'Existing', plugin: 'example.event', componentType: 1 },
};

/**
 * A node a plugin creates during the tick under test. Positioned well
 * outside the default jsdom window so panToNodeIfOffscreen has to act.
 */
const freshNode: StoreNode = {
  id: 'fresh_node',
  type: 'element',
  position: { x: 4000, y: 6000 },
  data: { label: 'Fresh', plugin: 'example.action', componentType: 4 },
};

const freshCenter = {
  x: freshNode.position.x + NODE_DIMENSIONS.DEFAULT_WIDTH / 2,
  y: freshNode.position.y + NODE_DIMENSIONS.DEFAULT_HEIGHT / 2,
};

/** Render the hook and mark ReactFlow ready, as FlowCanvas does on init. */
function renderReadyViewport() {
  const view = renderHook(() => useViewportActions());
  act(() => {
    view.result.current.setReady();
  });
  return view;
}

describe('useViewportActions', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    useGraphStore.setState({ nodes: [{ ...existingNode }], edges: [] });
  });

  afterEach(() => {
    useGraphStore.setState({ nodes: [], edges: [] });
  });

  describe('live-state node lookups (issue #3589109)', () => {
    it('focusNode centers a node added in the same tick', () => {
      const { result } = renderReadyViewport();

      act(() => {
        useGraphStore.getState().addNode(freshNode);
        result.current.focusNode(freshNode.id);
      });

      expect(mockSetCenter).toHaveBeenCalledWith(
        freshCenter.x,
        freshCenter.y,
        expect.objectContaining({ zoom: 1 }),
      );
    });

    it('topAlignNode acts on a node added in the same tick', () => {
      const { result } = renderReadyViewport();

      act(() => {
        useGraphStore.getState().addNode(freshNode);
        result.current.topAlignNode(freshNode.id);
      });

      expect(mockSetCenter).toHaveBeenCalledTimes(1);
      expect(mockSetCenter.mock.calls[0][0]).toBe(freshCenter.x);
    });

    it('panToNode centers a node added in the same tick', () => {
      const { result } = renderReadyViewport();

      act(() => {
        useGraphStore.getState().addNode(freshNode);
        result.current.panToNode(freshNode.id);
      });

      expect(mockSetCenter).toHaveBeenCalledWith(
        freshCenter.x,
        freshCenter.y,
        expect.objectContaining({ zoom: 1 }),
      );
    });

    it('fitToNodes frames a node added in the same tick', () => {
      const { result } = renderReadyViewport();

      act(() => {
        useGraphStore.getState().addNode(freshNode);
        result.current.fitToNodes([freshNode.id]);
      });

      expect(mockFitView).toHaveBeenCalledTimes(1);
      const options = mockFitView.mock.calls[0][0] as { nodes: StoreNode[] };
      expect(options.nodes.map((n) => n.id)).toEqual([freshNode.id]);
    });

    it('fitToNodes without IDs includes a node added in the same tick', () => {
      const { result } = renderReadyViewport();

      act(() => {
        useGraphStore.getState().addNode(freshNode);
        result.current.fitToNodes();
      });

      const options = mockFitView.mock.calls[0][0] as { nodes: StoreNode[] };
      expect(options.nodes.map((n) => n.id)).toEqual(
        expect.arrayContaining([existingNode.id, freshNode.id]),
      );
    });

    it('fitToNodePair frames a pair created in the same tick', () => {
      const { result } = renderReadyViewport();

      act(() => {
        useGraphStore.getState().addNode(freshNode);
        result.current.fitToNodePair(existingNode.id, freshNode.id);
      });

      expect(mockFitView).toHaveBeenCalledTimes(1);
      const options = mockFitView.mock.calls[0][0] as { nodes: StoreNode[] };
      expect(options.nodes.map((n) => n.id)).toEqual(
        expect.arrayContaining([existingNode.id, freshNode.id]),
      );
    });

    it('panToNodeIfOffscreen pans to an off-screen node added in the same tick', () => {
      const { result } = renderReadyViewport();

      act(() => {
        useGraphStore.getState().addNode(freshNode);
        result.current.panToNodeIfOffscreen(freshNode.id);
      });

      expect(mockSetCenter).toHaveBeenCalledWith(
        freshCenter.x,
        freshCenter.y,
        expect.objectContaining({ zoom: 1 }),
      );
    });
  });

  describe('unknown nodes', () => {
    it('does nothing when the node ID does not exist', () => {
      const { result } = renderReadyViewport();

      act(() => {
        result.current.focusNode('no_such_node');
      });

      expect(mockSetCenter).not.toHaveBeenCalled();
    });
  });

  describe('deferred execution', () => {
    it('queues an operation issued before ready and applies it once ready', () => {
      jest.useFakeTimers();
      const view = renderHook(() => useViewportActions());

      // Not ready yet — the call must be queued, not executed.
      act(() => {
        view.result.current.focusNode(freshNode.id);
      });
      expect(mockSetCenter).not.toHaveBeenCalled();

      // The node only arrives in the store after the operation was queued.
      // Live reads mean the deferred run still finds it.
      act(() => {
        useGraphStore.getState().addNode(freshNode);
        view.result.current.setReady();
      });

      act(() => {
        jest.runAllTimers();
      });

      expect(mockSetCenter).toHaveBeenCalledWith(
        freshCenter.x,
        freshCenter.y,
        expect.objectContaining({ zoom: 1 }),
      );
      jest.useRealTimers();
    });
  });
});
