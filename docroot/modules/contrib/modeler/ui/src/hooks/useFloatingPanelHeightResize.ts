/** Resize a floating plugin panel from its bottom edge. */
import { useCallback, useEffect, useRef } from 'react';
import type { RefObject } from 'react';
import { getFloatingBounds } from './useFloatingPanelDrag';

interface UseFloatingPanelHeightResizeProps {
  elementRef: RefObject<HTMLElement | null>;
  panelY: number;
  setPanelHeight: (height: number) => void;
  setPanelResizing: (resizing: boolean) => void;
  onResizeEnd: () => void;
  minHeight: number;
  margin: number;
  enabled?: boolean;
}

interface UseFloatingPanelHeightResizeReturn {
  startResize: (event: React.PointerEvent) => void;
  resizeByKeyboard: (delta: number) => void;
}

export function getMaximumFloatingPanelHeight(
  element: HTMLElement | null,
  panelY: number,
  margin: number,
): number {
  return Math.max(0, getFloatingBounds(element).height - panelY - margin);
}

export function clampFloatingPanelHeight(
  element: HTMLElement | null,
  height: number,
  panelY: number,
  minHeight: number,
  margin: number,
): number {
  const maximum = getMaximumFloatingPanelHeight(element, panelY, margin);
  return Math.min(Math.max(height, minHeight), maximum);
}

export function useFloatingPanelHeightResize({
  elementRef,
  panelY,
  setPanelHeight,
  setPanelResizing,
  onResizeEnd,
  minHeight,
  margin,
  enabled = true,
}: UseFloatingPanelHeightResizeProps): UseFloatingPanelHeightResizeReturn {
  const pointerMoveRef = useRef<((event: PointerEvent) => void) | null>(null);
  const pointerEndRef = useRef<(() => void) | null>(null);

  const removeListeners = useCallback(() => {
    if (pointerMoveRef.current) {
      document.removeEventListener('pointermove', pointerMoveRef.current);
      pointerMoveRef.current = null;
    }
    if (pointerEndRef.current) {
      document.removeEventListener('pointerup', pointerEndRef.current);
      document.removeEventListener('pointercancel', pointerEndRef.current);
      pointerEndRef.current = null;
    }
  }, []);

  const startResize = useCallback((event: React.PointerEvent) => {
    if (!enabled || event.button !== 0) return;

    event.preventDefault();
    removeListeners();
    setPanelResizing(true);

    const element = elementRef.current;
    const startY = event.clientY;
    // The state is intentionally unset before the first interaction. Measuring
    // here preserves content-driven automatic sizing until the user acts.
    const startHeight = element?.offsetHeight ?? minHeight;
    setPanelHeight(
      clampFloatingPanelHeight(element, startHeight, panelY, minHeight, margin),
    );

    const handlePointerMove = (moveEvent: PointerEvent) => {
      setPanelHeight(
        clampFloatingPanelHeight(
          element,
          startHeight + moveEvent.clientY - startY,
          panelY,
          minHeight,
          margin,
        ),
      );
    };

    const handlePointerEnd = () => {
      setPanelResizing(false);
      removeListeners();
      onResizeEnd();
    };

    pointerMoveRef.current = handlePointerMove;
    pointerEndRef.current = handlePointerEnd;
    document.addEventListener('pointermove', handlePointerMove);
    document.addEventListener('pointerup', handlePointerEnd);
    document.addEventListener('pointercancel', handlePointerEnd);
  }, [
    enabled,
    elementRef,
    margin,
    minHeight,
    onResizeEnd,
    panelY,
    removeListeners,
    setPanelHeight,
    setPanelResizing,
  ]);

  const resizeByKeyboard = useCallback((delta: number) => {
    if (!enabled) return;
    const element = elementRef.current;
    const currentHeight = element?.offsetHeight ?? minHeight;
    setPanelResizing(true);
    setPanelHeight(
      clampFloatingPanelHeight(
        element,
        currentHeight + delta,
        panelY,
        minHeight,
        margin,
      ),
    );
    setPanelResizing(false);
    onResizeEnd();
  }, [
    enabled,
    elementRef,
    margin,
    minHeight,
    onResizeEnd,
    panelY,
    setPanelHeight,
    setPanelResizing,
  ]);

  useEffect(() => removeListeners, [removeListeners]);

  return { startResize, resizeByKeyboard };
}
