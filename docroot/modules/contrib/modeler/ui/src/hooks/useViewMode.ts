/**
 * useViewMode — Manages the modeler's view mode (fullscreen / restored).
 *
 * In Drupal mode the default is "fullscreen" (position: fixed, covers viewport).
 * In standalone mode the default is "restored" (fills parent container).
 *
 * When the regular (Drupal) modeler is in "restored" mode it becomes a
 * draggable + resizable floating window overlaying the host page.
 *
 * The standalone modeler in "fullscreen" mode takes over the viewport
 * (position: fixed) just like the Drupal modeler's default.
 */

import { useState, useCallback, useRef, useEffect } from 'react';

export type ViewMode = 'fullscreen' | 'restored';

interface WindowRect {
  top: number;
  left: number;
  width: number;
  height: number;
}

interface UseViewModeProps {
  /** Whether the modeler runs in standalone (embedded) mode. */
  isStandalone: boolean;
  /** Ref to the `.workflow-modeler` container element. */
  modelerRef: React.RefObject<HTMLDivElement | null>;
}

interface UseViewModeReturn {
  /** Current view mode. */
  viewMode: ViewMode;
  /** Toggle between fullscreen and restored. */
  toggleViewMode: () => void;
  /** Start dragging the window (call from a mousedown on the title bar). */
  startDrag: (e: React.MouseEvent) => void;
  /** Start resizing the window (call from a mousedown on the resize handle). */
  startResize: (e: React.MouseEvent) => void;
  /** Whether the window is currently being dragged. */
  isDragging: boolean;
  /** Whether the window is currently being resized. */
  isResizing: boolean;
}

/** localStorage key for persisted window rect. */
const STORAGE_KEY = 'workflow_modeler_window_rect';

/** Default restored size: 80 % of viewport, centered. */
function defaultRect(): WindowRect {
  const w = Math.round(window.innerWidth * 0.8);
  const h = Math.round(window.innerHeight * 0.8);
  return {
    top: Math.round((window.innerHeight - h) / 2),
    left: Math.round((window.innerWidth - w) / 2),
    width: w,
    height: h,
  };
}

/** Minimum dimensions for the windowed modeler. */
const MIN_WIDTH = 480;
const MIN_HEIGHT = 360;

/** Try to load a previously saved rect from localStorage. */
function loadSavedRect(): WindowRect | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw) as Record<string, unknown>;
    if (
      typeof parsed.top === 'number' &&
      typeof parsed.left === 'number' &&
      typeof parsed.width === 'number' &&
      typeof parsed.height === 'number'
    ) {
      // Clamp to current viewport so the window isn't off-screen after a
      // browser resize.
      const vw = window.innerWidth;
      const vh = window.innerHeight;
      const width = Math.max(MIN_WIDTH, Math.min(parsed.width as number, vw));
      const height = Math.max(MIN_HEIGHT, Math.min(parsed.height as number, vh));
      const left = Math.max(0, Math.min(parsed.left as number, vw - MIN_WIDTH));
      const top = Math.max(0, Math.min(parsed.top as number, vh - MIN_HEIGHT));
      return { top, left, width, height };
    }
  } catch {
    // Ignore corrupt / unavailable storage.
  }
  return null;
}

/** Persist the rect to localStorage (fire-and-forget). */
function saveRect(rect: WindowRect): void {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(rect));
  } catch {
    // Storage full or unavailable — ignore.
  }
}

export function useViewMode({
  isStandalone,
  modelerRef,
}: UseViewModeProps): UseViewModeReturn {
  const [viewMode, setViewMode] = useState<ViewMode>(
    isStandalone ? 'restored' : 'fullscreen',
  );
  const [isDragging, setIsDragging] = useState(false);
  const [isResizing, setIsResizing] = useState(false);

  // Persisted window position/size across toggles.
  // Prefer a previously saved rect from localStorage, fall back to 80 % default.
  const rectRef = useRef<WindowRect>(loadSavedRect() ?? defaultRect());

  // ────────────────────────────────────────────────────────────
  //  Apply / clear inline styles on the modeler container
  // ────────────────────────────────────────────────────────────

  const applyRect = useCallback((rect: WindowRect) => {
    const el = modelerRef.current;
    if (!el) return;
    el.style.top = `${rect.top}px`;
    el.style.left = `${rect.left}px`;
    el.style.width = `${rect.width}px`;
    el.style.height = `${rect.height}px`;
  }, [modelerRef]);

  const clearInlineStyles = useCallback(() => {
    const el = modelerRef.current;
    if (!el) return;
    el.style.top = '';
    el.style.left = '';
    el.style.width = '';
    el.style.height = '';
  }, [modelerRef]);

  // When the mode changes, apply or clear inline positioning.
  // For standalone "restored" we do NOT apply inline styles — the CSS
  // class already handles position: relative + 100%.
  useEffect(() => {
    if (viewMode === 'restored' && !isStandalone) {
      applyRect(rectRef.current);
    } else {
      clearInlineStyles();
    }
  }, [viewMode, isStandalone, applyRect, clearInlineStyles]);

  // ────────────────────────────────────────────────────────────
  //  Toggle
  // ────────────────────────────────────────────────────────────

  const toggleViewMode = useCallback(() => {
    setViewMode(prev => {
      const next = prev === 'fullscreen' ? 'restored' : 'fullscreen';
      // When switching to restored in Drupal mode, use the saved rect
      // from localStorage (or fall back to 80 % default).
      if (next === 'restored' && !isStandalone) {
        rectRef.current = loadSavedRect() ?? defaultRect();
      }
      return next;
    });
  }, [isStandalone]);

  // ────────────────────────────────────────────────────────────
  //  Drag (title bar) — only for Drupal restored mode
  // ────────────────────────────────────────────────────────────

  const startDrag = useCallback((e: React.MouseEvent) => {
    if (viewMode !== 'restored' || isStandalone) return;
    e.preventDefault();
    setIsDragging(true);

    const startX = e.clientX;
    const startY = e.clientY;
    const startTop = rectRef.current.top;
    const startLeft = rectRef.current.left;

    const handleMouseMove = (moveEvent: MouseEvent) => {
      const deltaX = moveEvent.clientX - startX;
      const deltaY = moveEvent.clientY - startY;
      rectRef.current = {
        ...rectRef.current,
        top: startTop + deltaY,
        left: startLeft + deltaX,
      };
      applyRect(rectRef.current);
    };

    const handleMouseUp = () => {
      setIsDragging(false);
      saveRect(rectRef.current);
      document.removeEventListener('mousemove', handleMouseMove);
      document.removeEventListener('mouseup', handleMouseUp);
    };

    document.addEventListener('mousemove', handleMouseMove);
    document.addEventListener('mouseup', handleMouseUp);
  }, [viewMode, isStandalone, applyRect]);

  // ────────────────────────────────────────────────────────────
  //  Resize (corner handle) — only for Drupal restored mode
  // ────────────────────────────────────────────────────────────

  const startResize = useCallback((e: React.MouseEvent) => {
    if (viewMode !== 'restored' || isStandalone) return;
    e.preventDefault();
    e.stopPropagation();
    setIsResizing(true);

    const startX = e.clientX;
    const startY = e.clientY;
    const startWidth = rectRef.current.width;
    const startHeight = rectRef.current.height;

    const handleMouseMove = (moveEvent: MouseEvent) => {
      const newWidth = Math.max(MIN_WIDTH, startWidth + (moveEvent.clientX - startX));
      const newHeight = Math.max(MIN_HEIGHT, startHeight + (moveEvent.clientY - startY));
      rectRef.current = {
        ...rectRef.current,
        width: newWidth,
        height: newHeight,
      };
      applyRect(rectRef.current);
    };

    const handleMouseUp = () => {
      setIsResizing(false);
      saveRect(rectRef.current);
      document.removeEventListener('mousemove', handleMouseMove);
      document.removeEventListener('mouseup', handleMouseUp);
    };

    document.addEventListener('mousemove', handleMouseMove);
    document.addEventListener('mouseup', handleMouseUp);
  }, [viewMode, isStandalone, applyRect]);

  return {
    viewMode,
    toggleViewMode,
    startDrag,
    startResize,
    isDragging,
    isResizing,
  };
}
