import { act, renderHook } from '@testing-library/react';
import {
  clampFloatingPanelHeight,
  getMaximumFloatingPanelHeight,
  useFloatingPanelHeightResize,
} from '../useFloatingPanelHeightResize';

function makeElement(height = 240, boundsHeight = 600): HTMLElement {
  const element = document.createElement('div');
  const parent = document.createElement('div');
  Object.defineProperty(element, 'offsetHeight', { configurable: true, value: height });
  Object.defineProperty(element, 'offsetParent', { configurable: true, value: parent });
  Object.defineProperty(parent, 'clientWidth', { configurable: true, value: 800 });
  Object.defineProperty(parent, 'clientHeight', { configurable: true, value: boundsHeight });
  return element;
}

function pointerEvent(clientY: number, button = 0): React.PointerEvent {
  return {
    button,
    clientY,
    preventDefault: jest.fn(),
  } as unknown as React.PointerEvent;
}

describe('floating panel height geometry', () => {
  const element = makeElement();

  it('calculates the room below the panel including its margin', () => {
    expect(getMaximumFloatingPanelHeight(element, 100, 16)).toBe(484);
  });

  it('clamps to the usable minimum', () => {
    expect(clampFloatingPanelHeight(element, 80, 100, 120, 16)).toBe(120);
  });

  it('clamps to the modeler space below the panel', () => {
    expect(clampFloatingPanelHeight(element, 999, 100, 120, 16)).toBe(484);
  });

  it('lets available modeler space win when it is smaller than the minimum', () => {
    expect(clampFloatingPanelHeight(element, 999, 500, 120, 16)).toBe(84);
  });
});

describe('useFloatingPanelHeightResize', () => {
  let handlers: Record<string, (event: PointerEvent) => void>;
  let addSpy: jest.SpyInstance;
  let removeSpy: jest.SpyInstance;
  const setHeight = jest.fn();
  const setResizing = jest.fn();
  const onResizeEnd = jest.fn();

  function props(overrides: Record<string, unknown> = {}) {
    return {
      elementRef: { current: makeElement() } as React.RefObject<HTMLElement | null>,
      panelY: 100,
      setPanelHeight: setHeight,
      setPanelResizing: setResizing,
      onResizeEnd,
      minHeight: 120,
      margin: 16,
      enabled: true,
      ...overrides,
    };
  }

  beforeEach(() => {
    jest.clearAllMocks();
    handlers = {};
    addSpy = jest.spyOn(document, 'addEventListener').mockImplementation(
      (type: string, listener: EventListenerOrEventListenerObject) => {
        handlers[type] = listener as (event: PointerEvent) => void;
      },
    );
    removeSpy = jest.spyOn(document, 'removeEventListener').mockImplementation(
      (type: string) => { delete handlers[type]; },
    );
  });

  afterEach(() => {
    addSpy.mockRestore();
    removeSpy.mockRestore();
  });

  it('measures the rendered automatic height when the first gesture starts', () => {
    const { result } = renderHook(() => useFloatingPanelHeightResize(props()));

    act(() => result.current.startResize(pointerEvent(200)));
    act(() => handlers.pointermove({ clientY: 250 } as PointerEvent));

    expect(setHeight).toHaveBeenLastCalledWith(290);
  });

  it('resizes in both directions and clamps to both bounds', () => {
    const { result } = renderHook(() => useFloatingPanelHeightResize(props()));
    act(() => result.current.startResize(pointerEvent(200)));

    act(() => handlers.pointermove({ clientY: -999 } as PointerEvent));
    expect(setHeight).toHaveBeenLastCalledWith(120);
    act(() => handlers.pointermove({ clientY: 999 } as PointerEvent));
    expect(setHeight).toHaveBeenLastCalledWith(484);
  });

  it('finishes and removes pointer listeners on release', () => {
    const { result } = renderHook(() => useFloatingPanelHeightResize(props()));
    act(() => result.current.startResize(pointerEvent(200)));
    act(() => handlers.pointerup({} as PointerEvent));

    expect(setResizing).toHaveBeenLastCalledWith(false);
    expect(onResizeEnd).toHaveBeenCalledTimes(1);
    expect(handlers).toEqual({});
  });

  it('removes active listeners when unmounted', () => {
    const { result, unmount } = renderHook(() => useFloatingPanelHeightResize(props()));
    act(() => result.current.startResize(pointerEvent(200)));
    unmount();

    expect(removeSpy).toHaveBeenCalledWith('pointermove', expect.any(Function));
    expect(removeSpy).toHaveBeenCalledWith('pointerup', expect.any(Function));
    expect(removeSpy).toHaveBeenCalledWith('pointercancel', expect.any(Function));
  });

  it('supports keyboard resizing with a larger Shift step', () => {
    const { result } = renderHook(() => useFloatingPanelHeightResize(props()));

    act(() => result.current.resizeByKeyboard(10));
    expect(setHeight).toHaveBeenLastCalledWith(250);
    act(() => result.current.resizeByKeyboard(-50));
    expect(setHeight).toHaveBeenLastCalledWith(190);
    expect(onResizeEnd).toHaveBeenCalledTimes(2);
  });

  it('does not start for docked panels or secondary buttons', () => {
    const { result } = renderHook(() => useFloatingPanelHeightResize(props({ enabled: false })));
    act(() => result.current.startResize(pointerEvent(200)));
    expect(addSpy).not.toHaveBeenCalled();

    const { result: enabled } = renderHook(() => useFloatingPanelHeightResize(props()));
    act(() => enabled.current.startResize(pointerEvent(200, 2)));
    expect(addSpy).not.toHaveBeenCalled();
  });
});
