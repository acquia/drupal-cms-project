The Modeler module provides a modern, React-based visual workflow editor for
Drupal. Built on [React Flow](https://reactflow.dev/), it offers a
professional drag-and-drop interface for creating and editing workflow
models -- complete with real-time configuration, execution replay, live
testing, and full keyboard accessibility.

The module is a plugin implementation for the
[Modeler API](https://www.drupal.org/project/modeler_api) module. The
Modeler API defines the framework for visual modelers in Drupal, while this
module provides the actual editing experience. Any module that implements the
Modeler API can use this modeler as its visual editor -- the most common
model owner is [ECA](https://www.drupal.org/project/eca).

For the full documentation, visit the
[project documentation](https://project.pages.drupalcode.org/modeler/).

## Features

### Visual workflow editing

Drag-and-drop nodes onto an infinite canvas, connect them with edges, and
configure each component through dynamic forms. Nodes snap to a grid for
clean alignment, and smart auto-positioning avoids overlaps when using
quick-add.

### Component library

All available components (events, actions, conditions, gateways, subprocesses)
are accessible through quick-add popups on the canvas, organized into
collapsible sections. Frequently used components are tracked as per-user
favorites and shown at the top.

### Quick-add

Hover over a node to reveal a "+" button that opens a context-aware popup
for adding a successor -- actions, gateways, or conditions. A collapsible
type filter lets you narrow the list. Selecting a condition creates a
placeholder node with the condition pre-attached, supporting condition-first
authoring. Hover over an edge to add a condition the same way. Dependency
rules automatically filter the popup to show only components that are valid
in the current workflow context.

### Execution replay

Load past execution data, step through it visually on the canvas, and
inspect the token values available at each step. Playback controls let you
play, pause, and adjust speed.

### Live testing

Trigger a workflow execution directly from the modeler and see the results
highlighted on the canvas immediately.

### Export

Export models in four formats:

- **Recipe** -- generate a Drupal recipe for distribution.
- **Archive** -- download a compressed archive of configuration files.
- **JSON** -- portable format that can be loaded in the standalone viewer.
- **SVG** -- visual snapshot of the canvas for documentation or
  presentations.

### Standalone viewer

A separate, read-only build of the modeler that can be embedded in any web
page without Drupal. It renders an exported JSON model with full visual
fidelity and optional replay playback. Build it with
`npm run build:standalone` from the `ui/` directory.

### View modes

Switch between fullscreen (covers the entire viewport) and a resizable
floating window. In restored mode the window can be dragged and resized,
with position persisted to local storage.

### Permissions

Six granular permissions control what each user can do: edit metadata,
switch context, edit templates, create templates, test, and replay.
Permissions are provided by the backend and enforced in the UI.

### Dark mode

Toggle between light and dark themes. The preference is persisted to local
storage across sessions.

### Search

Find nodes and edges by label, plugin, type, or ID with live highlighting
on the canvas and a results dropdown.

### Flow filtering

When a model has multiple event flows, show or hide individual flows to
focus on the part of the workflow you are working on.

### Annotations

Attach documentation notes to any node or edge.

### Undo / redo

Full undo and redo support for all canvas changes. Use `Ctrl+Z` and
`Ctrl+Shift+Z` (or the toolbar buttons) to step back and forth through
your editing history.

### Keyboard shortcuts

Full keyboard navigation including copy/paste, undo/redo, delete, search,
and Figma-like canvas gestures (wheel to pan, Ctrl+wheel to zoom). Shortcuts
are context-aware and disabled inside form fields.

### Accessibility

WCAG AA compliant with screen reader support, focus management, live
regions for status announcements, and respect for reduced-motion and
high-contrast preferences. All interactive elements meet minimum touch
target sizes.


## Requirements

- Drupal `^11.3 || ^12.0`
- [Modeler API](https://www.drupal.org/project/modeler_api) module
  (`modeler_api`) version `^1.1`


## Recommended modules

- [ECA](https://www.drupal.org/project/eca): The most common model owner.
  ECA provides event-condition-action workflows for Drupal and uses the
  Modeler as its visual editor.


## Installation

Install as you would normally install a contributed Drupal module. For
further information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

```bash
composer require drupal/modeler
drush en modeler
```

You also need a model owner module installed. For ECA:

```bash
composer require drupal/eca
drush en eca eca_ui
```


## Configuration

The module has no standalone configuration page. It activates automatically
when a model owner module (e.g., ECA) is installed:

1. Navigate to the model owner's administration page (e.g.,
   **Administration > Configuration > Workflow > ECA**).
2. Click **Add** to create a new model, or click an existing model to edit
   it.
3. The modeler opens with its panel interface (canvas, replay panel,
   property panel) and a toolbar along the top.

User preferences (dark mode, view mode position) are stored in the
browser's local storage and require no server-side configuration.

Permissions for modeler actions (editing, testing, replay) are managed
through the Modeler API's permission system for each model owner separately.


## FAQ

**Q: Does the modeler work without ECA?**

**A:** Yes. The modeler works with any module that implements the Modeler
API. ECA is the most common model owner, but it is not a hard requirement.

**Q: Can I embed a workflow diagram on a public page?**

**A:** Yes. Export the model as JSON and use the standalone viewer to embed
a read-only version on any web page. See the
[Standalone Viewer documentation](https://project.pages.drupalcode.org/modeler/features/standalone-viewer/)
for details.

**Q: Is there an undo function?**

**A:** Yes. The modeler supports undo and redo through keyboard shortcuts
(`Ctrl+Z` / `Ctrl+Shift+Z`) and toolbar buttons. History tracks all
canvas changes including node moves, additions, deletions, and
configuration edits.
