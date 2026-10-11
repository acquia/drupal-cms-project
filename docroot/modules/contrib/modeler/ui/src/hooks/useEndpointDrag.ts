/**
 * Custom hook for dragging an edge's SOURCE or TARGET endpoint to a different
 * node (per-handle, selection-gated reconnection — issue #3585553).
 *
 * This deliberately does NOT use React Flow v11's built-in reconnect
 * (`edgesUpdatable`/`onEdgeUpdate`/`updateEdge`) because that conflicts with
 * new-edge drags originating from the same source handle. Instead we render
 * our own grips on the selected edge (see DefaultEdge) and drive the
 * reconnect through the graph store, mirroring the control-point drag pattern.
 *
 * Drop hit-testing uses `document.elementFromPoint` at the pointer's release
 * position. We resolve the node under the cursor via the closest
 * `.react-flow__node[data-id]` ancestor, and the specific handle (when the
 * pointer is over one) via the closest `.react-flow__handle`
 * (`data-handleid` / `data-nodeid`). This is robust against canvas pan/zoom
 * because it reads the live DOM rather than recomputing flow coordinates, and
 * it does not depend on React Flow internal store APIs.
 *
 * On a valid drop the proposed `Connection` is committed via `onReconnectEdge`
 * (which updates top-level edge fields and saves history). On an empty or
 * invalid drop nothing is mutated, so the endpoint visually snaps back to its
 * original node/handle automatically.
 */
import { useCallback, useEffect, useRef, useState } from 'react';
import type { Connection } from 'reactflow';
import { useUISettingsStore } from '../store/useUISettingsStore';

/** Which end of the edge a grip controls. */
export type EndpointKind = 'source' | 'target';

/** Result of hit-testing the DOM at the drop position. */
export interface DropTarget {
  nodeId: string;
  handleId: string | null;
}

/** A point in flow (canvas) coordinates. */
export interface FlowPoint {
  x: number;
  y: number;
}

/**
 * Convert client (screen) coordinates to flow (canvas) coordinates by reading
 * the live `.react-flow__viewport` CSS transform and the `.react-flow__renderer`
 * bounding rect — the exact technique used by useControlPointDrag. Returns null
 * when the renderer/viewport elements are unavailable (e.g. before mount).
 *
 * Exported for direct unit testing of the coordinate math.
 */
export function clientToFlowPoint(clientX: number, clientY: number): FlowPoint | null {
  const reactFlowWrapper = document.querySelector('.react-flow__renderer') as HTMLElement | null;
  const viewportElement = document.querySelector('.react-flow__viewport') as HTMLElement | null;
  if (!reactFlowWrapper || !viewportElement) return null;

  const transform = viewportElement.style.transform;
  const transformMatch = transform.match(/translate\(([-\d.]+)px,\s*([-\d.]+)px\)\s*scale\(([\d.]+)\)/);

  let translateX = 0;
  let translateY = 0;
  let scale = 1;
  if (transformMatch) {
    translateX = parseFloat(transformMatch[1]);
    translateY = parseFloat(transformMatch[2]);
    scale = parseFloat(transformMatch[3]);
  }

  const rect = reactFlowWrapper.getBoundingClientRect();
  const mouseX = clientX - rect.left;
  const mouseY = clientY - rect.top;

  return {
    x: (mouseX - translateX) / scale,
    y: (mouseY - translateY) / scale,
  };
}

interface UseEndpointDragProps {
  /** ID of the edge whose endpoint is being dragged. */
  edgeId: string;
  /** Which endpoint this grip controls. */
  endpoint: EndpointKind;
  /** Current source node id of the edge. */
  source: string;
  /** Current target node id of the edge. */
  target: string;
  /** Current source handle id of the edge. */
  sourceHandle?: string | null;
  /** Current target handle id of the edge. */
  targetHandle?: string | null;
  /** Global canvas lock — when true, dragging is disabled entirely. */
  isLocked: boolean;
  /**
   * Validate a proposed connection. Receives the full proposed
   * source/target/handles for the edge after the move. Returns true when the
   * reconnection is allowed.
   */
  validateConnection?: (connection: Connection) => boolean;
  /** Commit the reconnection (top-level edge fields). */
  onReconnectEdge?: (
    edgeId: string,
    updates: {
      source?: string;
      sourceHandle?: string | null;
      target?: string;
      targetHandle?: string | null;
    },
  ) => void;
}

/**
 * The canonical handle id a reconnected endpoint connects to on the
 * destination node. Each node exposes exactly one source handle (`output`) and
 * one target handle (`input`), so the destination handle is fully determined
 * by which endpoint kind is being dragged: a dragged SOURCE endpoint must land
 * on the destination's `output` handle; a dragged TARGET endpoint on `input`.
 */
export const DESTINATION_HANDLE_ID: Record<EndpointKind, string> = {
  source: 'output',
  target: 'input',
};

/**
 * Resolve the node (and destination handle) under the given client coordinates
 * for a reconnect drop.
 *
 * The reconnect drag/drop is OUR OWN system (DOM hit-test + validate +
 * onReconnectEdge), independent of React Flow's `onConnect`/`isConnectable`.
 * A node whose source handle is "reserved" by a selected edge is rendered
 * non-connectable (`isConnectable={false}`) so a NEW-edge drag cannot start
 * there — but React Flow also gives non-connectable handles
 * `pointer-events: none`, which would hide them from `elementFromPoint`. We
 * therefore do NOT depend on hitting the handle element: once the pointer is
 * over a node we infer the destination handle from the dragged `endpoint`
 * kind ({@link DESTINATION_HANDLE_ID}). This makes dropping ONTO such a node
 * work (issue #3585553 follow-up) while leaving new-edge suppression intact.
 *
 * Exported for direct unit testing of the hit-test logic.
 *
 * @param endpoint Which endpoint is being dragged — determines the destination
 *   handle id when only the node (not a specific handle) is resolved.
 */
export function hitTestDropTarget(
  clientX: number,
  clientY: number,
  endpoint: EndpointKind,
): DropTarget | null {
  // Some environments (e.g. jsdom) do not implement elementFromPoint; treat a
  // missing implementation as "no target under the cursor" so a drop simply
  // snaps back instead of throwing.
  if (typeof document.elementFromPoint !== 'function') return null;

  let el = document.elementFromPoint(clientX, clientY) as HTMLElement | null;

  // Robustness (issue #3585553): if the topmost element is an endpoint grip
  // (e.g. the destination node's own selected-edge grip sitting on top with
  // pointer-events:all, z-index:1000), it would mask the node/handle beneath
  // and the drop would resolve nothing. Temporarily disable pointer events on
  // ALL grips and re-query so the hit-test reaches the real drop target, then
  // restore. This is deterministic regardless of CSS/class timing and
  // complements the global `reconnect-dragging` wrapper class.
  if (el && el.closest('.edge-endpoint-grip')) {
    const grips = Array.from(
      document.querySelectorAll<HTMLElement>('.edge-endpoint-grip'),
    );
    const previous = grips.map((g) => g.style.pointerEvents);
    grips.forEach((g) => {
      g.style.pointerEvents = 'none';
    });
    el = document.elementFromPoint(clientX, clientY) as HTMLElement | null;
    grips.forEach((g, i) => {
      g.style.pointerEvents = previous[i];
    });
  }

  if (!el) return null;

  // Prefer a specific handle when the pointer is over a hit-testable one AND it
  // matches the endpoint kind being dragged. (A non-connectable handle has
  // pointer-events:none, so it won't be hit; we fall through to the node.)
  const handleEl = el.closest('.react-flow__handle') as HTMLElement | null;
  if (handleEl) {
    const nodeId = handleEl.getAttribute('data-nodeid');
    if (nodeId) {
      const handleId = handleEl.getAttribute('data-handleid');
      // Normalize to the canonical destination handle for this endpoint kind so
      // a committed reconnect always carries a concrete handle id.
      return { nodeId, handleId: handleId ?? DESTINATION_HANDLE_ID[endpoint] };
    }
  }

  // Otherwise resolve the node under the cursor and infer the destination
  // handle from the dragged endpoint kind — independent of handle
  // connectability / pointer-events.
  const nodeEl = el.closest('.react-flow__node[data-id]') as HTMLElement | null;
  if (nodeEl) {
    const nodeId = nodeEl.getAttribute('data-id');
    if (nodeId) {
      return { nodeId, handleId: DESTINATION_HANDLE_ID[endpoint] };
    }
  }

  return null;
}

/**
 * Build the proposed `Connection` for a reconnection: take the edge's current
 * endpoints and replace the dragged end with the drop target.
 *
 * Exported for direct unit testing.
 */
export function buildProposedConnection(
  args: Pick<UseEndpointDragProps, 'endpoint' | 'source' | 'target' | 'sourceHandle' | 'targetHandle'>,
  drop: DropTarget,
): Connection {
  if (args.endpoint === 'source') {
    return {
      source: drop.nodeId,
      sourceHandle: drop.handleId,
      target: args.target,
      targetHandle: args.targetHandle ?? null,
    };
  }
  return {
    source: args.source,
    sourceHandle: args.sourceHandle ?? null,
    target: drop.nodeId,
    targetHandle: drop.handleId,
  };
}

export function useEndpointDrag({
  edgeId,
  endpoint,
  source,
  target,
  sourceHandle,
  targetHandle,
  isLocked,
  validateConnection,
  onReconnectEdge,
}: UseEndpointDragProps) {
  const [isDragging, setIsDragging] = useState(false);
  // Live cursor position in FLOW coordinates while dragging. Drives the
  // reconnect preview line in DefaultEdge (mirrors React Flow's new-connection
  // line). Null whenever no drag is active.
  const [previewPoint, setPreviewPoint] = useState<FlowPoint | null>(null);
  // Global flag so the canvas can make ALL grips non-interactive during a
  // drag (issue #3585553). Without this, a non-dragged grip on the
  // destination node's selected edge sits on top at the drop point and
  // intercepts the elementFromPoint hit-test, blocking the drop.
  const setReconnectDragActive = useUISettingsStore((s) => s.setReconnectDragActive);

  // Track the active document listeners so they can be removed if the component
  // unmounts mid-drag (handleMouseUp would otherwise never fire).
  const mouseMoveRef = useRef<((e: MouseEvent) => void) | null>(null);
  const mouseUpRef = useRef<((e: MouseEvent) => void) | null>(null);

  const handleEndpointDrag = useCallback(
    (event: React.MouseEvent) => {
      if (isLocked || !onReconnectEdge) return;

      event.stopPropagation();
      event.preventDefault();
      setIsDragging(true);
      // Signal the canvas that a reconnect drag is active so every endpoint
      // grip becomes pointer-events:none for the duration of the gesture.
      setReconnectDragActive(true);

      const handleMouseMove = (e: MouseEvent) => {
        // Track the cursor in flow coords so DefaultEdge can draw a live
        // preview line from the fixed endpoint to the pointer. The edge itself
        // is NOT mutated during the drag — the commit happens on mouseup.
        const point = clientToFlowPoint(e.clientX, e.clientY);
        if (point) setPreviewPoint(point);
      };

      const handleMouseUp = (e: MouseEvent) => {
        setIsDragging(false);
        // Clear the preview so the prospective line disappears on drop,
        // regardless of whether the drop commits or snaps back.
        setPreviewPoint(null);
        // Clear the global drag flag on EVERY path (commit, snap-back, no-op,
        // invalid) so grips become interactive again immediately after drop.
        setReconnectDragActive(false);
        document.removeEventListener('mousemove', handleMouseMove);
        document.removeEventListener('mouseup', handleMouseUp);
        mouseMoveRef.current = null;
        mouseUpRef.current = null;

        const drop = hitTestDropTarget(e.clientX, e.clientY, endpoint);
        // Empty canvas / no node under cursor → snap back (no mutation).
        if (!drop) return;

        const proposed = buildProposedConnection(
          { endpoint, source, target, sourceHandle, targetHandle },
          drop,
        );

        // No-op when the endpoint did not actually move to a different
        // node/handle — avoids spurious history entries.
        if (endpoint === 'source') {
          if (proposed.source === source && (proposed.sourceHandle ?? null) === (sourceHandle ?? null)) {
            return;
          }
        } else if (proposed.target === target && (proposed.targetHandle ?? null) === (targetHandle ?? null)) {
          return;
        }

        // Invalid target → snap back (no mutation).
        if (validateConnection && !validateConnection(proposed)) return;

        // Commit only the changed end's top-level fields.
        if (endpoint === 'source') {
          onReconnectEdge(edgeId, {
            source: proposed.source ?? undefined,
            sourceHandle: proposed.sourceHandle,
          });
        } else {
          onReconnectEdge(edgeId, {
            target: proposed.target ?? undefined,
            targetHandle: proposed.targetHandle,
          });
        }
      };

      mouseMoveRef.current = handleMouseMove;
      mouseUpRef.current = handleMouseUp;
      document.addEventListener('mousemove', handleMouseMove);
      document.addEventListener('mouseup', handleMouseUp);
    },
    [edgeId, endpoint, source, target, sourceHandle, targetHandle, isLocked, validateConnection, onReconnectEdge, setReconnectDragActive],
  );

  // Remove any still-attached document listeners on unmount (e.g. if the
  // component unmounts while a reconnect drag is in progress).
  useEffect(() => {
    return () => {
      if (mouseMoveRef.current) {
        document.removeEventListener('mousemove', mouseMoveRef.current);
        mouseMoveRef.current = null;
      }
      if (mouseUpRef.current) {
        document.removeEventListener('mouseup', mouseUpRef.current);
        mouseUpRef.current = null;
      }
    };
  }, []);

  return {
    isDragging,
    previewPoint,
    handleEndpointDrag,
  };
}
