# View Modes

The Modeler supports two view modes that control how the editor is displayed
within the browser window.

## Fullscreen mode

In **Fullscreen** mode, the modeler covers the entire browser viewport. This
is the default when the modeler is opened from within Drupal.

- The modeler uses `position: fixed` and fills the full screen.
- All Drupal admin UI is hidden behind the modeler.
- This provides maximum canvas space for editing.

## Restored mode

In **Restored** mode, the modeler appears as a floating window within the
page. The behavior differs depending on context:

### In Drupal

When restored in Drupal, the modeler becomes a **draggable, resizable window**:

- **Dragging**: Click and drag the toolbar's center area to move the window.
- **Resizing**: Drag the corner handle at the bottom-right to resize.
- **Minimum size**: The window cannot be smaller than 480 x 360 pixels.
- **Default size**: 80% of the viewport width and height, centered.
- **Persistence**: The window position and size are saved to your browser's
  local storage and restored on the next visit.

### In standalone mode

When using the [Standalone Viewer](standalone-viewer.md), restored mode simply
fills the parent container element. There is no drag or resize behavior --
the viewer adapts to whatever size its container provides.

## Switching view modes

There are two ways to toggle between fullscreen and restored:

1. **Toolbar button**: Click the maximize/restore icon in the toolbar's
   right section.
2. **Double-click**: Double-click the model title area in the toolbar center.
