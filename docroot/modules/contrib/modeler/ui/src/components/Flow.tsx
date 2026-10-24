import React, { Profiler, useCallback, useEffect, useMemo, useState, useRef } from 'react';
import { useReactFlow } from 'reactflow';
import { useGraphStore } from '../store/useGraphStore';
import { useSelectionStore } from '../store/useSelectionStore';
import { useModelStore } from '../store/useModelStore';
import { usePanelStore } from '../store/usePanelStore';
import { useFilterStore } from '../store/useFilterStore';
import { useContextStore } from '../store/useContextStore';
import { useComponentStore } from '../store/useComponentStore';
import type { StoreNode as Node, StoreEdge as Edge, StoreComponent } from '../types/settings';
import { useSimpleReplaySync, ReplayStep } from '../hooks/useSimpleReplaySync';
import { useKeyboardShortcuts } from '../hooks/useKeyboardShortcuts';
import { useClipboard } from '../hooks/useClipboard';
import { useReplayCoordination } from '../hooks/useReplayCoordination';
import { useViewportActions } from '../hooks/useViewportActions';
import { useFlowEventHandlers } from '../hooks/useFlowEventHandlers';
import { useReplayIndicators } from '../hooks/useReplayIndicators';
import { useModalState } from '../hooks/useModalState';
import { useSearch } from '../hooks/useSearch';
import { useModelDataLoader } from '../hooks/useModelDataLoader';
import { useConfiguration } from '../hooks/useConfiguration';
import { useCloseHandler } from '../hooks/useCloseHandler';
import { useMessagesContainer } from '../hooks/useMessagesContainer';
import { useQuickAdd } from '../hooks/useQuickAdd';
import { useNodeEdgeActions } from '../hooks/useNodeEdgeActions';
import { useSelectionSync } from '../hooks/useSelectionSync';
import { useStatusAnnouncer } from '../hooks/useStatusAnnouncer';
import { useTestRunner } from '../hooks/useTestRunner';
import { useExport } from '../hooks/useExport';
import type { ExportFormat } from '../hooks/useExport';
import { useLazyTokens } from '../hooks/useLazyTokens';
import { useViewMode } from '../hooks/useViewMode';
import { useHistory } from '../hooks/useHistory';
import { exportModelData } from '../utils/modelUtils';
import { t } from '../utils/translation';
import { showDrupalMessage } from '../utils/drupalMessage';
import { validateModelConstraints, validateNoAdjacentConditions, validateConditionOutdegree } from '../utils/constraintValidation';
import { expandReplayStep } from '../utils/replayExpansion';
import { findElementForReplayStep, resolveStepForSelectedNode } from '../utils/replayStepUtils';
import { resolvePredictedTokens } from '../utils/predecessorTokens';
import { useReplayLoader, LISTEN_ITEM_INDEX } from '../hooks/useReplayLoader';
import type { ReplayEntry } from '../hooks/useReplayLoader';
import type { Settings, DrupalAjax, ModelConstraints } from '../types/settings';
import { hasPermission } from '../utils/permissions';
import { getReachableNodeIds, findOwningReviewedEventId, findOwningEventId } from '../utils/graphUtils';

// Component imports
import FlowCanvas from './FlowCanvas';
import PropertyPanel from './PropertyPanel';
import Modals from './Modals';
import Toolbar from './Toolbar';
import CanvasToolbar from './CanvasToolbar';
import PanelErrorBoundary from './PanelErrorBoundary';
import PluginPanelSlot from './PluginPanelContainer';
import { onRenderCallback } from '../utils/profiling';
import { usePluginPanels, usePluginWidgets } from '../hooks/usePluginPanels';
import { createPluginApi, setApiReadOnly, setMutationHooks, clearMutationHooks, setViewportHooks, clearViewportHooks } from '../plugins/pluginApi';
import { markReady, markUnready } from '../plugins/pluginRegistry';
import type { ModelerPluginApi } from '../types/pluginApi';
import type { ModelerContext, ModelData } from '../types/settings';

/** Stable empty arrays used as default prop values to avoid creating new
 *  references on every render (which would defeat React.memo). */
const EMPTY_CONTEXTS: ModelerContext[] = [];

/** Stable placeholder while no model data has been loaded yet. A fresh object
 *  literal here would re-seed the metadata dialog on every render. */
const EMPTY_METADATA: ModelData['metadata'] = {};

interface FlowProps {
  settings: Settings;
  drupal: DrupalAjax;
}

function FlowInner({ settings, drupal }: FlowProps) {
  // ReactFlow hooks
  const { getViewport } = useReactFlow();

  const modelerRef = useRef<HTMLDivElement>(null);

  // Messages container hook
  const {
    messagesContainerRef,
    messagesVisible,
    hasMessages,
    handleToggleMessages,
    handleClearMessages
  } = useMessagesContainer();

  // Context selection from store
  const contexts = useContextStore(state => state.contexts);
  const selectedContextId = useContextStore(state => state.selectedContextId);
  const setSelectedContextId = useContextStore(state => state.setSelectedContextId);

  // Store state - single source of truth
  const nodes = useGraphStore(state => state.nodes);
  const edges = useGraphStore(state => state.edges);
  const setNodes = useGraphStore(state => state.setNodes);
  const setEdges = useGraphStore(state => state.setEdges);

  // Refs for pre-save validation — keeps a stable callback identity while
  // always reflecting the current nodes/edges arrays (via ref).
  const validationNodesRef = useRef(nodes);
  validationNodesRef.current = nodes;
  const validationEdgesRef = useRef(edges);
  validationEdgesRef.current = edges;
  const modelConstraints = settings?.modeler_api?.model_constraints;
  const modelConstraintsRef = useRef<ModelConstraints | undefined>(modelConstraints);

  // Global and template tokens are loaded on demand from modeler_api endpoints
  // to avoid blocking the initial page render.  Falls back to inline
  // drupalSettings when the URL is not provided (older modeler_api versions).
  const globalTokens = useLazyTokens(
    settings.modeler_api?.global_tokens_url,
    settings.modeler_api?.global_tokens,
  );
  const templateTokens = useLazyTokens(
    settings.modeler_api?.template_tokens_url,
    settings.modeler_api?.template_tokens,
  );
  modelConstraintsRef.current = modelConstraints;
  const validateBeforeSave = useCallback((): string | null => {
    const currentNodes = validationNodesRef.current;
    const currentEdges = validationEdgesRef.current;
    const placeholders = currentNodes.filter(n => n.type === 'placeholder');
    if (placeholders.length > 0) {
      const count = placeholders.length;
      return t(
        'Cannot save: @count placeholder node(s) still need an action or gateway assigned. Please replace all placeholder nodes before saving.',
        { '@count': String(count) },
      );
    }
    // Structural-invariant safety net: two condition nodes may never be
    // directly connected (issue #3589093).  This is a hard invariant, NOT an
    // owner-configurable constraint, so it must run UNCONDITIONALLY — even
    // when the owner provides no model_constraints.  validateModelConstraints
    // no longer runs this check internally, so it is the single source here.
    const errors = validateNoAdjacentConditions(currentNodes, currentEdges);

    // Structural-invariant safety net: a condition node may have at most one
    // outgoing connection (issue #3589093).  Also a hard invariant, run
    // UNCONDITIONALLY alongside the no-adjacent-conditions check.
    errors.push(...validateConditionOutdegree(currentNodes, currentEdges));

    // Validate model-level cardinality constraints when the owner provides them.
    const constraints = modelConstraintsRef.current;
    if (constraints) {
      errors.push(...validateModelConstraints(currentNodes, currentEdges, constraints));
    }

    if (errors.length > 0) {
      return t('Cannot save: ') + errors.join(' ');
    }
    return null;
  }, []);
  const selectedNode = useSelectionStore(state => state.selectedNode);
  const setSelectedNode = useSelectionStore(state => state.setSelectedNode);
  const selectedEdge = useSelectionStore(state => state.selectedEdge);
  const setSelectedEdge = useSelectionStore(state => state.setSelectedEdge);
  const selectedNodes = useSelectionStore(state => state.selectedNodes);
  const selectedEdges = useSelectionStore(state => state.selectedEdges);
  const setSelectedNodes = useSelectionStore(state => state.setSelectedNodes);
  const setSelectedEdges = useSelectionStore(state => state.setSelectedEdges);
  const modelData = useModelStore(state => state.modelData);
  const setReplayPanelCollapsed = usePanelStore(state => state.setReplayPanelCollapsed);
  const setPropertyPanelCollapsed = usePanelStore(state => state.setPropertyPanelCollapsed);
  const propertyPanelCollapsed = usePanelStore(state => state.propertyPanelCollapsed);
  const panelMode = usePanelStore(state => state.panelMode);
  const setPanelMode = usePanelStore(state => state.setPanelMode);

  // Unified viewport actions — handles all programmatic pan/zoom
  const viewportActions = useViewportActions();

  // Read-only mode: no modifications are allowed when:
  //  1. explicitly set via drupalSettings.modeler_api.readOnly, OR
  //  2. the model is an existing template and the user lacks the
  //     "edit template" permission, OR
  //  3. running in standalone viewer mode (no Drupal backend).
  const isStandalone = !!settings.modeler?.standalone;

  // View mode: fullscreen (default Drupal) or restored (default standalone)
  const {
    viewMode,
    toggleViewMode,
    startDrag,
    startResize,
    isDragging,
    isResizing,
  } = useViewMode({ isStandalone, modelerRef });

  const isTemplateEditBlocked =
    !settings.modeler_api?.isNew &&
    !!settings.modeler_api?.metadata?.template &&
    !hasPermission(settings, 'edit template'); // eslint-disable-line i18n/no-untranslated-strings
  const isReadOnly = !!settings.modeler_api?.readOnly || isTemplateEditBlocked || isStandalone;

  // ── Plugin panel integration ─────────────────────────────────────────
  // Create the public API once per mount and update the read-only flag.
  const pluginApiRef = useRef<ModelerPluginApi | null>(null);
  if (!pluginApiRef.current) {
    pluginApiRef.current = createPluginApi();
  }
  const pluginApi = pluginApiRef.current;

  // Keep the API's read-only state in sync.
  useEffect(() => {
    setApiReadOnly(isReadOnly);
  }, [isReadOnly]);

  // Signal ready/unready to the plugin registry.
  useEffect(() => {
    // Expose the API on the global WorkflowModeler object if available.
    if (window.WorkflowModeler) {
      window.WorkflowModeler.api = pluginApi;
    }
    markReady();
    return () => {
      markUnready();
      clearMutationHooks();
      clearViewportHooks();
      if (window.WorkflowModeler) {
        window.WorkflowModeler.api = null;
      }
    };
  }, [pluginApi]);

  // Retrieve registered plugin panels for each slot position.
  const leftPluginPanels = usePluginPanels('left');
  const rightPluginPanels = usePluginPanels('right');
  const bottomPluginPanels = usePluginPanels('bottom');

  // Retrieve registered toolbar widgets for each toolbar position.
  const leftPluginWidgets = usePluginWidgets('left');
  const rightPluginWidgets = usePluginWidgets('right');

  // Local state for UI interactions
  const [isReplayMode, setIsReplayMode] = useState(false);
  const [currentReplayStep, setCurrentReplayStep] = useState(-1);
  // Mirror of currentReplayStep for stale-free reads in session snapshot logic.
  const currentReplayStepRef = useRef(currentReplayStep);
  currentReplayStepRef.current = currentReplayStep;
  const [showEdgeOrderNumbers] = useState(true);
  const [showAllAnnotations] = useState(false);
  const [isShiftPressed, setIsShiftPressed] = useState(false);
  const [isCtrlPressed, setIsCtrlPressed] = useState(false);
  const [isAltPressed, setIsAltPressed] = useState(false);
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);

  // History (undo/redo) - disabled in read-only mode
  const { saveHistory, undo, redo, canUndo, canRedo } = useHistory({ enabled: !isReadOnly, setHasUnsavedChanges });

  // Canvas is locked when in read-only mode
  const isLocked = isReadOnly;
  const [isEventPopupOpen, setIsEventPopupOpen] = useState(false);

  // ── Per-event review sessions ──────────────────────────────────────────────
  // Each reviewed event keeps its own session. The "live" session view (the
  // entries / selected entry / step that feed the replay sync hooks below) is
  // held in the three pieces of state that follow and ALWAYS reflects the
  // ACTIVE reviewed event. When the user switches to a different event, the
  // current live view is snapshotted into `sessionsRef` and the target event's
  // saved snapshot (if any) is restored.
  const [loadedReplayEntries, setLoadedReplayEntries] = useState<ReplayEntry[]>([]);
  const [selectedReplayEntryIndex, setSelectedReplayEntryIndex] = useState(-1);
  // The active event's backend empty/warning message (shown in the review body
  // only after the user cancels listening with no data — A47). Part of the
  // per-event ReviewSession snapshot so each event keeps its own.
  const [activeBackendMessage, setActiveBackendMessage] = useState<string | null>(null);
  // Set when the user cancels the listener while history is still in flight
  // (A48): the cancel rule (newest entry vs backend message) is then applied
  // once the loader resolves. Keyed to the event the cancel was issued for.
  const pendingCancelResolveRef = useRef<string | null>(null);

  /** A saved (inactive) per-event session snapshot. */
  interface ReviewSession {
    replayEntries: ReplayEntry[];
    /**
     * The selected dropdown item: LISTEN_ITEM_INDEX (-2) = the persistent
     * "listen" item, -1 = no entry (empty/backend-message), 0..n-1 = a data
     * entry. Restored as-is on switch (without re-arming the listener).
     */
    selectedEntryIndex: number;
    currentStep: number;
    /** The backend empty/warning message for this event (A47/A49), or null. */
    backendMessage: string | null;
  }
  // Saved snapshots for events that are NOT the active one. The active event's
  // session lives in the live state above (loadedReplayEntries / index / step).
  const sessionsRef = useRef<Map<string, ReviewSession>>(new Map());
  // Which event the Replay view is currently showing (and whose session the
  // live state mirrors). `null` = no event is being reviewed.
  const [activeReviewedComponentId, setActiveReviewedComponentId] = useState<string | null>(null);
  // TRUE iff the currently-active review session was last (re)activated by the
  // [-token PICKER (loadStepDataForPicker) rather than an explicit Review action.
  // While true, PropertyPanel must keep showing Properties (not switch to the
  // Replay view) so the user stays in the field they are editing; the step data
  // still flows into the picker. Cleared the moment an explicit Review action
  // (enterReviewForNode / resumeReviewForNode) claims the session, or when the
  // session is torn down (handleCancelReview to no-session).
  const [pickerInitiatedSession, setPickerInitiatedSession] = useState(false);
  // Render-time mirror of the events that currently HAVE a review session (the
  // active event plus every event with a saved snapshot). `sessionsRef` is a
  // ref (not reactive), so this state drives per-node button visibility and the
  // owning-event lookup at render time. Updated wherever a session is created.
  const [sessionEventIds, setSessionEventIds] = useState<string[]>([]);
  const registerSessionEvent = useCallback((eventId: string) => {
    setSessionEventIds(prev => (prev.includes(eventId) ? prev : [...prev, eventId]));
  }, []);
  // The event id the running live listener is bound to (one listener at a time).
  const listeningComponentIdRef = useRef<string | null>(null);
  // The event id the most recent load/test REQUEST was started for, so a late
  // response is routed into the correct event's session even after navigation.
  const requestTargetComponentIdRef = useRef<string | null>(null);
  // Guards the one-time "open with saved replayData → auto-enter review" effect
  // so it fires once per loaded model (and never re-fires after the user leaves
  // review or the data is cleared).
  const autoReviewAppliedRef = useRef(false);

  // Mirror the live session view in refs so snapshot/switch callbacks read the
  // latest values without stale closures.
  const loadedReplayEntriesRef = useRef(loadedReplayEntries);
  loadedReplayEntriesRef.current = loadedReplayEntries;
  const selectedReplayEntryIndexRef = useRef(selectedReplayEntryIndex);
  selectedReplayEntryIndexRef.current = selectedReplayEntryIndex;
  const activeBackendMessageRef = useRef(activeBackendMessage);
  activeBackendMessageRef.current = activeBackendMessage;
  const activeReviewedComponentIdRef = useRef(activeReviewedComponentId);
  activeReviewedComponentIdRef.current = activeReviewedComponentId;

  // Status announcer for screen readers (aria-live region)
  const { message: statusMessage, announce } = useStatusAnnouncer();

  // Search functionality
  const {
    searchTerm,
    highlightedSearchResult,
    onSearchHighlight,
    onSearchFocus,
    clearSearch,
  } = useSearch({ viewportActions });

  // Modal state management
  const {
    showMetadataModal,
    showConfirmDialog,
    confirmDialogTitle,
    confirmDialogMessage,
    confirmDialogType,
    confirmDialogLoading,
    confirmDialogPrimaryLabel,
    confirmDialogSecondaryLabel,
    confirmDialogCancelLabel,
    confirmDialogPrimaryVariant,
    onMetadataSubmit,
    showConfirmationDialog,
    handleConfirmDialog,
    handleCancelDialog,
    handleCloseWithoutSave,
    openMetadataModal,
    closeMetadataModal
  } = useModalState({ setHasUnsavedChanges });

  // Close handler
  const {
    handleClose,
    handleSaveComplete,
    saveButtonRef
  } = useCloseHandler({
    settings,
    hasUnsavedChanges,
    showConfirmationDialog
  });

  // Handle initial state based on whether this is a new model
  useEffect(() => {
    const isNew = settings?.modeler_api?.isNew;
    
    if (isNew) {
      const timer = setTimeout(() => {
        openMetadataModal();
      }, 100);
      return () => clearTimeout(timer);
    }
  }, [settings?.modeler_api?.isNew, openMetadataModal]);

  // collapsePanels: start all panels collapsed, then auto-expand/collapse
  // the property panel based on whether something is selected.
  const collapsePanels = !!settings.modeler?.collapsePanels;

  // Set initial collapse state when collapsePanels is requested
  useEffect(() => {
    if (collapsePanels) {
      setPropertyPanelCollapsed(true);
      setReplayPanelCollapsed(true);
    }
  // Run once on mount — intentionally not reacting to further changes.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Auto-expand property panel when a node or edge is selected,
  // auto-collapse when selection is cleared AND replay is not active.
  // During replay, intermediate null-selection states (between steps) should
  // not collapse the panel — it stays open until replay is exited.
  useEffect(() => {
    if (!collapsePanels) return;
    const hasSelection = !!selectedNode || !!selectedEdge;
    if (hasSelection) {
      setPropertyPanelCollapsed(false);
    } else if (!isReplayMode) {
      setPropertyPanelCollapsed(true);
    }
  }, [collapsePanels, selectedNode, selectedEdge, isReplayMode, setPropertyPanelCollapsed]);

  // Wrapper for closing metadata modal - opens event popup for new models
  const handleCloseMetadataModal = useCallback(() => {
    closeMetadataModal();
    if (settings?.modeler_api?.isNew) {
      setTimeout(() => {
        setIsEventPopupOpen(true);
      }, 150);
    }
  }, [closeMetadataModal, settings?.modeler_api?.isNew]);

  // Model data loading and management
  const { replayData: initialReplayData } = useModelDataLoader({ settings, viewportActions });

  // Active replay data: use selected entry's data if available, otherwise use initial data.
  // Memoized to keep a stable reference — many hooks depend on replayData identity.
  const replayData: ReplayStep[] = useMemo(() => {
    if (
      selectedReplayEntryIndex >= 0 &&
      selectedReplayEntryIndex < loadedReplayEntries.length
    ) {
      return (loadedReplayEntries[selectedReplayEntryIndex].history || []) as ReplayStep[];
    }
    return initialReplayData;
  }, [selectedReplayEntryIndex, loadedReplayEntries, initialReplayData]);

  // Selected-node-aware step-data resolution (issue #3577207). ECA delivers
  // `replayData` in its compact, marker-bearing form; expansion always happens
  // on a non-mutating deep copy so the stored `replayData` (and therefore the
  // JSON export) keeps the markers intact. The result carries a `predicted`
  // flag so the panel/picker can render the predicted badge.
  //
  // Resolution order (spec §3.2):
  //   (a) the selected node IS replay-covered → use ITS own step data
  //       (predicted=false); selecting a covered node always shows its data.
  //   (b) else if predecessor tokens resolve (a covered predecessor exists) →
  //       use those (predicted=true), so a freshly-added successor inherits the
  //       predecessor's runtime context without re-running replay.
  //   (c) else fall back to the legacy current-step data (e.g. while stepping
  //       through replay), predicted=false.
  // Recomputed when the selection, graph, replay data, or current step changes.
  //
  // Within (a), WHICH of the node's steps is shown follows resolveStepForSelectedNode():
  // the current step wins when it is the selected node's own step, otherwise the
  // node's FIRST covering step is used. In a model that LOOPS the same node
  // executes once per iteration, and stepping selects it every time, so
  // anchoring on the first occurrence made every iteration display iteration
  // 1's token data (issue #3589126). Manual canvas clicks are unaffected:
  // selectReplayFromCanvas() syncs the current step to the node's first
  // occurrence, so both paths agree on that step.
  //
  // `nodes` is passed so that a selected condition NODE resolves to the
  // successor step that evaluated it (issue #3589108). Condition nodes are
  // never a step's `id`, so without this they always fell through to the
  // predicted-token branch and were mislabeled "Predicted".
  const expandedStepData = useMemo<{ data: Record<string, unknown> | null; predicted: boolean }>(() => {
    const selId = selectedNode?.id;
    if (selId) {
      const ownIdx = resolveStepForSelectedNode(replayData, edges, selId, nodes, currentReplayStep);
      if (ownIdx >= 0) {
        return { data: expandReplayStep(replayData, ownIdx), predicted: false };
      }
      const predicted = resolvePredictedTokens({ nodeId: selId, nodes, edges, replayData });
      if (Object.keys(predicted).length > 0) {
        return { data: predicted, predicted: true };
      }
    }
    return { data: expandReplayStep(replayData, currentReplayStep), predicted: false };
  }, [replayData, currentReplayStep, selectedNode, nodes, edges]);
  
  // Configuration management
  const {
    onConfigurationChange,
    onNodeUpdate,
    onEdgeUpdate,
    onReconnectEdge,
    handleAutoLayout
  } = useConfiguration({ setHasUnsavedChanges, saveHistory });

  // Register mutation hooks so that plugin API mutations integrate with
  // undo/redo history and unsaved-changes tracking.
  useEffect(() => {
    setMutationHooks({
      saveHistory: () => saveHistory(),
      markUnsaved: () => setHasUnsavedChanges(true),
      autoLayout: () => handleAutoLayout(),
    });
    setViewportHooks({
      focusNode: (nodeId: string) => viewportActions.focusNode(nodeId),
      fitView: () => viewportActions.fitToNodes(),
    });
  }, [saveHistory, setHasUnsavedChanges, handleAutoLayout, viewportActions]);

  // Quick add functionality
  const { addSuccessorNode, addConditionWithPlaceholder } = useQuickAdd({ setHasUnsavedChanges, saveHistory, viewportActions });

  // Node and edge action handlers (add condition, insert action on edge, add event, condition edge insert)
  const {
    handleAddCondition,
    handleAddEvent,
    handleAddActionOnEdge,
  } = useNodeEdgeActions({ setHasUnsavedChanges, saveHistory, viewportActions });

  // Combined quick-add handler: routes condition selections to the
  // condition-with-placeholder flow, everything else to addSuccessorNode.
  const handleQuickAdd = useCallback((component: StoreComponent, sourceNodeId: string) => {
    if (component.type === 'link') {
      addConditionWithPlaceholder(component, sourceNodeId);
    } else {
      addSuccessorNode(component, sourceNodeId);
    }
  }, [addSuccessorNode, addConditionWithPlaceholder]);

  // Replace a placeholder node with a real action/gateway component.
  const handleReplacePlaceholder = useCallback((nodeId: string, component: StoreComponent) => {
    if (saveHistory) saveHistory();
    const nodeType = component.type === 'gateway' ? 'gateway' : 'element';
    const label = component.label || component.plugin?.split('.').pop() || t('New Node');

    setNodes(prev => prev.map(n => {
      if (n.id !== nodeId) return n;
      return {
        ...n,
        type: nodeType,
        data: {
          ...n.data,
          plugin: component.plugin,
          label,
          componentType: component.componentType,
          description: component.description,
          documentationUrl: component.documentationUrl,
        },
      };
    }));
    setHasUnsavedChanges(true);
  }, [setNodes, setHasUnsavedChanges, saveHistory]);

  // Sync selected objects when nodes/edges change
  useSelectionSync();

  // Start flow filter from store
  const visibleStartNodeIds = useFilterStore(state => state.visibleStartNodeIds);
  const setVisibleStartNodeIds = useFilterStore(state => state.setVisibleStartNodeIds);

  // Track previous start node IDs so we can detect additions/removals.
  const prevStartNodeIdsRef = useRef<Set<string>>(new Set());

  // Keep the flow filter in sync when start nodes are added or removed.
  //  - Removed start nodes are pruned from the selection (revert to "All" if none remain).
  //  - Newly added start nodes are auto-included so they are immediately visible.
  //
  // The ref starts empty.  On the first run (initial model load) we only
  // populate it — we must NOT treat every loaded node as "newly added",
  // because that would immediately override a filter that was set during
  // model loading (e.g. via selectComponentId).
  useEffect(() => {
    const currentStartIds = new Set(
      nodes.filter(n => n.type === 'start').map(n => n.id),
    );
    const prevIds = prevStartNodeIdsRef.current;

    // First meaningful population — just record and bail.
    if (prevIds.size === 0 && currentStartIds.size > 0) {
      prevStartNodeIdsRef.current = currentStartIds;
      return;
    }

    prevStartNodeIdsRef.current = currentStartIds;

    if (visibleStartNodeIds === null) return;

    // Detect start nodes that were just added to the canvas.
    const added: string[] = [];
    currentStartIds.forEach(id => {
      if (!prevIds.has(id)) added.push(id);
    });

    // Remove IDs that no longer exist on the canvas.
    let next = visibleStartNodeIds.filter(id => currentStartIds.has(id));

    // Auto-include any newly added start nodes in the selection.
    for (const id of added) {
      if (!next.includes(id)) {
        next = [...next, id];
      }
    }

    // If every start node is now selected (or none remain), revert to "All".
    if (next.length === 0 || next.length === currentStartIds.size) {
      if (visibleStartNodeIds !== null) {
        setVisibleStartNodeIds(null);
      }
    } else if (
      next.length !== visibleStartNodeIds.length ||
      next.some((id, i) => id !== visibleStartNodeIds[i])
    ) {
      setVisibleStartNodeIds(next);
    }
  }, [nodes, visibleStartNodeIds, setVisibleStartNodeIds]);

  // Compute the set of visible node IDs based on the start flow filter.
  // Uses BFS from each visible start node to discover all reachable nodes.
  const visibleNodeIds = useMemo<Set<string> | null>(() => {
    if (visibleStartNodeIds === null) return null; // All visible

    const reachable = new Set<string>();
    const adjacency = new Map<string, string[]>();

    // Build adjacency from edges
    for (const edge of edges) {
      if (!adjacency.has(edge.source)) adjacency.set(edge.source, []);
      adjacency.get(edge.source)!.push(edge.target);
    }

    // BFS from each selected start node
    const queue: string[] = [...visibleStartNodeIds];
    for (const id of queue) reachable.add(id);

    while (queue.length > 0) {
      const current = queue.shift()!;
      const neighbors = adjacency.get(current);
      if (neighbors) {
        for (const neighbor of neighbors) {
          if (!reachable.has(neighbor)) {
            reachable.add(neighbor);
            queue.push(neighbor);
          }
        }
      }
    }

    return reachable;
  }, [visibleStartNodeIds, edges]);

  // Apply the flow filter: set `hidden` on nodes and edges not in the visible set.
  const filteredNodes = useMemo(() => {
    if (visibleNodeIds === null) {
      // Remove any stale hidden flags
      return nodes.map(n => n.hidden ? { ...n, hidden: false } : n);
    }
    return nodes.map(n => ({
      ...n,
      hidden: !visibleNodeIds.has(n.id),
    }));
  }, [nodes, visibleNodeIds]);

  const filteredEdges = useMemo(() => {
    if (visibleNodeIds === null) {
      return edges.map(e => e.hidden ? { ...e, hidden: false } : e);
    }
    return edges.map(e => ({
      ...e,
      hidden: !visibleNodeIds.has(e.source) || !visibleNodeIds.has(e.target),
    }));
  }, [edges, visibleNodeIds]);

  // Replay indicators
  const { replayIndicators } = useReplayIndicators({
    isReplayMode,
    currentReplayStep,
    replayData,
    edges,
    nodes,
  });

  // Initialize custom hooks
  const { isSyncing, isReplaySyncingRef, handleCanvasNodeClick, handleCanvasEdgeClick, handleReplayStepSelect: baseHandleReplayStepSelect } = useSimpleReplaySync({
    replayData,
    nodes,
    edges,
    setNodes,
    setEdges,
    setSelectedNode,
    setSelectedEdge,
    currentStep: currentReplayStep,
    setCurrentStep: setCurrentReplayStep,
  });

  // Wrap handleReplayStepSelect to also activate replay mode
  const handleReplayStepSelect = useCallback((stepIndex: number) => {
    baseHandleReplayStepSelect(stepIndex);
    if (stepIndex >= 0 && !isReplayMode) {
      setIsReplayMode(true);
    }
  }, [baseHandleReplayStepSelect, isReplayMode, setIsReplayMode]);

  const { handleCopy, handlePaste, canCopy, canPaste } = useClipboard({
    selectedNode,
    selectedEdge,
    selectedNodeIds: selectedNodes,
    selectedEdgeIds: selectedEdges,
    nodes,
    edges,
    setNodes,
    setEdges,
    setSelectedNode,
    setSelectedEdge,
    setSelectedNodes,
    setSelectedEdges,
    setHasUnsavedChanges,
    announce,
    saveHistory,
  });

  const canDeleteSelected = useCallback(
    (): boolean => selectedNode !== null || selectedEdge !== null,
    [selectedNode, selectedEdge],
  );

  const { autoSyncToReplay, toggleReplayMode, hasReplayData } = useReplayCoordination({
    replayData,
    nodes,
    edges,
    isReplayMode,
    currentReplayStep,
    setIsReplayMode,
    setCurrentReplayStep,
    selectedNode,
    selectedEdge,
    isSyncing,
    handleReplayStepSelect,
  });

  // Derive the selected start node ID (or auto-detect if there's only one start node)
  const selectedStartNodeId = useMemo(() => {
    if (selectedNode?.type === 'start') return selectedNode.id;
    const startNodes = nodes.filter(n => n.type === 'start');
    if (startNodes.length === 1) return startNodes[0].id;
    return null;
  }, [selectedNode, nodes]);

  // ALL event/start node ids in the model (session-agnostic). Used to resolve
  // the @-picker's owning event structurally so Step-data works BEFORE any
  // review session exists (Feature J caveat fix).
  const allEventIds = useMemo(
    () => nodes.filter(n => n.type === 'start').map(n => n.id),
    [nodes],
  );

  const isNewModel = !!settings.modeler_api?.isNew;

  // Granular permission checks (string keys are permission identifiers, not UI text)
  /* eslint-disable i18n/no-untranslated-strings */
  const canEditMetadata = hasPermission(settings, 'edit metadata');
  const canSwitchContext = hasPermission(settings, 'switch context');
  const canEditTemplate = hasPermission(settings, 'edit template');
  const canCreateTemplate = hasPermission(settings, 'create template');
  const canTest = hasPermission(settings, 'test');
  const canReplay = hasPermission(settings, 'replay');
  /* eslint-enable i18n/no-untranslated-strings */

  // Note: "edit template" restriction is now enforced via isReadOnly
  // (computed earlier) — when the model is an existing template and the
  // user lacks the permission, the entire modeler enters read-only mode.

  // Testing implies replay — hide the test button when replay is denied.
  // In standalone mode test/replay URLs are not available but data may
  // have been embedded in the JSON export.
  const hasTestUrl = !isStandalone && !isNewModel && !!settings.modeler_api?.test_url && canTest && canReplay;
  const hasReplayUrl = !isStandalone && !isNewModel && !!settings.modeler_api?.replay_url && canReplay;
  const hasAnyReplayCapability = hasTestUrl || hasReplayUrl || initialReplayData.length > 0;

  // Event handlers from extracted hook
  const {
    onNodesChange,
    onEdgesChange,
    onSelectionChange,
    onNodeClick,
    onEdgeClick,
    onDeleteNode,
    onDeleteEdge,
    handleDeleteSelected,
    onConnect,
    onConnectStart,
    onConnectEnd,
    onPaneClick,
    onSelectionStart,
    onNodeDragStart,
    onNodeDragStop,
  } = useFlowEventHandlers({
    handleCanvasNodeClick,
    handleCanvasEdgeClick,
    setHasUnsavedChanges,
    isSyncing,
    isReplaySyncingRef,
    hasReplayData,
    isReplayMode,
    currentReplayStep,
    autoSyncToReplay,
    announce,
    saveHistory,
    modelConstraints
  });

  // Handle delete with confirmation for multi-selection panel
  const handleDeleteSelectedWithConfirm = useCallback(() => {
    const totalItems = selectedNodes.length + selectedEdges.length;
    if (totalItems === 0) return;

    showConfirmationDialog(
      t('Delete Selected Items'),
      t('Are you sure you want to delete @count selected items? This action cannot be undone.', { '@count': String(totalItems) }),
      'danger',
      () => { handleDeleteSelected(); },
      undefined,
      {
        primaryLabel: t('Delete'),
        secondaryLabel: false,
        cancelLabel: t('Cancel'),
        primaryVariant: 'danger',
      }
    );
  }, [selectedNodes.length, selectedEdges.length, showConfirmationDialog, handleDeleteSelected]);

  // Save the current live session view into the snapshot map under the given
  // event id (so it can be restored later). Called before switching events.
  const snapshotActiveSession = useCallback((eventId: string | null) => {
    if (!eventId) return;
    sessionsRef.current.set(eventId, {
      replayEntries: loadedReplayEntriesRef.current,
      selectedEntryIndex: selectedReplayEntryIndexRef.current,
      currentStep: currentReplayStepRef.current,
      backendMessage: activeBackendMessageRef.current,
    });
  }, []);

  // Make `eventId` the active reviewed event: snapshot the currently-active
  // event's session, then restore `eventId`'s saved snapshot into the live
  // state (or reset to empty if it has none yet). Does NOT touch the listener
  // or panel mode — callers handle those.
  const switchActiveSession = useCallback((eventId: string) => {
    const prev = activeReviewedComponentIdRef.current;
    if (prev === eventId) return;
    if (prev) snapshotActiveSession(prev);
    const saved = sessionsRef.current.get(eventId);
    setActiveReviewedComponentId(eventId);
    activeReviewedComponentIdRef.current = eventId;
    // This event now has a session (becomes the active one).
    registerSessionEvent(eventId);
    if (saved) {
      setLoadedReplayEntries(saved.replayEntries);
      setSelectedReplayEntryIndex(saved.selectedEntryIndex);
      setCurrentReplayStep(saved.currentStep);
      setActiveBackendMessage(saved.backendMessage);
      // A45/A51: restoring a snapshot whose selection is the listen item must
      // NOT re-arm the listener — switchActiveSession never starts a listener;
      // only enterReviewForNode's first start and explicit listen selection do.
    } else {
      setLoadedReplayEntries([]);
      setSelectedReplayEntryIndex(-1);
      setCurrentReplayStep(-1);
      setActiveBackendMessage(null);
    }
  }, [snapshotActiveSession, registerSessionEvent]);

  // Handle dynamically loaded replay entries from PropertyPanel.
  // `targetEventId` is the event the loaded data belongs to (defaults to the
  // request target); results for a non-active event are written to its saved
  // session snapshot rather than the live view.
  const handleReplayEntriesLoaded = useCallback((entries: ReplayEntry[]) => {
    // Route the result to the event the request was started for. If that event
    // is no longer the active one (user navigated away), write to its saved
    // session snapshot instead of the live view.
    const targetEventId = requestTargetComponentIdRef.current;
    const activeId = activeReviewedComponentIdRef.current;

    if (targetEventId && activeId && targetEventId !== activeId) {
      const prev = sessionsRef.current.get(targetEventId);
      // For a non-active event, keep a listen-item selection sticky while its
      // listener is still bound (A39); otherwise select newest / none.
      const prevSel = prev ? prev.selectedEntryIndex : -1;
      const stillListening = listeningComponentIdRef.current === targetEventId;
      const keepListen = prevSel === LISTEN_ITEM_INDEX && stillListening;
      sessionsRef.current.set(targetEventId, {
        replayEntries: entries,
        selectedEntryIndex: keepListen ? LISTEN_ITEM_INDEX : (entries.length > 0 ? 0 : -1),
        // Preserve any existing step for that event; default to -1.
        currentStep: prev ? prev.currentStep : -1,
        backendMessage: prev ? prev.backendMessage : null,
      });
      // A late response created/updated this event's saved session.
      registerSessionEvent(targetEventId);
      announce(t('@count replay entries loaded', { '@count': String(entries.length) }));
      return;
    }

    // Otherwise the result is for the ACTIVE event — update the live view.
    setLoadedReplayEntries(entries);

    // A48: a cancel was issued for this event while history was in flight.
    // Apply the cancel rule now that the loader has resolved: select the newest
    // entry if any (A46) else show the backend message with no entry (A47).
    if (targetEventId && pendingCancelResolveRef.current === targetEventId) {
      pendingCancelResolveRef.current = null;
      if (entries.length > 0) {
        setSelectedReplayEntryIndex(0);
        const firstEntryData = (entries[0].history || []) as ReplayStep[];
        if (firstEntryData.length > 0) {
          setIsReplayMode(true);
          setCurrentReplayStep(-1);
        }
        setReplayPanelCollapsed(false);
      } else {
        setSelectedReplayEntryIndex(-1);
      }
      announce(t('@count replay entries loaded', { '@count': String(entries.length) }));
      return;
    }

    // A39: parallel history arrived while still listening (listen item selected
    // and this event's listener still active) — store the entries but KEEP the
    // listen item selected (do not jump to the newest entry).
    const listenSelected = selectedReplayEntryIndexRef.current === LISTEN_ITEM_INDEX;
    const stillListening = listeningComponentIdRef.current === activeId;
    if (listenSelected && stillListening) {
      if (entries.length > 0) {
        setReplayPanelCollapsed(false);
        announce(t('@count replay entries loaded', { '@count': String(entries.length) }));
      }
      return;
    }

    // The user already has a REAL dataset selected (e.g. they picked one to
    // cancel listening — Caveat 1) and we are NOT listening: a late history load
    // resolving must NOT yank the selection to newest. Refresh entries but keep
    // their chosen index.
    const userPickedIndex = selectedReplayEntryIndexRef.current;
    if (!stillListening && userPickedIndex >= 0 && entries.length > 0) {
      setReplayPanelCollapsed(false);
      announce(t('@count replay entries loaded', { '@count': String(entries.length) }));
      return;
    }

    // Normal load (e.g. resume / not listening): select newest entry or none.
    if (entries.length > 0) {
      setSelectedReplayEntryIndex(0);
      const firstEntryData = (entries[0].history || []) as ReplayStep[];
      if (firstEntryData.length > 0) {
        setIsReplayMode(true);
        setCurrentReplayStep(-1);
      }
      setReplayPanelCollapsed(false);
      announce(t('@count replay entries loaded', { '@count': String(entries.length) }));
    } else {
      setSelectedReplayEntryIndex(-1);
    }
  }, [setIsReplayMode, setCurrentReplayStep, announce, setReplayPanelCollapsed, registerSessionEvent]);

  // Receive the backend empty/warning message for a load and store it on the
  // event the request was started for (active live state or its snapshot), so
  // each event surfaces its own message in the empty body after cancel (A47/A49).
  const handleEmptyMessageLoaded = useCallback((message: string | null) => {
    const targetEventId = requestTargetComponentIdRef.current;
    const activeId = activeReviewedComponentIdRef.current;
    if (targetEventId && activeId && targetEventId !== activeId) {
      const prev = sessionsRef.current.get(targetEventId);
      if (prev) {
        sessionsRef.current.set(targetEventId, { ...prev, backendMessage: message });
      }
      return;
    }
    setActiveBackendMessage(message);
  }, []);

  // Flow-level replay loader. Used when ENTERING Review mode to load historical
  // execution data for the reviewed event node (in parallel with starting the
  // live listener). PropertyPanel no longer owns the load action — review entry
  // is orchestrated here so listen + history-load happen together (Q4/Q5).
  const { loadReplayData, loading: isReplayLoading } = useReplayLoader({
    settings,
    onEntriesLoaded: handleReplayEntriesLoaded,
    onEmptyMessage: handleEmptyMessageLoaded,
  });

  // Handle replay data received from the test runner. Attribute it to the event
  // the test was STARTED for (request target), not the currently-selected node,
  // so late responses land in the right event's session.
  const handleTestReplayDataReceived = useCallback((data: any[]) => {
    const targetEventId = requestTargetComponentIdRef.current || selectedStartNodeId || '';
    // The listener for this event is now finished (one-listener bookkeeping).
    if (listeningComponentIdRef.current === targetEventId) {
      listeningComponentIdRef.current = null;
    }
    const entry: ReplayEntry = {
      model_id: settings.modeler?.modelId || '',
      component_id: targetEventId,
      history: data,
      timestamp: new Date().toISOString(),
      user: 'test',
      ip: '',
      url: '',
    };
    // A40/A41/A42: the live result becomes a NEW data entry at the top
    // (newest-first), preserving any history already loaded in parallel. With
    // the listener now cleared above, handleReplayEntriesLoaded selects this
    // new entry (index 0) and the listen item remains in the dropdown.
    const targetIsActive = targetEventId === activeReviewedComponentIdRef.current;
    const existing = targetIsActive
      ? loadedReplayEntriesRef.current
      : (sessionsRef.current.get(targetEventId)?.replayEntries ?? []);
    handleReplayEntriesLoaded([entry, ...existing]);
    announce(t('Test completed — replay data received'));
  }, [settings.modeler?.modelId, selectedStartNodeId, handleReplayEntriesLoaded, announce]);

  // Test runner hook
  const {
    isTestRunning,
    isTestInitiating,
    testError,
    startTest,
    cancelTest,
    notifySaveComplete,
  } = useTestRunner({
    settings,
    onReplayDataReceived: handleTestReplayDataReceived,
    validateBeforeSave,
  });

  // Handle selecting a replay entry from the dropdown (Review panel AND the
  // [-token picker route here — keep the cancel logic in this ONE handler so
  // both get it). Selecting a REAL dataset (index >= 0) while the live listener
  // is armed for the active reviewed event CANCELS the listener first, so the
  // user's chosen dataset sticks and no further incoming data overwrites it.
  // The "Listen…" item (LISTEN_ITEM_INDEX) is the re-arm path and is handled by
  // handleSelectListenItem — never cancelled here. (Declared AFTER useTestRunner
  // so `cancelTest` is in scope.)
  const handleSelectReplayEntry = useCallback((index: number) => {
    if (
      index >= 0 &&
      listeningComponentIdRef.current !== null &&
      listeningComponentIdRef.current === activeReviewedComponentIdRef.current
    ) {
      cancelTest();
      listeningComponentIdRef.current = null;
      // The user explicitly picked a dataset — make sure a previously-deferred
      // cancel (A48) cannot later re-select the newest entry over their choice.
      pendingCancelResolveRef.current = null;
    }
    setSelectedReplayEntryIndex(index);
    // Reset replay step when switching entries
    setCurrentReplayStep(-1);
    if (index >= 0 && index < loadedReplayEntries.length) {
      const entryData = (loadedReplayEntries[index].history || []) as ReplayStep[];
      if (entryData.length > 0) {
        setIsReplayMode(true);
      }
    }
  }, [loadedReplayEntries, cancelTest, setCurrentReplayStep, setIsReplayMode]);

  // Components from store (needed for export to derive required modules)
  const components = useComponentStore(state => state.components);

  // Export functionality
  const [showExportDialog, setShowExportDialog] = useState(false);
  const [isExporting, setIsExporting] = useState(false);
  const pendingExportAfterSaveRef = useRef(false);

  // A replay session is "active" once an event is being reviewed and its
  // (active) session has loaded data OR a running/initiating live listener.
  // While active, the user can freely switch between Properties and Replay
  // views without losing state, and the "Review flow" (go-to-replay) control is
  // offered from ANY selected node.
  // A50: stays active while listening with zero data (listen item selected, or
  // the test runner reports running/initiating), while data is shown, and while
  // an empty body is showing the backend message — so the dropdown + body keep
  // rendering on first entry before any data arrives.
  const replaySessionActive =
    activeReviewedComponentId !== null &&
    (replayData.length > 0 ||
      loadedReplayEntries.length > 0 ||
      isTestRunning ||
      isTestInitiating ||
      selectedReplayEntryIndex === LISTEN_ITEM_INDEX ||
      activeBackendMessage !== null);

  // The session-event that "owns" the currently-selected node (i.e. whose flow
  // reaches it), among events that currently HAVE a session. Active-preferred so
  // a shared node keeps the active session. null when the selected node belongs
  // to no session-flow. Drives the per-node "Review flow" button visibility and
  // the non-event "Review flow" click resolution (so it resumes the OWNING
  // event rather than a stale active one).
  const reviewableEventId = useMemo(
    () => findOwningReviewedEventId(selectedNode?.id, sessionEventIds, activeReviewedComponentId, edges),
    [selectedNode?.id, sessionEventIds, activeReviewedComponentId, edges],
  );

  // The event that owns the selected node resolved STRUCTURALLY (session-
  // agnostic) — used ONLY to gate/drive the @-picker's Step-data category so it
  // works BEFORE any session exists. Prefers the active reviewed event (or the
  // single selected start node) for shared nodes; null when no event reaches the
  // node. Distinct from `reviewableEventId` (which stays session-gated and keeps
  // driving the Review-flow BUTTON visibility unchanged).
  const pickerOwningEventId = useMemo(
    () => findOwningEventId(selectedNode?.id, allEventIds, activeReviewedComponentId ?? selectedStartNodeId, edges),
    [selectedNode?.id, allEventIds, activeReviewedComponentId, selectedStartNodeId, edges],
  );

  // START (or restart) a per-event review session for `componentId` and show it.
  //
  // Per-event model: make `componentId` the ACTIVE reviewed event (snapshotting
  // any previously-active event's session and restoring this event's saved one),
  // enter the Replay view, then — only on a SAVED model — (re)start the live
  // listener + load history FOR this event. The one-listener rule is enforced by
  // cancelling any listener bound to a DIFFERENT event first; that event's
  // already-loaded data/step are retained in its session snapshot. Each request
  // records its target event so a late response is routed correctly.
  const enterReviewForNode = useCallback((componentId: string | null) => {
    // Explicit Review action claims the session — normal review-mode gating
    // applies again (the picker-initiated suppression no longer holds).
    setPickerInitiatedSession(false);
    if (!componentId) {
      setActiveReviewedComponentId(null);
      activeReviewedComponentIdRef.current = null;
      setPanelMode('review');
      return;
    }

    // One listener at a time: stop a listener bound to a different event. Its
    // loaded data/step are preserved in that event's session snapshot.
    if (listeningComponentIdRef.current && listeningComponentIdRef.current !== componentId) {
      cancelTest();
      listeningComponentIdRef.current = null;
    }

    // Switch the live session view to this event (restore its snapshot if any).
    switchActiveSession(componentId);
    setPanelMode('review');

    // Listening + history require a saved model with the relevant endpoints.
    if (!isNewModel) {
      requestTargetComponentIdRef.current = componentId;
      pendingCancelResolveRef.current = null;
      if (hasTestUrl) {
        // A37: first entry selects the LISTEN item and auto-starts the listener;
        // the waiting body shows because the listen item is selected. History
        // loads in parallel (A39) without stealing the selection.
        setSelectedReplayEntryIndex(LISTEN_ITEM_INDEX);
        setActiveBackendMessage(null);
        listeningComponentIdRef.current = componentId;
        startTest(componentId);
      }
      if (hasReplayUrl) {
        loadReplayData(componentId);
      }
    }
  }, [setPanelMode, isNewModel, hasTestUrl, hasReplayUrl, startTest, cancelTest, loadReplayData, switchActiveSession]);

  // Feature J: load step data ON DEMAND for the [-token picker, scoped to the
  // OWNING event, WITHOUT switching the side panel to the Review view (the user
  // stays in the property field they are editing). This routes through the SAME
  // per-event session machinery as Review (switchActiveSession + the single
  // listener + history load), so the picker is a thin view over one source of
  // truth and never starts a second listener. No-op when that event is already
  // the active session (data already loaded or listener already armed).
  const loadStepDataForPicker = useCallback((componentId: string) => {
    if (!componentId || isNewModel) return;
    // Already the active reviewed event → its data/listener are already live.
    // Leave the picker-initiated flag untouched: whoever last activated this
    // session (picker or explicit Review) keeps ownership of the panel mode.
    if (componentId === activeReviewedComponentIdRef.current) return;

    // One listener at a time: stop a listener bound to a different event. Its
    // loaded data/step are preserved in that event's session snapshot.
    if (listeningComponentIdRef.current && listeningComponentIdRef.current !== componentId) {
      cancelTest();
      listeningComponentIdRef.current = null;
    }

    // The picker is (re)activating this session purely to feed itself — the
    // panel must stay on Properties (not switch to Review).
    setPickerInitiatedSession(true);

    // Make this the active session (restore its snapshot if any). Does NOT
    // change panelMode — the property field stays visible.
    switchActiveSession(componentId);

    requestTargetComponentIdRef.current = componentId;
    pendingCancelResolveRef.current = null;
    if (hasTestUrl) {
      // Arm the single listener and show the picker's waiting state via the
      // listen-item selection (mirrors enterReviewForNode's first-entry path).
      setSelectedReplayEntryIndex(LISTEN_ITEM_INDEX);
      setActiveBackendMessage(null);
      listeningComponentIdRef.current = componentId;
      startTest(componentId);
    }
    if (hasReplayUrl) {
      loadReplayData(componentId);
    }
  }, [isNewModel, hasTestUrl, hasReplayUrl, startTest, cancelTest, loadReplayData, switchActiveSession]);

  // RESUME an existing (already-reviewed) event's session WITHOUT re-listening:
  // switch the active view to that event and show its retained data/step. Used
  // when the user clicks "Review flow" on an event that already has a session.
  const resumeReviewForNode = useCallback((componentId: string) => {
    // Explicit Review action claims the session — clear the picker-initiated
    // suppression so the Replay view shows normally.
    setPickerInitiatedSession(false);
    // One listener at a time: if a listener is running for a different event,
    // stop it (its data is retained); we do NOT auto-restart this event's.
    if (listeningComponentIdRef.current && listeningComponentIdRef.current !== componentId) {
      cancelTest();
      listeningComponentIdRef.current = null;
    }
    switchActiveSession(componentId);
    setPanelMode('review');
  }, [cancelTest, switchActiveSession, setPanelMode]);

  // A44: the user explicitly selected the "Listen…" item again — re-arm the
  // live listener for the ACTIVE event and show the waiting body. Existing
  // loaded entries are kept (we do NOT clear them); history may reload but
  // handleReplayEntriesLoaded keeps the listen item selected while listening.
  const handleSelectListenItem = useCallback(() => {
    const eventId = activeReviewedComponentIdRef.current;
    if (!eventId || isNewModel) return;
    setSelectedReplayEntryIndex(LISTEN_ITEM_INDEX);
    setCurrentReplayStep(-1);
    setActiveBackendMessage(null);
    pendingCancelResolveRef.current = null;
    requestTargetComponentIdRef.current = eventId;
    if (hasTestUrl) {
      listeningComponentIdRef.current = eventId;
      startTest(eventId);
    }
  }, [isNewModel, hasTestUrl, startTest, setCurrentReplayStep]);

  // Cancel the live listener for the ACTIVE event (A46/A47/A48). Stops the
  // listener now; then:
  //   • if history is still in flight → defer the resolution (A48): mark a
  //     pending cancel so handleReplayEntriesLoaded applies the rule on resolve.
  //   • else if data entries exist → select the newest (index 0) (A46).
  //   • else → no entry (-1); the empty body shows the stored backend message
  //     (A47).
  const handleCancelReview = useCallback(() => {
    const eventId = activeReviewedComponentIdRef.current;
    cancelTest();
    if (eventId && listeningComponentIdRef.current === eventId) {
      listeningComponentIdRef.current = null;
    }
    if (isReplayLoading) {
      // A48: resolve once the in-flight history load completes.
      pendingCancelResolveRef.current = eventId;
      return;
    }
    const entries = loadedReplayEntriesRef.current;
    if (entries.length > 0) {
      setSelectedReplayEntryIndex(0);
      setCurrentReplayStep(-1);
      const firstEntryData = (entries[0].history || []) as ReplayStep[];
      if (firstEntryData.length > 0) setIsReplayMode(true);
    } else {
      setSelectedReplayEntryIndex(-1);
    }
  }, [cancelTest, isReplayLoading, setCurrentReplayStep, setIsReplayMode]);

  // Resolve a "Review flow" click (PropertyPanel → onRequestReviewMode) into the
  // right per-event action. Three cases (maintainer-confirmed):
  //   (a) selected node is an EVENT that already has a session → RESUME it
  //       (switch the active view to that event, show its retained data/step;
  //       no re-listen, no guard).
  //   (b) selected node is an EVENT with NO session yet → START a new session
  //       for it (validate → unsaved-changes guard → enterReviewForNode, which
  //       also stops any other event's listener).
  //   (c) selected node is NOT an event → resume the session of the event whose
  //       flow OWNS the selected node (active-preferred). This makes the node
  //       in-flow for the now-active event so the auto-exit effect will NOT
  //       bounce back to Properties. If no session owns it yet but it traces
  //       back STRUCTURALLY to a starting event (`pickerOwningEventId`), START a
  //       new session for that event (validate first). Only a truly orphaned
  //       node (no owning event at all) is a no-op.
  const requestReviewMode = useCallback(() => {
    const selectedEventId = selectedStartNodeId;

    // (c) Non-event selection → resume the OWNING event's session (if any). We
    // resume the event whose flow contains the selected node (not the stale
    // active event), so reviewedFlowNodeIds recomputes to include the node and
    // the auto-exit effect keeps Review open instead of bouncing.
    if (!selectedEventId) {
      if (reviewableEventId) {
        resumeReviewForNode(reviewableEventId);
      } else if (pickerOwningEventId) {
        // No session yet owns this node, but we can trace back structurally to
        // its starting event — start a review session for that event so the
        // user can review the flow the selected action belongs to. Prefer the
        // structural resolution only when no session owns the node (the
        // `reviewableEventId` branch above handles the session case).
        const validationError = validateBeforeSave();
        if (validationError) {
          showDrupalMessage(validationError, 'error');
          return;
        }
        enterReviewForNode(pickerOwningEventId);
      }
      return;
    }

    // (a) The selected event already has a session → resume it (no guard/listen).
    if (sessionsRef.current.has(selectedEventId) || selectedEventId === activeReviewedComponentIdRef.current) {
      resumeReviewForNode(selectedEventId);
      return;
    }

    // (b) New session for this event — validate, then enter review directly.
    // Note: there is intentionally NO unsaved-changes guard here. Review and
    // live listening proceed even with unsaved changes (the saved-model
    // requirement is still enforced for brand-new models via the isNewModel
    // gate inside enterReviewForNode). Only structural validation gates entry.
    const validationError = validateBeforeSave();
    if (validationError) {
      showDrupalMessage(validationError, 'error');
      return;
    }

    enterReviewForNode(selectedEventId);
  }, [validateBeforeSave, selectedStartNodeId, reviewableEventId, pickerOwningEventId, enterReviewForNode, resumeReviewForNode]);

  // Alt+Shift+R toggles the side panel between Properties and Review.
  // LEAVING review is unguarded - the session stays active and resumes on
  // return - while ENTERING routes through requestReviewMode so structural
  // validation and the per-event session logic (start / resume / return to the
  // owning event) still apply exactly as for the header button. A collapsed
  // panel is expanded first, otherwise the toggle would have no visible effect.
  const handleToggleReviewMode = useCallback(() => {
    if (propertyPanelCollapsed) {
      setPropertyPanelCollapsed(false);
    }
    if (panelMode === 'review') {
      setPanelMode('event');
      return;
    }
    requestReviewMode();
  }, [propertyPanelCollapsed, setPropertyPanelCollapsed, panelMode, setPanelMode, requestReviewMode]);

  // Keyboard shortcuts (copy/paste/delete disabled in read-only mode).
  // Registered here - after requestReviewMode / handleToggleReviewMode - so the
  // review-toggle callback is in scope for the capability wiring below.
  useKeyboardShortcuts({
    callbacks: {
      onDelete: handleDeleteSelected,
      onCopy: handleCopy,
      onPaste: handlePaste,
      onToggleSearch: () => {
        // The search bar is always visible inline; Ctrl+F focuses the input.
        // WCAG 2.2 SC 2.1.1 - keyboard-accessible search.
        const searchInput = document.querySelector<HTMLInputElement>('.search-input');
        if (searchInput) {
          searchInput.focus();
        }
      },
      onEscape: () => {
        clearSearch();
        // Blur the search input so focus returns to the canvas
        const searchInput = document.querySelector<HTMLInputElement>('.search-input');
        if (searchInput && document.activeElement === searchInput) {
          searchInput.blur();
        }
      },
      onUndo: undo,
      onRedo: redo,
      onToggleReviewMode: handleToggleReviewMode,
    },
    modifiers: {
      isShiftPressed,
      setIsShiftPressed,
      isCtrlPressed,
      setIsCtrlPressed,
      isAltPressed,
      setIsAltPressed,
    },
    capabilities: {
      canDelete: !isReadOnly && canDeleteSelected(),
      canCopy: !isReadOnly && canCopy,
      canPaste: !isReadOnly && canPaste,
      canSearch: true,
      canEscape: true,
      canUndo: !isReadOnly && canUndo(),
      canRedo: !isReadOnly && canRedo(),
      // Leaving review is always allowed; entering needs a reviewable model.
      canToggleReviewMode: panelMode === 'review' || (hasAnyReplayCapability && !isNewModel),
    },
    isModelerFocused: true,
    enabled: true,
  });

  // Auto-enter Review mode when a model OPENS already carrying saved replay data
  // (settings.modeler.replayData → initialReplayData). The embedded data is shown
  // as-is (no live listener, no history reload — like resuming), so the user lands
  // directly on the Replay view for the flow the data belongs to.
  //
  // Fires ONCE per loaded model (autoReviewAppliedRef). Requires the graph to be
  // loaded so the owning event can be resolved. The target event is the event
  // whose flow owns the FIRST replay step's node; falls back to the single start
  // node when that cannot be resolved (e.g. a single-flow model).
  useEffect(() => {
    if (autoReviewAppliedRef.current) return;
    if (initialReplayData.length === 0) return;
    if (nodes.length === 0) return;
    // A picker- or user-initiated session already claimed the panel — respect it.
    if (activeReviewedComponentIdRef.current !== null) {
      autoReviewAppliedRef.current = true;
      return;
    }

    // Resolve the event the embedded data belongs to. Try, in order:
    //   1. the event whose flow OWNS the first replay step's node;
    //   2. the event id carried by the first step itself (a "started" step's id
    //      is the event id) when it matches a known start node;
    //   3. the single selected/sole start node;
    //   4. the FIRST start node in the model.
    // The progressive fallback guarantees we still enter review (and show the
    // data) for multi-event models where the first step does not resolve to a
    // node via the canvas graph.
    const firstElement = findElementForReplayStep(initialReplayData, nodes, edges, 0);
    const firstNodeId = firstElement?.type === 'node' ? firstElement.id : null;
    const firstStepId =
      typeof initialReplayData[0]?.id === 'string' ? initialReplayData[0].id : null;
    const firstStepEventId =
      firstStepId && allEventIds.includes(firstStepId) ? firstStepId : null;
    const targetEventId =
      findOwningEventId(firstNodeId, allEventIds, selectedStartNodeId, edges) ??
      firstStepEventId ??
      selectedStartNodeId ??
      (allEventIds.length > 0 ? allEventIds[0] : null);
    if (!targetEventId) return;

    autoReviewAppliedRef.current = true;

    // Surface the embedded data as a SELECTED replay entry. The Replay body only
    // renders steps when a data entry is selected (dataEntrySelected && hasSteps);
    // `replayData` alone is not enough. Wrap initialReplayData in a synthetic
    // entry, seed it into the target event's session snapshot, then switch to that
    // session (which restores the snapshot with the entry selected at index 0).
    const syntheticEntry: ReplayEntry = {
      model_id: settings.modeler?.modelId || '',
      component_id: targetEventId,
      history: initialReplayData as unknown as ReplayEntry['history'],
      timestamp: new Date().toISOString(),
      user: '',
      ip: '',
      url: '',
    };
    sessionsRef.current.set(targetEventId, {
      replayEntries: [syntheticEntry],
      selectedEntryIndex: 0,
      currentStep: -1,
      backendMessage: null,
    });
    registerSessionEvent(targetEventId);

    // Listener-free entry (mirrors resumeReviewForNode): make the owning event the
    // active reviewed session (restoring the snapshot seeded above) and show the
    // Replay view — no live listener, no history reload.
    switchActiveSession(targetEventId);
    setPanelMode('review');
  }, [initialReplayData, nodes, edges, allEventIds, selectedStartNodeId, settings.modeler?.modelId, registerSessionEvent, switchActiveSession, setPanelMode]);

  const {
    canExport,
    availableFormats,
    hasReplayData: exportHasReplayData,
    executeExport,
    getRequiredModules,
  } = useExport({
    settings,
    nodes,
    edges,
    components,
    modelData,
    replayData,
    announce,
  });

  // Handle the export button click: check unsaved changes, then open dialog
  // In standalone mode there is nothing to save — go straight to dialog.
  const handleExportClick = useCallback(() => {
    if (hasUnsavedChanges && !isStandalone) {
      showConfirmationDialog(
        t('Unsaved Changes'),
        t('You have unsaved changes. Please save your model before exporting.'),
        'warning',
        () => {
          // Mark that we want to open the export dialog after save completes
          pendingExportAfterSaveRef.current = true;
          saveButtonRef.current?.click();
        },
        undefined,
        {
          primaryLabel: t('Save Now'),
          secondaryLabel: false,
          cancelLabel: t('Cancel'),
          primaryVariant: 'primary',
        },
      );
      return;
    }
    setShowExportDialog(true);
  }, [hasUnsavedChanges, isStandalone, showConfirmationDialog, saveButtonRef]);

  // Handle format selection from the export dialog
  const handleExportFormat = useCallback(async (format: ExportFormat, includeReplayData?: boolean) => {
    setIsExporting(true);
    try {
      await executeExport(format, includeReplayData);
      setShowExportDialog(false);
    } catch (_error) {
      // Error is already announced via the hook
    } finally {
      setIsExporting(false);
    }
  }, [executeExport]);

  const handleCloseExportDialog = useCallback(() => {
    setShowExportDialog(false);
  }, []);

  // Auto-collapse replay panel when there's no data and no test running.
  // Auto-expand when the Test button becomes available so users can access it.
  // When collapsePanels is active, skip the auto-expand — the user can still
  // expand manually by clicking the collapsed panel tab.
  const showTestButton = hasTestUrl && !!selectedStartNodeId;
  useEffect(() => {
    if (collapsePanels) return;
    const hasData = replayData && replayData.length > 0;
    if (!hasData && !isTestRunning && !isTestInitiating && !showTestButton) {
      setReplayPanelCollapsed(true);
    } else if (showTestButton || hasData || isTestRunning || isTestInitiating) {
      setReplayPanelCollapsed(false);
    }
  }, [replayData, isTestRunning, isTestInitiating, showTestButton, setReplayPanelCollapsed, collapsePanels]);

  // New-edge connection handlers (onConnectStart/onConnectEnd) now come from
  // useFlowEventHandlers above — they implement "drop a new edge onto a node
  // body" (issue #3585553 follow-on UX).
  const onInit = useCallback(() => {
    viewportActions.setReady();
  }, [viewportActions]);

  // ── Stable Toolbar callbacks ──────────────────────────────────────
  // Using functional updaters (prev => ...) so these callbacks have no
  // dependencies and keep a stable identity across renders.  This is
  // critical for React.memo on Toolbar to work.


  // Refs for values captured by onSave/onSaveComplete to avoid dependency
  // churn while keeping the callbacks stable.
  const nodesRef = useRef(nodes);
  nodesRef.current = nodes;
  const edgesRef = useRef(edges);
  edgesRef.current = edges;
  const modelDataRef = useRef(modelData);
  modelDataRef.current = modelData;

  const handleSaveData = useCallback(() =>
    exportModelData(nodesRef.current, edgesRef.current, {
      id: modelDataRef.current?.id,
      version: modelDataRef.current?.version,
      ...modelDataRef.current?.metadata,
    }),
  []);

  const handleSaveCompleteToolbar = useCallback(() => {
    handleSaveComplete(setHasUnsavedChanges);
    notifySaveComplete();
    if (pendingExportAfterSaveRef.current) {
      pendingExportAfterSaveRef.current = false;
      setShowExportDialog(true);
    }
  }, [handleSaveComplete, notifySaveComplete]);

  // Set of node ids that belong to the ACTIVE reviewed flow: the currently
  // reviewed event plus every node reachable downstream from it (BFS over
  // directed edges). Keyed on the ACTIVE reviewed event so it never uses a
  // stale event after switching sessions. A node shared with other flows is
  // in-flow if it is reachable from the active reviewed event.
  const reviewedFlowNodeIds = useMemo(
    () => getReachableNodeIds(activeReviewedComponentId, edges),
    [activeReviewedComponentId, edges],
  );

  // Auto-exit to Properties when, while reviewing, the user selects a canvas
  // component that is NOT part of the ACTIVE reviewed event's flow.
  //
  // Replay and Properties coexist: selecting an IN-FLOW node (the reviewed event
  // or any reachable descendant) keeps Review active and updates the background
  // Properties view. But selecting an OUT-OF-FLOW node switches to Properties for
  // that node. The replay SESSION is preserved either way (active reviewed id,
  // step, entry, and listener are untouched), so the user can return via the
  // header "Review flow" button.
  //
  // Replay-step-driven highlighting (which programmatically selects in-flow
  // nodes while walking steps) is ignored via isReplaySyncingRef so it never
  // triggers an exit. No selection / background click also stays in Review.
  useEffect(() => {
    if (panelMode !== 'review') return;
    if (!replaySessionActive) return;
    if (isReplaySyncingRef.current) return;
    if (!selectedNode) return;
    if (reviewedFlowNodeIds.has(selectedNode.id)) return;
    // Out-of-flow user selection → show that node's properties (session kept).
    setPanelMode('event');
  }, [panelMode, replaySessionActive, selectedNode, reviewedFlowNodeIds, isReplaySyncingRef, setPanelMode]);

  // Stable contexts prop for CanvasToolbar
  const toolbarContexts = canSwitchContext && !isReadOnly ? contexts : EMPTY_CONTEXTS;

  return (
    <Profiler id="Flow" onRender={onRenderCallback}>
    <div
      className={[
        'workflow-modeler',
        isStandalone ? 'standalone' : '',
        viewMode,
        isDragging ? 'is-dragging' : '',
        isResizing ? 'is-resizing' : '',
      ].filter(Boolean).join(' ')}
      ref={modelerRef}
    >
      <PanelErrorBoundary panelName={t('Toolbar')} className="toolbar-error">
        <Toolbar
          modelName={modelData?.metadata?.label || t('Untitled Workflow')}
          hasUnsavedChanges={hasUnsavedChanges}
          onSave={handleSaveData}
          onSaveComplete={handleSaveCompleteToolbar}
          saveButtonRef={saveButtonRef}
          onOpenMetadata={openMetadataModal}
          onToggleMessages={handleToggleMessages}
          onClearMessages={handleClearMessages}
          isLocked={isLocked}
          isReadOnly={isReadOnly}
          hasMessages={hasMessages}
          messagesVisible={messagesVisible}
          onSearchHighlight={onSearchHighlight}
          onSearchFocus={onSearchFocus}
          settings={settings}
          drupal={drupal}
          onClose={handleClose}
          announce={announce}
          validateBeforeSave={validateBeforeSave}

          onAddEvent={handleAddEvent}
          isEventPopupOpen={isEventPopupOpen}
          onEventPopupOpenChange={setIsEventPopupOpen}
          onExport={handleExportClick}
          canExport={canExport}
          viewMode={viewMode}
          onToggleViewMode={toggleViewMode}
          onStartDrag={startDrag}
          pluginWidgetsLeft={leftPluginWidgets}
          pluginWidgetsRight={rightPluginWidgets}
          pluginApi={pluginApi}
        />
      </PanelErrorBoundary>

      {/* Messages container - positioned at top, fades out */}
      <div
        ref={messagesContainerRef}
        role="log"
        aria-label={t('Workflow messages')}
        aria-live="polite"
        aria-relevant="additions removals"
        className={`workflow-messages-container ${messagesVisible ? 'visible' : 'hidden'}`}
      />

      <div className="workflow-modeler-content">
        {/* Plugin panels - Left slot */}
        {leftPluginPanels.length > 0 && (
          <PluginPanelSlot panels={leftPluginPanels} api={pluginApi} position="left" />
        )}

        {/* Main Canvas */}
        <div className="workflow-modeler-canvas">
          <CanvasToolbar
            isLocked={isLocked}
            isReadOnly={isReadOnly}
            onCopy={handleCopy}
            onPaste={handlePaste}
            onUndo={undo}
            onRedo={redo}
            hasSelection={!!selectedNode || !!selectedEdge}
            canPaste={canPaste}
            canUndo={canUndo()}
            canRedo={canRedo()}
            onAutoLayout={handleAutoLayout}
            onFitView={() => viewportActions.fitToNodes()}
            contexts={toolbarContexts}
            selectedContextId={selectedContextId}
            onContextChange={setSelectedContextId}
          />
          <PanelErrorBoundary panelName={t('Canvas')} className="canvas-error">
            <FlowCanvas
              nodes={filteredNodes}
              edges={filteredEdges}
              eventHandlers={{
                onNodesChange,
                onEdgesChange,
                onConnect,
                onSelectionChange,
                onConnectStart,
                onConnectEnd,
                onNodeClick,
                onEdgeClick,
                onPaneClick,
                onSelectionStart,
                onNodeDragStart,
                onNodeDragStop,
                onInit,
              }}
              elementCallbacks={{
                onEdgeUpdate,
                onNodeUpdate,
                onDeleteNode,
                onDeleteEdge,
                onReconnectEdge,
              }}
              viewport={getViewport()}
              modifierKeys={{
                isShiftPressed,
                isCtrlPressed,
                isAltPressed,
              }}
              uiState={{
                isDragActive: false,
                isLocked,
                showEdgeOrderNumbers,
                showAllAnnotations,
              }}
              search={{
                searchTerm,
                highlightedSearchResult,
              }}
              replay={{
                replayData,
                currentReplayStep,
                isReplayMode,
                replayIndicators,
              }}
              setEdges={setEdges}
              setHasUnsavedChanges={setHasUnsavedChanges}
              quickAdd={{
                onQuickAdd: handleQuickAdd,
                onAddCondition: handleAddCondition,
                onAddActionOnEdge: handleAddActionOnEdge,
                onReplacePlaceholder: handleReplacePlaceholder,
              }}
              modelConstraints={modelConstraints}
            />
          </PanelErrorBoundary>
        </div>

        {/* Unified right-hand panel — shows component properties ("Event"
            mode) or the execution replay / "Review flow" content depending on
            the panel mode toggle in its header. The replay content is rendered
            inside this panel (no separate replay column). */}
        <PanelErrorBoundary panelName={t('Properties')} className="property-panel-error">
          <PropertyPanel
            node={selectedNode}
            edge={selectedEdge}
            selectedNodes={selectedNodes.map(id => nodes.find(n => n.id === id)).filter((n): n is Node => n !== undefined)}
            selectedEdges={selectedEdges.map(id => edges.find(e => e.id === id)).filter((e): e is Edge => e !== undefined)}
            onConfigurationChange={onConfigurationChange}
            onNodeUpdate={onNodeUpdate}
            onEdgeUpdate={onEdgeUpdate}
            onDeleteSelected={handleDeleteSelectedWithConfirm}
            settings={settings}
            isLocked={isLocked}
            onReplayEntriesLoaded={handleReplayEntriesLoaded}
            hasAnyReplayCapability={hasAnyReplayCapability}
            onRequestReviewMode={requestReviewMode}
            replaySessionActive={replaySessionActive}
            pickerInitiatedSession={pickerInitiatedSession}
            reviewableEventId={reviewableEventId}
            // Review-mode (replay) props — state lifted here in Flow.tsx.
            replayData={replayData}
            currentReplayStep={currentReplayStep}
            onSelectReplayStep={handleReplayStepSelect}
            onToggleReplay={toggleReplayMode}
            isReplayMode={isReplayMode}
            stepData={expandedStepData.data}
            stepDataPredicted={expandedStepData.predicted}
            stepInfo={currentReplayStep >= 0 && currentReplayStep < replayData.length ? {
              type: replayData[currentReplayStep].type,
              id: replayData[currentReplayStep].id,
              successorId: replayData[currentReplayStep].successorId,
              conditionId: replayData[currentReplayStep].conditionId,
              object: replayData[currentReplayStep].object,
              exception: replayData[currentReplayStep].exception
            } : null}
            reviewNodes={nodes}
            reviewEdges={edges}
            replayEntries={loadedReplayEntries}
            selectedReplayEntryIndex={selectedReplayEntryIndex}
            onSelectReplayEntry={handleSelectReplayEntry}
            onSelectListenItem={handleSelectListenItem}
            backendMessage={activeBackendMessage}
            selectedStartNodeId={selectedStartNodeId}
            hasTestUrl={hasTestUrl}
            isTestRunning={isTestRunning}
            isTestInitiating={isTestInitiating}
            testError={testError}
            onStartTest={startTest}
            onCancelTest={handleCancelReview}
            globalTokens={globalTokens}
            templateTokens={templateTokens}
            isTemplate={!!settings.modeler_api?.metadata?.template}
            isReplayLoading={isReplayLoading}
            onLoadStepData={loadStepDataForPicker}
            pickerOwningEventId={pickerOwningEventId}
          />
        </PanelErrorBoundary>

        {/* Plugin panels - Right slot */}
        {rightPluginPanels.length > 0 && (
          <PluginPanelSlot panels={rightPluginPanels} api={pluginApi} position="right" />
        )}
      </div>

      {/* Plugin panels - Bottom slot */}
      {bottomPluginPanels.length > 0 && (
        <PluginPanelSlot panels={bottomPluginPanels} api={pluginApi} position="bottom" />
      )}

      <PanelErrorBoundary panelName={t('Modals')} className="modals-error">
        <Modals
          showMetadataModal={showMetadataModal}
          onCloseMetadataModal={handleCloseMetadataModal}
          onMetadataSubmit={onMetadataSubmit}
          modelMetadata={modelData?.metadata || EMPTY_METADATA}
          metadataForm={settings?.modeler?.metadataForm}
          modelId={modelData?.id}
          isNewModel={settings?.modeler_api?.isNew || false}
          canEditMetadata={canEditMetadata && !isReadOnly}
          canCreateTemplate={canCreateTemplate && canEditTemplate && !isReadOnly}
          showConfirmDialog={showConfirmDialog}
          confirmDialogTitle={confirmDialogTitle}
          confirmDialogMessage={confirmDialogMessage}
          confirmDialogType={confirmDialogType}
          onConfirmDialog={handleConfirmDialog}
          onCancelDialog={handleCancelDialog}
          onCloseWithoutSave={handleCloseWithoutSave}
          confirmDialogLoading={confirmDialogLoading}
          confirmDialogPrimaryLabel={confirmDialogPrimaryLabel}
          confirmDialogSecondaryLabel={confirmDialogSecondaryLabel}
          confirmDialogCancelLabel={confirmDialogCancelLabel}
          confirmDialogPrimaryVariant={confirmDialogPrimaryVariant}
          showExportDialog={showExportDialog}
          onCloseExportDialog={handleCloseExportDialog}
          exportAvailableFormats={availableFormats}
          exportHasReplayData={exportHasReplayData}
          exportRequiredModules={getRequiredModules()}
          onExport={handleExportFormat}
          isExporting={isExporting}
        />
      </PanelErrorBoundary>

      {/* Resize handle — only in Drupal restored (windowed) mode */}
      {viewMode === 'restored' && !isStandalone && (
        <div
          className="modeler-resize-handle"
          onMouseDown={startResize}
          aria-hidden="true"
        />
      )}

      {/* Hidden aria-live region for screen reader announcements */}
      <div
        role="status"
        aria-live="polite"
        aria-atomic="true"
        className="sr-only"
      >
        {statusMessage}
      </div>
    </div>
    </Profiler>
  );
}

// Main Flow component wrapper
function Flow(props: FlowProps) {
  return <FlowInner {...props} />;
}

export default Flow;
