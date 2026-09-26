/**
 * usePanelResize - Hook for handling panel resize interactions
 * 
 * Manages the mouse drag behavior for resizing panels, tracking resize state
 * and calculating new widths based on drag delta.
 */

import { useCallback, useEffect, useRef } from 'react';

interface UsePanelResizeProps {
  panelWidth: number;
  setPanelWidth: (width: number) => void;
  setPanelResizing: (resizing: boolean) => void;
  /** Direction of resize: 'left' means dragging left edge, 'right' means dragging right edge */
  direction?: 'left' | 'right';
  minWidth?: number;
  maxWidth?: number;
}

interface UsePanelResizeReturn {
  startResize: (e: React.MouseEvent) => void;
}

export function usePanelResize({
  panelWidth,
  setPanelWidth,
  setPanelResizing,
  direction = 'left',
  minWidth = 200,
  maxWidth = 800,
}: UsePanelResizeProps): UsePanelResizeReturn {
  // Track the active document listeners so they can be removed if the component
  // unmounts mid-resize (handleMouseUp would otherwise never fire).
  const mouseMoveRef = useRef<((e: MouseEvent) => void) | null>(null);
  const mouseUpRef = useRef<((e: MouseEvent) => void) | null>(null);

  const startResize = useCallback((e: React.MouseEvent) => {
    e.preventDefault();
    setPanelResizing(true);

    const startX = e.clientX;
    const startWidth = panelWidth;

    const handleMouseMove = (moveEvent: MouseEvent) => {
      const deltaX = direction === 'left' 
        ? startX - moveEvent.clientX  // Dragging left edge: subtract to increase width
        : moveEvent.clientX - startX; // Dragging right edge: add to increase width
      
      let newWidth = startWidth + deltaX;
      
      // Clamp to min/max bounds
      newWidth = Math.max(minWidth, Math.min(maxWidth, newWidth));
      
      setPanelWidth(newWidth);
    };

    const handleMouseUp = () => {
      setPanelResizing(false);
      document.removeEventListener('mousemove', handleMouseMove);
      document.removeEventListener('mouseup', handleMouseUp);
      mouseMoveRef.current = null;
      mouseUpRef.current = null;
    };

    mouseMoveRef.current = handleMouseMove;
    mouseUpRef.current = handleMouseUp;
    document.addEventListener('mousemove', handleMouseMove);
    document.addEventListener('mouseup', handleMouseUp);
  }, [panelWidth, setPanelWidth, setPanelResizing, direction, minWidth, maxWidth]);

  // Remove any still-attached document listeners on unmount (e.g. if the
  // component unmounts while a resize is in progress).
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
    startResize,
  };
}
