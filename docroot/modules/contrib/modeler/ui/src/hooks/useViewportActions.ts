/**
 * useViewportActions - Unified viewport management for the modeler canvas
 *
 * Provides a consistent API for all programmatic pan/zoom operations.
 * Key principles:
 *   - Preserve the user's current zoom level unless visibility requires a change.
 *   - Minimize animation for accessibility (respects prefers-reduced-motion).
 *   - Single codepath: all viewport operations go through this hook.
 *   - Deferred execution: operations queued before ReactFlow is ready are
 *     applied once readiness is signaled.
 *   - Live state: node lookups read useGraphStore.getState() at call time
 *     rather than closing over a render-time snapshot. The plugin API mutates
 *     the store synchronously, so a plugin can create a node and focus it
 *     within one tick, before React has re-rendered (issue #3589109). Reading
 *     live state also keeps these callbacks stable, so the deferred operation
 *     effect below runs on readiness changes instead of on every graph edit.
 *
 * IMPORTANT: ReactFlow's setCenter() defaults to maxZoom when no zoom option
 * is provided. This hook always passes the current zoom explicitly to prevent
 * unintended zoom jumps.
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import { useReactFlow } from 'reactflow';
import { useGraphStore } from '../store/useGraphStore';
import { useSelectionStore } from '../store/useSelectionStore';
import { NODE_DIMENSIONS, VIEWPORT, TIMING } from '../constants/dimensions';
import type { StoreNode } from '../types/settings';

// ── Helpers ─────────────────────────────────────────────────────────

/**
 * Detect whether the user prefers reduced motion.  Returns true when
 * the OS / browser has "reduce motion" enabled.
 */
function prefersReducedMotion(): boolean {
  if (typeof window === 'undefined') return false;
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Return the animation duration to use, honoring prefers-reduced-motion.
 */
function getAnimationDuration(): number {
  return prefersReducedMotion() ? 0 : VIEWPORT.PAN_ANIMATION_DURATION;
}

/**
 * Get the center coordinates of a node.
 */
function getNodeCenter(node: StoreNode): { x: number; y: number } {
  const width = node.width || NODE_DIMENSIONS.DEFAULT_WIDTH;
  const height = node.height || NODE_DIMENSIONS.DEFAULT_HEIGHT;
  return {
    x: node.position.x + width / 2,
    y: node.position.y + height / 2,
  };
}

/**
 * Check whether a point (in flow coordinates) is within the currently
 * visible viewport bounds, with an optional margin in pixels (screen space).
 */
function isPointInViewport(
  pointX: number,
  pointY: number,
  viewport: { x: number; y: number; zoom: number },
  containerWidth: number,
  containerHeight: number,
  margin = 40,
): boolean {
  // Convert viewport bounds from screen coordinates to flow coordinates.
  const left = (-viewport.x / viewport.zoom) + (margin / viewport.zoom);
  const top = (-viewport.y / viewport.zoom) + (margin / viewport.zoom);
  const right = (-viewport.x + containerWidth) / viewport.zoom - (margin / viewport.zoom);
  const bottom = (-viewport.y + containerHeight) / viewport.zoom - (margin / viewport.zoom);

  return pointX >= left && pointX <= right && pointY >= top && pointY <= bottom;
}

// ── Pending operation types ─────────────────────────────────────────

type PendingOperation =
  | { kind: 'panToNode'; nodeId: string }
  | { kind: 'panToNodeIfOffscreen'; nodeId: string }
  | { kind: 'fitToNodes'; nodeIds?: string[] }
  | { kind: 'topAlignNode'; nodeId: string }
  | { kind: 'focusNode'; nodeId: string }
  | { kind: 'fitToNodePair'; nodeIds: [string, string] }
  | { kind: 'selectAndFocus'; node: StoreNode; targetKind: 'topAlignNode' | 'focusNode' };

// ── Hook ────────────────────────────────────────────────────────────

export interface ViewportActions {
  /** Pan to center a node, preserving the current zoom level. */
  panToNode: (nodeId: string) => void;

  /**
   * Pan to center a node only if it is currently off-screen.
   * If the node is already visible, this is a no-op.
   */
  panToNodeIfOffscreen: (nodeId: string) => void;

  /**
   * Fit the viewport to specific nodes (by ID) or all visible nodes.
   * This is the only operation that freely changes the zoom level.
   * maxZoom is capped at FIT_MAX_ZOOM (1.5) to prevent over-zooming.
   */
  fitToNodes: (nodeIds?: string[]) => void;

  /**
   * Position a node near the top of the viewport (for start/event nodes).
   * Preserves the current zoom level.
   */
  topAlignNode: (nodeId: string) => void;

  /**
   * Pan to a node at the current zoom level.  Used by search and plugin API.
   * Always pans to center the node but preserves zoom.
   */
  focusNode: (nodeId: string) => void;

  /**
   * Fit the viewport to exactly two nodes (e.g. source + placeholder).
   * maxZoom is capped to prevent over-zooming when nodes are close.
   */
  fitToNodePair: (nodeId1: string, nodeId2: string) => void;

  /**
   * Combined operation for model load: select a node and then focus or
   * top-align it.  Deferred until ReactFlow is ready, then applies the
   * node selection followed by the viewport operation.
   */
  selectAndFocus: (node: StoreNode, kind: 'topAlignNode' | 'focusNode') => void;

  /** Signal that ReactFlow is initialized and viewport operations can execute. */
  setReady: () => void;
}

export function useViewportActions(): ViewportActions {
  const { setCenter, fitView, getViewport, getZoom } = useReactFlow();
  const setSelectedNode = useSelectionStore(state => state.setSelectedNode);

  const [ready, setReadyState] = useState(false);
  const pendingRef = useRef<PendingOperation | null>(null);

  // ── Core operations ──────────────────────────────────────────────

  const panToNode = useCallback((nodeId: string) => {
    if (!ready) {
      pendingRef.current = { kind: 'panToNode', nodeId };
      return;
    }
    const node = useGraphStore.getState().nodes.find(n => n.id === nodeId);
    if (!node) return;

    const center = getNodeCenter(node);
    const currentZoom = getZoom();
    const duration = getAnimationDuration();

    setCenter(center.x, center.y, { zoom: currentZoom, duration });
  }, [ready, getZoom, setCenter]);

  const panToNodeIfOffscreen = useCallback((nodeId: string) => {
    if (!ready) {
      pendingRef.current = { kind: 'panToNodeIfOffscreen', nodeId };
      return;
    }
    const node = useGraphStore.getState().nodes.find(n => n.id === nodeId);
    if (!node) return;

    const center = getNodeCenter(node);
    const viewport = getViewport();

    // Get the ReactFlow container dimensions.
    const container = document.querySelector('.react-flow');
    const containerWidth = container?.clientWidth || window.innerWidth;
    const containerHeight = container?.clientHeight || window.innerHeight;

    if (!isPointInViewport(center.x, center.y, viewport, containerWidth, containerHeight)) {
      const currentZoom = getZoom();
      const duration = getAnimationDuration();
      setCenter(center.x, center.y, { zoom: currentZoom, duration });
    }
  }, [ready, getViewport, getZoom, setCenter]);

  const fitToNodes = useCallback((nodeIds?: string[]) => {
    if (!ready) {
      pendingRef.current = { kind: 'fitToNodes', nodeIds };
      return;
    }

    const { nodes } = useGraphStore.getState();
    const duration = getAnimationDuration();

    if (nodeIds && nodeIds.length > 0) {
      const targetNodes = nodes.filter(n => nodeIds.includes(n.id));
      if (targetNodes.length > 0) {
        fitView({
          nodes: targetNodes,
          padding: VIEWPORT.FIT_VIEW_PADDING,
          maxZoom: VIEWPORT.FIT_MAX_ZOOM,
          duration,
        });
        return;
      }
    }

    // No specific nodes: fit all visible nodes
    const visibleNodes = nodes.filter(n => !n.hidden);
    if (visibleNodes.length > 0) {
      fitView({
        nodes: visibleNodes,
        padding: VIEWPORT.FIT_VIEW_PADDING,
        maxZoom: VIEWPORT.FIT_MAX_ZOOM,
        duration,
      });
    } else {
      fitView({
        padding: VIEWPORT.FIT_VIEW_PADDING,
        maxZoom: VIEWPORT.FIT_MAX_ZOOM,
        duration,
      });
    }
  }, [ready, fitView]);

  const topAlignNode = useCallback((nodeId: string) => {
    if (!ready) {
      pendingRef.current = { kind: 'topAlignNode', nodeId };
      return;
    }
    const node = useGraphStore.getState().nodes.find(n => n.id === nodeId);
    if (!node) return;

    const center = getNodeCenter(node);
    const currentZoom = getZoom();
    const duration = getAnimationDuration();
    const viewportHeight = window.innerHeight || 800;

    // Position the node near the top of the viewport (TOP_ALIGN_OFFSET
    // pixels from the top edge).  setCenter expects the flow-coordinate
    // center of the desired viewport, so we shift the Y downward so the
    // node appears near the top.
    const viewportCenterOffset =
      (viewportHeight / (2 * currentZoom)) - (VIEWPORT.TOP_ALIGN_OFFSET / currentZoom);
    const adjustedY = center.y + viewportCenterOffset;

    setCenter(center.x, adjustedY, { zoom: currentZoom, duration });
  }, [ready, getZoom, setCenter]);

  const focusNode = useCallback((nodeId: string) => {
    if (!ready) {
      pendingRef.current = { kind: 'focusNode', nodeId };
      return;
    }
    const node = useGraphStore.getState().nodes.find(n => n.id === nodeId);
    if (!node) return;

    const center = getNodeCenter(node);
    const currentZoom = getZoom();
    const duration = getAnimationDuration();

    setCenter(center.x, center.y, { zoom: currentZoom, duration });
  }, [ready, getZoom, setCenter]);

  const fitToNodePair = useCallback((nodeId1: string, nodeId2: string) => {
    if (!ready) {
      pendingRef.current = { kind: 'fitToNodePair', nodeIds: [nodeId1, nodeId2] };
      return;
    }

    const { nodes } = useGraphStore.getState();
    const targetNodes = nodes.filter(n => n.id === nodeId1 || n.id === nodeId2);
    if (targetNodes.length === 0) return;

    const duration = getAnimationDuration();
    fitView({
      nodes: targetNodes,
      padding: VIEWPORT.FIT_VIEW_PADDING,
      maxZoom: VIEWPORT.FIT_MAX_ZOOM,
      duration,
    });
  }, [ready, fitView]);

  const selectAndFocus = useCallback((node: StoreNode, kind: 'topAlignNode' | 'focusNode') => {
    if (!ready) {
      pendingRef.current = { kind: 'selectAndFocus', node, targetKind: kind };
      return;
    }
    setSelectedNode(node);
    // Allow selection to propagate one tick before panning.
    setTimeout(() => {
      if (kind === 'topAlignNode') {
        topAlignNode(node.id);
      } else {
        focusNode(node.id);
      }
    }, TIMING.SYNC_DELAY);
  }, [ready, setSelectedNode, topAlignNode, focusNode]);

  // ── Readiness signal ─────────────────────────────────────────────

  const setReady = useCallback(() => {
    setReadyState(true);
  }, []);

  // ── Deferred execution ───────────────────────────────────────────
  // When ReactFlow becomes ready, apply any queued operation.

  useEffect(() => {
    if (!ready) return;

    const pending = pendingRef.current;
    if (!pending) return;

    // Consume the pending operation so it fires only once.
    pendingRef.current = null;

    // Brief delay for React to flush node/edge state into ReactFlow's
    // internal store so that node dimensions are measured before we pan.
    setTimeout(() => {
      switch (pending.kind) {
        case 'panToNode':
          panToNode(pending.nodeId);
          break;
        case 'panToNodeIfOffscreen':
          panToNodeIfOffscreen(pending.nodeId);
          break;
        case 'fitToNodes':
          fitToNodes(pending.nodeIds);
          break;
        case 'topAlignNode':
          topAlignNode(pending.nodeId);
          break;
        case 'focusNode':
          focusNode(pending.nodeId);
          break;
        case 'fitToNodePair':
          fitToNodePair(pending.nodeIds[0], pending.nodeIds[1]);
          break;
        case 'selectAndFocus':
          selectAndFocus(pending.node, pending.targetKind);
          break;
      }
    }, TIMING.SYNC_DELAY);
  }, [ready, panToNode, panToNodeIfOffscreen, fitToNodes, topAlignNode, focusNode, fitToNodePair, selectAndFocus]);

  return {
    panToNode,
    panToNodeIfOffscreen,
    fitToNodes,
    topAlignNode,
    focusNode,
    fitToNodePair,
    selectAndFocus,
    setReady,
  };
}
