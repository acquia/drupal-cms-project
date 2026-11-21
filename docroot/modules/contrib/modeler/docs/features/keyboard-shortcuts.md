# Keyboard Shortcuts

The Modeler supports comprehensive keyboard navigation for efficient workflow
editing. All functionality is accessible via keyboard, meeting WCAG AA
accessibility standards.

## Shortcut reference

### Editing shortcuts

| Shortcut | Action | Context |
|----------|--------|---------|
| `Delete` | Delete selected nodes/edges | When elements are selected |
| `Ctrl+C` (`Cmd+C` on Mac) | Copy selected elements | When elements are selected |
| `Ctrl+V` (`Cmd+V` on Mac) | Paste elements | When the canvas has focus |
| `Ctrl+F` (`Cmd+F` on Mac) | Toggle search bar | Global (overrides browser find) |
| `Escape` | Clear search and highlights | Global |
| `Ctrl+Z` (`Cmd+Z` on Mac) | Undo last action | When history is available |
| `Ctrl+Shift+Z` (`Cmd+Shift+Z` on Mac) | Redo previously undone action | When future history is available |
| `Ctrl+Y` (`Cmd+Y` on Mac) | Redo previously undone action | When future history is available |

### Panel shortcuts

| Shortcut | Action | Context |
|----------|--------|---------|
| `Alt+Shift+R` (`Option+Shift+R` on Mac) | Toggle between properties and review mode | When the model has replay or test capability, or while in review mode |

!!! note "Why this combination"
    The shortcut is matched by **physical key position** (the `R` key), not by
    the character the keyboard produces. It therefore works on every keyboard
    layout, including macOS, where holding Option turns the `R` key into a
    different character. No browser reserves `Alt+Shift+R`, so nothing has to
    be overridden - and `Ctrl+Shift+R` is deliberately left alone, so the
    browser's hard reload keeps working.

### Mouse + modifier combinations

| Combination | Effect | Visual feedback |
|-------------|--------|-----------------|
| `Left-click + Drag` on background | Draw selection rectangle | Crosshair cursor |
| `Shift + Click` | Add/remove element from selection | -- |
| `Ctrl + Mouse wheel` (`Cmd + wheel` on Mac) | Zoom in/out | -- |
| `Middle-click + Drag` or `Right-click + Drag` | Pan the canvas | Grabbing cursor |
| `Space + Drag` | Pan the canvas (hand tool) | Grabbing cursor |

## Context awareness

Keyboard shortcuts are **context-aware** -- they are automatically disabled
when you are:

- Typing in a **text input** or **text area** (e.g., a configuration form
  field).
- Editing a **content-editable field** (e.g., a node label).
- Selecting text on the page.

This prevents shortcuts from interfering with normal text editing.

## Token editing shortcut

When working with token fields in the Property Panel:

| Shortcut | Action |
|----------|--------|
| `Ctrl+E` (`Cmd+E` on Mac) | Open inline editor for the token at cursor position |

## Browser override

The `Ctrl+F` shortcut overrides the browser's built-in find function and
opens the modeler's own search bar instead. This is intentional -- the
modeler's search understands workflow elements and can highlight matching
nodes and edges on the canvas.

Press `Escape` to close the search bar and clear highlights.

## Cross-browser compatibility

Keyboard shortcuts work consistently across all major browsers. The modeler
handles platform-specific differences:

- **Mac**: Uses `Cmd` instead of `Ctrl` for modifier shortcuts.
- **Firefox**: Special handling ensures shortcuts work reliably in all contexts.
