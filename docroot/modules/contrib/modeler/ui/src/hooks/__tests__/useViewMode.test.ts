/**
 * Tests for useViewMode hook
 */

import { renderHook, act } from '@testing-library/react';
import { useViewMode } from '../useViewMode';

describe('useViewMode', () => {
  // Create a mock modeler element with style manipulation
  let mockElement: HTMLDivElement;
  let modelerRef: React.RefObject<HTMLDivElement | null>;

  // Mock document event listeners
  let addEventListenerSpy: jest.SpyInstance;
  let removeEventListenerSpy: jest.SpyInstance;
  let eventHandlers: Record<string, EventListenerOrEventListenerObject>;

  beforeEach(() => {
    jest.clearAllMocks();
    localStorage.clear();
    mockElement = document.createElement('div');
    modelerRef = { current: mockElement };
    eventHandlers = {};

    addEventListenerSpy = jest.spyOn(document, 'addEventListener').mockImplementation(
      (event: string, handler: EventListenerOrEventListenerObject) => {
        eventHandlers[event] = handler;
      },
    );

    removeEventListenerSpy = jest.spyOn(document, 'removeEventListener').mockImplementation(
      (event: string) => {
        delete eventHandlers[event];
      },
    );

    // Mock viewport dimensions
    Object.defineProperty(window, 'innerWidth', { value: 1280, writable: true });
    Object.defineProperty(window, 'innerHeight', { value: 800, writable: true });
  });

  afterEach(() => {
    addEventListenerSpy.mockRestore();
    removeEventListenerSpy.mockRestore();
  });

  describe('initial state', () => {
    it('should default to fullscreen for Drupal mode', () => {
      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef }),
      );

      expect(result.current.viewMode).toBe('fullscreen');
    });

    it('should default to restored for standalone mode', () => {
      const { result } = renderHook(() =>
        useViewMode({ isStandalone: true, modelerRef }),
      );

      expect(result.current.viewMode).toBe('restored');
    });

    it('should not be dragging or resizing initially', () => {
      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef }),
      );

      expect(result.current.isDragging).toBe(false);
      expect(result.current.isResizing).toBe(false);
    });
  });

  describe('toggleViewMode', () => {
    it('should toggle from fullscreen to restored', () => {
      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef }),
      );

      act(() => {
        result.current.toggleViewMode();
      });

      expect(result.current.viewMode).toBe('restored');
    });

    it('should toggle from restored to fullscreen', () => {
      const { result } = renderHook(() =>
        useViewMode({ isStandalone: true, modelerRef }),
      );

      // Starts as restored
      expect(result.current.viewMode).toBe('restored');

      act(() => {
        result.current.toggleViewMode();
      });

      expect(result.current.viewMode).toBe('fullscreen');
    });

    it('should toggle back and forth', () => {
      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef }),
      );

      // fullscreen -> restored -> fullscreen
      act(() => { result.current.toggleViewMode(); });
      expect(result.current.viewMode).toBe('restored');

      act(() => { result.current.toggleViewMode(); });
      expect(result.current.viewMode).toBe('fullscreen');
    });

    it('should apply inline styles when entering Drupal restored mode', () => {
      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef }),
      );

      act(() => {
        result.current.toggleViewMode();
      });

      // Should have position styles set
      expect(mockElement.style.top).not.toBe('');
      expect(mockElement.style.left).not.toBe('');
      expect(mockElement.style.width).not.toBe('');
      expect(mockElement.style.height).not.toBe('');
    });

    it('should clear inline styles when entering fullscreen mode', () => {
      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef }),
      );

      // Enter restored
      act(() => { result.current.toggleViewMode(); });
      expect(mockElement.style.width).not.toBe('');

      // Back to fullscreen
      act(() => { result.current.toggleViewMode(); });
      expect(mockElement.style.top).toBe('');
      expect(mockElement.style.left).toBe('');
      expect(mockElement.style.width).toBe('');
      expect(mockElement.style.height).toBe('');
    });

    it('should NOT apply inline styles for standalone restored mode', () => {
      const { result } = renderHook(() =>
        useViewMode({ isStandalone: true, modelerRef }),
      );

      // Already restored — styles should be clear (CSS class handles it)
      expect(mockElement.style.top).toBe('');
      expect(mockElement.style.width).toBe('');

      // Toggle to fullscreen and back
      act(() => { result.current.toggleViewMode(); });
      act(() => { result.current.toggleViewMode(); });
      expect(mockElement.style.top).toBe('');
    });
  });

  describe('startDrag', () => {
    it('should set isDragging to true on mousedown in restored Drupal mode', () => {
      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef }),
      );

      // Enter restored mode
      act(() => { result.current.toggleViewMode(); });

      const mouseEvent = {
        preventDefault: jest.fn(),
        clientX: 400,
        clientY: 200,
      } as unknown as React.MouseEvent;

      act(() => {
        result.current.startDrag(mouseEvent);
      });

      expect(result.current.isDragging).toBe(true);
      expect(mouseEvent.preventDefault).toHaveBeenCalled();
    });

    it('should NOT start drag in fullscreen mode', () => {
      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef }),
      );

      const mouseEvent = {
        preventDefault: jest.fn(),
        clientX: 400,
        clientY: 200,
      } as unknown as React.MouseEvent;

      act(() => {
        result.current.startDrag(mouseEvent);
      });

      expect(result.current.isDragging).toBe(false);
    });

    it('should NOT start drag in standalone mode', () => {
      const { result } = renderHook(() =>
        useViewMode({ isStandalone: true, modelerRef }),
      );

      const mouseEvent = {
        preventDefault: jest.fn(),
        clientX: 400,
        clientY: 200,
      } as unknown as React.MouseEvent;

      act(() => {
        result.current.startDrag(mouseEvent);
      });

      expect(result.current.isDragging).toBe(false);
    });

    it('should add document event listeners when drag starts', () => {
      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef }),
      );

      act(() => { result.current.toggleViewMode(); });

      const mouseEvent = {
        preventDefault: jest.fn(),
        clientX: 400,
        clientY: 200,
      } as unknown as React.MouseEvent;

      act(() => {
        result.current.startDrag(mouseEvent);
      });

      expect(addEventListenerSpy).toHaveBeenCalledWith('mousemove', expect.any(Function));
      expect(addEventListenerSpy).toHaveBeenCalledWith('mouseup', expect.any(Function));
    });

    it('should update position on mousemove during drag', () => {
      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef }),
      );

      act(() => { result.current.toggleViewMode(); });

      const initialTop = mockElement.style.top;
      const initialLeft = mockElement.style.left;

      const mouseEvent = {
        preventDefault: jest.fn(),
        clientX: 400,
        clientY: 200,
      } as unknown as React.MouseEvent;

      act(() => {
        result.current.startDrag(mouseEvent);
      });

      // Simulate drag
      act(() => {
        (eventHandlers['mousemove'] as EventListener)?.({ clientX: 450, clientY: 230 } as MouseEvent);
      });

      // Position should have changed
      expect(mockElement.style.top).not.toBe(initialTop);
      expect(mockElement.style.left).not.toBe(initialLeft);
    });

    it('should stop dragging and remove listeners on mouseup', () => {
      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef }),
      );

      act(() => { result.current.toggleViewMode(); });

      const mouseEvent = {
        preventDefault: jest.fn(),
        clientX: 400,
        clientY: 200,
      } as unknown as React.MouseEvent;

      act(() => {
        result.current.startDrag(mouseEvent);
      });

      expect(result.current.isDragging).toBe(true);

      act(() => {
        (eventHandlers['mouseup'] as EventListener)?.({} as MouseEvent);
      });

      expect(result.current.isDragging).toBe(false);
      expect(removeEventListenerSpy).toHaveBeenCalledWith('mousemove', expect.any(Function));
      expect(removeEventListenerSpy).toHaveBeenCalledWith('mouseup', expect.any(Function));
    });
  });

  describe('startResize', () => {
    it('should set isResizing to true on mousedown in restored Drupal mode', () => {
      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef }),
      );

      act(() => { result.current.toggleViewMode(); });

      const mouseEvent = {
        preventDefault: jest.fn(),
        stopPropagation: jest.fn(),
        clientX: 600,
        clientY: 400,
      } as unknown as React.MouseEvent;

      act(() => {
        result.current.startResize(mouseEvent);
      });

      expect(result.current.isResizing).toBe(true);
      expect(mouseEvent.preventDefault).toHaveBeenCalled();
      expect(mouseEvent.stopPropagation).toHaveBeenCalled();
    });

    it('should NOT start resize in fullscreen mode', () => {
      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef }),
      );

      const mouseEvent = {
        preventDefault: jest.fn(),
        stopPropagation: jest.fn(),
        clientX: 600,
        clientY: 400,
      } as unknown as React.MouseEvent;

      act(() => {
        result.current.startResize(mouseEvent);
      });

      expect(result.current.isResizing).toBe(false);
    });

    it('should update dimensions on mousemove during resize', () => {
      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef }),
      );

      act(() => { result.current.toggleViewMode(); });

      const initialWidth = mockElement.style.width;

      const mouseEvent = {
        preventDefault: jest.fn(),
        stopPropagation: jest.fn(),
        clientX: 600,
        clientY: 400,
      } as unknown as React.MouseEvent;

      act(() => {
        result.current.startResize(mouseEvent);
      });

      // Simulate resize drag
      act(() => {
        (eventHandlers['mousemove'] as EventListener)?.({ clientX: 700, clientY: 500 } as MouseEvent);
      });

      expect(mockElement.style.width).not.toBe(initialWidth);
    });

    it('should enforce minimum dimensions during resize', () => {
      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef }),
      );

      act(() => { result.current.toggleViewMode(); });

      const mouseEvent = {
        preventDefault: jest.fn(),
        stopPropagation: jest.fn(),
        clientX: 600,
        clientY: 400,
      } as unknown as React.MouseEvent;

      act(() => {
        result.current.startResize(mouseEvent);
      });

      // Drag far to the left/up to try to shrink below minimum
      act(() => {
        (eventHandlers['mousemove'] as EventListener)?.({ clientX: 0, clientY: 0 } as MouseEvent);
      });

      // Width and height should be at least the minimum (480x360)
      const width = parseInt(mockElement.style.width, 10);
      const height = parseInt(mockElement.style.height, 10);
      expect(width).toBeGreaterThanOrEqual(480);
      expect(height).toBeGreaterThanOrEqual(360);
    });

    it('should stop resizing and remove listeners on mouseup', () => {
      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef }),
      );

      act(() => { result.current.toggleViewMode(); });

      const mouseEvent = {
        preventDefault: jest.fn(),
        stopPropagation: jest.fn(),
        clientX: 600,
        clientY: 400,
      } as unknown as React.MouseEvent;

      act(() => {
        result.current.startResize(mouseEvent);
      });

      expect(result.current.isResizing).toBe(true);

      act(() => {
        (eventHandlers['mouseup'] as EventListener)?.({} as MouseEvent);
      });

      expect(result.current.isResizing).toBe(false);
      expect(removeEventListenerSpy).toHaveBeenCalledWith('mousemove', expect.any(Function));
      expect(removeEventListenerSpy).toHaveBeenCalledWith('mouseup', expect.any(Function));
    });
  });

  describe('default rect calculation', () => {
    it('should center the window at 80% of viewport', () => {
      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef }),
      );

      act(() => { result.current.toggleViewMode(); });

      // 80% of 1280x800 = 1024x640
      // centered: top = (800-640)/2 = 80, left = (1280-1024)/2 = 128
      expect(mockElement.style.width).toBe('1024px');
      expect(mockElement.style.height).toBe('640px');
      expect(mockElement.style.top).toBe('80px');
      expect(mockElement.style.left).toBe('128px');
    });
  });

  describe('null ref handling', () => {
    it('should not throw when modelerRef is null', () => {
      const nullRef = { current: null };

      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef: nullRef }),
      );

      expect(() => {
        act(() => { result.current.toggleViewMode(); });
      }).not.toThrow();
    });
  });

  describe('localStorage persistence', () => {
    const STORAGE_KEY = 'workflow_modeler_window_rect';

    it('should save rect to localStorage after drag ends', () => {
      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef }),
      );

      act(() => { result.current.toggleViewMode(); });

      const mouseEvent = {
        preventDefault: jest.fn(),
        clientX: 400,
        clientY: 200,
      } as unknown as React.MouseEvent;

      act(() => { result.current.startDrag(mouseEvent); });

      // Move the window
      act(() => {
        (eventHandlers['mousemove'] as EventListener)?.({ clientX: 450, clientY: 230 } as MouseEvent);
      });

      // Release
      act(() => {
        (eventHandlers['mouseup'] as EventListener)?.({} as MouseEvent);
      });

      const saved = JSON.parse(localStorage.getItem(STORAGE_KEY)!);
      expect(saved).toHaveProperty('top');
      expect(saved).toHaveProperty('left');
      expect(saved).toHaveProperty('width');
      expect(saved).toHaveProperty('height');
    });

    it('should save rect to localStorage after resize ends', () => {
      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef }),
      );

      act(() => { result.current.toggleViewMode(); });

      const mouseEvent = {
        preventDefault: jest.fn(),
        stopPropagation: jest.fn(),
        clientX: 600,
        clientY: 400,
      } as unknown as React.MouseEvent;

      act(() => { result.current.startResize(mouseEvent); });

      act(() => {
        (eventHandlers['mousemove'] as EventListener)?.({ clientX: 700, clientY: 500 } as MouseEvent);
      });

      act(() => {
        (eventHandlers['mouseup'] as EventListener)?.({} as MouseEvent);
      });

      const saved = JSON.parse(localStorage.getItem(STORAGE_KEY)!);
      expect(saved).toHaveProperty('width');
      expect(saved).toHaveProperty('height');
    });

    it('should restore saved rect when toggling to restored mode', () => {
      const savedRect = { top: 50, left: 100, width: 900, height: 500 };
      localStorage.setItem(STORAGE_KEY, JSON.stringify(savedRect));

      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef }),
      );

      act(() => { result.current.toggleViewMode(); });

      expect(mockElement.style.top).toBe('50px');
      expect(mockElement.style.left).toBe('100px');
      expect(mockElement.style.width).toBe('900px');
      expect(mockElement.style.height).toBe('500px');
    });

    it('should use saved rect on initial mount (ref initialization)', () => {
      const savedRect = { top: 30, left: 60, width: 800, height: 600 };
      localStorage.setItem(STORAGE_KEY, JSON.stringify(savedRect));

      // Create a fresh element + ref for this test
      const el = document.createElement('div');
      const ref = { current: el };

      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef: ref }),
      );

      act(() => { result.current.toggleViewMode(); });

      expect(el.style.top).toBe('30px');
      expect(el.style.left).toBe('60px');
      expect(el.style.width).toBe('800px');
      expect(el.style.height).toBe('600px');
    });

    it('should clamp saved rect to current viewport', () => {
      // Save a rect that extends beyond the 1280x800 viewport
      const savedRect = { top: 9000, left: 9000, width: 5000, height: 5000 };
      localStorage.setItem(STORAGE_KEY, JSON.stringify(savedRect));

      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef }),
      );

      act(() => { result.current.toggleViewMode(); });

      const top = parseInt(mockElement.style.top, 10);
      const left = parseInt(mockElement.style.left, 10);
      const width = parseInt(mockElement.style.width, 10);
      const height = parseInt(mockElement.style.height, 10);

      // Width/height clamped to viewport (1280/800)
      expect(width).toBeLessThanOrEqual(1280);
      expect(height).toBeLessThanOrEqual(800);
      // Position clamped so at least MIN_WIDTH/MIN_HEIGHT is visible
      expect(left).toBeLessThanOrEqual(1280 - 480);
      expect(top).toBeLessThanOrEqual(800 - 360);
    });

    it('should fall back to default rect if localStorage has invalid data', () => {
      localStorage.setItem(STORAGE_KEY, 'not-valid-json');

      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef }),
      );

      act(() => { result.current.toggleViewMode(); });

      // Should use the 80% default: 1024x640 centered
      expect(mockElement.style.width).toBe('1024px');
      expect(mockElement.style.height).toBe('640px');
    });

    it('should fall back to default rect if localStorage has missing fields', () => {
      localStorage.setItem(STORAGE_KEY, JSON.stringify({ top: 10, left: 20 }));

      const { result } = renderHook(() =>
        useViewMode({ isStandalone: false, modelerRef }),
      );

      act(() => { result.current.toggleViewMode(); });

      expect(mockElement.style.width).toBe('1024px');
      expect(mockElement.style.height).toBe('640px');
    });

    it('should not persist rect in standalone mode', () => {
      const { result } = renderHook(() =>
        useViewMode({ isStandalone: true, modelerRef }),
      );

      // Standalone starts in restored — drag is a no-op
      const mouseEvent = {
        preventDefault: jest.fn(),
        clientX: 400,
        clientY: 200,
      } as unknown as React.MouseEvent;

      act(() => { result.current.startDrag(mouseEvent); });

      expect(localStorage.getItem(STORAGE_KEY)).toBeNull();
    });
  });
});
