import { useEffect, useCallback, useRef } from 'react';

interface KeyboardShortcutCallbacks {
  onDelete?: () => void;
  onCopy?: () => void;
  onPaste?: () => void;
  onToggleSearch?: () => void;
  onEscape?: () => void;
  onUndo?: () => void;
  onRedo?: () => void;
  /** Toggle between the property and review panel modes (Alt+Shift+R). */
  onToggleReviewMode?: () => void;
}

interface ModifierStates {
  isShiftPressed: boolean;
  setIsShiftPressed: (pressed: boolean) => void;
  isCtrlPressed: boolean;
  setIsCtrlPressed: (pressed: boolean) => void;
  isAltPressed: boolean;
  setIsAltPressed: (pressed: boolean) => void;
}

interface KeyboardCapabilities {
  canDelete: boolean;
  canCopy: boolean;
  canPaste: boolean;
  canSearch: boolean;
  canEscape: boolean;
  canUndo: boolean;
  canRedo: boolean;
  canToggleReviewMode: boolean;
}

interface UseKeyboardShortcutsProps {
  callbacks: KeyboardShortcutCallbacks;
  modifiers: ModifierStates;
  capabilities: KeyboardCapabilities;
  isModelerFocused: boolean;
  enabled?: boolean; // Allow disabling shortcuts (e.g., during replay)
}

// ============ Extracted Helpers ============

/** Check if user is interacting with form elements */
function isInFormField(event: KeyboardEvent): boolean {
  const target = event.target as HTMLElement;
  return target.tagName === 'INPUT' || 
         target.tagName === 'TEXTAREA' || 
         target.contentEditable === 'true' ||
         target.isContentEditable;
}

/** Check if there is a text selection on the page */
function hasTextSelection(): boolean {
  return !!(window.getSelection &&
            (window.getSelection()?.toString().length ?? 0) > 0);
}

/**
 * Check if user is in a context where shortcuts should be suppressed.
 * Form fields block all shortcuts. Text selection only blocks copy
 * (to allow native text copy) but not paste, search, delete, etc.
 */
function isInputContext(event: KeyboardEvent): boolean {
  if (isInFormField(event)) return true;

  // Text selection only blocks Ctrl+C so the user can copy text natively.
  // Other shortcuts (paste, search, etc.) should still work.
  const ctrlOrMeta = event.ctrlKey || event.metaKey;
  if (hasTextSelection() && ctrlOrMeta && event.key === 'c') return true;

  return false;
}

/**
 * Match the property/review mode toggle: Alt+Shift+R (Option+Shift+R on macOS).
 *
 * Matched on `event.code` (the physical key) rather than `event.key`, because
 * macOS rewrites `event.key` for Option combinations (Option+R reports the
 * registered-trademark sign, not "R"), which would make the shortcut
 * unreachable there. `Alt+Shift+R` itself is unreserved in Chrome, Firefox,
 * Safari and Edge on Linux, macOS and Windows, so it never collides with a
 * browser or OS binding. Ctrl/Meta must be absent so the browser's own
 * Ctrl+Shift+R (hard reload) stays untouched.
 */
function isReviewToggle(event: KeyboardEvent): boolean {
  return event.altKey && event.shiftKey && !event.ctrlKey && !event.metaKey && event.code === 'KeyR';
}

/**
 * Check whether the keyboard event matches a registered shortcut.
 * Returns true if the event corresponds to a known shortcut key combination.
 */
function isShortcutKey(
  event: KeyboardEvent,
  capabilities: KeyboardCapabilities
): boolean {
  const ctrlOrMeta = event.ctrlKey || event.metaKey;
  if ((event.key === 'Delete' || event.key === 'Backspace') && capabilities.canDelete) return true;
  if (ctrlOrMeta && event.key === 'c' && capabilities.canCopy) return true;
  if (ctrlOrMeta && event.key === 'v' && capabilities.canPaste) return true;
  if (ctrlOrMeta && event.key === 'f' && capabilities.canSearch) return true;
  if (event.key === 'Escape' && capabilities.canEscape) return true;
  if (ctrlOrMeta && event.key === 'z' && capabilities.canUndo && !event.shiftKey) return true;
  if (ctrlOrMeta && event.key === 'z' && capabilities.canRedo && event.shiftKey) return true;
  if (ctrlOrMeta && event.key === 'y' && capabilities.canRedo) return true;
  if (isReviewToggle(event) && capabilities.canToggleReviewMode) return true;
  return false;
}

/**
 * Create a keyboard shortcut handler that processes all shortcut keys.
 * Only executes callbacks on keydown; keypress/keyup only preventDefault.
 */
function createShortcutHandler(
  isModelerContext: (event: KeyboardEvent) => boolean,
  capabilities: KeyboardCapabilities,
  callbacks: KeyboardShortcutCallbacks
) {
  return (event: KeyboardEvent) => {
    if (!isModelerContext(event)) return;
    if (isInputContext(event)) return;
    
    // For keypress/keyup events, only prevent default to block browser behavior
    // (e.g. Firefox may fire keypress for Ctrl+F). Do NOT execute callbacks again.
    if (event.type !== 'keydown') {
      if (isShortcutKey(event, capabilities)) {
        event.preventDefault();
        event.stopPropagation();
      }
      return;
    }

    const { canDelete, canCopy, canPaste, canSearch, canEscape, canUndo, canRedo, canToggleReviewMode } = capabilities;
    const { onDelete, onCopy, onPaste, onToggleSearch, onEscape, onUndo, onRedo, onToggleReviewMode } = callbacks;
    const ctrlOrMeta = event.ctrlKey || event.metaKey;
    
    if ((event.key === 'Delete' || event.key === 'Backspace') && canDelete && onDelete) {
      event.preventDefault();
      event.stopPropagation();
      onDelete();
      return false;
    }
    
    if (ctrlOrMeta && event.key === 'c' && canCopy && onCopy) {
      event.preventDefault();
      event.stopPropagation();
      onCopy();
      return false;
    }
    
    if (ctrlOrMeta && event.key === 'v' && canPaste && onPaste) {
      event.preventDefault();
      event.stopPropagation();
      onPaste();
      return false;
    }
    
    if (ctrlOrMeta && event.key === 'f' && canSearch && onToggleSearch) {
      event.preventDefault();
      event.stopPropagation();
      onToggleSearch();
      return false;
    }
    
    if (event.key === 'Escape' && canEscape && onEscape) {
      event.preventDefault();
      event.stopPropagation();
      onEscape();
      return false;
    }
    
    if (ctrlOrMeta && event.key === 'z' && canUndo && onUndo && !event.shiftKey) {
      event.preventDefault();
      event.stopPropagation();
      onUndo();
      return false;
    }
    
    if (ctrlOrMeta && event.key === 'z' && canRedo && onRedo && event.shiftKey) {
      event.preventDefault();
      event.stopPropagation();
      onRedo();
      return false;
    }
    
    if (ctrlOrMeta && event.key === 'y' && canRedo && onRedo) {
      event.preventDefault();
      event.stopPropagation();
      onRedo();
      return false;
    }

    if (isReviewToggle(event) && canToggleReviewMode && onToggleReviewMode) {
      event.preventDefault();
      event.stopPropagation();
      onToggleReviewMode();
      return false;
    }
  };
}

// ============ Main Hook ============

/**
 * Custom hook for managing keyboard shortcuts and modifiers in the modeler
 * 
 * Handles:
 * - Keyboard shortcuts (Ctrl+C, Ctrl+V, Ctrl+F, Delete, Escape, Alt+Shift+R)
 * - Modifier key tracking (Shift, Ctrl, Alt)
 * - Context-aware event handling (avoid conflicts with form inputs)
 * - Firefox-specific event handling
 *
 * Canvas zoom/pan gestures are handled natively by ReactFlow props
 * (panOnScroll, zoomOnPinch, etc.) configured in FlowCanvas.tsx.
 */
export const useKeyboardShortcuts = ({
  callbacks,
  modifiers,
  capabilities,
  isModelerFocused,
  enabled = true
}: UseKeyboardShortcutsProps) => {
  
  const { 
    isShiftPressed, setIsShiftPressed, 
    isCtrlPressed, setIsCtrlPressed,
    isAltPressed, setIsAltPressed 
  } = modifiers;

  // Check if event is within modeler context
  const isModelerContext = useCallback((event: KeyboardEvent): boolean => {
    if (isModelerFocused) return true;
    const target = event.target as HTMLElement;
    return target.closest('.workflow-modeler, .modeler') !== null;
  }, [isModelerFocused]);

  // Use refs to track current modifier state so event handlers always have
  // the latest values without needing to re-register on every state change.
  // This prevents missed keyup events during listener teardown/re-registration.
  const shiftRef = useRef(isShiftPressed);
  const ctrlRef = useRef(isCtrlPressed);
  const altRef = useRef(isAltPressed);

  // Keep refs in sync with props
  useEffect(() => { shiftRef.current = isShiftPressed; }, [isShiftPressed]);
  useEffect(() => { ctrlRef.current = isCtrlPressed; }, [isCtrlPressed]);
  useEffect(() => { altRef.current = isAltPressed; }, [isAltPressed]);

  // Handle keyboard modifiers and mouse wheel combinations
  useEffect(() => {
    if (!enabled) return;
    
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.shiftKey && !shiftRef.current) { shiftRef.current = true; setIsShiftPressed(true); }
      if ((e.ctrlKey || e.metaKey) && !ctrlRef.current) { ctrlRef.current = true; setIsCtrlPressed(true); }
      if (e.altKey && !altRef.current) { altRef.current = true; setIsAltPressed(true); }
    };

    const handleKeyUp = (e: KeyboardEvent) => {
      if (!e.shiftKey && shiftRef.current) { shiftRef.current = false; setIsShiftPressed(false); }
      if (!e.ctrlKey && !e.metaKey && ctrlRef.current) { ctrlRef.current = false; setIsCtrlPressed(false); }
      if (!e.altKey && altRef.current) { altRef.current = false; setIsAltPressed(false); }
    };

    window.addEventListener('keydown', handleKeyDown);
    window.addEventListener('keyup', handleKeyUp);

    return () => {
      window.removeEventListener('keydown', handleKeyDown);
      window.removeEventListener('keyup', handleKeyUp);
    };
  }, [
    enabled, 
    setIsShiftPressed, setIsCtrlPressed, setIsAltPressed
  ]);

  // Handle keyboard shortcuts (single handler for keydown + Firefox keypress/keyup)
  useEffect(() => {
    if (!enabled) return;
    
    const handleShortcut = createShortcutHandler(isModelerContext, capabilities, callbacks);

    // Add multiple event listeners with capture to ensure we intercept before other handlers
    document.addEventListener('keydown', handleShortcut, { capture: true });
    document.addEventListener('keypress', handleShortcut, { capture: true });
    document.addEventListener('keyup', handleShortcut, { capture: true });
    
    // Additional Firefox-specific handling on the modeler element
    const modelerElement = document.querySelector('.workflow-modeler, .modeler');
    if (modelerElement) {
      modelerElement.addEventListener('keydown', handleShortcut as EventListener, { capture: true });
      modelerElement.addEventListener('keypress', handleShortcut as EventListener, { capture: true });
      modelerElement.addEventListener('keyup', handleShortcut as EventListener, { capture: true });
    }

    return () => {
      document.removeEventListener('keydown', handleShortcut, { capture: true });
      document.removeEventListener('keypress', handleShortcut, { capture: true });
      document.removeEventListener('keyup', handleShortcut, { capture: true });

      if (modelerElement) {
        modelerElement.removeEventListener('keydown', handleShortcut as EventListener, { capture: true });
        modelerElement.removeEventListener('keypress', handleShortcut as EventListener, { capture: true });
        modelerElement.removeEventListener('keyup', handleShortcut as EventListener, { capture: true });
      }
    };
  }, [
    enabled,
    capabilities,
    isModelerContext,
    callbacks
  ]);
};
