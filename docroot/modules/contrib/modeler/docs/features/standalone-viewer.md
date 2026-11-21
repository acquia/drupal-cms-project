# Standalone Viewer

The Standalone Viewer is a **read-only version** of the Modeler that can be
embedded in any web page -- without requiring Drupal. It renders an exported
workflow model with full visual fidelity, including replay playback when
replay data is included.

## What it does

The standalone viewer provides:

- **Read-only canvas**: Pan, zoom, and inspect the workflow visually.
- **Configuration form viewing**: Click any node or edge to see its
  configuration (read-only).
- **Replay playback**: Step through execution history if replay data was
  included in the JSON export.
- **Dark mode**: Toggle between light and dark themes.
- **Search**: Find nodes and edges by label, plugin, type, or ID.
- **Zoom controls**: Zoom in/out and fit-to-screen.

The following features are **not available** in standalone mode:

- Editing (adding, moving, deleting, or configuring components).
- Saving.
- Context switching.
- Live testing.

## Building the viewer

All commands run from the `ui/` directory:

```bash
# Development build
npm run build:standalone

# Production build (minified)
npm run build:standalone:production
```

This produces two output files:

- `modeler-viewer.bundle.js` -- the viewer JavaScript.
- `modeler-viewer.bundle.css` -- the viewer styles.

## Embedding in a web page

Include the built files and initialize the viewer:

```html
<link rel="stylesheet" href="modeler-viewer.bundle.css">
<script src="modeler-viewer.bundle.js"></script>

<div id="workflow-viewer" style="height: 600px;"></div>

<script>
  const { destroy } = window.WorkflowModelerViewer.init('#workflow-viewer', {
    modelUrl: '/path/to/exported-model.json'
  });
</script>
```

!!! note
    The viewer API is exposed as `window.WorkflowModelerViewer`. Always use the
    `window.` prefix to access it reliably.

### Initialization options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `modelUrl` | `string` | -- | URL to fetch the JSON model from. |
| `model` | `object` | -- | Inline model data (alternative to `modelUrl`). |
| `collapsePanels` | `boolean` | `false` | Start with all panels collapsed; auto-expand the property panel when a node or edge is selected, auto-collapse when selection is cleared. |

Use **either** `modelUrl` or `model` -- not both. If `model` is provided, the
viewer uses it directly without making a network request.

#### Collapsed panels

When `collapsePanels` is `true`, the viewer launches with the property panel
and replay panel collapsed, giving maximum canvas space.
Clicking a node or edge automatically opens the property panel. Clicking empty
canvas space collapses it again.

```javascript
window.WorkflowModelerViewer.init('#viewer', {
  modelUrl: 'model.json',
  collapsePanels: true,
});
```

This is especially useful when embedding the viewer in documentation pages
where vertical and horizontal space is limited.

### Container requirements

The container element **must have a defined height** (e.g., `height: 600px` or
`height: 100vh`). Without an explicit height, the viewer cannot render
correctly because it relies on the container dimensions to size the canvas.

### Cleanup

The `init()` function returns an object with a `destroy()` method. Call it when
you need to unmount the viewer (e.g., in a single-page application):

```javascript
const viewer = window.WorkflowModelerViewer.init('#viewer', { modelUrl: '...' });

// Later, when removing the viewer
viewer.destroy();
```

## Creating exportable models

To create a JSON file suitable for the standalone viewer:

1. Open the model in the Modeler.
2. Click the **Export** button in the toolbar.
3. Select **JSON** format.
4. Optionally check **Include Replay Data** if you want replay functionality.
5. Click **Export**.

See [Export](export.md) for full details on the export dialog.

## CSS encapsulation

The viewer isolates itself from host-page stylesheets so it renders correctly
on any site (Drupal admin themes, mkdocs-material, custom designs, etc.).
The isolation works in two layers:

1. **`all: revert` on the root element** -- The `.modeler` container resets its
   own properties to browser defaults, preventing the host page's global rules
   from affecting the modeler's root.

2. **Targeted descendant resets** -- Because `all: revert` does not cascade to
   child elements, the viewer adds scoped reset rules for the HTML elements
   most commonly affected by host-page stylesheets:

    - **SVG icons** (Feather Icons): Restores `width: 1em; height: 1em` and
      stroke defaults that host pages often override (e.g., mkdocs-material
      sets `height: auto; max-width: 100%` on all SVGs).
    - **Headings** (`h1`--`h6`): Reverts font-size, margin, and related
      properties that host pages enlarge or re-space.
    - **Images and media**: Reverts `max-width` and `height` constraints.

These resets are carefully scoped to avoid interfering with ReactFlow's own
CSS.  No action is needed from embedders -- the protections are built into the
viewer's stylesheet.

## View modes in standalone

The standalone viewer defaults to **Restored** mode, filling its parent
container. It can also be toggled to **Fullscreen** mode using the toolbar
button. See [View Modes](view-modes.md) for details.
