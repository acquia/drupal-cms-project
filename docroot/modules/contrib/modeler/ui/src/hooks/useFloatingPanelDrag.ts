/**
 * useFloatingPanelDrag - Hook for moving a floating panel with the mouse
 * or the keyboard.
 *
 * Mirrors `usePanelResize`: the gesture is driven by document-level
 * `mousemove` / `mouseup` listeners which are tracked in refs so they can be
 * detached again if the component unmounts mid-drag (in which case the
 * `mouseup` handler that normally removes them would never fire).
 *
 * Every position the hook hands back is clamped to the panel's containing
 * block, so a panel can never be dragged out of reach.
 */

import { useCallback, useEffect, useRef } from 'react';
import type { RefObject } from 'react';

/** Offset of a floating panel from the top-left of its containing block. */
export interface FloatingPosition {
  x: number;
  y: number;
}

interface UseFloatingPanelDragProps {
  /** Current position of the panel. */
  position: FloatingPosition;
  /** Called with the new (already clamped) position. */
  setPosition: (position: FloatingPosition) => void;
  /** Called when a mouse drag starts and ends. */
  setDragging: (dragging: boolean) => void;
  /** The floating element — used to measure both it and its bounds. */
  elementRef: RefObject<HTMLElement | null>;
  /** Minimum gap kept between the panel and the edge of its bounds. */
  margin?: number;
  /** Whether the gesture is available at all (false for docked panels). */
  enabled?: boolean;
}

interface UseFloatingPanelDragReturn {
  startDrag: (e: React.MouseEvent) => void;
  nudge: (deltaX: number, deltaY: number) => void;
}

/**
 * Size of the box a floating panel is positioned against.
 *
 * A floating panel is `position: absolute`, so its containing block is its
 * `offsetParent` — `.workflow-modeler`, which is positioned in every view
 * mode (fixed overlay, `restored` window, `standalone` container).  Clamping
 * against that box rather than the browser viewport keeps a floating panel
 * inside a windowed or embedded modeler too.
 *
 * Falls back to the browser viewport when there is no offset parent (e.g. a
 * detached element, or jsdom, which never reports one).
 */
export function getFloatingBounds(el: HTMLElement | null): { width: number; height: number } {
  const parent = el?.offsetParent as HTMLElement | null;
  if (parent) {
    return { width: parent.clientWidth, height: parent.clientHeight };
  }
  return { width: window.innerWidth, height: window.innerHeight };
}

/**
 * Constrain a position so the panel stays fully inside its bounds, keeping
 * `margin` pixels of clearance on every side.
 *
 * When the panel is larger than its bounds the lower bound wins (the
 * `Math.max` on the upper bound), so the panel is pinned to the top-left and
 * its header — the only way to move it again — always stays reachable.
 */
export function clampFloatingPosition(
  el: HTMLElement | null,
  position: FloatingPosition,
  margin = 0,
): FloatingPosition {
  const bounds = getFloatingBounds(el);
  const panelWidth = el?.offsetWidth ?? 0;
  const panelHeight = el?.offsetHeight ?? 0;

  const maxX = Math.max(margin, bounds.width - panelWidth - margin);
  const maxY = Math.max(margin, bounds.height - panelHeight - margin);

  return {
    x: Math.min(Math.max(position.x, margin), maxX),
    y: Math.min(Math.max(position.y, margin), maxY),
  };
}

export function useFloatingPanelDrag({
  position,
  setPosition,
  setDragging,
  elementRef,
  margin = 0,
  enabled = true,
}: UseFloatingPanelDragProps): UseFloatingPanelDragReturn {
  // Track the active document listeners so they can be removed if the
  // component unmounts mid-drag (handleMouseUp would otherwise never fire).
  const mouseMoveRef = useRef<((e: MouseEvent) => void) | null>(null);
  const mouseUpRef = useRef<((e: MouseEvent) => void) | null>(null);

  const startDrag = useCallback((e: React.MouseEvent) => {
    // Primary button only — secondary clicks open context menus.
    if (!enabled || e.button !== 0) return;

    e.preventDefault();
    setDragging(true);

    const startX = e.clientX;
    const startY = e.clientY;
    const startPosition = position;
    const el = elementRef.current;

    const handleMouseMove = (moveEvent: MouseEvent) => {
      setPosition(
        clampFloatingPosition(
          el,
          {
            x: startPosition.x + (moveEvent.clientX - startX),
            y: startPosition.y + (moveEvent.clientY - startY),
          },
          margin,
        ),
      );
    };

    const handleMouseUp = () => {
      setDragging(false);
      document.removeEventListener('mousemove', handleMouseMove);
      document.removeEventListener('mouseup', handleMouseUp);
      mouseMoveRef.current = null;
      mouseUpRef.current = null;
    };

    mouseMoveRef.current = handleMouseMove;
    mouseUpRef.current = handleMouseUp;
    document.addEventListener('mousemove', handleMouseMove);
    document.addEventListener('mouseup', handleMouseUp);
  }, [enabled, position, setPosition, setDragging, elementRef, margin]);

  /** Keyboard equivalent of a drag — move the panel by a fixed step. */
  const nudge = useCallback((deltaX: number, deltaY: number) => {
    if (!enabled) return;
    setPosition(
      clampFloatingPosition(
        elementRef.current,
        { x: position.x + deltaX, y: position.y + deltaY },
        margin,
      ),
    );
  }, [enabled, position, setPosition, elementRef, margin]);

  // Remove any still-attached document listeners on unmount (e.g. if the
  // component unmounts while a drag is in progress).
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
    startDrag,
    nudge,
  };
}
