/**
 * Tests for useClickOutside hook
 */

import { renderHook } from '@testing-library/react';
import { useClickOutside } from '../useClickOutside';

describe('useClickOutside', () => {
  beforeEach(() => {
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  it('should not add event listener when isActive is false', () => {
    const onClickOutside = jest.fn();
    const ref = { current: document.createElement('div') };
    
    const refs = [ref as React.RefObject<HTMLElement | null>];
    
    renderHook(() => useClickOutside(false, refs, onClickOutside));
    
    jest.runAllTimers();
    
    document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }));
    
    expect(onClickOutside).not.toHaveBeenCalled();
  });

  it('should call onClickOutside when clicking outside refs', () => {
    const onClickOutside = jest.fn();
    const ref = { current: document.createElement('div') };
    
    const refs = [ref as React.RefObject<HTMLElement | null>];
    
    const { unmount } = renderHook(() => useClickOutside(true, refs, onClickOutside));
    
    jest.runAllTimers();
    
    document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }));
    
    expect(onClickOutside).toHaveBeenCalledTimes(1);
    
    unmount();
  });

  it('should not call onClickOutside when clicking inside refs', () => {
    const onClickOutside = jest.fn();
    const refElement = document.createElement('div');
    const ref = { current: refElement };
    
    const refs = [ref as React.RefObject<HTMLElement | null>];
    
    const { unmount } = renderHook(() => useClickOutside(true, refs, onClickOutside));
    
    jest.runAllTimers();
    
    refElement.dispatchEvent(new Event('pointerdown', { bubbles: true }));
    
    expect(onClickOutside).not.toHaveBeenCalled();
    
    unmount();
  });

  it('should clean up event listener on unmount', () => {
    const onClickOutside = jest.fn();
    const ref = { current: document.createElement('div') };
    
    const refs = [ref as React.RefObject<HTMLElement | null>];
    
    const { unmount } = renderHook(() => useClickOutside(true, refs, onClickOutside));
    
    jest.runAllTimers();
    
    unmount();
    
    document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }));
    
    expect(onClickOutside).not.toHaveBeenCalled();
  });

  it('should handle null target in event', () => {
    const onClickOutside = jest.fn();
    const ref = { current: document.createElement('div') };
    
    const refs = [ref as React.RefObject<HTMLElement | null>];
    
    const { unmount } = renderHook(() => useClickOutside(true, refs, onClickOutside));
    
    jest.runAllTimers();
    
    const event = new Event('pointerdown', { bubbles: true });
    Object.defineProperty(event, 'target', { get: () => null });
    document.body.dispatchEvent(event);
    
    expect(onClickOutside).not.toHaveBeenCalled();
    
    unmount();
  });

  // ── Modal awareness (regression tests for #3576271) ──────────────────

  describe('modal awareness', () => {
    it('should not trigger when clicking an element with aria-modal="true"', () => {
      const onClickOutside = jest.fn();
      const ref = { current: document.createElement('div') };
      const refs = [ref as React.RefObject<HTMLElement | null>];

      // Create a modal dialog element outside the ref
      const modal = document.createElement('div');
      modal.setAttribute('aria-modal', 'true');
      document.body.appendChild(modal);

      const { unmount } = renderHook(() => useClickOutside(true, refs, onClickOutside));
      jest.runAllTimers();

      modal.dispatchEvent(new Event('pointerdown', { bubbles: true }));
      expect(onClickOutside).not.toHaveBeenCalled();

      document.body.removeChild(modal);
      unmount();
    });

    it('should not trigger when clicking a child inside aria-modal="true"', () => {
      const onClickOutside = jest.fn();
      const ref = { current: document.createElement('div') };
      const refs = [ref as React.RefObject<HTMLElement | null>];

      const modal = document.createElement('div');
      modal.setAttribute('aria-modal', 'true');
      const child = document.createElement('button');
      child.textContent = 'Close';
      modal.appendChild(child);
      document.body.appendChild(modal);

      const { unmount } = renderHook(() => useClickOutside(true, refs, onClickOutside));
      jest.runAllTimers();

      child.dispatchEvent(new Event('pointerdown', { bubbles: true }));
      expect(onClickOutside).not.toHaveBeenCalled();

      document.body.removeChild(modal);
      unmount();
    });

    it('should not trigger when clicking on .documentation-popup-overlay', () => {
      const onClickOutside = jest.fn();
      const ref = { current: document.createElement('div') };
      const refs = [ref as React.RefObject<HTMLElement | null>];

      const overlay = document.createElement('div');
      overlay.className = 'documentation-popup-overlay';
      document.body.appendChild(overlay);

      const { unmount } = renderHook(() => useClickOutside(true, refs, onClickOutside));
      jest.runAllTimers();

      overlay.dispatchEvent(new Event('pointerdown', { bubbles: true }));
      expect(onClickOutside).not.toHaveBeenCalled();

      document.body.removeChild(overlay);
      unmount();
    });

    it('should not trigger when clicking a child inside .documentation-popup-overlay', () => {
      const onClickOutside = jest.fn();
      const ref = { current: document.createElement('div') };
      const refs = [ref as React.RefObject<HTMLElement | null>];

      const overlay = document.createElement('div');
      overlay.className = 'documentation-popup-overlay';
      const inner = document.createElement('div');
      inner.className = 'documentation-popup';
      overlay.appendChild(inner);
      document.body.appendChild(overlay);

      const { unmount } = renderHook(() => useClickOutside(true, refs, onClickOutside));
      jest.runAllTimers();

      inner.dispatchEvent(new Event('pointerdown', { bubbles: true }));
      expect(onClickOutside).not.toHaveBeenCalled();

      document.body.removeChild(overlay);
      unmount();
    });

    it('should still trigger for non-modal elements outside refs', () => {
      const onClickOutside = jest.fn();
      const ref = { current: document.createElement('div') };
      const refs = [ref as React.RefObject<HTMLElement | null>];

      // A regular (non-modal) element outside the refs
      const regularDiv = document.createElement('div');
      regularDiv.className = 'some-other-element';
      document.body.appendChild(regularDiv);

      const { unmount } = renderHook(() => useClickOutside(true, refs, onClickOutside));
      jest.runAllTimers();

      regularDiv.dispatchEvent(new Event('pointerdown', { bubbles: true }));
      expect(onClickOutside).toHaveBeenCalledTimes(1);

      document.body.removeChild(regularDiv);
      unmount();
    });
  });
});
