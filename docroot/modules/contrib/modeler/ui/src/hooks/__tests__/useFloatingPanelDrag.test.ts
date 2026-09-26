/**
 * Tests for useFloatingPanelDrag hook
 */

import React from 'react';
import { renderHook, act } from '@testing-library/react';
import {
  useFloatingPanelDrag,
  clampFloatingPosition,
  getFloatingBounds,
} from '../useFloatingPanelDrag';

/**
 * Build a detached element with a fixed offset box, optionally inside a
 * bounding offset parent.  jsdom performs no layout, so every geometry value
 * the hook reads has to be stubbed.
 */
function makeElement(
  size: { width: number; height: number },
  bounds?: { width: number; height: number },
): HTMLElement {
  const el = document.createElement('div');
  Object.defineProperty(el, 'offsetWidth', { configurable: true, value: size.width });
  Object.defineProperty(el, 'offsetHeight', { configurable: true, value: size.height });

  let parent: HTMLElement | null = null;
  if (bounds) {
    parent = document.createElement('div');
    Object.defineProperty(parent, 'clientWidth', { configurable: true, value: bounds.width });
    Object.defineProperty(parent, 'clientHeight', { configurable: true, value: bounds.height });
  }
  Object.defineProperty(el, 'offsetParent', { configurable: true, value: parent });

  return el;
}

function mouseEvent(clientX: number, clientY: number, button = 0): React.MouseEvent {
  return {
    button,
    clientX,
    clientY,
    preventDefault: jest.fn(),
  } as unknown as React.MouseEvent;
}

describe('getFloatingBounds', () => {
  it('measures the offset parent when there is one', () => {
    const el = makeElement({ width: 300, height: 200 }, { width: 900, height: 500 });
    expect(getFloatingBounds(el)).toEqual({ width: 900, height: 500 });
  });

  it('falls back to the browser viewport without an offset parent', () => {
    const el = makeElement({ width: 300, height: 200 });
    expect(getFloatingBounds(el)).toEqual({
      width: window.innerWidth,
      height: window.innerHeight,
    });
  });

  it('falls back to the browser viewport for a null element', () => {
    expect(getFloatingBounds(null)).toEqual({
      width: window.innerWidth,
      height: window.innerHeight,
    });
  });
});

describe('clampFloatingPosition', () => {
  const el = makeElement({ width: 300, height: 200 }, { width: 1000, height: 800 });

  it('leaves a position that is already inside the bounds alone', () => {
    expect(clampFloatingPosition(el, { x: 400, y: 300 }, 16)).toEqual({ x: 400, y: 300 });
  });

  it('clamps against the top-left edge', () => {
    expect(clampFloatingPosition(el, { x: -500, y: -500 }, 16)).toEqual({ x: 16, y: 16 });
  });

  it('clamps against the bottom-right edge', () => {
    // 1000 - 300 - 16 = 684 ; 800 - 200 - 16 = 584
    expect(clampFloatingPosition(el, { x: 9999, y: 9999 }, 16)).toEqual({ x: 684, y: 584 });
  });

  it('pins a panel larger than its bounds to the top-left so it stays reachable', () => {
    const huge = makeElement({ width: 4000, height: 4000 }, { width: 1000, height: 800 });
    expect(clampFloatingPosition(huge, { x: 9999, y: 9999 }, 16)).toEqual({ x: 16, y: 16 });
  });

  it('defaults the margin to zero', () => {
    expect(clampFloatingPosition(el, { x: -50, y: -50 })).toEqual({ x: 0, y: 0 });
  });

  it('treats a null element as zero-sized', () => {
    expect(clampFloatingPosition(null, { x: -10, y: -10 }, 5)).toEqual({ x: 5, y: 5 });
  });
});

describe('useFloatingPanelDrag', () => {
  let addEventListenerSpy: jest.SpyInstance;
  let removeEventListenerSpy: jest.SpyInstance;
  let eventHandlers: Record<string, (e: MouseEvent) => void>;

  const setPosition = jest.fn();
  const setDragging = jest.fn();

  function makeProps(overrides: Record<string, unknown> = {}) {
    const el = makeElement({ width: 300, height: 200 }, { width: 1000, height: 800 });
    return {
      position: { x: 100, y: 100 },
      setPosition,
      setDragging,
      elementRef: { current: el } as React.RefObject<HTMLElement | null>,
      margin: 16,
      ...overrides,
    };
  }

  function fireMove(clientX: number, clientY: number): void {
    act(() => {
      eventHandlers.mousemove?.({ clientX, clientY } as MouseEvent);
    });
  }

  beforeEach(() => {
    jest.clearAllMocks();
    eventHandlers = {};

    addEventListenerSpy = jest.spyOn(document, 'addEventListener').mockImplementation(
      (event: string, handler: EventListenerOrEventListenerObject) => {
        eventHandlers[event] = handler as (e: MouseEvent) => void;
      },
    );

    removeEventListenerSpy = jest.spyOn(document, 'removeEventListener').mockImplementation(
      (event: string) => {
        delete eventHandlers[event];
      },
    );
  });

  afterEach(() => {
    addEventListenerSpy.mockRestore();
    removeEventListenerSpy.mockRestore();
  });

  describe('startDrag', () => {
    it('attaches document listeners and flags the drag', () => {
      const { result } = renderHook(() => useFloatingPanelDrag(makeProps()));

      act(() => result.current.startDrag(mouseEvent(500, 400)));

      expect(setDragging).toHaveBeenCalledWith(true);
      expect(addEventListenerSpy).toHaveBeenCalledWith('mousemove', expect.any(Function));
      expect(addEventListenerSpy).toHaveBeenCalledWith('mouseup', expect.any(Function));
    });

    it('moves the panel by the pointer delta', () => {
      const { result } = renderHook(() => useFloatingPanelDrag(makeProps()));

      act(() => result.current.startDrag(mouseEvent(500, 400)));
      fireMove(560, 370);

      expect(setPosition).toHaveBeenLastCalledWith({ x: 160, y: 70 });
    });

    it('clamps the panel inside its bounds while dragging', () => {
      const { result } = renderHook(() => useFloatingPanelDrag(makeProps()));

      act(() => result.current.startDrag(mouseEvent(500, 400)));

      // Way past the bottom-right corner.
      fireMove(9999, 9999);
      expect(setPosition).toHaveBeenLastCalledWith({ x: 684, y: 584 });

      // Way past the top-left corner.
      fireMove(-9999, -9999);
      expect(setPosition).toHaveBeenLastCalledWith({ x: 16, y: 16 });
    });

    it('detaches the listeners and clears the flag on mouseup', () => {
      const { result } = renderHook(() => useFloatingPanelDrag(makeProps()));

      act(() => result.current.startDrag(mouseEvent(500, 400)));
      act(() => eventHandlers.mouseup?.({} as MouseEvent));

      expect(setDragging).toHaveBeenLastCalledWith(false);
      expect(removeEventListenerSpy).toHaveBeenCalledWith('mousemove', expect.any(Function));
      expect(removeEventListenerSpy).toHaveBeenCalledWith('mouseup', expect.any(Function));
    });

    it('ignores non-primary mouse buttons', () => {
      const { result } = renderHook(() => useFloatingPanelDrag(makeProps()));

      act(() => result.current.startDrag(mouseEvent(500, 400, 2)));

      expect(setDragging).not.toHaveBeenCalled();
      expect(addEventListenerSpy).not.toHaveBeenCalled();
    });

    it('does nothing when disabled', () => {
      const { result } = renderHook(() => useFloatingPanelDrag(makeProps({ enabled: false })));

      act(() => result.current.startDrag(mouseEvent(500, 400)));

      expect(setDragging).not.toHaveBeenCalled();
      expect(addEventListenerSpy).not.toHaveBeenCalled();
    });
  });

  describe('nudge', () => {
    it('offsets the current position', () => {
      const { result } = renderHook(() => useFloatingPanelDrag(makeProps()));

      act(() => result.current.nudge(10, -10));

      expect(setPosition).toHaveBeenCalledWith({ x: 110, y: 90 });
    });

    it('clamps just like a drag does', () => {
      const { result } = renderHook(() =>
        useFloatingPanelDrag(makeProps({ position: { x: 16, y: 16 } })),
      );

      act(() => result.current.nudge(-100, -100));

      expect(setPosition).toHaveBeenCalledWith({ x: 16, y: 16 });
    });

    it('does nothing when disabled', () => {
      const { result } = renderHook(() => useFloatingPanelDrag(makeProps({ enabled: false })));

      act(() => result.current.nudge(10, 10));

      expect(setPosition).not.toHaveBeenCalled();
    });
  });

  describe('cleanup', () => {
    it('detaches listeners left behind by a drag interrupted by unmount', () => {
      const { result, unmount } = renderHook(() => useFloatingPanelDrag(makeProps()));

      act(() => result.current.startDrag(mouseEvent(500, 400)));
      expect(Object.keys(eventHandlers).sort()).toEqual(['mousemove', 'mouseup']);

      // No mouseup — the pointer is still down when the panel goes away.
      unmount();

      expect(removeEventListenerSpy).toHaveBeenCalledWith('mousemove', expect.any(Function));
      expect(removeEventListenerSpy).toHaveBeenCalledWith('mouseup', expect.any(Function));
      expect(eventHandlers).toEqual({});
    });

    it('does not remove anything when no drag was in progress', () => {
      const { unmount } = renderHook(() => useFloatingPanelDrag(makeProps()));

      unmount();

      expect(removeEventListenerSpy).not.toHaveBeenCalled();
    });
  });
});
