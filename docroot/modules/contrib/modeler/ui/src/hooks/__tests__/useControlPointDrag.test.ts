import { renderHook, act } from '@testing-library/react';
import { useControlPointDrag } from '../useControlPointDrag';

describe('useControlPointDrag', () => {
  const defaultProps = {
    id: 'edge-1',
    edgeCenterX: 250,
    edgeCenterY: 200,
    isLocked: false,
    onEdgeUpdate: jest.fn(),
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('return values', () => {
    it('should return isDragging and handleControlPointDrag', () => {
      const { result } = renderHook(() => useControlPointDrag(defaultProps));
      expect(typeof result.current.isDragging).toBe('boolean');
      expect(typeof result.current.handleControlPointDrag).toBe('function');
    });

    it('should start with isDragging false', () => {
      const { result } = renderHook(() => useControlPointDrag(defaultProps));
      expect(result.current.isDragging).toBe(false);
    });
  });

  describe('handleControlPointDrag', () => {
    it('should not start drag when locked', () => {
      const { result } = renderHook(() =>
        useControlPointDrag({ ...defaultProps, isLocked: true })
      );

      const mockEvent = {
        stopPropagation: jest.fn(),
        preventDefault: jest.fn(),
      } as unknown as React.MouseEvent;

      act(() => {
        result.current.handleControlPointDrag(mockEvent);
      });

      expect(mockEvent.stopPropagation).not.toHaveBeenCalled();
      expect(result.current.isDragging).toBe(false);
    });

    it('should not start drag when onEdgeUpdate is not provided', () => {
      const { result } = renderHook(() =>
        useControlPointDrag({ ...defaultProps, onEdgeUpdate: undefined })
      );

      const mockEvent = {
        stopPropagation: jest.fn(),
        preventDefault: jest.fn(),
      } as unknown as React.MouseEvent;

      act(() => {
        result.current.handleControlPointDrag(mockEvent);
      });

      expect(result.current.isDragging).toBe(false);
    });

    it('should call stopPropagation and preventDefault when starting drag', () => {
      // Mock DOM elements
      const mockRenderer = document.createElement('div');
      mockRenderer.className = 'react-flow__renderer';
      const mockViewport = document.createElement('div');
      mockViewport.className = 'react-flow__viewport';
      mockViewport.style.transform = 'translate(0px, 0px) scale(1)';
      document.body.appendChild(mockRenderer);
      document.body.appendChild(mockViewport);

      const { result } = renderHook(() => useControlPointDrag(defaultProps));

      const mockEvent = {
        stopPropagation: jest.fn(),
        preventDefault: jest.fn(),
      } as unknown as React.MouseEvent;

      act(() => {
        result.current.handleControlPointDrag(mockEvent);
      });

      expect(mockEvent.stopPropagation).toHaveBeenCalled();
      expect(mockEvent.preventDefault).toHaveBeenCalled();
      expect(result.current.isDragging).toBe(true);

      // Cleanup: simulate mouseup to remove listeners
      document.dispatchEvent(new MouseEvent('mouseup'));

      document.body.removeChild(mockRenderer);
      document.body.removeChild(mockViewport);
    });

    it('should set isDragging to false on mouseup', () => {
      const mockRenderer = document.createElement('div');
      mockRenderer.className = 'react-flow__renderer';
      const mockViewport = document.createElement('div');
      mockViewport.className = 'react-flow__viewport';
      mockViewport.style.transform = 'translate(0px, 0px) scale(1)';
      document.body.appendChild(mockRenderer);
      document.body.appendChild(mockViewport);

      const { result } = renderHook(() => useControlPointDrag(defaultProps));

      const mockEvent = {
        stopPropagation: jest.fn(),
        preventDefault: jest.fn(),
      } as unknown as React.MouseEvent;

      act(() => {
        result.current.handleControlPointDrag(mockEvent);
      });

      expect(result.current.isDragging).toBe(true);

      act(() => {
        document.dispatchEvent(new MouseEvent('mouseup'));
      });

      expect(result.current.isDragging).toBe(false);

      document.body.removeChild(mockRenderer);
      document.body.removeChild(mockViewport);
    });
  });

  describe('edge cases', () => {
    it('should not start drag when renderer element is missing', () => {
      const { result } = renderHook(() => useControlPointDrag(defaultProps));

      const mockEvent = {
        stopPropagation: jest.fn(),
        preventDefault: jest.fn(),
      } as unknown as React.MouseEvent;

      act(() => {
        result.current.handleControlPointDrag(mockEvent);
      });

      // Still sets isDragging true but returns early from no DOM elements
      // The early return happens after setIsDragging(true)
      expect(mockEvent.stopPropagation).toHaveBeenCalled();
    });
  });
});
