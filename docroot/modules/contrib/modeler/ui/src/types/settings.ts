/**
 * Shared settings interfaces used across the modeler application.
 *
 * These types describe the Drupal-provided settings object that is passed
 * into the React application at initialization and threaded through
 * components and hooks.
 */

import type { FormField } from './forms';

/**
 * Component type names used in context definitions.
 */
export type ContextComponentType = 'start' | 'subprocess' | 'swimlane' | 'element' | 'link' | 'gateway' | 'annotation';

/**
 * A dependency constraint: a plugin can only be used when at least one of
 * its listed predecessor plugins is present in the current workflow.
 */
export interface ContextDependency {
  type: ContextComponentType;
  id: string;
}

/**
 * Dependency definitions delivered via drupalSettings.modeler_api.dependencies.
 *
 * Top-level keys are component type names (e.g. `"link"`, `"element"`).
 * Each value is a record keyed by plugin ID, mapping to an array of
 * predecessor dependency constraints.
 *
 * Matches the schema defined in dependency_list.schema.json.
 */
export type ModelerDependencies = Partial<
  Record<ContextComponentType, Record<string, ContextDependency[]>>
>;

/**
 * Plugins allowed for a single component type within a context.
 */
interface ContextComponentEntry {
  plugins: string[];
}

/**
 * A resolved modeler API context for a given model owner.
 * Matches the schema defined in context_list.schema.json.
 */
export interface ModelerContext {
  id: string;
  topic: string;
  model_owner: string;
  components: Partial<Record<ContextComponentType, ContextComponentEntry>>;
}

/**
 * Model-owner-provided labels for the core component types.
 *
 * The modeler itself is model-owner agnostic — different owners may call
 * their components by different names (e.g. "Event" vs "Trigger", "Action"
 * vs "Task").  This map lets the backend supply the correct terminology.
 *
 * Keys correspond to the internal node/edge types used by the modeler.
 * All fields are optional; missing labels fall back to generic defaults
 * (see `DEFAULT_COMPONENT_LABELS` in `componentUtils.ts`).
 */
export interface ComponentLabels {
  /** Label for start/event nodes (default: "Start"). */
  start?: string;
  /** Label for element/action nodes (default: "Element"). */
  element?: string;
  /** Label for link/condition edges (default: "Link"). */
  link?: string;
  /** Label for gateway nodes (default: "Gateway"). */
  gateway?: string;
  /** Label for subprocess nodes (default: "Subprocess"). */
  subprocess?: string;
}

/**
 * Plural component labels used for grouping headings in the component
 * panel and quick-add popups (e.g. "Events", "Actions", "Conditions").
 *
 * Keys mirror {@link ComponentLabels}; missing entries fall back to
 * {@link DEFAULT_COMPONENT_LABELS_PLURAL} in `componentUtils.ts`.
 */
export type ComponentLabelsPlural = ComponentLabels;

/**
 * Min/max cardinality pair used for both component counts and successor counts.
 */
export interface CardinalityRange {
  /** Minimum count (inclusive). */
  min?: number;
  /** Maximum count (inclusive). */
  max?: number;
}

/**
 * Successor cardinality constraint with an optional parallel-edge condition rule.
 *
 * Extends {@link CardinalityRange} with a flag that model owners can set to
 * require every edge in a parallel group (same source + same target) to carry
 * a condition.  When `requireConditionWhenParallel` is `true` and two or more
 * edges connect the same source/target pair, the model is invalid unless every
 * edge in that group has a condition assigned.
 */
export interface SuccessorCardinality extends CardinalityRange {
  /**
   * When `true`, parallel successors (multiple edges from the same source to
   * the same target) must all carry a condition.  Opt-in per source component
   * type; defaults to `false` / absent.
   */
  requireConditionWhenParallel?: boolean;
  /**
   * When `true`, condition edges that share the SAME non-empty `conditionId`
   * and the same target are grouped on load into ONE shared (reused) condition
   * node with multiple inbound edges and a single outbound edge (issue
   * #3589093).  Owner-opt-in per source component type; defaults to `false` /
   * absent (no grouping — one condition node per condition edge).
   *
   * NOTE: the PHP side that defines/sets this flag is a separate effort
   * (modeler_api #3588502, eca #3590335).  Until it ships, this flag will be
   * absent and the modeler reads it DEFENSIVELY (absent/false ⇒ no grouping).
   */
  allowConditionReuse?: boolean;
}

/**
 * Cardinality constraint for a single component type.
 *
 * Model owners can declare minimum and maximum counts for each component type,
 * as well as successor (outgoing edge) constraints per node of that type.
 * The modeler enforces these both at save time (backend) and before saving
 * (frontend), and uses successor constraints to suppress UI affordances
 * (quick-add button, outgoing edge handle) on nodes that have reached
 * their maximum.
 */
export interface CardinalityConstraint extends CardinalityRange {
  /** Successor (outgoing edge) cardinality per node of this type. */
  successors?: SuccessorCardinality;
}

/**
 * Model-level cardinality constraints keyed by component type name.
 *
 * Example: `{ start: { min: 1, max: 1 }, element: { min: 1, max: 1 } }`
 */
export type ModelConstraints = Partial<Record<ContextComponentType, CardinalityConstraint>>;

/**
 * A single entry in a workflow execution replay.
 *
 * Replay data is an ordered list of steps that describe which nodes
 * were executed, which successors were added/ignored, and whether
 * conditions were evaluated.  The `type` field determines the step
 * semantics; optional fields are present depending on the step type.
 *
 * Additional backend-provided properties are captured by the index
 * signature so that future extensions do not require type changes.
 */
export interface ReplayDataEntry {
  /** Step type: 'started', 'execute', 'add successor', 'ignore successor', 'access denied', etc. */
  type: string;
  /** ID of the node this step relates to. */
  id?: string;
  /** ID of the successor node (for successor steps). */
  successorId?: string;
  /** ID of the condition plugin evaluated (for condition steps). */
  conditionId?: string;
  /** Exception information if the step failed. */
  exception?: {
    class?: string;
    code?: number;
    message?: string;
    file?: string;
    trace?: string;
  } | Record<string, unknown>;
  /** Allow additional backend-provided properties. */
  [key: string]: unknown;
}

/**
 * A component definition provided by the Drupal backend.
 *
 * Components represent available plugins (events, actions, conditions,
 * gateways, subprocesses) that can be added to the workflow.
 *
 * This is an alias for {@link StoreComponent} — the canonical definition
 * used across the application.  It exists so that code dealing with
 * component *definitions* (as opposed to store state) can use the more
 * descriptive name.
 */
export type ComponentDefinition = StoreComponent;

/**
 * Settings specific to the modeler UI (drupalSettings.modeler).
 */
interface ModelerSettings {
  stayInContextOnClose?: boolean;
  replayData?: ReplayDataEntry[];
  modelId?: string;
  components?: StoreComponent[];
  modelData?: ModelData | string;
  selectComponentId?: string;
  selectContextId?: string;
  /**
   * Key/value pairs provided via query parameter that should be used as
   * default configuration values for newly added components (nodes and
   * edge conditions). When a config form field key matches a key in this
   * map, the contextConfig value is applied automatically.
   */
  setContextConfig?: Record<string, string>;
  /**
   * When true the modeler runs in standalone (viewer) mode without a
   * Drupal backend.  The canvas is read-only, editing UI is hidden,
   * and configuration forms are read from `configForms` instead of
   * being fetched from the backend.
   */
  standalone?: boolean;
  /**
   * Pre-loaded configuration form schemas keyed by plugin ID (for nodes)
   * or condition plugin ID (for edges).  Only used in standalone mode.
   * Each value is the `form` array normally returned by the backend
   * config_url endpoint.
   */
  configForms?: Record<string, Record<string, unknown>[]>;
  /**
   * The model metadata form, converted from the shared Drupal form array the
   * model owner defines. Rendered by the metadata dialog through the same
   * `ConfigurationForm` component as any plugin configuration form.
   */
  metadataForm?: FormField[];
  /**
   * When true, all panels start collapsed.  Panels auto-expand when they
   * receive content (e.g. when a node is selected the property panel opens)
   * and auto-collapse when the content is cleared (e.g. selection is removed).
   * Primarily useful in standalone viewer mode to maximize canvas space.
   */
  collapsePanels?: boolean;
  /**
   * Maps integer component-type constants (e.g. 1, 4, 5) to their string
   * node-type names (e.g. "start", "element", "link").  Provided once by
   * the backend so that individual component entries only carry `componentType`
   * and the frontend resolves `type` at load time.
   */
  typeMap?: Record<number, string>;
}

/**
 * A single global token entry as provided by the Drupal backend.
 */
export interface GlobalToken {
  name: string;
  description?: string;
  dynamic?: boolean;
  type?: string;
  'raw token': string;
  token: string;
  value?: string | number | boolean | null;
  parent?: string;
  children?: Record<string, GlobalToken>;
}

/**
 * Owner-provided component definitions keyed by category.
 */
interface OwnerComponents {
  [category: string]: StoreComponent[];
}

/**
 * Granular permissions provided by the modeler API via
 * `drupalSettings.modeler_api.permissions`.
 *
 * Each key corresponds to a feature the backend can enable or disable.
 * Values default to `true` when omitted.
 */
export interface ModelerPermissions {
  /** Whether the user may edit model metadata (label, version, tags, …). */
  'edit metadata'?: boolean;
  /** Whether the user may switch the active context. */
  'switch context'?: boolean;
  /** Whether the user may edit a template model. */
  'edit template'?: boolean;
  /** Whether the user may mark a model as template. */
  'create template'?: boolean;
  /** Whether the user may trigger test executions. */
  'test'?: boolean;
  /** Whether the user may use the replay panel / load replay data. */
  'replay'?: boolean;
}

/**
 * API endpoint URLs and model metadata (drupalSettings.modeler_api).
 */
interface ModelerApiSettings {
  token_url?: string;
  save_url?: string;
  config_url?: string;
  replay_url?: string;
  test_url?: string;
  collection_url?: string;
  export_url?: string;
  export_recipe_url?: string;
  isNew?: boolean;
  readOnly?: boolean;
  /**
   * Granular permissions from the backend.  Missing keys default to `true`.
   */
  permissions?: ModelerPermissions;
  favorite_components?: Record<number, string[]>;
  contexts?: ModelerContext[];
  /**
   * Dependency definitions that constrain which plugins can be used based
   * on which predecessor plugins are present in the current workflow.
   * Keyed by component type, then by plugin ID.
   */
  dependencies?: ModelerDependencies;
  /**
   * URL for on-demand loading of global tokens.  The modeler fetches from
   * this endpoint after mount to avoid blocking the initial page render.
   * Mutually exclusive with `global_tokens` (inline data).
   */
  global_tokens_url?: string;
  /**
   * Global tokens available across the entire site, keyed by raw token
   * string (e.g. `[current-date:custom:?]`). Each entry has a `name`,
   * optional `description`, `token` (without prefix), `raw token`,
   * `value`, and optionally `children` (recursive same structure).
   * @deprecated Prefer `global_tokens_url` for lazy loading.
   */
  global_tokens?: Record<string, GlobalToken>;
  /**
   * URL for on-demand loading of template tokens.  The modeler fetches from
   * this endpoint after mount.  Mutually exclusive with `template_tokens`.
   */
  template_tokens_url?: string;
  /**
   * Template tokens defined by the current template model, keyed by raw
   * token string. Uses the same recursive `GlobalToken` structure.
   * Only present when the model is a template (`metadata.template` is true).
   * @deprecated Prefer `template_tokens_url` for lazy loading.
   */
  template_tokens?: Record<string, GlobalToken>;
  metadata?: {
    version?: string;
    label?: string;
    documentation?: string;
    storage?: string;
    executable?: boolean;
    template?: boolean;
    tags?: string[];
    changelog?: string;
    summary?: string;
    recipes?: string[];
    config_actions?: Array<{ config: string; actions: unknown }>;
    export_config?: string[];
    modules?: string[];
  };
  /**
   * Model-owner-provided labels for the core component types.
   * Allows different model owners to use their own terminology
   * (e.g. "Event" vs "Trigger", "Action" vs "Task").
   */
  component_labels?: ComponentLabels;
  /**
   * Model-owner-provided plural labels for grouping headings
   * (e.g. "Events", "Actions", "Conditions").
   */
  component_labels_plural?: ComponentLabelsPlural;
  /**
   * Cardinality constraints for component types (min/max counts).
   * Enforced both client-side before save and server-side during save.
   */
  model_constraints?: ModelConstraints;
}

/**
 * Top-level settings object passed from Drupal into the React app.
 */
export interface Settings {
  modeler?: ModelerSettings;
  modeler_api?: ModelerApiSettings;
  ownerComponents?: OwnerComponents;
}

/**
 * Drupal AJAX helper object injected by the host page.
 */
/**
 * Object returned by `Drupal.ajax()`.  Only the members used by the
 * modeler are listed; additional properties are accessible via the
 * index signature.
 */
export interface DrupalAjaxObject {
  success?: (...args: unknown[]) => unknown;
  error?: (...args: unknown[]) => unknown;
  execute: () => void;
  [key: string]: unknown;
}

/**
 * Drupal AJAX helper object injected by the host page.
 */
export interface DrupalAjax {
  ajax: (settings: Record<string, unknown>) => DrupalAjaxObject;
  t?: (text: string) => string;
}

/**
 * Edge order information used to display and reorder edges from the same source node.
 */
export interface EdgeOrderInfo {
  pathX?: number;
  pathY?: number;
  order: number;
  totalEdges: number;
  sourceNodeId?: string;
}

/**
 * Base data interface shared by all edge component types (Default, Condition, Annotation).
 */
export interface BaseEdgeData {
  isSelected?: boolean;
  controlOffset?: { x: number; y: number };
  replayHighlight?: string;
  /** Global canvas lock state (read-only / standalone). */
  globalLocked?: boolean;
  edgeOrdersVisible?: boolean;
  edgeOrderInfo?: EdgeOrderInfo;
  onEdgeUpdate?: (id: string, updates: { controlOffset: { x: number; y: number } }) => void;
  onReorderEdge?: (sourceNodeId: string, fromOrder: number, toOrder: number) => void;
  /** Callback to delete this edge (connection). Mirrors node `onDelete`. */
  onDeleteEdge?: (edgeId: string) => void;

  // ── Endpoint reconnection (issue #3585553) ───────────────────────────
  // Per-handle, selection-gated edge-endpoint reconnection. An endpoint grip
  // is rendered (and draggable) IFF its handle hosts EXACTLY ONE selected
  // edge endpoint. The source and target ends are evaluated independently.
  /**
   * True when this edge's SOURCE endpoint may be reconnected: the edge is
   * selected AND it is the only selected edge using this source node/handle.
   * When true (and not {@link globalLocked}), DefaultEdge renders a draggable
   * "move source" grip at the source endpoint. Computed by FlowCanvas from the
   * current selection.
   */
  sourceGripEnabled?: boolean;
  /**
   * True when this edge's TARGET endpoint may be reconnected: the edge is
   * selected AND it is the only selected edge using this target node/handle.
   * When true (and not {@link globalLocked}), DefaultEdge renders a draggable
   * "move target" grip at the target endpoint.
   */
  targetGripEnabled?: boolean;
  /**
   * Commit a reconnection of one of this edge's endpoints. Updates TOP-LEVEL
   * edge fields (`source`/`target`/`sourceHandle`/`targetHandle`), NOT
   * `data` — a pure reconnect does not change edge data or type. Saves history
   * first so undo/redo and unsaved-changes tracking work. Injected by
   * FlowCanvas; only the changed end's fields are provided.
   */
  onReconnectEdge?: (
    edgeId: string,
    updates: {
      source?: string;
      sourceHandle?: string | null;
      target?: string;
      targetHandle?: string | null;
    },
  ) => void;
  /**
   * Validate a proposed reconnection of this edge before committing. Receives
   * the full proposed `{ source, target, sourceHandle, targetHandle }` (with
   * the dragged end replaced by the drop target). Returns true when allowed.
   * Wraps the shared connection-validation logic with this edge excluded from
   * outbound counts. Injected by FlowCanvas.
   */
  validateReconnect?: (connection: import('reactflow').Connection) => boolean;
}

/**
 * Base data interface shared by all node component types.
 */
export interface BaseNodeData {
  label?: string;
  plugin?: string;
  annotation?: string;
  isAnnotationVisible?: boolean;
  /** Global canvas read-only flag — injected at render time, not persisted. */
  isLocked?: boolean;
  /** When true, the source (output) handle is rendered but not connectable
   *  because the node has reached its maximum outgoing connections per model
   *  constraints. The handle must stay in the DOM for existing edges to render. */
  sourceHandleDisabled?: boolean;
  /** Why the source handle is disabled, used to pick the explanatory tooltip
   *  message. `'condition-single-out'` for the condition 1-outbound rule
   *  (issue #3589093); `'max-successors'` for the generic max-successors
   *  constraint. Only meaningful when {@link sourceHandleDisabled} is true. */
  sourceHandleDisabledReason?: 'max-successors' | 'condition-single-out';
  onDelete?: () => void;
  onToggleAnnotation?: () => void;
  onQuickAdd?: (component: StoreComponent) => void;
  /** Callback to replace a placeholder node with a real action/gateway component. */
  onReplacePlaceholder?: (component: StoreComponent) => void;
}

// ── Store-level data interfaces ──────────────────────────────────────────

/**
 * Complete data payload carried by every workflow node in the store.
 *
 * This extends {@link BaseNodeData} (used by visual node renderers) with
 * persistent model properties (configuration, componentType, etc.) and
 * runtime UI-injected properties (replay highlights, descriptions, etc.).
 */
export interface NodeData extends BaseNodeData {
  // ── Persistent / model properties ────────────────────────────────────
  /** Server-side plugin configuration key/value pairs. */
  configuration?: Record<string, unknown>;
  /** Integer component-type constant (1=Start, 2=Subprocess, 4=Element, 5=Link, 6=Gateway). */
  componentType?: number | string;

  // ── Internal condition-node properties (issue #3589093) ──────────────
  // Conditions are stored as edge properties in the backend JSON, but are
  // promoted to first-class condition *nodes* internally so they can be
  // selected, configured, and laid out like any other component.  These
  // fields are written by `parseModelData` when it synthesizes a condition
  // node and consumed by `exportModelData` when it collapses the node back
  // onto an edge.  They MUST NOT be serialized to the backend JSON.
  /**
   * Original condition ID from the backend, preserved on the condition node
   * so that demote (export) can write it back unchanged for round-trip
   * stability.  Empty/undefined for conditions newly created in the frontend.
   */
  conditionId?: string | null;
  /**
   * Internal marker flagging a synthesized condition node.  Used by
   * `exportModelData` to identify nodes that must be collapsed back onto an
   * edge and excluded from the exported `nodes[]`.  MUST NOT be exported.
   */
  __isConditionNode?: boolean;
  /** Human-readable description shown in property panel. */
  description?: string;
  /** URL to plugin documentation (null when unavailable). */
  documentationUrl?: string | null;
  /** Internal node type string (e.g. "start", "gateway"). Used by layout strategies. */
  nodeType?: string;
  /** Number of child sub-flows (subprocess nodes only). */
  subflowCount?: number;

  // ── Runtime / UI-injected properties ─────────────────────────────────
  /** Whether this node is highlighted during replay. */
  highlighted?: boolean;
  /** Whether this node was added as part of replay visualization. */
  fromReplay?: boolean;
  /** Replay visualization type. */
  replayType?: 'add' | 'ignore';
}

/**
 * Complete data payload carried by every workflow edge in the store.
 *
 * This extends {@link BaseEdgeData} (used by visual edge renderers) with
 * persistent model properties (condition, annotation, etc.) and
 * runtime UI-injected properties (replay state, search, ordering, etc.).
 */
export interface EdgeData extends BaseEdgeData {
  // ── Persistent / model properties ────────────────────────────────────
  /** Condition plugin ID assigned to this edge. */
  condition?: string | null;
  /** Original condition ID from the backend (preserves round-trip stability). */
  conditionId?: string | null;
  /** Human-readable condition label. */
  conditionLabel?: string | null;
  /** Condition plugin configuration key/value pairs. */
  conditionConfiguration?: Record<string, unknown> | null;
  /** Edge annotation text. */
  annotation?: string | null;
  /** Whether the annotation is currently visible on the canvas. */
  isAnnotationVisible?: boolean;
  /** Edge ordering weight (when source node has multiple outgoing edges). */
  order?: number;

  // ── Runtime / UI-injected properties ─────────────────────────────────
  /** Whether this edge is highlighted during replay. */
  highlighted?: boolean;
  /** Whether this edge was added as part of replay visualization. */
  fromReplay?: boolean;
  /** Replay visualization type. */
  replayType?: 'add' | 'ignore';
  /** Whether to show order numbers on edges from the same source. */
  showOrderNumbers?: boolean;
  /** Whether to show annotations on the canvas. */
  showAnnotations?: boolean;
  /** Current search term for highlighting matches. */
  searchTerm?: string;
  /** Whether this edge matches the current search. */
  isHighlighted?: boolean;
  /** Replay data array injected by FlowCanvas. */
  replayData?: Record<string, unknown>[];
  /** Current replay step index. */
  currentReplayStep?: number;
  /** Whether the canvas is in replay mode. */
  isReplayMode?: boolean;

  // ── Callback properties injected by FlowCanvas ──────────────────────
  /** Callback to insert a condition node on a plain edge. */
  onAddCondition?: (edgeId: string, component: StoreComponent) => void;
  /** Callback to toggle annotation visibility. */
  onToggleAnnotation?: () => void;
}

/**
 * A component definition provided by the Drupal backend.
 *
 * Components represent available plugins (events, actions, conditions,
 * gateways, subprocesses) that can be added to the workflow.
 */
export interface StoreComponent {
  type?: string;
  provider?: string;
  label: string;
  plugin: string;
  description?: string;
  documentationUrl?: string | null;
  componentType?: number;
}

/**
 * Data passed to the configuration modal when it opens.
 */
export interface ConfigModalData {
  /** ID of the node being configured. */
  nodeId?: string;
  /** ID of the edge being configured. */
  edgeId?: string;
  /** Additional context-specific properties. */
  [key: string]: unknown;
}

/**
 * Serialized model data exchanged with the Drupal backend.
 */
export interface ModelData {
  id?: string;
  version?: string;
  metadata?: {
    label?: string;
    /**
     * The model's own version. Also lives on the top level of a serialized
     * model, but the store keeps it in the metadata it merges from
     * `modeler_api.metadata`, which is where the dialog reads it.
     */
    version?: string;
    documentation?: string;
    executable?: boolean;
    template?: boolean;
    tags?: string[];
    changelog?: string;
    summary?: string;
    recipes?: string[];
    config_actions?: Array<{ config: string; actions: unknown }>;
    export_config?: string[];
    modules?: string[];
    storage?: string;
  };
  nodes?: StoreNode[];
  edges?: StoreEdge[];
}

// ── Store Node / Edge types ──────────────────────────────────────────────
// These describe the full shape of nodes and edges as held in the Zustand
// store and processed by ReactFlow.  They extend ReactFlow's own Node/Edge
// types, narrowing the generic `data` parameter from `any` to our typed
// interfaces.  This ensures compatibility with ReactFlow functions like
// applyNodeChanges / applyEdgeChanges while providing type safety on `.data`.

import type { Node as RFNode, Edge as RFEdge } from 'reactflow';

/**
 * A workflow node as represented in the store and ReactFlow canvas.
 *
 * Extends ReactFlow's `Node<NodeData>` so it is fully compatible with
 * ReactFlow's utilities while constraining `.data` to {@link NodeData}.
 */
export type StoreNode = RFNode<NodeData>;

/**
 * A workflow edge as represented in the store and ReactFlow canvas.
 *
 * Extends ReactFlow's `Edge<EdgeData>` so it is fully compatible with
 * ReactFlow's utilities while constraining `.data` to {@link EdgeData}.
 */
export type StoreEdge = RFEdge<EdgeData>;

