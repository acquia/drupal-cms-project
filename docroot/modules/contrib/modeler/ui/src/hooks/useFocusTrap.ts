/**
 * useFocusTrap - Traps keyboard focus within a container element.
 *
 * When active, Tab and Shift+Tab cycle through focusable elements inside
 * the container without escaping to the background.  Escape key triggers
 * the onClose callback.
 *
 * Also restores focus to the element that was focused before the trap
 * activated (focus return).
 */

import { useEffect, useLayoutEffect, useRef, useCallback } from 'react';

const FOCUSABLE_SELECTOR = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  // ContentEditableField renders contenteditable="false" when disabled, so
  // matching the enabled state only keeps disabled fields out of the cycle.
  '[contenteditable="true"]',
  '[tabindex]:not([tabindex="-1"])',
].join(', ');

interface UseFocusTrapOptions {
  /** Whether the trap is currently active */
  isActive: boolean;
  /** Called when Escape is pressed inside the trap */
  onClose?: () => void;
  /** Ref to the container element that should trap focus */
  containerRef: React.RefObject<HTMLElement | null>;
  /** Whether to auto-focus the first focusable element (default: true) */
  autoFocus?: boolean;
}

export function useFocusTrap({
  isActive,
  onClose,
  containerRef,
  autoFocus = true,
}: UseFocusTrapOptions): void {
  // Store the element that had focus before the trap activated
  const previousFocusRef = useRef<HTMLElement | null>(null);

  // Get all focusable elements inside the container
  const getFocusableElements = useCallback((): HTMLElement[] => {
    if (!containerRef.current) return [];
    return Array.from(
      containerRef.current.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR),
    ).filter(
      (el) => !el.hasAttribute('disabled') && el.offsetParent !== null,
    );
  }, [containerRef]);

  // Capture previous focus synchronously (before browser paints) when trap activates.
  // useLayoutEffect runs before the DOM is painted, so document.activeElement is still
  // the element that was focused before the dialog rendered.
  useLayoutEffect(() => {
    if (isActive) {
      previousFocusRef.current = document.activeElement as HTMLElement | null;
    }
  }, [isActive]);

  // Activate: move focus into container
  useEffect(() => {
    if (!isActive) return;

    if (autoFocus) {
      // Small delay to let the DOM render before focusing
      const timer = setTimeout(() => {
        const focusable = getFocusableElements();
        if (focusable.length > 0) {
          focusable[0].focus();
        } else {
          // If no focusable children, focus the container itself
          containerRef.current?.focus();
        }
      }, 50);
      return () => clearTimeout(timer);
    }
  }, [isActive, autoFocus, getFocusableElements, containerRef]);

  // Deactivate: restore focus to the previously focused element
  useEffect(() => {
    if (isActive) return;

    // Only restore if we previously saved a focus target
    const previousElement = previousFocusRef.current;
    if (previousElement && typeof previousElement.focus === 'function') {
      // Small delay to ensure the dialog is fully unmounted
      const timer = setTimeout(() => {
        // Check the element is still in the DOM
        if (document.body.contains(previousElement)) {
          previousElement.focus();
        }
      }, 0);
      return () => clearTimeout(timer);
    }
  }, [isActive]);

  // Keyboard handler: trap Tab, handle Escape
  useEffect(() => {
    if (!isActive) return;

    const handleKeyDown = (e: KeyboardEvent) => {
      // Only handle keys when focus is inside this container.
      // This prevents an outer focus trap (e.g. QuickAddPopup) from
      // reacting to Escape presses meant for a nested dialog
      // (e.g. DocumentationPopup) that has its own trap.
      const container = containerRef.current;
      if (!container) return;
      const focused = document.activeElement as HTMLElement | null;
      if (focused && focused !== document.body && !container.contains(focused)) return;

      if (e.key === 'Escape' && onClose) {
        e.preventDefault();
        e.stopImmediatePropagation();
        onClose();
        return;
      }

      if (e.key !== 'Tab') return;

      const focusable = getFocusableElements();
      if (focusable.length === 0) return;

      const firstFocusable = focusable[0];
      const lastFocusable = focusable[focusable.length - 1];

      if (e.shiftKey) {
        // Shift+Tab: if on first element, wrap to last
        if (document.activeElement === firstFocusable) {
          e.preventDefault();
          lastFocusable.focus();
        }
      } else {
        // Tab: if on last element, wrap to first
        if (document.activeElement === lastFocusable) {
          e.preventDefault();
          firstFocusable.focus();
        }
      }
    };

    // Use capture phase to intercept before other handlers
    document.addEventListener('keydown', handleKeyDown, true);
    return () => document.removeEventListener('keydown', handleKeyDown, true);
  }, [isActive, onClose, getFocusableElements, containerRef]);
}
