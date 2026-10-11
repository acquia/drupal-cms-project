import { renderHook, act } from '@testing-library/react';
import { useFocusTrap } from '../useFocusTrap';
import React from 'react';

describe('useFocusTrap', () => {
  let container: HTMLDivElement;
  let button1: HTMLButtonElement;
  let button2: HTMLButtonElement;
  let button3: HTMLButtonElement;
  let outsideButton: HTMLButtonElement;
  let containerRef: React.RefObject<HTMLDivElement>;

  beforeEach(() => {
    // Build a DOM structure with focusable elements
    container = document.createElement('div');
    button1 = document.createElement('button');
    button1.textContent = 'First';
    button2 = document.createElement('button');
    button2.textContent = 'Second';
    button3 = document.createElement('button');
    button3.textContent = 'Third';
    outsideButton = document.createElement('button');
    outsideButton.textContent = 'Outside';

    container.appendChild(button1);
    container.appendChild(button2);
    container.appendChild(button3);
    document.body.appendChild(container);
    document.body.appendChild(outsideButton);

    // Mock offsetParent (jsdom doesn't support layout)
    Object.defineProperty(button1, 'offsetParent', { get: () => container });
    Object.defineProperty(button2, 'offsetParent', { get: () => container });
    Object.defineProperty(button3, 'offsetParent', { get: () => container });

    containerRef = { current: container } as React.RefObject<HTMLDivElement>;
  });

  afterEach(() => {
    document.body.removeChild(container);
    document.body.removeChild(outsideButton);
    jest.restoreAllMocks();
  });

  test('should not attach listeners when inactive', () => {
    const addSpy = jest.spyOn(document, 'addEventListener');

    renderHook(() =>
      useFocusTrap({ isActive: false, containerRef }),
    );

    // Should not add keydown listener for the trap
    const keydownCalls = addSpy.mock.calls.filter(
      ([event]) => event === 'keydown',
    );
    expect(keydownCalls.length).toBe(0);
  });

  test('should attach keydown listener when active', () => {
    const addSpy = jest.spyOn(document, 'addEventListener');

    renderHook(() =>
      useFocusTrap({ isActive: true, containerRef }),
    );

    const keydownCalls = addSpy.mock.calls.filter(
      ([event]) => event === 'keydown',
    );
    expect(keydownCalls.length).toBeGreaterThan(0);
  });

  test('should auto-focus first focusable element when active', async () => {
    jest.useFakeTimers();
    const focusSpy = jest.spyOn(button1, 'focus');

    renderHook(() =>
      useFocusTrap({ isActive: true, containerRef, autoFocus: true }),
    );

    act(() => {
      jest.advanceTimersByTime(100);
    });

    expect(focusSpy).toHaveBeenCalled();
    jest.useRealTimers();
  });

  test('should not auto-focus when autoFocus is false', async () => {
    jest.useFakeTimers();
    const focusSpy = jest.spyOn(button1, 'focus');

    renderHook(() =>
      useFocusTrap({ isActive: true, containerRef, autoFocus: false }),
    );

    act(() => {
      jest.advanceTimersByTime(100);
    });

    expect(focusSpy).not.toHaveBeenCalled();
    jest.useRealTimers();
  });

  test('should call onClose when Escape is pressed', () => {
    const onClose = jest.fn();

    renderHook(() =>
      useFocusTrap({ isActive: true, onClose, containerRef }),
    );

    const event = new KeyboardEvent('keydown', {
      key: 'Escape',
      bubbles: true,
      cancelable: true,
    });

    act(() => {
      document.dispatchEvent(event);
    });

    expect(onClose).toHaveBeenCalledTimes(1);
  });

  test('should not call onClose when Escape is pressed and trap is inactive', () => {
    const onClose = jest.fn();

    renderHook(() =>
      useFocusTrap({ isActive: false, onClose, containerRef }),
    );

    const event = new KeyboardEvent('keydown', {
      key: 'Escape',
      bubbles: true,
    });

    act(() => {
      document.dispatchEvent(event);
    });

    expect(onClose).not.toHaveBeenCalled();
  });

  test('should wrap focus from last to first on Tab', () => {
    renderHook(() =>
      useFocusTrap({ isActive: true, containerRef }),
    );

    // Focus the last button
    button3.focus();
    const focusSpy = jest.spyOn(button1, 'focus');

    const event = new KeyboardEvent('keydown', {
      key: 'Tab',
      bubbles: true,
      cancelable: true,
    });

    act(() => {
      document.dispatchEvent(event);
    });

    expect(focusSpy).toHaveBeenCalled();
  });

  test('should wrap focus from first to last on Shift+Tab', () => {
    renderHook(() =>
      useFocusTrap({ isActive: true, containerRef }),
    );

    // Focus the first button
    button1.focus();
    const focusSpy = jest.spyOn(button3, 'focus');

    const event = new KeyboardEvent('keydown', {
      key: 'Tab',
      shiftKey: true,
      bubbles: true,
      cancelable: true,
    });

    act(() => {
      document.dispatchEvent(event);
    });

    expect(focusSpy).toHaveBeenCalled();
  });

  test('should not wrap focus on Tab when not on last element', () => {
    renderHook(() =>
      useFocusTrap({ isActive: true, containerRef }),
    );

    // Focus the first button (not last)
    button1.focus();
    const focusSpy1 = jest.spyOn(button1, 'focus');
    // Clear any calls from the auto-focus
    focusSpy1.mockClear();

    const event = new KeyboardEvent('keydown', {
      key: 'Tab',
      bubbles: true,
      cancelable: true,
    });

    act(() => {
      document.dispatchEvent(event);
    });

    // Should NOT have called focus on button1 (no wrapping needed)
    expect(focusSpy1).not.toHaveBeenCalled();
  });

  test('should restore focus when deactivated', () => {
    jest.useFakeTimers();

    // Focus the outside button first
    outsideButton.focus();

    const { rerender } = renderHook(
      ({ isActive }) => useFocusTrap({ isActive, containerRef }),
      { initialProps: { isActive: true } },
    );

    // Deactivate the trap
    const focusSpy = jest.spyOn(outsideButton, 'focus');

    rerender({ isActive: false });

    act(() => {
      jest.advanceTimersByTime(50);
    });

    expect(focusSpy).toHaveBeenCalled();
    jest.useRealTimers();
  });

  test('should not restore focus if previous element was removed from DOM', () => {
    jest.useFakeTimers();

    outsideButton.focus();

    const { rerender } = renderHook(
      ({ isActive }) => useFocusTrap({ isActive, containerRef }),
      { initialProps: { isActive: true } },
    );

    // Remove the outside button from DOM
    document.body.removeChild(outsideButton);

    const focusSpy = jest.spyOn(outsideButton, 'focus');

    rerender({ isActive: false });

    act(() => {
      jest.advanceTimersByTime(50);
    });

    expect(focusSpy).not.toHaveBeenCalled();

    // Re-add to avoid afterEach errors
    document.body.appendChild(outsideButton);
    jest.useRealTimers();
  });

  test('should focus container when no focusable children', () => {
    jest.useFakeTimers();

    // Empty container
    const emptyContainer = document.createElement('div');
    emptyContainer.tabIndex = -1;
    document.body.appendChild(emptyContainer);
    const emptyRef = { current: emptyContainer } as React.RefObject<HTMLDivElement>;
    const focusSpy = jest.spyOn(emptyContainer, 'focus');

    renderHook(() =>
      useFocusTrap({ isActive: true, containerRef: emptyRef, autoFocus: true }),
    );

    act(() => {
      jest.advanceTimersByTime(100);
    });

    expect(focusSpy).toHaveBeenCalled();

    document.body.removeChild(emptyContainer);
    jest.useRealTimers();
  });

  test('should remove listener on cleanup', () => {
    const removeSpy = jest.spyOn(document, 'removeEventListener');

    const { unmount } = renderHook(() =>
      useFocusTrap({ isActive: true, containerRef }),
    );

    unmount();

    const keydownCalls = removeSpy.mock.calls.filter(
      ([event]) => event === 'keydown',
    );
    expect(keydownCalls.length).toBeGreaterThan(0);
  });

  test('should preventDefault on Escape', () => {
    const onClose = jest.fn();

    renderHook(() =>
      useFocusTrap({ isActive: true, onClose, containerRef }),
    );

    const event = new KeyboardEvent('keydown', {
      key: 'Escape',
      bubbles: true,
      cancelable: true,
    });
    const preventSpy = jest.spyOn(event, 'preventDefault');

    act(() => {
      document.dispatchEvent(event);
    });

    expect(preventSpy).toHaveBeenCalled();
  });

  test('should ignore non-Tab non-Escape keys', () => {
    const onClose = jest.fn();

    renderHook(() =>
      useFocusTrap({ isActive: true, onClose, containerRef }),
    );

    const event = new KeyboardEvent('keydown', {
      key: 'a',
      bubbles: true,
      cancelable: true,
    });
    const preventSpy = jest.spyOn(event, 'preventDefault');

    act(() => {
      document.dispatchEvent(event);
    });

    expect(onClose).not.toHaveBeenCalled();
    expect(preventSpy).not.toHaveBeenCalled();
  });

  test('should handle null containerRef gracefully', () => {
    const nullRef = { current: null } as React.RefObject<HTMLElement | null>;

    // Should not throw
    expect(() => {
      renderHook(() =>
        useFocusTrap({ isActive: true, containerRef: nullRef }),
      );
    }).not.toThrow();
  });

  test('should skip disabled buttons in focusable list', () => {
    button2.disabled = true;

    renderHook(() =>
      useFocusTrap({ isActive: true, containerRef }),
    );

    // Focus last enabled button
    button3.focus();
    // Tab should wrap to button1 (skipping disabled button2)
    const focusSpy = jest.spyOn(button1, 'focus');

    const event = new KeyboardEvent('keydown', {
      key: 'Tab',
      bubbles: true,
      cancelable: true,
    });

    act(() => {
      document.dispatchEvent(event);
    });

    expect(focusSpy).toHaveBeenCalled();
    button2.disabled = false;
  });

  test('should filter out elements with offsetParent null (hidden elements)', () => {
    // Create a hidden button (offsetParent === null)
    const hiddenButton = document.createElement('button');
    hiddenButton.textContent = 'Hidden';
    Object.defineProperty(hiddenButton, 'offsetParent', { get: () => null });
    container.appendChild(hiddenButton);

    renderHook(() =>
      useFocusTrap({ isActive: true, containerRef }),
    );

    // Focus last visible button (button3) and Tab
    button3.focus();
    const focusSpy = jest.spyOn(button1, 'focus');

    const event = new KeyboardEvent('keydown', {
      key: 'Tab',
      bubbles: true,
      cancelable: true,
    });

    act(() => {
      document.dispatchEvent(event);
    });

    // Should wrap to button1, skipping the hidden button
    expect(focusSpy).toHaveBeenCalled();

    container.removeChild(hiddenButton);
  });

  test('should do nothing on Tab when focusable list is empty', () => {
    // Empty container with no focusable children
    const emptyContainer = document.createElement('div');
    document.body.appendChild(emptyContainer);
    const emptyRef = { current: emptyContainer } as React.RefObject<HTMLDivElement>;

    renderHook(() =>
      useFocusTrap({ isActive: true, containerRef: emptyRef, autoFocus: false }),
    );

    const event = new KeyboardEvent('keydown', {
      key: 'Tab',
      bubbles: true,
      cancelable: true,
    });
    const preventSpy = jest.spyOn(event, 'preventDefault');

    act(() => {
      document.dispatchEvent(event);
    });

    // Should not call preventDefault (no wrapping to do)
    expect(preventSpy).not.toHaveBeenCalled();

    document.body.removeChild(emptyContainer);
  });

  test('should stopImmediatePropagation on Escape', () => {
    const onClose = jest.fn();

    renderHook(() =>
      useFocusTrap({ isActive: true, onClose, containerRef }),
    );

    const event = new KeyboardEvent('keydown', {
      key: 'Escape',
      bubbles: true,
      cancelable: true,
    });
    const stopSpy = jest.spyOn(event, 'stopImmediatePropagation');

    act(() => {
      document.dispatchEvent(event);
    });

    expect(stopSpy).toHaveBeenCalled();
  });

  test('should not Shift+Tab wrap when not on first element', () => {
    renderHook(() =>
      useFocusTrap({ isActive: true, containerRef }),
    );

    // Focus the second button (not first)
    button2.focus();
    const focusSpy3 = jest.spyOn(button3, 'focus');

    const event = new KeyboardEvent('keydown', {
      key: 'Tab',
      shiftKey: true,
      bubbles: true,
      cancelable: true,
    });

    act(() => {
      document.dispatchEvent(event);
    });

    // Should NOT wrap to last (focus stays natural)
    expect(focusSpy3).not.toHaveBeenCalled();
  });

  test('should preventDefault on Tab wrap (last to first)', () => {
    renderHook(() =>
      useFocusTrap({ isActive: true, containerRef }),
    );

    button3.focus();

    const event = new KeyboardEvent('keydown', {
      key: 'Tab',
      bubbles: true,
      cancelable: true,
    });
    const preventSpy = jest.spyOn(event, 'preventDefault');

    act(() => {
      document.dispatchEvent(event);
    });

    expect(preventSpy).toHaveBeenCalled();
  });

  test('should preventDefault on Shift+Tab wrap (first to last)', () => {
    renderHook(() =>
      useFocusTrap({ isActive: true, containerRef }),
    );

    button1.focus();

    const event = new KeyboardEvent('keydown', {
      key: 'Tab',
      shiftKey: true,
      bubbles: true,
      cancelable: true,
    });
    const preventSpy = jest.spyOn(event, 'preventDefault');

    act(() => {
      document.dispatchEvent(event);
    });

    expect(preventSpy).toHaveBeenCalled();
  });

  test('should not restore focus when previousFocusRef is null', () => {
    jest.useFakeTimers();

    // Do NOT focus anything before activating the trap
    // (document.activeElement will be <body> which is not a normal element we saved)

    const { rerender } = renderHook(
      ({ isActive }) => useFocusTrap({ isActive, containerRef, autoFocus: false }),
      { initialProps: { isActive: false } },
    );

    // Activate
    rerender({ isActive: true });

    // Now deactivate — previousFocusRef should be null or body
    rerender({ isActive: false });

    act(() => {
      jest.advanceTimersByTime(50);
    });

    // Should not throw — just gracefully skip restore
    jest.useRealTimers();
  });

  test('should capture previous focus on activation via useLayoutEffect', () => {
    jest.useFakeTimers();

    // Focus outside button
    outsideButton.focus();
    expect(document.activeElement).toBe(outsideButton);

    const { rerender } = renderHook(
      ({ isActive }) => useFocusTrap({ isActive, containerRef }),
      { initialProps: { isActive: false } },
    );

    // Activate — should capture outsideButton as previous focus
    rerender({ isActive: true });

    act(() => {
      jest.advanceTimersByTime(100);
    });

    // Now deactivate — should restore to outsideButton
    const focusSpy = jest.spyOn(outsideButton, 'focus');
    rerender({ isActive: false });

    act(() => {
      jest.advanceTimersByTime(50);
    });

    expect(focusSpy).toHaveBeenCalled();
    jest.useRealTimers();
  });

  // ── Nested dialog isolation (regression tests for #3576271) ──────────

  describe('nested dialog isolation', () => {
    let containerA: HTMLDivElement;
    let containerB: HTMLDivElement;
    let btnA: HTMLButtonElement;
    let btnB: HTMLButtonElement;
    let refA: React.RefObject<HTMLDivElement>;
    let refB: React.RefObject<HTMLDivElement>;

    beforeEach(() => {
      containerA = document.createElement('div');
      containerA.setAttribute('data-testid', 'trap-a');
      btnA = document.createElement('button');
      btnA.textContent = 'A-Btn';
      containerA.appendChild(btnA);
      document.body.appendChild(containerA);
      Object.defineProperty(btnA, 'offsetParent', { get: () => containerA });

      containerB = document.createElement('div');
      containerB.setAttribute('data-testid', 'trap-b');
      btnB = document.createElement('button');
      btnB.textContent = 'B-Btn';
      containerB.appendChild(btnB);
      document.body.appendChild(containerB);
      Object.defineProperty(btnB, 'offsetParent', { get: () => containerB });

      refA = { current: containerA } as React.RefObject<HTMLDivElement>;
      refB = { current: containerB } as React.RefObject<HTMLDivElement>;
    });

    afterEach(() => {
      document.body.removeChild(containerA);
      document.body.removeChild(containerB);
    });

    test('Escape only closes the trap whose container has focus', () => {
      const onCloseA = jest.fn();
      const onCloseB = jest.fn();

      renderHook(() => useFocusTrap({ isActive: true, onClose: onCloseA, containerRef: refA, autoFocus: false }));
      renderHook(() => useFocusTrap({ isActive: true, onClose: onCloseB, containerRef: refB, autoFocus: false }));

      // Focus inside container B (the "nested" dialog)
      btnB.focus();
      expect(document.activeElement).toBe(btnB);

      const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
      act(() => { document.dispatchEvent(event); });

      // Only the trap that owns the focused element should close
      expect(onCloseB).toHaveBeenCalledTimes(1);
      expect(onCloseA).not.toHaveBeenCalled();
    });

    test('Escape is ignored when activeElement is outside the container', () => {
      const onClose = jest.fn();

      renderHook(() => useFocusTrap({ isActive: true, onClose, containerRef: refA, autoFocus: false }));

      // Focus an element outside container A
      btnB.focus();
      expect(document.activeElement).toBe(btnB);

      const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
      act(() => { document.dispatchEvent(event); });

      expect(onClose).not.toHaveBeenCalled();
    });

    test('Escape still fires when activeElement is document.body', () => {
      const onClose = jest.fn();

      renderHook(() => useFocusTrap({ isActive: true, onClose, containerRef: refA, autoFocus: false }));

      // Blur everything so activeElement is document.body
      (document.activeElement as HTMLElement)?.blur?.();
      expect(document.activeElement).toBe(document.body);

      const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
      act(() => { document.dispatchEvent(event); });

      expect(onClose).toHaveBeenCalledTimes(1);
    });

    test('Escape fires when activeElement is inside the container', () => {
      const onClose = jest.fn();

      renderHook(() => useFocusTrap({ isActive: true, onClose, containerRef: refA, autoFocus: false }));

      btnA.focus();
      expect(document.activeElement).toBe(btnA);

      const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
      act(() => { document.dispatchEvent(event); });

      expect(onClose).toHaveBeenCalledTimes(1);
    });

    test('Tab is ignored when activeElement is outside the container', () => {
      renderHook(() => useFocusTrap({ isActive: true, containerRef: refA, autoFocus: false }));

      // Focus element outside container A
      btnB.focus();

      const event = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true });
      const preventSpy = jest.spyOn(event, 'preventDefault');

      act(() => { document.dispatchEvent(event); });

      // Tab should not be intercepted for a foreign container
      expect(preventSpy).not.toHaveBeenCalled();
    });

    test('stopImmediatePropagation prevents sibling handlers from running', () => {
      const onClose = jest.fn();
      const siblingHandler = jest.fn();

      renderHook(() => useFocusTrap({ isActive: true, onClose, containerRef: refA, autoFocus: false }));

      // Register a sibling capture-phase keydown handler
      document.addEventListener('keydown', siblingHandler, true);

      btnA.focus();

      const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
      act(() => { document.dispatchEvent(event); });

      expect(onClose).toHaveBeenCalledTimes(1);
      // The sibling handler should NOT have fired because stopImmediatePropagation was called
      expect(siblingHandler).not.toHaveBeenCalled();

      document.removeEventListener('keydown', siblingHandler, true);
    });
  });
});
