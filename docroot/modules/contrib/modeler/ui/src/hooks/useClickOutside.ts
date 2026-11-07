/**
 * useClickOutside - Hook for detecting clicks outside a set of refs.
 *
 * Listens for `pointerdown` events on the document (capture phase) and
 * calls the provided callback when a click occurs outside all given refs.
 * Uses a delayed listener registration to prevent the opening click from
 * immediately closing the target element.
 *
 * Clicks that land on or inside a modal overlay (`.documentation-popup-overlay`
 * or any element with `[aria-modal="true"]`) are ignored so that nested
 * dialogs (e.g. a documentation popup opened from within the quick-add
 * popup) do not inadvertently close the parent.
 */

import { useEffect } from 'react';

/** Selector that matches modal overlays and modal dialogs. */
const MODAL_SELECTOR = '[aria-modal="true"], .documentation-popup-overlay';

export function useClickOutside(
  isActive: boolean,
  refs: React.RefObject<HTMLElement | null>[],
  onClickOutside: () => void,
): void {
  useEffect(() => {
    if (!isActive) return;

    const handleClickOutside = (e: Event) => {
      const target = e.target as HTMLElement | null;
      if (!target) return;

      const isInsideAnyRef = refs.some(
        ref => ref.current && ref.current.contains(target),
      );

      if (isInsideAnyRef) return;

      // Ignore clicks inside a modal dialog or its overlay that is layered
      // on top of this element (e.g. a DocumentationPopup opened from a
      // QuickAddPopup).  Without this check, interacting with or closing
      // the nested modal would also close the parent popup because the
      // click target is outside the parent's refs.
      if (target.closest(MODAL_SELECTOR)) return;

      onClickOutside();
    };

    // Delay adding the listener to prevent the same click that opened
    // the popup from immediately closing it.
    const timer = setTimeout(() => {
      document.addEventListener('pointerdown', handleClickOutside, true);
    }, 0);

    return () => {
      clearTimeout(timer);
      document.removeEventListener('pointerdown', handleClickOutside, true);
    };
  }, [isActive, refs, onClickOutside]);
}
