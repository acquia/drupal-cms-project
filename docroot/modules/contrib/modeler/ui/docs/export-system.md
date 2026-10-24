# Export System

Model export in four formats: Recipe, Archive, JSON, and SVG.

## Available Formats

| Format | Type | Trigger | Output |
|--------|------|---------|--------|
| Recipe | Backend | Opens `export_recipe_url` in new tab | Drupal recipe form |
| Archive | Backend | Fetches `export_url` with CSRF token | `.tar.gz` download |
| JSON | Client | Builds JSON from store data | `.json` download |
| SVG | Client | Reads canvas DOM, renders native SVG | `.svg` download |

### Format Availability

- **Recipe** and **Archive** require backend URLs (`export_recipe_url`, `export_url`) in `drupalSettings.modeler_api`. These are only present for saved models and are injected by `modeler_api/src/Api.php`.
- **JSON** and **SVG** are always available client-side when the model has at least one node.

## Architecture

### Hook: `useExport`

```
src/hooks/useExport.ts
```

Central hook providing all export logic. Returns:

```typescript
interface UseExportReturn {
  canExport: boolean;           // true when nodes.length > 0
  availableFormats: ExportFormat[];  // based on settings
  hasReplayData: boolean;       // replay steps available for JSON
  executeExport: (format, includeReplayData?) => Promise<void>;
  getRequiredModules: () => string[];
}
```

### Component: `ExportDialog`

```
src/components/ExportDialog.tsx
```

Modal dialog with radio-button format selection. Shows format-specific options when JSON is selected (replay data checkbox, required modules list). Uses `useFocusTrap` for keyboard accessibility.

### Integration in `Flow.tsx`

The export feature follows the same "save then act" pattern as the close handler (`useCloseHandler.ts`):

1. User clicks the export toolbar button.
2. If there are unsaved changes, a confirmation dialog asks to save first.
3. A `pendingExportAfterSaveRef` ref is set before triggering save.
4. On `onSaveComplete`, the ref is checked and the export dialog opens automatically.
5. If no unsaved changes, the export dialog opens immediately.

## JSON Export Details

The JSON export includes:

- **Model data**: nodes, edges, metadata (via `exportModelData` from `utils/modelUtils.ts`)
- **Required modules**: derived from the `provider` property of each node's plugin and each edge's condition, matched against the component registry
- **Replay data** (optional): the full replay step history, included only when the user checks the checkbox

## SVG Export Details

The SVG export generates a standalone SVG file using **native SVG elements only** (no `<foreignObject>`). This ensures compatibility with non-browser SVG consumers like Inkscape and system image viewers.

### Rendering Pipeline

1. **`resolveVar()`** / **`resolveColors()`** -- reads computed CSS custom property values from the `.modeler` element at export time
2. **`parseTranslate()`** -- extracts x/y coordinates from CSS `transform: translate()` strings on node elements
3. **`renderRectNode()`** -- renders action, event, and subprocess nodes as SVG `<rect>` + `<text>` elements with headers and labels
4. **`renderGatewayNode()`** -- renders gateway diamonds as rotated `<rect>` elements with centered text
5. **`renderEdgeLabel()`** -- renders condition labels, annotation labels, and order badges
6. **`cleanEdgeMarkup()`** -- post-processes the extracted ReactFlow edge SVG:
   - Resolves `var(--modeler-...)` CSS variables to hex values
   - Adds `fill: none` to `<path>` style attributes (SVG defaults fill to black)
   - Removes invisible interaction paths (`.react-flow__edge-interaction`)
   - Strips interactive DOM attributes (`tabindex`, `role`, `aria-*`, `data-testid`)
7. **`exportCanvasToSvg()`** -- orchestrates the full export: reads DOM, computes bounds, extracts edge paths, assembles the final SVG string

### Key Design Decisions

- **No `<foreignObject>`**: Originally used to embed HTML nodes directly, but `foreignObject` only renders in browsers -- Inkscape and other SVG tools show blank nodes. The native SVG approach rebuilds every node as primitive SVG elements.
- **CSS variable resolution**: ReactFlow edge paths use `var(--modeler-color-edge-stroke)` in inline styles. Standalone SVGs cannot access external stylesheets, so all CSS variables are resolved to computed hex values at export time.
- **Font-family quoting**: Uses single quotes (`'Segoe UI'`) inside XML attributes to avoid breaking double-quoted attribute values.

## Backend Integration

### Settings (`ModelerApiSettings`)

```typescript
interface ModelerApiSettings {
  export_url?: string;          // GET endpoint for .tar.gz archive
  export_recipe_url?: string;   // URL to open recipe export form
  token_url: string;            // CSRF token endpoint
  // ...other settings
}
```

### Archive Export Flow

1. Fetch CSRF token from `token_url` via `fetchValidatedCsrfToken()`
2. GET `export_url` with `X-CSRF-Token` header
3. Read response as Blob
4. Extract filename from `Content-Disposition` header
5. Trigger browser download via temporary `<a>` element

## Testing

### Unit Tests

- `src/hooks/__tests__/useExport.test.ts` -- hook logic: format availability, required modules derivation, all four export paths, error handling
- `src/components/__tests__/ExportDialog.test.tsx` -- dialog rendering, format selection, JSON options, button states, overlay/cancel interactions, accessibility attributes
- `src/components/__tests__/Modals.test.tsx` -- ExportDialog integration in the Modals container

### E2E Tests

- `tests/e2e/export.spec.ts` -- export button visibility, dialog open/close, format selection, focus trapping, JSON/SVG download triggers

### Storybook

- `src/components/ExportDialog.stories.tsx` -- five stories: Default, JsonWithOptions, ClientSideOnly, Exporting, Closed
- Accessibility audits run automatically via axe-core in both light and dark mode

## File Reference

| File | Purpose |
|------|---------|
| `src/hooks/useExport.ts` | Export logic hook (~740 lines) |
| `src/components/ExportDialog.tsx` | Format selection dialog |
| `src/components/ExportDialog.stories.tsx` | Storybook stories |
| `src/hooks/__tests__/useExport.test.ts` | Hook unit tests |
| `src/components/__tests__/ExportDialog.test.tsx` | Dialog unit tests |
| `tests/e2e/export.spec.ts` | E2E tests |
| `tests/e2e/pages/ModelerPage.ts` | Page object (export methods) |
| `src/components/Flow.tsx` | Integration: toolbar wiring, save-then-export flow |
| `src/components/Toolbar.tsx` | Export button (FiDownload icon) |
| `src/components/Modals.tsx` | ExportDialog mounting |
| `src/types/settings.ts` | `export_url`, `export_recipe_url` type definitions |
| `src/styles/modeler.css` | Export dialog CSS |

## Standalone Viewer

The JSON export includes everything needed to display the model in a standalone viewer that runs without a Drupal backend. The viewer is embeddable in any web page and fills its parent container.

### Enhanced JSON Format

When exporting to JSON, the hook automatically:

1. **Fetches all configuration form schemas** from the backend (parallel requests for all unique plugins) and includes them as `configForms` — a map from plugin ID to form field array. This covers both node plugins and edge condition plugins.
2. **Includes a `components` array** with metadata (plugin, label, category, provider, componentType, description, documentationUrl) for every plugin used in the model.
3. **Includes the `metadataForm` array** — the model metadata form the backend converted from the model owner's Drupal form array, so the viewer can render the model information dialog. The standalone entry point maps it back to `settings.modeler.metadataForm`.

Example of the additional fields in the exported JSON:

```json
{
  "id": "my_workflow",
  "version": "1.0.0",
  "metadata": { "label": "My Workflow", ... },
  "nodes": [...],
  "edges": [...],
  "requiredModules": ["workflow_base", "workflow_form"],
  "replayData": [...],
  "configForms": {
    "form:form_build": [
      { "key": "form_ids", "type": "textfield", "title": "Form IDs", ... }
    ],
    "scalar_comparison": [
      { "key": "left", "type": "textfield", "title": "Left value", ... },
      { "key": "operator", "type": "select", "title": "Operator", "options": { "equals": "Equals", "contains": "Contains" }, ... }
    ]
  },
  "metadataForm": [
    { "key": "label", "type": "textfield", "title": "Label", ... },
    { "key": "model_id", "type": "machine_name", "title": "Model ID", "source": "label", "disabled": true, ... },
    { "key": "recipe_export", "type": "group", "title": "Recipe export", "open": false, "children": [...] }
  ],
  "components": [
    { "plugin": "form:form_build", "label": "Form Build", "category": "Events", "provider": "workflow_form", ... },
    { "plugin": "scalar_comparison", "label": "Compare two scalar values", "category": "Conditions", "provider": "workflow_base", ... }
  ]
}
```

#### Config Form Field Format

Every form field - in `configForms` as well as in `metadataForm` - follows the `FormField` interface in `src/types/forms.ts`, which `ConfigurationForm.tsx` renders:

| Property | Type | Description |
|----------|------|-------------|
| `key` | `string` | Machine name (matches configuration keys) |
| `type` | `string` | `textfield`, `textarea`, `select`, `checkbox`, `number`, `radios`, `checkboxes`, `markup`, `machine_name`, `group` |
| `title` | `string` | Human-readable label (not `label` — that is for components) |
| `description` | `string` | Help text (HTML allowed, sanitized on render) |
| `default_value` | `any` | Default when no configuration value is present |
| `required` | `boolean` | Whether the field is mandatory |
| `options` | `Record<string, string>` | For `select`/`radios`/`checkboxes`: `{ "value": "Label", ... }` — **not** an array of objects |
| `token_support` | `boolean` | Whether the field accepts token drag-and-drop |
| `min`, `max`, `step` | `number` | For `number` fields |
| `markup` | `string` | For `markup` fields: raw HTML content |
| `format` | `string` | Widget format from the config-schema contract: `json` or `yaml` selects the matching editor regardless of the element type |
| `disabled` | `boolean` | Mirrors Drupal's `#disabled`: rendered but not editable |
| `maxlength` | `number` | Mirrors Drupal's `#maxlength` for text elements |
| `source` | `string` | For `machine_name` fields: the key of the field the machine name is derived from |
| `children`, `open` | `FormField[]`, `boolean` | For `group` fields: child fields and whether the group starts expanded |

### Building the Standalone Viewer

```bash
# Development build (includes both main bundle and standalone viewer)
npm run build:standalone

# Production build (minified)
npm run build:standalone:production
```

This produces:
- `dist/modeler-viewer.bundle.js` — Self-contained IIFE bundle (~2MB dev, smaller minified)
- `dist/modeler-viewer.bundle.css` — CSS bundle (same as the main modeler CSS)

### Embedding in a Web Page

The viewer fills its parent container, so the container **must have a defined height** (via CSS `height`, `flex`, `grid`, etc.). Without a height the viewer collapses to zero.

#### `ViewerOptions` Interface

```typescript
interface ViewerOptions {
  /** URL to fetch the model JSON from. */
  modelUrl?: string;
  /** Inline model data (takes precedence over modelUrl). */
  model?: ExportedModel;
  /**
   * When true, panels start collapsed and auto-expand/collapse based on
   * user interaction (e.g. selecting a node expands the property panel).
   * Default: false.
   */
  collapsePanels?: boolean;
}
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `modelUrl` | `string` | -- | URL to fetch the JSON model from |
| `model` | `ExportedModel` | -- | Inline model data (alternative to `modelUrl`) |
| `collapsePanels` | `boolean` | `false` | Start with all panels collapsed; auto-expand on selection |

Use **either** `modelUrl` or `model` -- not both.

#### Loading from a JSON file (recommended)

```html
<link rel="stylesheet" href="modeler-viewer.bundle.css">
<div id="workflow-viewer" style="height: 600px;"></div>
<script src="modeler-viewer.bundle.js"></script>
<script>
  window.WorkflowModelerViewer.init('#workflow-viewer', {
    modelUrl: 'my-workflow.json'
  }).catch(function(err) {
    document.getElementById('workflow-viewer').innerHTML =
      '<div class="error">' + err.message + '</div>';
  });
</script>
```

The URL can be relative (to the HTML page) or absolute. Any `.json` file produced by the Export dialog works directly.

#### Inline model data

```html
<script>
  window.WorkflowModelerViewer.init('#workflow-viewer', {
    model: {
      id: 'example',
      metadata: { label: 'My Workflow' },
      nodes: [
        { id: 'e1', type: 'start', plugin: 'form:form_submit', label: 'Form submitted',
          position: { x: 200, y: 100 }, configuration: {} }
      ],
      edges: [],
      components: [
        { plugin: 'form:form_submit', label: 'Form Submit', category: 'Events',
          componentType: 1, type: 'start' }
      ],
      configForms: {
        'form:form_submit': [
          { key: 'form_ids', type: 'textfield', title: 'Form IDs',
            description: 'Comma-separated form IDs.', default_value: '', required: true }
        ]
      }
    }
  });
</script>
```

#### Flex / grid layouts

When the container gets its height from a flex or grid parent, use `flex: 1; min-height: 0` or equivalent:

```html
<body style="display: flex; flex-direction: column; height: 100vh;">
  <header>My App</header>
  <div id="workflow-viewer" style="flex: 1; min-height: 0;"></div>
</body>
```

#### Lifecycle

`init()` returns `Promise<{ destroy: () => void }>`. Call `destroy()` to unmount the viewer and clean up the React tree:

```javascript
const viewer = await window.WorkflowModelerViewer.init('#workflow-viewer', { modelUrl: 'model.json' });
// Later:
viewer.destroy();
```

### How Standalone Mode Works

The standalone entry point (`src/standalone.tsx`) synthesizes a `Settings` object with `settings.modeler.standalone = true`. This flag propagates through the existing component tree:

| Component / File | Standalone Behavior |
|------------------|-------------------|
| `App.tsx` | Adds `standalone` CSS class to `.modeler` root (enables embedded layout) |
| `Flow.tsx` | Adds `standalone` CSS class to `.workflow-modeler`; forces `isReadOnly = true`; skips unsaved-changes dialog |
| `Toolbar.tsx` | Hides Close button; shows fullscreen/restore toggle; Save hidden via read-only |
| Quick-add popups | Hidden (read-only mode disables them) |
| `useConfigurationLoader` | Returns pre-baked forms from `settings.modeler.configForms` instead of fetching from backend |
| `useViewMode` | Defaults to `restored`; toggling to `fullscreen` covers the viewport |
| `PropertyPanel` | Shows config forms in disabled/read-only mode |
| `ReplayPanel` | Fully functional if `replayData` is included in the JSON |
| `FlowCanvas` | Read-only: nodes not draggable or connectable, but selectable for viewing properties |

#### `collapsePanels` Behavior

When `options.collapsePanels` is `true`, `standalone.tsx` sets `settings.modeler.collapsePanels = true`. In `Flow.tsx` this triggers two effects:

1. **Initial collapse** (mount-time): A `useEffect` with an empty dependency array collapses both panels (property, replay) via their respective setters.
2. **Auto-expand/collapse** (selection-driven): A second `useEffect` watches `selectedNode` and `selectedEdge`. When either becomes non-null, the property panel expands; when both are null, it collapses.
3. **Replay panel guard**: The existing `useEffect` that auto-expands the replay panel when replay data or test capability is detected is guarded with `if (collapsePanels) return;` to prevent it from overriding the initial collapse.

The store's `setPropertyPanelCollapsed(collapsed: boolean)` setter was added specifically for this feature -- the existing `togglePropertyPanelCollapse` only toggled and persisted to `localStorage`, which is not desirable for programmatic control.

### View Modes

The modeler supports three view modes, managed by the `useViewMode` hook:

| Mode | Drupal (regular) | Standalone |
|------|-----------------|------------|
| **Fullscreen** (default regular) | `position: fixed`, covers entire viewport, z-index 9999 | Same — takes over the viewport |
| **Restored** (default standalone) | Floating window: `position: fixed`, 80% viewport, centered, draggable + resizable, with rounded corners and shadow | Fills parent container: `position: relative`, 100% width/height |

**Toggling**: The toolbar shows a fullscreen/restore button (FiMaximize2 / FiMinimize2 icons). Double-clicking the toolbar title area also toggles.

**Drupal restored mode (floating window)**:
- Draggable by the toolbar center area (grab cursor)
- Resizable via a diagonal-lines handle in the bottom-right corner
- Minimum size: 480 x 360px
- Initial size: 80% of viewport, centered
- `is-dragging` / `is-resizing` CSS classes disable transitions and text selection during interaction

**Standalone restored mode (embedded)**:
- No drag or resize — the modeler simply fills its parent container
- Toggling to fullscreen adds `position: fixed` to cover the viewport

### CSS: View Mode Layout

```css
/* Default — fixed full-screen overlay */
.workflow-modeler { position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999; }

/* Drupal restored (windowed) — inline styles set top/left/width/height */
.workflow-modeler.restored { right: auto; bottom: auto; border-radius: 8px; box-shadow: ...; }

/* Standalone restored — fills parent container */
.workflow-modeler.standalone { position: relative; width: 100%; height: 100%; z-index: auto; }

/* Standalone fullscreen — overrides back to fixed overlay */
.workflow-modeler.standalone.fullscreen { position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999; }

/* Root wrapper needs explicit height in standalone mode */
.modeler.standalone { height: 100%; }
```

The `.modeler.standalone` rule is needed because `.modeler` uses `all: revert` (CSS isolation for Drupal), which resets height. Without an explicit `height: 100%` on `.modeler`, the modeler collapses.

### CSS Encapsulation for Host-Page Isolation

The standalone viewer is embedded inside arbitrary host pages whose global CSS could interfere with the modeler's layout. Two layers of protection handle this:

1. **`all: revert` on `.modeler`** -- Resets the root container's own properties to browser defaults, shielding it from the host page's cascade.

2. **Targeted descendant resets** (at the top of `modeler.css`, before the `.modeler` block) -- `all: revert` does not cascade to child elements, so host-page rules like mkdocs-material's `.md-typeset svg { height: auto }` or `.md-typeset h1 { font-size: 2em }` still affect descendants. Targeted resets fix the most common conflicts:

   | Selector | Purpose | Specificity |
   |----------|---------|-------------|
   | `.modeler svg:where(:not(.react-flow svg))` | Restores Feather Icon defaults (`width: 1em`, `height: 1em`, stroke props). The `:where()` wrapper keeps specificity at (0,1,1) so more-specific rules in our stylesheet still win. The `:not()` guard excludes ReactFlow canvas SVGs. | (0,1,1) |
   | `.modeler h1` through `.modeler h6` | Reverts `font-size`, `font-weight`, `line-height`, `margin`, `padding`, `letter-spacing`, `color` to browser defaults. | (0,1,1) |
   | `:where(.modeler) img, :where(.modeler) video` | Reverts `max-width` and `height` constraints. `:where()` keeps specificity at (0,0,1). | (0,0,1) |

   **Why not a blanket `all: revert` on all descendants?** ReactFlow's CSS lives in the same cascade origin as host-page CSS, so reverting all properties on `.modeler *` would undo ReactFlow's own styles, breaking the canvas.

### Relevant Files

| File | Purpose |
|------|---------|
| `src/standalone.tsx` | Standalone entry point with `init()` API |
| `src/hooks/useViewMode.ts` | View mode state, drag, resize logic |
| `src/hooks/__tests__/useViewMode.test.ts` | 22 unit tests for view mode hook |
| `src/App.tsx` | Adds `standalone` class to `.modeler` root |
| `src/components/Flow.tsx` | Adds view mode CSS classes, renders resize handle |
| `src/components/Toolbar.tsx` | Fullscreen/restore toggle button, drag handle |
| `src/styles/modeler.css` | View mode CSS (restored, standalone, fullscreen) |
| `src/types/settings.ts` | `standalone` and `configForms` type definitions |
| `src/__tests__/standalone.test.ts` | Standalone `init()` unit tests |
| `standalone.html` | Example HTML embedding file |
| `build.sh` | `--standalone` flag for build |
| `package.json` | `build:standalone` and `build:standalone:production` scripts |
