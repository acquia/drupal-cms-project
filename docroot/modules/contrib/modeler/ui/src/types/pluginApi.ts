/**
 * Type definitions for the Workflow Modeler Plugin API.
 *
 * External Drupal modules use these types to register panels and interact
 * with the modeler's state.  The API is exposed globally as
 * `WorkflowModeler` after the modeler bundle loads.
 *
 * @example
 * ```js
 * // In another Drupal module's JavaScript:
 * WorkflowModeler.registerPanel({
 *   id: 'my_module_analytics',
 *   label: 'Analytics',
 *   position: 'right',
 *   weight: 10,
 *   render(container, api) {
 *     container.innerHTML = '<h3>Analytics</h3>';
 *     api.onSelectionChange((node, edge) => {
 *       // React to selection changes
 *     });
 *   },
 *   destroy(container) {
 *     container.innerHTML = '';
 *   },
 * });
 * ```
 */

/**
 * Allowed positions for plugin panels within the modeler layout.
 *
 * - `'right'`  – Rendered to the right of the property panel.
 * - `'left'`   – Rendered to the left of the canvas.
 * - `'bottom'` – Rendered below the canvas.
 */
export type PanelPosition = 'left' | 'right' | 'bottom';

/**
 * Read-only snapshot of a workflow node, safe to expose to plugins.
 */
export interface PluginNode {
  id: string;
  type?: string;
  data: Record<string, unknown>;
  position: { x: number; y: number };
}

/**
 * Read-only snapshot of a workflow edge, safe to expose to plugins.
 */
export interface PluginEdge {
  id: string;
  source: string;
  target: string;
  type?: string;
  data?: Record<string, unknown>;
}

/**
 * Read-only snapshot of model metadata, safe to expose to plugins.
 */
export interface PluginModelData {
  id?: string;
  version?: string;
  metadata?: {
    label?: string;
    documentation?: string;
    executable?: boolean;
    tags?: string[];
    changelog?: string;
    summary?: string;
    recipes?: string[];
    config_actions?: Array<{ config: string; actions: unknown }>;
    export_config?: string[];
    modules?: string[];
  };
}

/**
 * Read-only snapshot of an available component (plugin) definition.
 */
export interface PluginComponent {
  /** Plugin identifier (e.g. `'eca_content_entity:create'`). */
  plugin: string;
  /** Human-readable label. */
  label: string;
  /** Component type string (e.g. `'start'`, `'element'`, `'link'`). */
  type?: string;
  /** Provider module name. */
  provider?: string;
  /** Short description. */
  description?: string;
  /** URL to documentation, or null. */
  documentationUrl?: string | null;
  /** Integer component-type constant (1=start, 2=subprocess, 4=element, 5=link, 6=gateway). */
  componentType?: number;
}

/**
 * Read-only snapshot of model-owner-provided component labels.
 */
export interface PluginComponentLabels {
  start?: string;
  element?: string;
  link?: string;
  gateway?: string;
  subprocess?: string;
}

/**
 * Read-only snapshot of a modeler context.
 */
export interface PluginContext {
  id: string;
  topic: string;
  model_owner: string;
}

/**
 * Read-only snapshot of the undo/redo history state.
 */
export interface PluginHistoryState {
  canUndo: boolean;
  canRedo: boolean;
}

/**
 * Read-only snapshot of an error record.
 */
export interface PluginError {
  id: string;
  message: string;
  dismissed: boolean;
}

/**
 * Descriptor for adding a new node via the plugin API.
 *
 * At minimum, `plugin` and `componentType` must be provided so the
 * modeler knows which component to instantiate and how to render it.
 */
export interface AddNodeDescriptor {
  /** Plugin identifier (e.g. `'eca_content_entity:create'`). */
  plugin: string;
  /** Integer component-type constant (1=start, 4=element, 6=gateway, 2=subprocess). */
  componentType: number;
  /** Human-readable label. If omitted, derived from the plugin ID. */
  label?: string;
  /**
   * Canvas position. If omitted, placed automatically by the shared
   * incremental layout primitives — see `sourceNodeId` for the two
   * automatic placement modes.
   */
  position?: { x: number; y: number };
  /**
   * ID of an existing node the new node is being added *below*, as its
   * successor.  Placement then matches exactly what quick-add produces
   * for the same parent: column-aligned one row-gap under the parent,
   * with neighboring flows shifted aside if required.
   *
   * This only controls **placement** — no edge is created.  Call
   * `addEdge(sourceNodeId, newNodeId)` to wire the two nodes together.
   *
   * Ignored when `position` is given (an explicit position always wins).
   * If the referenced node does not exist, `addNode()` returns `null`
   * without mutating the graph, consistent with `addEdge()`.
   *
   * When omitted, the node is placed as a brand-new event flow to the
   * right of every existing flow — the same position the modeler's own
   * "add event" affordance would choose.
   */
  sourceNodeId?: string;
  /** Initial configuration values. */
  configuration?: Record<string, unknown>;
  /** Short description. */
  description?: string;
  /** URL to plugin documentation. */
  documentationUrl?: string | null;
}

/**
 * Partial updates for an existing node, passed to `updateNode()`.
 *
 * Only the specified fields are updated; everything else remains unchanged.
 */
export interface UpdateNodeDescriptor {
  /** New human-readable label. */
  label?: string;
  /** New canvas position. */
  position?: { x: number; y: number };
  /** Merged configuration (shallow merge with existing). */
  configuration?: Record<string, unknown>;
  /** Annotation text. */
  annotation?: string;
}

/**
 * Partial updates for an existing edge, passed to `updateEdge()`.
 */
export interface UpdateEdgeDescriptor {
  /** Annotation text (only for condition edges). */
  annotation?: string;
}

/**
 * Descriptor for setting a condition on an edge.
 */
export interface SetConditionDescriptor {
  /** Condition plugin identifier. */
  plugin: string;
  /** Human-readable condition label. If omitted, derived from the plugin ID. */
  label?: string;
  /** Initial condition configuration values. */
  configuration?: Record<string, unknown>;
}

/**
 * Called when the primary selection or any multi-selection member changes.
 *
 * The callback receives only the first selected node and edge. Use the
 * multi-selection getters when the complete selection is needed.
 */
export type SelectionChangeCallback = (
  node: PluginNode | null,
  edge: PluginEdge | null,
) => void;
export type NodesChangeCallback = (nodes: PluginNode[]) => void;
export type EdgesChangeCallback = (edges: PluginEdge[]) => void;
export type ModelDataChangeCallback = (data: PluginModelData | null) => void;
export type DarkModeChangeCallback = (isDarkMode: boolean) => void;
export type ContextChangeCallback = (contextId: string | null) => void;
export type ComponentsChangeCallback = (components: PluginComponent[]) => void;
export type ReadOnlyChangeCallback = (isReadOnly: boolean) => void;
export type ReadyCallback = () => void;

/**
 * Unsubscribe function returned by event subscription methods.
 * Call it to stop receiving further updates.
 */
export type Unsubscribe = () => void;

/**
 * The public API object passed to panel `render()` callbacks and available
 * as `WorkflowModeler.api` after the modeler mounts.
 *
 * All data returned by getters is a **deep-cloned snapshot** — mutations
 * will not affect the modeler's internal state.
 *
 * Mutation methods (add/update/remove) are no-ops in read-only mode and
 * automatically integrate with the undo/redo history and unsaved-changes
 * tracking.
 */
export interface ModelerPluginApi {
  // ── Getters (snapshots) ─────────────────────────────────────────────
  /** Returns a deep-cloned snapshot of all current nodes. */
  getNodes: () => PluginNode[];
  /** Returns a deep-cloned snapshot of all current edges. */
  getEdges: () => PluginEdge[];
  /** Returns a single node by ID, or null if not found. */
  getNodeById: (nodeId: string) => PluginNode | null;
  /** Returns a single edge by ID, or null if not found. */
  getEdgeById: (edgeId: string) => PluginEdge | null;
  /** Returns the first selected node (the primary selection), or null. */
  getSelectedNode: () => PluginNode | null;
  /** Returns the first selected edge (the primary selection), or null. */
  getSelectedEdge: () => PluginEdge | null;
  /**
   * Returns every selected node, in selection order.
   *
   * A single selection yields a one-element array, so a plugin never needs
   * `getSelectedNode()` as well.
   */
  getSelectedNodes: () => PluginNode[];
  /** Returns every selected edge, in selection order. */
  getSelectedEdges: () => PluginEdge[];
  /** Returns a deep-cloned snapshot of the model data. */
  getModelData: () => PluginModelData | null;
  /** Returns whether the modeler is in read-only mode. */
  isReadOnly: () => boolean;
  /** Returns whether dark mode is active. */
  isDarkMode: () => boolean;
  /** Returns a deep-cloned list of all available components (plugins). */
  getComponents: () => PluginComponent[];
  /** Returns the model-owner-provided component labels. */
  getComponentLabels: () => PluginComponentLabels;
  /** Returns a deep-cloned list of all available contexts. */
  getContexts: () => PluginContext[];
  /** Returns the currently selected context ID, or null (all). */
  getSelectedContextId: () => string | null;
  /**
   * Returns the IDs of start nodes whose flows are currently visible,
   * or `null` when all flows are visible (no filter active).
   */
  getFilteredNodeIds: () => string[] | null;
  /** Returns the current undo/redo availability. */
  getHistoryState: () => PluginHistoryState;
  /** Returns the current error log. */
  getErrors: () => PluginError[];

  // ── Event subscriptions ─────────────────────────────────────────────
  /**
   * Subscribe to primary and multi-selection changes.
   *
   * The callback receives only the first selected node and edge.
   */
  onSelectionChange: (callback: SelectionChangeCallback) => Unsubscribe;
  /** Subscribe to node list changes (add, remove, update, reorder). */
  onNodesChange: (callback: NodesChangeCallback) => Unsubscribe;
  /** Subscribe to edge list changes. */
  onEdgesChange: (callback: EdgesChangeCallback) => Unsubscribe;
  /** Subscribe to model metadata changes. */
  onModelDataChange: (callback: ModelDataChangeCallback) => Unsubscribe;
  /** Subscribe to dark mode changes. */
  onDarkModeChange: (callback: DarkModeChangeCallback) => Unsubscribe;
  /** Subscribe to context selection changes. */
  onContextChange: (callback: ContextChangeCallback) => Unsubscribe;
  /** Subscribe to available components list changes. */
  onComponentsChange: (callback: ComponentsChangeCallback) => Unsubscribe;
  /** Subscribe to read-only state changes. */
  onReadOnlyChange: (callback: ReadOnlyChangeCallback) => Unsubscribe;

  // ── Selection & viewport actions ────────────────────────────────────
  /**
   * Select a node by ID.  Pass `null` to clear selection.
   * No-op in read-only mode.
   */
  selectNode: (nodeId: string | null) => void;
  /**
   * Select an edge by ID.  Pass `null` to clear selection.
   * No-op in read-only mode.
   */
  selectEdge: (edgeId: string | null) => void;
  /** Clear the current selection (node, edge, multi-selection). */
  clearSelection: () => void;
  /** Center the viewport on a specific node. */
  focusNode: (nodeId: string) => void;
  /** Fit all nodes into the viewport. */
  fitView: () => void;

  // ── Node mutations ──────────────────────────────────────────────────
  /**
   * Add a new node to the canvas.  Returns the ID of the newly created
   * node, or `null` if the operation was rejected (read-only mode, or an
   * unknown `sourceNodeId`).
   *
   * If `position` is omitted the node is placed automatically by the same
   * layout primitives the UI itself uses: as a successor of
   * `descriptor.sourceNodeId` when that is given, otherwise as a new event
   * flow to the right of everything already on the canvas.
   */
  addNode: (descriptor: AddNodeDescriptor) => string | null;
  /**
   * Update an existing node's data (label, configuration, position, etc.).
   * Returns `true` on success, `false` if the node was not found or the
   * modeler is read-only.
   */
  updateNode: (nodeId: string, updates: UpdateNodeDescriptor) => boolean;
  /**
   * Remove a node and all its connected edges.
   * Returns `true` on success, `false` if the node was not found or the
   * modeler is read-only.
   */
  removeNode: (nodeId: string) => boolean;

  // ── Edge mutations ──────────────────────────────────────────────────
  /**
   * Add a new edge connecting two nodes.  Returns the ID of the newly
   * created edge, or `null` if the operation was rejected.
   */
  addEdge: (sourceNodeId: string, targetNodeId: string) => string | null;
  /**
   * Update an existing edge's data.
   * Returns `true` on success, `false` if the edge was not found or the
   * modeler is read-only.
   */
  updateEdge: (edgeId: string, updates: UpdateEdgeDescriptor) => boolean;
  /**
   * Remove an edge.
   * Returns `true` on success, `false` if the edge was not found or the
   * modeler is read-only.
   */
  removeEdge: (edgeId: string) => boolean;
  /**
   * Attach a condition to an existing edge.  Conditions are first-class
   * nodes: this inserts a condition node on the identified edge, splitting
   * it into `source → conditionNode → target`.  Returns `true` on success,
   * `false` if the edge was not found or the modeler is read-only.
   */
  setCondition: (edgeId: string, condition: SetConditionDescriptor) => boolean;
  /**
   * Remove a condition, collapsing its condition node back into a single
   * plain edge (the inverse of `setCondition`).  The `edgeId` argument is
   * resolved to a condition node when it is either the condition node id
   * itself or an edge adjacent to a condition node.  Returns `true` on
   * success, `false` if no condition could be resolved or the modeler is
   * read-only.
   */
  removeCondition: (edgeId: string) => boolean;

  // ── Canvas actions ──────────────────────────────────────────────────
  /**
   * Trigger automatic layout of all nodes.
   * No-op in read-only mode.
   */
  autoLayout: () => void;
  /**
   * Toggle or set dark mode.
   */
  setDarkMode: (enabled: boolean) => void;
  /**
   * Set the flow filter to show only the specified start node flows.
   * Pass `null` to show all flows.
   */
  setFlowFilter: (startNodeIds: string[] | null) => void;
}

/**
 * Registration descriptor for a plugin panel.
 *
 * Modules provide this object to `WorkflowModeler.registerPanel()`.
 */
export interface PluginPanelDescriptor {
  /** Unique identifier for the panel (e.g. `'my_module_analytics'`). */
  id: string;
  /** Human-readable label shown in the panel header. */
  label: string;
  /**
   * Where to render the panel.
   *
   * For docked panels this is the slot the panel is placed in.  For
   * floating panels (see `floating`) it only seeds the initial on-screen
   * corner the panel appears at.
   *
   * @default 'right'
   */
  position?: PanelPosition;
  /**
   * Ordering weight — lower values appear first.
   * @default 0
   */
  weight?: number;
  /**
   * Initial width of the panel in pixels.
   * @default 320
   */
  width?: number;
  /**
   * Render the panel detached from the layout, floating above the canvas.
   *
   * A floating panel reserves no space next to the canvas and can be moved
   * by the user by dragging its header (or by focusing the move handle and
   * using the arrow keys).  It is always kept within the modeler's bounds.
   *
   * This is a registration-time choice: there is no user-facing control to
   * switch a panel between docked and floating.
   *
   * @default false
   */
  floating?: boolean;
  /**
   * Render the panel content into the given container element.
   * Called once when the panel mounts.  The `api` object provides
   * read-only access to the modeler state plus event subscriptions.
   */
  render: (container: HTMLElement, api: ModelerPluginApi) => void;
  /**
   * Optional cleanup callback called before the panel is removed.
   * Use this to detach event listeners, tear down frameworks, etc.
   */
  destroy?: (container: HTMLElement) => void;
  /**
   * Optional callback called when the panel is resized.
   */
  onResize?: (width: number, height: number) => void;
}

/**
 * Internal representation of a registered panel (descriptor + runtime state).
 */
export interface RegisteredPanel extends PluginPanelDescriptor {
  position: PanelPosition;
  weight: number;
  width: number;
  floating: boolean;
}

// ── Toolbar Widget Types ──────────────────────────────────────────────

/**
 * Allowed positions for toolbar widgets.
 *
 * - `'left'`  – Before the model title (alongside the event button and context selector).
 * - `'right'` – In the right toolbar region (alongside zoom, settings, etc.).
 */
export type ToolbarWidgetPosition = 'left' | 'right';

/**
 * Registration descriptor for a toolbar widget.
 *
 * Modules provide this object to `WorkflowModeler.registerWidget()`.
 *
 * @example
 * ```js
 * WorkflowModeler.registerWidget({
 *   id: 'my_module_ai_toggle',
 *   label: 'AI Assistant',
 *   position: 'right',
 *   weight: 5,
 *   render(container, api) {
 *     const btn = document.createElement('button');
 *     btn.className = 'toolbar-btn';
 *     btn.title = 'Toggle AI Panel';
 *     btn.innerHTML = '🤖';
 *     btn.addEventListener('click', () => {
 *       // Toggle a panel on/off
 *       try {
 *         WorkflowModeler.registerPanel({ ... });
 *       } catch {
 *         WorkflowModeler.unregisterPanel('my_module_ai_panel');
 *       }
 *     });
 *     container.appendChild(btn);
 *   },
 * });
 * ```
 */
export interface PluginToolbarWidgetDescriptor {
  /** Unique identifier for the widget (e.g. `'my_module_ai_toggle'`). */
  id: string;
  /** Human-readable label (used for aria-label and tooltip). */
  label: string;
  /**
   * Where to render the widget in the toolbar.
   * @default 'right'
   */
  position?: ToolbarWidgetPosition;
  /**
   * Ordering weight — lower values appear first within the position.
   * @default 0
   */
  weight?: number;
  /**
   * Render the widget content into the given container element.
   * Called once when the widget mounts.  The `api` object provides
   * read-only access to the modeler state plus event subscriptions.
   *
   * The container is an inline element within the toolbar.  Widgets
   * typically render a single `<button>` styled with the `toolbar-btn`
   * CSS class for visual consistency.
   */
  render: (container: HTMLElement, api: ModelerPluginApi) => void;
  /**
   * Optional cleanup callback called before the widget is removed.
   */
  destroy?: (container: HTMLElement) => void;
}

/**
 * Internal representation of a registered widget (descriptor + defaults applied).
 */
export interface RegisteredWidget extends PluginToolbarWidgetDescriptor {
  position: ToolbarWidgetPosition;
  weight: number;
}

// ── Global Object ─────────────────────────────────────────────────────

/**
 * The global `WorkflowModeler` object exposed on `window`.
 */
export interface WorkflowModelerGlobal {
  /**
   * Register a new panel.  Can be called before or after the modeler
   * mounts — panels registered early are rendered as soon as the
   * modeler becomes ready.
   */
  registerPanel: (descriptor: PluginPanelDescriptor) => void;
  /**
   * Remove a previously registered panel by ID.
   */
  unregisterPanel: (panelId: string) => void;
  /**
   * Register a toolbar widget.  Can be called before or after the
   * modeler mounts.
   */
  registerWidget: (descriptor: PluginToolbarWidgetDescriptor) => void;
  /**
   * Remove a previously registered toolbar widget by ID.
   */
  unregisterWidget: (widgetId: string) => void;
  /**
   * The public API for reading modeler state and subscribing to events.
   * Available once the modeler has mounted (`null` before that).
   */
  api: ModelerPluginApi | null;
  /**
   * Subscribe to be notified when the modeler becomes ready
   * (i.e. the React app has mounted and the API is available).
   */
  onReady: (callback: ReadyCallback) => Unsubscribe;
}
