# Plugin API

The Modeler exposes a JavaScript Plugin API that allows other Drupal modules to
extend the modeler UI at runtime. Modules can register **toolbar widgets**
(buttons, toggles) and **panels** (sidebar or bottom sections) that interact
with the modeler's state through a comprehensive public API that supports both
reading state and modifying the workflow graph.

## Concepts

The plugin system has three main parts:

| Concept | Description |
|---------|-------------|
| **Global object** | `window.WorkflowModeler` -- the entry point for all plugin operations. Available as soon as the modeler bundle loads. |
| **Widgets** | Small inline elements (typically buttons) rendered inside the toolbar. Registered with `registerWidget()`. |
| **Panels** | Larger content areas rendered alongside the canvas. Registered with `registerPanel()`. |
| **Public API** | `window.WorkflowModeler.api` -- provides access to nodes, edges, selection state, model metadata, available components, contexts, and more. Includes event subscriptions and methods to add, update, and remove nodes and edges. Available once the modeler has mounted. |

## Lifecycle

Registrations can happen **before or after** the modeler mounts. This is
important because Drupal module scripts may load in any order relative to the
modeler bundle.

```
Modeler bundle loads   -->  window.WorkflowModeler created
                             Drupal.behaviors.workflowModelerReact registered
Drupal.attachBehaviors -->  React app starts mounting
                             Plugin behaviors attach and listen for ready event
React app mounted      -->  WorkflowModeler.api becomes available
                             onReady() callbacks fire
                             workflow-modeler:ready DOM event dispatched
                             queued widgets and panels render
React app unmounts     -->  WorkflowModeler.api set to null
(HTMX navigation)          registrations survive for re-mount
```

### The `workflow-modeler:ready` DOM event

When the React app has fully mounted and the plugin API is available, the
modeler dispatches a **`workflow-modeler:ready`** custom event on `document`.
This is the recommended way for external Drupal behaviors to know when it is
safe to call `WorkflowModeler.registerWidget()` and friends.

The event is dispatched **asynchronously** (via `setTimeout(…, 0)`) to
guarantee it fires after `Drupal.attachBehaviors()` has completed, even when
the modeler mounts synchronously during HTMX navigation.

```javascript
document.addEventListener('workflow-modeler:ready', () => {
  // WorkflowModeler and WorkflowModeler.api are now available.
  console.log('Modeler is ready!');
}, { once: true });
```

## Toolbar widgets

Toolbar widgets are inline elements rendered inside the modeler toolbar. They
are ideal for toggle buttons, status indicators, or action triggers.

### Registering a widget

```javascript
const modeler = window.WorkflowModeler;

modeler.registerWidget({
  id: 'my_module_toggle',
  label: 'My Feature',
  position: 'right',   // 'left' or 'right'
  weight: 5,            // lower = rendered first
  render(container, api) {
    const btn = document.createElement('button');
    btn.className = 'toolbar-btn';
    btn.title = 'Toggle My Feature';
    btn.textContent = 'MF';
    btn.addEventListener('click', () => {
      btn.classList.toggle('active');
    });
    container.appendChild(btn);
  },
  destroy(container) {
    container.innerHTML = '';
  },
});
```

### Widget descriptor reference

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `id` | `string` | *required* | Unique identifier for the widget. |
| `label` | `string` | *required* | Human-readable label (used for `aria-label`). |
| `position` | `'left' \| 'right'` | `'right'` | Where to render in the toolbar. `left` places it near the event button and search bar; `right` places it near the save button and kebab menu. |
| `weight` | `number` | `0` | Ordering weight within the position. Lower values appear first. |
| `render` | `(container, api) => void` | *required* | Called once when the widget mounts. Receives a DOM element to render into and the [public API](#public-api-reference). |
| `destroy` | `(container) => void` | -- | Optional cleanup callback called before the widget is removed. |

### Styling

For visual consistency, render a `<button>` with the `toolbar-btn` CSS class.
Add the `active` class to indicate a toggled-on state:

```javascript
btn.className = 'toolbar-btn';       // normal state
btn.className = 'toolbar-btn active'; // toggled on
```

### Removing a widget

```javascript
window.WorkflowModeler.unregisterWidget('my_module_toggle');
```

## Panels

Panels are larger content areas rendered alongside the canvas. They can be
positioned on the left, right, or bottom of the modeler, are resizable, and
scroll vertically when their content outgrows the available height.

A panel can also be registered as **floating**, in which case it is detached
from the layout and the user can drag it around over the canvas. See
[Floating panels](#floating-panels).

!!! info "Showing and hiding a panel"
    Panels have no built-in collapse control. Visibility belongs to the
    plugin: call `registerPanel()` to show the panel and `unregisterPanel()`
    to hide it again -- typically from a toolbar widget that toggles between
    the two.

### Registering a panel

```javascript
const modeler = window.WorkflowModeler;

modeler.registerPanel({
  id: 'my_module_info',
  label: 'Model Info',
  position: 'right',
  weight: 10,
  width: 350,
  render(container, api) {
    const heading = document.createElement('h3');
    heading.textContent = 'Model Information';
    container.appendChild(heading);

    const info = document.createElement('div');
    const data = api.getModelData();
    info.textContent = data?.metadata?.label || 'Untitled';
    container.appendChild(info);

    // Subscribe to live updates
    api.onSelectionChange((node, edge) => {
      if (node) {
        info.textContent = 'Selected: ' + (node.data.label || node.id);
      } else if (edge) {
        info.textContent = 'Edge: ' + edge.source + ' -> ' + edge.target;
      } else {
        info.textContent = data?.metadata?.label || 'Nothing selected';
      }
    });
  },
  destroy(container) {
    container.innerHTML = '';
  },
});
```

### Panel descriptor reference

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `id` | `string` | *required* | Unique identifier for the panel. |
| `label` | `string` | *required* | Human-readable label shown in the panel header. |
| `position` | `'left' \| 'right' \| 'bottom'` | `'right'` | Where to render the panel. `right` places it after the property panel; `left` places it before the canvas; `bottom` places it below the canvas. For a floating panel this only seeds the corner it first appears in. |
| `weight` | `number` | `0` | Ordering weight within the position. Lower values appear first. |
| `width` | `number` | `320` | Initial width in pixels (min: 200, max: 600). |
| `floating` | `boolean` | `false` | Detach the panel from the layout so it floats over the canvas and can be dragged by the user. See [Floating panels](#floating-panels). |
| `render` | `(container, api) => void` | *required* | Called once when the panel mounts. Receives a DOM element to render into and the [public API](#public-api-reference). |
| `destroy` | `(container) => void` | -- | Optional cleanup callback called before the panel is removed. |
| `onResize` | `(width, height) => void` | -- | Optional callback fired after the user finishes resizing the panel. |

### Panel features

Each registered panel automatically receives:

- **Header** -- a strip showing the panel label. For a floating panel it is
  also the drag surface.
- **Resize handles** -- drag the side edge to adjust width. Floating panels
  also have a bottom-edge handle for height; focus it and press Up or Down to
  resize (hold Shift for a larger step). Docked panels remain automatically
  sized vertically.
- **Vertical scrolling** -- when the content is taller than the space
  available, the content area scrolls instead of being clipped. Your
  `render()` container is the scroll container, so you do not need to manage
  overflow yourself.
- **Error boundary** -- if the panel's render function throws, only that panel
  shows an error; the rest of the modeler continues working.

### Floating panels

Set `floating: true` to lift a panel out of the layout. A floating panel:

- reserves no space beside the canvas, so the canvas keeps its full width;
- is drawn above the canvas with a drop shadow;
- can be moved by dragging its header, or by focusing the move handle in the
  header and pressing the arrow keys (hold Shift for a larger step);
- keeps its content-driven automatic height until the first bottom-edge resize,
  then constrains height between 120 pixels and the space below the panel,
  including a 16-pixel modeler margin;
- scrolls the plugin content container when a manually constrained height is
  smaller than its content;
- is kept inside the modeler's bounds, including when the window is resized,
  so it can never be dragged out of reach;
- remembers where the user put it for the rest of the page session, so hiding
  the panel with `unregisterPanel()` and showing it again later does not reset
  its position. A page reload returns it to its default corner.

```javascript
modeler.registerPanel({
  id: 'my_module_inspector',
  label: 'Inspector',
  position: 'right',
  floating: true,
  width: 280,
  render(container, api) {
    container.textContent = api.getModelData()?.metadata?.label || '';
  },
});
```

`floating` is a registration-time choice. There is no user-facing control to
switch a panel between docked and floating, so pick the mode that suits your
content: floating suits small, occasional inspectors, while docked suits
panels the user keeps open while working.

### Removing a panel

```javascript
window.WorkflowModeler.unregisterPanel('my_module_info');
```

## Public API reference

The public API is available as `window.WorkflowModeler.api` after the modeler
mounts. It is also passed as the second argument to every `render()` callback.

!!! warning "Data safety"
    All data returned by API getters is a **deep-cloned snapshot**. Mutating the
    returned objects will not affect the modeler's internal state.

!!! info "Mutation methods and read-only mode"
    All mutation methods (`addNode`, `updateNode`, `removeNode`, `addEdge`,
    `updateEdge`, `removeEdge`, `setCondition`, `removeCondition`,
    `autoLayout`) are **no-ops in read-only mode**. They return `false` or
    `null` to indicate the operation was rejected. Always check the return value
    if your plugin needs to know whether the operation succeeded.

    Mutations automatically integrate with the modeler's **undo/redo history**
    and **unsaved-changes tracking** -- there is no need to manage this
    manually.

### Getters

#### Graph data

| Method | Returns | Description |
|--------|---------|-------------|
| `getNodes()` | `PluginNode[]` | All current nodes (id, type, data, position). |
| `getEdges()` | `PluginEdge[]` | All current edges (id, source, target, type, data). |
| `getNodeById(nodeId)` | `PluginNode \| null` | A single node by ID, or `null` if not found. |
| `getEdgeById(edgeId)` | `PluginEdge \| null` | A single edge by ID, or `null` if not found. |
| `getSelectedNode()` | `PluginNode \| null` | The first selected node (the primary selection), or `null`. |
| `getSelectedEdge()` | `PluginEdge \| null` | The first selected edge (the primary selection), or `null`. |
| `getSelectedNodes()` | `PluginNode[]` | Every selected node, in selection order. A single selection returns a one-element array. |
| `getSelectedEdges()` | `PluginEdge[]` | Every selected edge, in selection order. A single selection returns a one-element array. |
| `getModelData()` | `PluginModelData \| null` | Model metadata (id, version, label, description, tags). |

#### State information

| Method | Returns | Description |
|--------|---------|-------------|
| `isReadOnly()` | `boolean` | Whether the modeler is in read-only mode. |
| `isDarkMode()` | `boolean` | Whether dark mode is active. |
| `getHistoryState()` | `PluginHistoryState` | Current undo/redo availability (`{ canUndo, canRedo }`). |
| `getErrors()` | `PluginError[]` | Current error log entries (`{ id, message, dismissed }`). |

#### Components and contexts

| Method | Returns | Description |
|--------|---------|-------------|
| `getComponents()` | `PluginComponent[]` | All available component definitions (events, actions, conditions, gateways, subprocesses). Each entry contains `plugin`, `label`, `type`, `provider`, `description`, `documentationUrl`, and `componentType`. |
| `getComponentLabels()` | `PluginComponentLabels` | Model-owner-provided terminology (e.g. `{ start: 'Event', element: 'Action', link: 'Condition' }`). |
| `getContexts()` | `PluginContext[]` | Available contexts (`{ id, topic, model_owner }`). |
| `getSelectedContextId()` | `string \| null` | The currently selected context ID, or `null` when no filter is active. |
| `getFilteredNodeIds()` | `string[] \| null` | IDs of start nodes whose flows are currently visible, or `null` when all flows are shown. |

### Event subscriptions

All subscription methods return an **unsubscribe function**. Call it to stop
receiving updates.

#### Graph events

| Method | Callback signature | Description |
|--------|-------------------|-------------|
| `onSelectionChange(cb)` | `(node, edge) => void` | Fires when the primary selection or any multi-selection member changes. The callback receives only the first selected node and edge. |
| `onNodesChange(cb)` | `(nodes) => void` | Fires when nodes are added, removed, or reordered. |
| `onEdgesChange(cb)` | `(edges) => void` | Fires when edges are added, removed, or reordered. |
| `onModelDataChange(cb)` | `(data) => void` | Fires when model metadata changes. |

#### State events

| Method | Callback signature | Description |
|--------|-------------------|-------------|
| `onDarkModeChange(cb)` | `(isDarkMode) => void` | Fires when dark mode is toggled. |
| `onContextChange(cb)` | `(contextId) => void` | Fires when the active context filter changes. `contextId` is `null` when no filter is active. |
| `onComponentsChange(cb)` | `(components) => void` | Fires when the list of available components changes. |
| `onReadOnlyChange(cb)` | `(isReadOnly) => void` | Fires when the read-only state changes. |

```javascript
const unsubscribe = api.onSelectionChange((node, edge) => {
  console.log('Selection changed:', node, edge);
});

// Later, stop listening:
unsubscribe();
```

### Selection and viewport actions

| Method | Description |
|--------|-------------|
| `selectNode(nodeId)` | Select a node by ID. Pass `null` to clear. No-op in read-only mode. |
| `selectEdge(edgeId)` | Select an edge by ID. Pass `null` to clear. No-op in read-only mode. |
| `clearSelection()` | Clear the current selection (node, edge, and multi-selection). |
| `focusNode(nodeId)` | Animate the viewport to center on a node. |
| `fitView()` | Fit all nodes into the viewport with animation. |

### Node mutations

| Method | Returns | Description |
|--------|---------|-------------|
| `addNode(descriptor)` | `string \| null` | Add a new node. Returns the generated node ID, or `null` in read-only mode. |
| `updateNode(nodeId, updates)` | `boolean` | Update a node's data. Returns `true` on success. |
| `removeNode(nodeId)` | `boolean` | Remove a node and all its connected edges. Returns `true` on success. |

#### AddNodeDescriptor

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `plugin` | `string` | yes | Plugin identifier (e.g. `'eca_content_entity:create'`). |
| `componentType` | `number` | yes | Integer component-type constant: `1` = start, `2` = subprocess, `4` = element, `6` = gateway. |
| `label` | `string` | no | Human-readable label. Defaults to the plugin ID suffix. |
| `position` | `{ x, y }` | no | Canvas position. If omitted, placed automatically to the right of existing nodes. |
| `configuration` | `object` | no | Initial configuration key/value pairs. |
| `description` | `string` | no | Short description. |
| `documentationUrl` | `string` | no | URL to plugin documentation. |

#### UpdateNodeDescriptor

| Property | Type | Description |
|----------|------|-------------|
| `label` | `string` | New human-readable label. |
| `position` | `{ x, y }` | New canvas position. |
| `configuration` | `object` | Configuration values (shallow-merged with existing). |
| `annotation` | `string` | Annotation text. |

```javascript
// Add a new action node
const nodeId = api.addNode({
  plugin: 'eca_content_entity:create',
  componentType: 4, // element
  label: 'Create Article',
});

// Update its configuration
if (nodeId) {
  api.updateNode(nodeId, {
    configuration: { type: 'article', status: '1' },
  });
}

// Remove it
api.removeNode(nodeId);
```

### Edge mutations

| Method | Returns | Description |
|--------|---------|-------------|
| `addEdge(sourceNodeId, targetNodeId)` | `string \| null` | Connect two nodes. Returns the generated edge ID, or `null` in read-only mode or if either node does not exist. |
| `updateEdge(edgeId, updates)` | `boolean` | Update an edge's data. Returns `true` on success. |
| `removeEdge(edgeId)` | `boolean` | Remove an edge. Returns `true` on success. |
| `setCondition(edgeId, condition)` | `boolean` | Insert a condition node on an edge (splitting the edge into two segments with a condition node in between). Returns `true` on success. |
| `removeCondition(edgeId)` | `boolean` | Remove the condition node from an edge (reconnecting the source and target directly). Returns `true` on success. |

#### UpdateEdgeDescriptor

| Property | Type | Description |
|----------|------|-------------|
| `annotation` | `string` | Annotation text for the connection. |

#### SetConditionDescriptor

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `plugin` | `string` | yes | Condition plugin identifier. |
| `label` | `string` | no | Human-readable condition label. Defaults to the plugin ID suffix. |
| `configuration` | `object` | no | Initial condition configuration values. |

```javascript
// Connect two nodes
const edgeId = api.addEdge('event_1', 'action_1');

// Insert a condition node on that edge
if (edgeId) {
  api.setCondition(edgeId, {
    plugin: 'eca_base:eca_scalar_comparison',
    label: 'Status is Published',
    configuration: { left: '[node:status]', right: '1' },
  });
}

// Remove the condition node (edge reconnects directly)
api.removeCondition(edgeId);

// Remove the edge entirely
api.removeEdge(edgeId);
```

### Canvas actions

| Method | Description |
|--------|-------------|
| `autoLayout()` | Trigger automatic layout of all nodes. No-op in read-only mode. |
| `setDarkMode(enabled)` | Toggle dark mode on or off. |
| `setFlowFilter(startNodeIds)` | Set the flow filter to show only the specified start node flows. Pass `null` to show all flows. |

### Ready callback (in-bundle)

Use `onReady()` to defer logic until the API is available. This is intended for
code that runs **inside** the modeler bundle or scripts that are guaranteed to
load before the modeler mounts:

```javascript
window.WorkflowModeler.onReady(() => {
  const api = window.WorkflowModeler.api;
  console.log('Model has', api.getNodes().length, 'nodes');
});
```

If the modeler is already mounted when `onReady()` is called, the callback
fires synchronously.

### Ready event (Drupal behaviors)

For **external Drupal modules** that use `Drupal.behaviors`, listen for the
`workflow-modeler:ready` DOM event instead. This avoids timing issues where
`onReady()` might fire before the behavior has attached its listener. See the
[Drupal integration](#drupal-integration) section below for the full pattern.

## Complete example: toolbar button that toggles a panel

This example shows the recommended pattern for a Drupal module that adds a
toolbar button which opens and closes a custom panel. It uses a proper Drupal
behavior that listens for the `workflow-modeler:ready` event, with public
methods exposed on a `Drupal.*` namespace.

```javascript
/**
 * @file
 * My Module - Modeler plugin.
 */

(function (Drupal, once) {

  'use strict';

  Drupal.myModuleModelerPlugin = Drupal.myModuleModelerPlugin || {};

  const PANEL_ID = 'my_module_panel';
  const WIDGET_ID = 'my_module_toggle';
  let panelActive = false;

  /**
   * Drupal behavior for the modeler plugin.
   *
   * Listens for the `workflow-modeler:ready` event and registers the
   * widget once the modeler is available.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.myModuleModelerPlugin = {
    attach: function (context) {
      once('my-module-modeler-plugin', 'html', context).forEach(function () {
        document.addEventListener(
          'workflow-modeler:ready',
          Drupal.myModuleModelerPlugin.registerToggleWidget,
          { once: true }
        );
      });
    },
  };

  /**
   * Registers the toolbar widget with the WorkflowModeler.
   */
  Drupal.myModuleModelerPlugin.registerToggleWidget = function () {
    const modeler = window.WorkflowModeler;
    if (!modeler) {
      return;
    }

    try {
      modeler.registerWidget({
        id: WIDGET_ID,
        label: 'My Feature',
        position: 'right',
        weight: 5,
        render: function (container) {
          const btn = document.createElement('button');
          btn.className = 'toolbar-btn';
          btn.title = 'Toggle My Feature';
          btn.textContent = 'MF';

          btn.addEventListener('click', function () {
            if (panelActive) {
              modeler.unregisterPanel(PANEL_ID);
              btn.classList.remove('active');
              panelActive = false;
            }
            else {
              modeler.registerPanel({
                id: PANEL_ID,
                label: 'My Feature',
                position: 'right',
                width: 400,
                render: function (panelContainer, panelApi) {
                  renderPanel(panelContainer, panelApi);
                },
                destroy: function (panelContainer) {
                  panelContainer.innerHTML = '';
                },
              });
              btn.classList.add('active');
              panelActive = true;
            }
          });

          container.appendChild(btn);
        },
        destroy: function (container) {
          if (panelActive) {
            modeler.unregisterPanel(PANEL_ID);
            panelActive = false;
          }
          container.innerHTML = '';
        },
      });
    }
    catch (err) {
      Drupal.throwError(err);
    }
  };

  /**
   * Renders the panel contents.
   *
   * @param {HTMLElement} container
   *   The panel container element.
   * @param {object} api
   *   The WorkflowModeler plugin API.
   */
  function renderPanel(container, api) {
    container.innerHTML = '<h3>My Feature</h3>';

    const status = document.createElement('p');
    status.textContent = 'Select a node to analyze.';
    container.appendChild(status);

    api.onSelectionChange(function (node, edge) {
      if (node) {
        status.textContent = 'Selected: ' + (node.data.label || node.id);
      }
      else {
        status.textContent = 'Select a node to analyze.';
      }
    });

    const stats = document.createElement('p');
    stats.textContent = 'Nodes: ' + api.getNodes().length;
    container.appendChild(stats);

    api.onNodesChange(function (nodes) {
      stats.textContent = 'Nodes: ' + nodes.length;
    });
  }

})(Drupal, once);
```

### Drupal integration

#### Library definition

Define a library in your module's `*.libraries.yml` with `core/drupal` and
`core/once` as dependencies. The library does **not** need to depend on
`modeler/react-ui` directly -- instead, use `hook_library_info_alter()` to
inject it as a dependency of the modeler library (see below).

```yaml
# my_module.libraries.yml
modeler_plugin:
  js:
    js/modeler-plugin.js: {}
  dependencies:
    - core/drupal
    - core/once
```

#### Loading the plugin with the modeler

The recommended way to ensure your plugin loads **together with the modeler**
is to use `hook_library_info_alter()` to add your library as a dependency of
`modeler/react-ui`. This way your script is automatically included whenever the
modeler is loaded -- no need for `hook_page_attachments()` or manual render
array attachments.

```php
<?php

namespace Drupal\my_module\Hook;

use Drupal\Core\Hook\Attribute\Hook;

class LibraryHooks {

  #[Hook('library_info_alter')]
  public function libraryInfoAlter(array &$libraries, string $extension): void {
    if ($extension === 'modeler' && isset($libraries['react-ui'])) {
      $libraries['react-ui']['dependencies'][] = 'my_module/modeler_plugin';
    }
  }

}
```

This approach has several advantages:

- The plugin script only loads on pages where the modeler is present.
- Load order is guaranteed: the modeler bundle loads first (since your library
  is a dependency of it, not the other way round), so `window.WorkflowModeler`
  is available by the time your script executes.
- Multiple modules can each add their own plugin library the same way without
  conflicting.

#### Namespacing conventions

Public methods that need to be accessible outside the closure (e.g. as event
listener callbacks) should be placed on a `Drupal.*` namespace object. The
behavior key should match the namespace:

```javascript
// Namespace for public methods
Drupal.myModuleModelerPlugin = Drupal.myModuleModelerPlugin || {};

// Behavior uses the same key
Drupal.behaviors.myModuleModelerPlugin = { ... };

// Registration function on the namespace
Drupal.myModuleModelerPlugin.registerToggleWidget = function () { ... };
```

This makes the registration function callable from outside the closure (e.g.
for debugging or testing) and ensures the event listener reference is stable.

!!! tip "Why `window.WorkflowModeler` instead of the bare global?"
    Always access the modeler via `window.WorkflowModeler` (typically captured
    into a local variable). The bare identifier `WorkflowModeler` will not
    resolve inside a `'use strict'` closure unless it is explicitly passed in.

## Complete example: node statistics panel

This example registers a panel (without a toolbar button) that displays live
statistics about the current model.

```javascript
(function (Drupal, once) {

  'use strict';

  Drupal.myModuleStatsPlugin = Drupal.myModuleStatsPlugin || {};

  Drupal.behaviors.myModuleStatsPlugin = {
    attach: function (context) {
      once('my-module-stats-plugin', 'html', context).forEach(function () {
        document.addEventListener(
          'workflow-modeler:ready',
          Drupal.myModuleStatsPlugin.registerPanel,
          { once: true }
        );
      });
    },
  };

  Drupal.myModuleStatsPlugin.registerPanel = function () {
    const modeler = window.WorkflowModeler;
    if (!modeler) {
      return;
    }

    try {
      modeler.registerPanel({
        id: 'my_module_stats',
        label: 'Statistics',
        position: 'right',
        weight: 20,
        width: 280,
        render: function (container, api) {
          container.innerHTML = '';

          const table = document.createElement('table');
          table.style.width = '100%';
          table.style.borderCollapse = 'collapse';

          function updateStats() {
            const nodes = api.getNodes();
            const edges = api.getEdges();
            const types = {};
            nodes.forEach(function (n) {
              const t = n.type || 'unknown';
              types[t] = (types[t] || 0) + 1;
            });

            table.innerHTML =
              '<tr><th>Metric</th><th>Count</th></tr>' +
              '<tr><td>Total Nodes</td><td>' + nodes.length + '</td></tr>' +
              '<tr><td>Total Edges</td><td>' + edges.length + '</td></tr>' +
              Object.entries(types).map(function (entry) {
                return '<tr><td>' + entry[0] + '</td><td>' + entry[1] + '</td></tr>';
              }).join('');
          }

          container.appendChild(table);
          updateStats();

          api.onNodesChange(updateStats);
          api.onEdgesChange(updateStats);
        },
      });
    }
    catch (err) {
      Drupal.throwError(err);
    }
  };

})(Drupal, once);
```

## Complete example: panel with mutation capabilities

This example shows a plugin panel that can add pre-configured action nodes to
the workflow -- demonstrating the mutation API. It lists available components
and lets the user click to add them with a default configuration.

```javascript
(function (Drupal, once) {

  'use strict';

  Drupal.myModuleQuickActions = Drupal.myModuleQuickActions || {};

  Drupal.behaviors.myModuleQuickActions = {
    attach: function (context) {
      once('my-module-quick-actions', 'html', context).forEach(function () {
        document.addEventListener(
          'workflow-modeler:ready',
          Drupal.myModuleQuickActions.registerPanel,
          { once: true }
        );
      });
    },
  };

  Drupal.myModuleQuickActions.registerPanel = function () {
    var modeler = window.WorkflowModeler;
    if (!modeler) {
      return;
    }

    try {
      modeler.registerPanel({
        id: 'my_module_quick_actions',
        label: 'Quick Actions',
        position: 'left',
        weight: 5,
        width: 300,
        render: function (container, api) {
          container.innerHTML = '';

          var heading = document.createElement('h3');
          heading.textContent = 'Quick Actions';
          container.appendChild(heading);

          // Show read-only warning when applicable
          if (api.isReadOnly()) {
            var warning = document.createElement('p');
            warning.textContent = 'Read-only mode — actions disabled.';
            warning.style.color = 'var(--modeler-color-text-secondary)';
            container.appendChild(warning);
            return;
          }

          // List available action components (componentType 4 = element)
          var components = api.getComponents().filter(function (c) {
            return c.componentType === 4;
          });

          var list = document.createElement('ul');
          list.style.listStyle = 'none';
          list.style.padding = '0';

          components.slice(0, 10).forEach(function (comp) {
            var item = document.createElement('li');
            var btn = document.createElement('button');
            btn.className = 'toolbar-btn';
            btn.style.width = '100%';
            btn.style.textAlign = 'left';
            btn.style.marginBottom = '4px';
            btn.textContent = comp.label;
            btn.title = comp.description || comp.plugin;

            btn.addEventListener('click', function () {
              var nodeId = api.addNode({
                plugin: comp.plugin,
                componentType: comp.componentType,
                label: comp.label,
                description: comp.description,
                documentationUrl: comp.documentationUrl,
              });

              if (nodeId) {
                // Select and focus the new node
                api.selectNode(nodeId);
                api.focusNode(nodeId);
              }
            });

            item.appendChild(btn);
            list.appendChild(item);
          });

          container.appendChild(list);

          // Add a "Connect primary selection" button
          var connectBtn = document.createElement('button');
          connectBtn.className = 'toolbar-btn';
          connectBtn.style.width = '100%';
          connectBtn.style.marginTop = '12px';
          connectBtn.textContent = 'Connect primary selection to last added';
          connectBtn.disabled = true;

          var lastAddedId = null;

          api.onSelectionChange(function (node) {
            connectBtn.disabled = !node || !lastAddedId
              || node.id === lastAddedId;
          });

          connectBtn.addEventListener('click', function () {
            var selected = api.getSelectedNode();
            if (selected && lastAddedId) {
              api.addEdge(selected.id, lastAddedId);
            }
          });

          container.appendChild(connectBtn);

          // Track the last node we added
          api.onNodesChange(function (nodes) {
            if (nodes.length > 0) {
              lastAddedId = nodes[nodes.length - 1].id;
            }
          });

          // React to read-only changes
          api.onReadOnlyChange(function (readOnly) {
            list.querySelectorAll('button').forEach(function (b) {
              b.disabled = readOnly;
            });
            connectBtn.disabled = readOnly;
          });
        },
        destroy: function (container) {
          container.innerHTML = '';
        },
      });
    }
    catch (err) {
      Drupal.throwError(err);
    }
  };

})(Drupal, once);
```

## Error handling

Plugin code runs inside error boundaries. If a panel's `render()` function
throws, only that panel displays an error message -- the rest of the modeler
continues working. The error boundary offers automatic retry with exponential
backoff, plus a manual "Try Again" button.

Widget errors are caught and logged to the console but do not crash the
toolbar.

## API stability

The Plugin API is designed to be stable across minor versions of the modeler.
The following guarantees apply:

- **Additive changes only**: New methods and properties may be added to the API
  in minor releases, but existing ones will not be removed or changed in
  breaking ways.
- **Deep-cloned data**: Plugins always receive copies, never internal references.
  Changes to internal data structures do not affect the plugin-facing shape.
- **Position values**: The set of allowed positions (`left`, `right`, `bottom`
  for panels; `left`, `right` for widgets) may be extended but not reduced.
