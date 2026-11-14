/**
 * Custom hook for handling drag interactions on edge control points.
 * Manages mouse events to allow users to reposition the bezier curve
 * control point by converting screen coordinates to flow coordinates.
 */
import { useState, useCallback, useRef, useEffect } from 'react';

interface UseControlPointDragProps {
  id: string;
  edgeCenterX: number;
  edgeCenterY: number;
  isLocked: boolean;
  onEdgeUpdate?: (id: string, updates: { controlOffset: { x: number; y: number } }) => void;
}

export function useControlPointDrag({
  id,
  edgeCenterX,
  edgeCenterY,
  isLocked,
  onEdgeUpdate,
}: UseControlPointDragProps) {
  const [isDragging, setIsDragging] = useState(false);

  // Track the active document listeners so they can be removed if the component
  // unmounts mid-drag (handleMouseUp would otherwise never fire).
  const mouseMoveRef = useRef<((e: MouseEvent) => void) | null>(null);
  const mouseUpRef = useRef<((e: MouseEvent) => void) | null>(null);

  const handleControlPointDrag = useCallback((event: React.MouseEvent) => {
    if (isLocked || !onEdgeUpdate) return;

    event.stopPropagation();
    event.preventDefault();
    setIsDragging(true);

    // Get the ReactFlow wrapper element to access the viewport transform
    const reactFlowWrapper = document.querySelector('.react-flow__renderer') as HTMLElement;
    const viewportElement = document.querySelector('.react-flow__viewport') as HTMLElement;

    if (!reactFlowWrapper || !viewportElement) return;

    // Get the transform values from the viewport
    const transform = viewportElement.style.transform;
    const transformMatch = transform.match(/translate\(([-\d.]+)px,\s*([-\d.]+)px\)\s*scale\(([\d.]+)\)/);

    let translateX = 0, translateY = 0, scale = 1;
    if (transformMatch) {
      translateX = parseFloat(transformMatch[1]);
      translateY = parseFloat(transformMatch[2]);
      scale = parseFloat(transformMatch[3]);
    }

    const handleMouseMove = (e: MouseEvent) => {
      // Get mouse position relative to the ReactFlow wrapper
      const rect = reactFlowWrapper.getBoundingClientRect();
      const mouseX = e.clientX - rect.left;
      const mouseY = e.clientY - rect.top;

      // Convert to flow coordinates by accounting for pan and zoom
      const flowX = (mouseX - translateX) / scale;
      const flowY = (mouseY - translateY) / scale;

      // Calculate offset from edge center
      const newOffset = {
        x: flowX - edgeCenterX,
        y: flowY - edgeCenterY
      };

      onEdgeUpdate(id, { controlOffset: newOffset });
    };

    const handleMouseUp = () => {
      setIsDragging(false);
      document.removeEventListener('mousemove', handleMouseMove);
      document.removeEventListener('mouseup', handleMouseUp);
      mouseMoveRef.current = null;
      mouseUpRef.current = null;
    };

    mouseMoveRef.current = handleMouseMove;
    mouseUpRef.current = handleMouseUp;
    document.addEventListener('mousemove', handleMouseMove);
    document.addEventListener('mouseup', handleMouseUp);
  }, [id, edgeCenterX, edgeCenterY, isLocked, onEdgeUpdate]);

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
    isDragging,
    handleControlPointDrag,
  };
}
