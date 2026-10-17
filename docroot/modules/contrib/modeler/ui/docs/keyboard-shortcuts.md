# Keyboard Interactions

Comprehensive keyboard navigation and shortcuts following accessibility standards.

## Keyboard Shortcuts System

### Supported Shortcuts
```typescript
// Main shortcuts handled by useKeyboardShortcuts hook
const keyboardShortcuts = {
  'Delete': 'deleteSelected',           // Delete selected elements
  'Ctrl+C': 'copySelected',            // Copy selected elements  
  'Cmd+C': 'copySelected',             // Copy on Mac
  'Ctrl+V': 'pasteElements',            // Paste elements
  'Cmd+V': 'pasteElements',            // Paste on Mac
  'Ctrl+Z': 'undo',                    // Undo last action
  'Cmd+Z': 'undo',                     // Undo on Mac
  'Ctrl+Shift+Z': 'redo',              // Redo last undone action
  'Cmd+Shift+Z': 'redo',               // Redo on Mac
  'Ctrl+Y': 'redo',                    // Redo (alternative)
  'Ctrl+F': 'toggleSearch',            // Toggle search interface
  'Cmd+F': 'toggleSearch',             // Toggle search on Mac
  'Alt+Shift+R': 'toggleReviewMode',   // Toggle property/review panel mode
  'Escape': 'clearSearchAndHighlights'   // Clear search/selections
};
```

### Hook Implementation
```typescript
// useKeyboardShortcuts.ts - centralized keyboard handling
const useKeyboardShortcuts = ({
  callbacks,
  capabilities,
  enabled = true
}: KeyboardShortcutConfig) => {
  const [isShiftPressed, setIsShiftPressed] = useState(false);
  const [isCtrlPressed, setIsCtrlPressed] = useState(false);
  const [isAltPressed, setIsAltPressed] = useState(false);

  const handleKeyDown = useCallback((e: KeyboardEvent) => {
    // Update modifier states
    if (e.shiftKey) setIsShiftPressed(true);
    if (e.ctrlKey || e.metaKey) setIsCtrlPressed(true);
    if (e.altKey) setIsAltPressed(true);

    // Check if in input field (skip shortcuts)
    if (isInputElement(e.target)) return;

    // Handle shortcuts
    const key = getKeyCombo(e);
    const handler = callbacks[key];
    
    if (handler && capabilities[key]) {
      e.preventDefault();
      e.stopPropagation();
      handler();
    }
  }, [callbacks, capabilities]);

  const handleKeyUp = useCallback((e: KeyboardEvent) => {
    if (!e.shiftKey) setIsShiftPressed(false);
    if (!e.ctrlKey && !e.metaKey) setIsCtrlPressed(false);
    if (!e.altKey) setIsAltPressed(false);
  }, []);

  // Add event listeners
  useEffect(() => {
    if (!enabled) return;

    document.addEventListener('keydown', handleKeyDown);
    document.addEventListener('keyup', handleKeyUp);

    return () => {
      document.removeEventListener('keydown', handleKeyDown);
      document.removeEventListener('keyup', handleKeyUp);
    };
  }, [enabled, handleKeyDown, handleKeyUp]);

  return {
    isShiftPressed,
    isCtrlPressed,
    isAltPressed
  };
};
```

## Core Architecture

**Separation of Concerns Pattern:**
1. **Input Handling**: Hook manages all event listeners and modifier tracking
2. **Context Awareness**: Automatically detects form inputs and text selections
3. **Callback Pattern**: Workflow logic provides callbacks for keyboard actions
4. **Capabilities-Based**: Actions checked against current application capabilities
5. **Cross-Browser**: Handles Firefox-specific quirks and capture phase events
6. **Extensible**: Easy to add new shortcuts or disable during specific modes

## Keyboard Shortcuts Supported

| Shortcut                  | Action                      | Context                    |
|---------------------------|-----------------------------|----------------------------|
| `Delete`                  | Delete selected nodes/edges | Canvas selection           |
| `Ctrl+C` (`Cmd+C` on Mac) | Copy selected elements      | Canvas selection           |
| `Ctrl+V` (`Cmd+V` on Mac) | Paste elements              | Canvas focus               |
| `Ctrl+Z` (`Cmd+Z` on Mac) | Undo last action            | Global                     |
| `Ctrl+Shift+Z` (`Cmd+Shift+Z`) / `Ctrl+Y` | Redo last undone action | Global      |
| `Ctrl+F` (`Cmd+F` on Mac) | Toggle search interface     | Global (overrides browser) |
| `Escape`                  | Clear search/highlights     | Global                     |
| `Alt+Shift+R` (`Option+Shift+R` on Mac) | Toggle property/review panel mode | Replay/test capability or review mode |

`Alt+Shift+R` is matched on `event.code === 'KeyR'` with
`altKey && shiftKey && !ctrlKey && !metaKey`, never on `event.key`: macOS turns
`Option+Shift+R` into a different character, while the physical key position
stays the same on every layout. No browser reserves the combination, and
`Ctrl+Shift+R` is deliberately not matched so the browser's hard reload keeps
working. The toggle goes through the same `isInputContext()` suppression as
every other shortcut, so it is ignored while focus is in an input, textarea or
contenteditable element.

## Mouse + Modifier Combinations

| Combination      | Effect                    | Visual Feedback          |
|------------------|---------------------------|--------------------------|
| `Shift+Click`    | Add/remove from selection | Crosshair cursor         |
| `Shift+Drag`     | Multi-select box          | Selection rectangle      |
| `Ctrl+Wheel`     | Vertical canvas panning   | Vertical resize cursor   |
| `Ctrl+Alt+Wheel` | Horizontal canvas panning | Horizontal resize cursor |

## Hook Interface

**Type Definitions:**
```typescript
interface KeyboardShortcutCallbacks {
  onDelete?: () => void;           // Delete key handler
  onCopy?: () => void;             // Ctrl+C handler
  onPaste?: () => void;            // Ctrl+V handler
  onUndo?: () => void;             // Ctrl+Z handler
  onRedo?: () => void;             // Ctrl+Shift+Z / Ctrl+Y handler
  onToggleSearch?: () => void;     // Ctrl+F handler
  onToggleReviewMode?: () => void; // Alt+Shift+R handler
  onEscape?: () => void;           // Escape handler
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
  canDelete: boolean;        // Whether delete action is available
  canCopy: boolean;          // Whether copy action is available  
  canPaste: boolean;         // Whether paste action is available
  canSearch: boolean;        // Whether search toggle is available
  canEscape: boolean;        // Whether escape action is available
  canToggleReviewMode: boolean; // Whether the property/review toggle is available
}
```

**Usage Pattern:**
```typescript
// In Flow.tsx
useKeyboardShortcuts({
  callbacks: {
    onDelete: handleDeleteSelected,
    onCopy: handleCopy,
    onPaste: handlePaste,
    onToggleSearch: handleToggleSearch,
    onEscape: clearSearchHandler
  },
  modifiers: { /* Shift, Ctrl, Alt state management */ },
  capabilities: {
    canDelete: canDeleteSelected(), // Derived from selection state
    canCopy,                        // From clipboard hook
    canPaste,                       // From clipboard hook  
    canSearch: true,                // Always available
    canEscape: true,                // Always available
  },
  isModelerFocused,
  getViewport, setViewport,
  enabled: true // Can disable during replay
});
```

## Context-Aware Event Handling

**Smart Context Detection:**
- **Form Inputs**: Automatically disabled in INPUT, TEXTAREA, and contentEditable elements
- **Text Selection**: Skips shortcuts when text is selected on page
- **Modeler Focus**: Only active when modeler component has focus
- **Element Targeting**: Uses `.workflow-modeler, .modeler` selectors for context (`.modeler` is the top-level container from `App.tsx`, `.workflow-modeler` is the inner layout from `Flow.tsx`)

**Event Capture Strategy:**
- **Capture Phase**: Intercepts events before ReactFlow handles them
- **Multiple Listeners**: Document and element-specific listeners for Firefox compatibility
- **Event Prevention**: Prevents default behaviors and stops propagation appropriately

## Cross-Browser Compatibility

**Firefox-Specific Handling:**
- **Triple Event Listeners**: keydown, keypress, and keyup events
- **Element-Specific Binding**: Direct element listeners in addition to document listeners  
- **Aggressive Prevention**: Multiple event cancellation methods

**Browser Detection:**
- **Mac Detection**: Proper Cmd vs Ctrl key handling
- **Platform Awareness**: Uses `navigator.platform` for Mac-specific behaviors

## Undo/Redo Integration

Undo/redo is implemented via `useHistoryStore`, which maintains past/future snapshot stacks (max 50 entries). Keyboard shortcuts (`Ctrl+Z`, `Ctrl+Shift+Z`, `Ctrl+Y`) are handled by `useKeyboardShortcuts`, and buttons are available in the `CanvasToolbar`.

```typescript
// Undo/redo wiring in the keyboard shortcuts hook
callbacks: {
  onUndo: () => {
    const snapshot = useHistoryStore.getState().undo();
    if (snapshot) applySnapshot(snapshot);
  },
  onRedo: () => {
    const snapshot = useHistoryStore.getState().redo();
    if (snapshot) applySnapshot(snapshot);
  },
},
capabilities: {
  canUndo: useHistoryStore.getState().canUndo,
  canRedo: useHistoryStore.getState().canRedo,
},
```

## Future Extensions

**Replay Mode Integration:**
```typescript
// Easy to disable during replay
useKeyboardShortcuts({
  // ... other options
  enabled: !isReplayMode  // Disable during replay
});
```

**Advanced Context Detection:**
```typescript
// Future context-aware features
interface AdvancedContext {
  isInPropertyPanel: boolean;
  isInReplayMode: boolean;
  selectedElementType: 'node' | 'edge' | null;
}
```

## File Structure

- **Hook**: `src/hooks/useKeyboardShortcuts.ts` - Complete keyboard management (~200 lines)
- **Integration**: `src/App.tsx` - Callback definitions and hook usage
- **Types**: Full TypeScript interfaces for type safety
- **Documentation**: Comprehensive JSDoc comments explaining all behaviors

This architecture ensures keyboard functionality remains maintainable and extensible while providing professional-grade user interaction patterns that work consistently across all supported browsers.
