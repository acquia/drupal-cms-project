/**
 * Tests for usePanelResize hook
 */

import { renderHook, act } from '@testing-library/react';
import { usePanelResize } from '../usePanelResize';

describe('usePanelResize', () => {
  const defaultProps = {
    panelWidth: 300,
    setPanelWidth: jest.fn(),
    setPanelResizing: jest.fn(),
  };

  // Mock document event listeners
  let addEventListenerSpy: jest.SpyInstance;
  let removeEventListenerSpy: jest.SpyInstance;
  let eventHandlers: Record<string, EventListenerOrEventListenerObject> = {};

  beforeEach(() => {
    jest.clearAllMocks();
    eventHandlers = {};
    
    addEventListenerSpy = jest.spyOn(document, 'addEventListener').mockImplementation(
      (event: string, handler: EventListenerOrEventListenerObject) => {
        eventHandlers[event] = handler;
      }
    );
    
    removeEventListenerSpy = jest.spyOn(document, 'removeEventListener').mockImplementation(
      (event: string) => {
        delete eventHandlers[event];
      }
    );
  });

  afterEach(() => {
    addEventListenerSpy.mockRestore();
    removeEventListenerSpy.mockRestore();
  });

  describe('startResize', () => {
    it('should return startResize function', () => {
      const { result } = renderHook(() => usePanelResize(defaultProps));
      
      expect(result.current.startResize).toBeDefined();
      expect(typeof result.current.startResize).toBe('function');
    });

    it('should set resizing state to true on mousedown', () => {
      const setPanelResizing = jest.fn();
      const { result } = renderHook(() => usePanelResize({
        ...defaultProps,
        setPanelResizing,
      }));
      
      const mouseEvent = {
        preventDefault: jest.fn(),
        clientX: 500,
      } as unknown as React.MouseEvent;
      
      act(() => {
        result.current.startResize(mouseEvent);
      });
      
      expect(mouseEvent.preventDefault).toHaveBeenCalled();
      expect(setPanelResizing).toHaveBeenCalledWith(true);
    });

    it('should add mousemove and mouseup event listeners', () => {
      const { result } = renderHook(() => usePanelResize(defaultProps));
      
      const mouseEvent = {
        preventDefault: jest.fn(),
        clientX: 500,
      } as unknown as React.MouseEvent;
      
      act(() => {
        result.current.startResize(mouseEvent);
      });
      
      expect(addEventListenerSpy).toHaveBeenCalledWith('mousemove', expect.any(Function));
      expect(addEventListenerSpy).toHaveBeenCalledWith('mouseup', expect.any(Function));
    });
  });

  describe('left direction resize', () => {
    it('should increase width when dragging left', () => {
      const setPanelWidth = jest.fn();
      const { result } = renderHook(() => usePanelResize({
        ...defaultProps,
        setPanelWidth,
        direction: 'left',
      }));
      
      const mouseEvent = {
        preventDefault: jest.fn(),
        clientX: 500,
      } as unknown as React.MouseEvent;
      
      act(() => {
        result.current.startResize(mouseEvent);
      });
      
      // Simulate drag to the left (decreasing clientX = increasing width)
      act(() => {
        (eventHandlers['mousemove'] as EventListener)?.({ clientX: 450 } as MouseEvent);
      });
      
      // Width should increase by 50 (500 - 450)
      expect(setPanelWidth).toHaveBeenCalledWith(350);
    });

    it('should decrease width when dragging right', () => {
      const setPanelWidth = jest.fn();
      const { result } = renderHook(() => usePanelResize({
        ...defaultProps,
        setPanelWidth,
        direction: 'left',
      }));
      
      const mouseEvent = {
        preventDefault: jest.fn(),
        clientX: 500,
      } as unknown as React.MouseEvent;
      
      act(() => {
        result.current.startResize(mouseEvent);
      });
      
      // Simulate drag to the right (increasing clientX = decreasing width)
      act(() => {
        (eventHandlers['mousemove'] as EventListener)?.({ clientX: 550 } as MouseEvent);
      });
      
      // Width should decrease by 50 (500 - 550 = -50)
      expect(setPanelWidth).toHaveBeenCalledWith(250);
    });
  });

  describe('right direction resize', () => {
    it('should increase width when dragging right', () => {
      const setPanelWidth = jest.fn();
      const { result } = renderHook(() => usePanelResize({
        ...defaultProps,
        setPanelWidth,
        direction: 'right',
      }));
      
      const mouseEvent = {
        preventDefault: jest.fn(),
        clientX: 500,
      } as unknown as React.MouseEvent;
      
      act(() => {
        result.current.startResize(mouseEvent);
      });
      
      // Simulate drag to the right (increasing clientX = increasing width)
      act(() => {
        (eventHandlers['mousemove'] as EventListener)?.({ clientX: 550 } as MouseEvent);
      });
      
      // Width should increase by 50 (550 - 500)
      expect(setPanelWidth).toHaveBeenCalledWith(350);
    });

    it('should decrease width when dragging left', () => {
      const setPanelWidth = jest.fn();
      const { result } = renderHook(() => usePanelResize({
        ...defaultProps,
        setPanelWidth,
        direction: 'right',
      }));
      
      const mouseEvent = {
        preventDefault: jest.fn(),
        clientX: 500,
      } as unknown as React.MouseEvent;
      
      act(() => {
        result.current.startResize(mouseEvent);
      });
      
      // Simulate drag to the left (decreasing clientX = decreasing width)
      act(() => {
        (eventHandlers['mousemove'] as EventListener)?.({ clientX: 450 } as MouseEvent);
      });
      
      // Width should decrease by 50 (450 - 500 = -50)
      expect(setPanelWidth).toHaveBeenCalledWith(250);
    });
  });

  describe('min/max constraints', () => {
    it('should clamp width to minWidth', () => {
      const setPanelWidth = jest.fn();
      const { result } = renderHook(() => usePanelResize({
        ...defaultProps,
        panelWidth: 250,
        setPanelWidth,
        minWidth: 200,
      }));
      
      const mouseEvent = {
        preventDefault: jest.fn(),
        clientX: 500,
      } as unknown as React.MouseEvent;
      
      act(() => {
        result.current.startResize(mouseEvent);
      });
      
      // Try to drag beyond minWidth
      act(() => {
        (eventHandlers['mousemove'] as EventListener)?.({ clientX: 600 } as MouseEvent);
      });
      
      // Should clamp to minWidth (250 - 100 = 150, but clamped to 200)
      expect(setPanelWidth).toHaveBeenCalledWith(200);
    });

    it('should clamp width to maxWidth', () => {
      const setPanelWidth = jest.fn();
      const { result } = renderHook(() => usePanelResize({
        ...defaultProps,
        panelWidth: 750,
        setPanelWidth,
        maxWidth: 800,
      }));
      
      const mouseEvent = {
        preventDefault: jest.fn(),
        clientX: 500,
      } as unknown as React.MouseEvent;
      
      act(() => {
        result.current.startResize(mouseEvent);
      });
      
      // Try to drag beyond maxWidth
      act(() => {
        (eventHandlers['mousemove'] as EventListener)?.({ clientX: 400 } as MouseEvent);
      });
      
      // Should clamp to maxWidth (750 + 100 = 850, but clamped to 800)
      expect(setPanelWidth).toHaveBeenCalledWith(800);
    });

    it('should use custom min/max values', () => {
      const setPanelWidth = jest.fn();
      const { result } = renderHook(() => usePanelResize({
        ...defaultProps,
        panelWidth: 300,
        setPanelWidth,
        minWidth: 100,
        maxWidth: 500,
      }));
      
      const mouseEvent = {
        preventDefault: jest.fn(),
        clientX: 500,
      } as unknown as React.MouseEvent;
      
      act(() => {
        result.current.startResize(mouseEvent);
      });
      
      // Test minWidth clamping
      act(() => {
        (eventHandlers['mousemove'] as EventListener)?.({ clientX: 800 } as MouseEvent);
      });
      expect(setPanelWidth).toHaveBeenCalledWith(100);
      
      // Test maxWidth clamping
      act(() => {
        (eventHandlers['mousemove'] as EventListener)?.({ clientX: 100 } as MouseEvent);
      });
      expect(setPanelWidth).toHaveBeenCalledWith(500);
    });
  });

  describe('mouseup handling', () => {
    it('should set resizing state to false on mouseup', () => {
      const setPanelResizing = jest.fn();
      const { result } = renderHook(() => usePanelResize({
        ...defaultProps,
        setPanelResizing,
      }));
      
      const mouseEvent = {
        preventDefault: jest.fn(),
        clientX: 500,
      } as unknown as React.MouseEvent;
      
      act(() => {
        result.current.startResize(mouseEvent);
      });
      
      setPanelResizing.mockClear();
      
      act(() => {
        (eventHandlers['mouseup'] as EventListener)?.({} as MouseEvent);
      });
      
      expect(setPanelResizing).toHaveBeenCalledWith(false);
    });

    it('should remove event listeners on mouseup', () => {
      const { result } = renderHook(() => usePanelResize(defaultProps));
      
      const mouseEvent = {
        preventDefault: jest.fn(),
        clientX: 500,
      } as unknown as React.MouseEvent;
      
      act(() => {
        result.current.startResize(mouseEvent);
      });
      
      expect(eventHandlers['mousemove']).toBeDefined();
      expect(eventHandlers['mouseup']).toBeDefined();
      
      act(() => {
        (eventHandlers['mouseup'] as EventListener)?.({} as MouseEvent);
      });
      
      expect(removeEventListenerSpy).toHaveBeenCalledWith('mousemove', expect.any(Function));
      expect(removeEventListenerSpy).toHaveBeenCalledWith('mouseup', expect.any(Function));
    });
  });

  describe('callback stability', () => {
    it('should maintain stable startResize reference when dependencies do not change', () => {
      const { result, rerender } = renderHook(() => usePanelResize(defaultProps));
      
      const firstStartResize = result.current.startResize;
      
      rerender();
      
      expect(result.current.startResize).toBe(firstStartResize);
    });

    it('should update startResize when panelWidth changes', () => {
      const { result, rerender } = renderHook(
        ({ panelWidth }) => usePanelResize({ ...defaultProps, panelWidth }),
        { initialProps: { panelWidth: 300 } }
      );
      
      const firstStartResize = result.current.startResize;
      
      rerender({ panelWidth: 400 });
      
      expect(result.current.startResize).not.toBe(firstStartResize);
    });
  });
});
