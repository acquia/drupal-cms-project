import React, { Profiler, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { FiChevronRight, FiChevronLeft, FiGitBranch, FiInfo, FiActivity, FiArrowLeft } from 'react-icons/fi';
import DocumentationButton from './DocumentationButton';
import InfoPopup from './InfoPopup';
import type { InfoItem } from './InfoPopup';
import MultiSelectionPanel from './MultiSelectionPanel';
import NodePropertiesPanel from './NodePropertiesPanel';
import EdgePropertiesPanel from './EdgePropertiesPanel';
import { usePanelStore } from '../store/usePanelStore';
import { useComponentStore } from '../store/useComponentStore';
import type { StoreNode as Node, StoreEdge as Edge, NodeData, EdgeData } from '../types/settings';
import { PANEL_DIMENSIONS } from '../constants/dimensions';
import { t } from '../utils/translation';
import { getComponentIcon, getComponentLabel, getComponentTypeName } from '../utils/componentUtils';
import { useConfigurationLoader } from '../hooks/useConfigurationLoader';
import type { ReplayEntry } from '../hooks/useReplayLoader';
import { LISTEN_ITEM_INDEX } from '../hooks/useReplayLoader';
import { usePanelResize } from '../hooks/usePanelResize';
import { useDebouncedField } from '../hooks/useDebouncedField';
import type { Settings, GlobalToken } from '../types/settings';
import { hasPermission } from '../utils/permissions';
import { onRenderCallback } from '../utils/profiling';
import ReplayPanelContent from './ReplayPanelContent';
import type { StepInfo } from './ReplayPanelContent';
import type { ReplayStep } from '../utils/replayStepUtils';
import { TokenSourceContext } from './TokenSourceContext';
import type { TokenSourceValue } from './TokenSourceContext';

/**
 * Props that thread the lifted replay/test state from `Flow.tsx` down to the
 * embedded {@link ReplayPanelContent} when the panel is in "Review flow" mode,
 * plus the token sources the in-field "[" picker reads through
 * {@link TokenSourceContext}. All of this state stays lifted in `Flow.tsx`;
 * PropertyPanel is a pass-through.
 */
interface ReviewModeProps {
  /** Replay steps for the currently selected execution entry. */
  replayData?: ReplayStep[] | null;
  /** Current replay step index (-1 = none selected). */
  currentReplayStep?: number;
  /** Select a replay step. */
  onSelectReplayStep?: (step: number) => void;
  /** Toggle replay (playback) mode. */
  onToggleReplay?: () => void;
  /** Expanded token data for the current step (or predicted predecessor data). */
  stepData?: Record<string, any> | null;
  /**
   * Whether {@link stepData} was PREDICTED from a replay-covered predecessor of
   * the selected node (issue #3577207) rather than confirmed by a replay run on
   * the node itself. Drives the subtle "predicted" badge + tooltip in the
   * Step-data panel and the @-token picker. Defaults to `false` (confirmed).
   */
  stepDataPredicted?: boolean;
  /** Metadata about the current step (type, ids, exception, ...). */
  stepInfo?: StepInfo | null;
  /** All nodes (for step labeling / filtering). */
  reviewNodes?: Node[];
  /** All edges (for step labeling / filtering). */
  reviewEdges?: Edge[];
  /** Loaded replay entries from the backend (multiple executions). */
  replayEntries?: ReplayEntry[];
  /** Index of the currently selected replay entry. */
  selectedReplayEntryIndex?: number;
  /** Select a different replay entry. */
  onSelectReplayEntry?: (index: number) => void;
  /** Select the persistent "listen" item (re-arms the live listener). */
  onSelectListenItem?: () => void;
  /** Per-event backend empty/warning message for the empty review body (A47). */
  backendMessage?: string | null;
  /** ID of the currently selected (or auto-detected) event node. */
  selectedStartNodeId?: string | null;
  /** Whether a test_url endpoint is available. */
  hasTestUrl?: boolean;
  /** Whether a test is currently running (polling for results). */
  isTestRunning?: boolean;
  /** Whether the initial test request is in flight. */
  isTestInitiating?: boolean;
  /** Error message if the test failed. */
  testError?: string | null;
  /** Start a test for the given event component ID. */
  onStartTest?: (componentId: string) => void;
  /** Cancel the running test. */
  onCancelTest?: () => void;
  /**
   * Global tokens - published to the "[" token picker only (the review panel
   * lists steps, not tokens).
   */
  globalTokens?: Record<string, GlobalToken>;
  /**
   * Template tokens - published to the "[" token picker only, and only for a
   * template model.
   */
  templateTokens?: Record<string, GlobalToken>;
  /** Whether the current model is a template (gates the template tokens). */
  isTemplate?: boolean;
  /**
   * Feature J: whether the owning event's history load is in flight (drives the
   * @-picker's "Polling for data" state). Lifted from Flow's useReplayLoader.
   */
  isReplayLoading?: boolean;
  /**
   * Feature J: enter/refresh the owning event's review session to load step
   * data on demand for the @-picker (routes through Flow.enterReviewForNode —
   * starts the SINGLE listener + loads history). The picker never loads itself.
   */
  onLoadStepData?: (eventId: string) => void;
  /**
   * Feature J (caveat fix): the event that owns the selected node resolved
   * STRUCTURALLY by Flow (session-agnostic, via findOwningEventId), so the
   * Step-data category shows BEFORE any session exists. Distinct from
   * `reviewableEventId` (session-gated, drives the Review-flow button). null
   * when no event flow reaches the node. Falls back to the legacy local
   * derivation in isolated contexts (tests/stories) that supply no prop.
   */
  pickerOwningEventId?: string | null;
}

interface PropertyPanelProps extends ReviewModeProps {
  node?: Node | null;
  edge?: Edge | null;
  selectedNodes?: Node[];
  selectedEdges?: Edge[];
  onConfigurationChange?: (nodeId: string, configuration: Record<string, any>) => void;
  onNodeUpdate?: (nodeId: string, data: Partial<NodeData>) => void;
  onEdgeUpdate?: (edgeId: string, data: Partial<EdgeData>) => void;
  onDeleteSelected?: () => void;
  isLocked?: boolean;
  settings?: Settings;
  isReplayMode?: boolean;
  /**
   * @deprecated no longer consumed here; retained for caller compatibility.
   * Flow.tsx owns replay loading on review entry.
   */
  onReplayEntriesLoaded?: (entries: ReplayEntry[]) => void;
  /**
   * Whether the model has any replay capability (replay_url, test_url, or
   * embedded replay data). When false, the "Review flow" toggle is shown but
   * disabled. Computed in `Flow.tsx` as `hasAnyReplayCapability`.
   */
  hasAnyReplayCapability?: boolean;
  /**
   * Request to START a replay session. Provided by Flow.tsx — it gates the
   * switch behind the unsaved-changes guard (Phase 2) because only the model
   * owner knows how to save, and it starts the live listener + loads history.
   * When omitted (e.g. isolated tests/stories), it falls back to a direct,
   * unguarded view switch.
   */
  onRequestReviewMode?: () => void;
  /**
   * Whether a replay session is currently active (started for an event node,
   * with loaded data or a running listener). When true, the user can switch
   * freely between Properties and Replay views without losing session state,
   * and the "Review flow" (go-to-replay) control is offered from ANY node.
   */
  replaySessionActive?: boolean;
  /**
   * TRUE when the currently-active review session was armed by the [-token
   * PICKER (Flow.loadStepDataForPicker) rather than an explicit Review action.
   * While true the panel keeps showing Properties (the step data flows into the
   * picker only) and never switches to the Replay view. Defaults to false for
   * isolated contexts (tests/stories).
   */
  pickerInitiatedSession?: boolean;
  /**
   * For a NON-event selected node: the id of the reviewed event whose flow OWNS
   * the selected node (i.e. reaches it), among events that currently have a
   * session — or null when the node belongs to no session-flow. Resolved by
   * Flow.tsx via findOwningReviewedEventId. Used to gate the "Review flow"
   * button for non-event nodes: show ONLY when an owning session-event exists,
   * so the button does not appear for nodes outside any reviewed flow.
   */
  reviewableEventId?: string | null;
}

const PropertyPanel: React.FC<PropertyPanelProps> = ({
  node,
  edge,
  selectedNodes = [],
  selectedEdges = [],
  onConfigurationChange,
  onNodeUpdate,
  onEdgeUpdate,
  onDeleteSelected,
  isLocked = false,
  settings = {},
  isReplayMode = false,
  // onReplayEntriesLoaded is retained on the interface for backward compat but
  // is no longer consumed here — Flow.tsx owns replay loading on review entry.
  hasAnyReplayCapability = false,
  onRequestReviewMode,
  replaySessionActive = false,
  pickerInitiatedSession = false,
  reviewableEventId = null,
  // Review-mode (replay) props — threaded down to ReplayPanelContent.
  replayData,
  currentReplayStep = -1,
  onSelectReplayStep,
  onToggleReplay,
  stepData,
  stepDataPredicted = false,
  stepInfo,
  reviewNodes = [],
  reviewEdges = [],
  replayEntries = [],
  selectedReplayEntryIndex = -1,
  onSelectReplayEntry,
  onSelectListenItem,
  backendMessage = null,
  selectedStartNodeId,
  hasTestUrl = false,
  isTestRunning = false,
  isTestInitiating = false,
  testError,
  onStartTest,
  onCancelTest,
  globalTokens,
  templateTokens,
  isTemplate = false,
  isReplayLoading = false,
  onLoadStepData,
  pickerOwningEventId: pickerOwningEventIdProp,
}) => {
  const panelWidth = usePanelStore(state => state.panelWidth);
  const panelIsResizing = usePanelStore(state => state.panelIsResizing);
  const setPanelWidth = usePanelStore(state => state.setPanelWidth);
  const setPanelResizing = usePanelStore(state => state.setPanelResizing);
  const propertyPanelCollapsed = usePanelStore(state => state.propertyPanelCollapsed);
  const togglePropertyPanelCollapse = usePanelStore(state => state.togglePropertyPanelCollapse);
  const panelMode = usePanelStore(state => state.panelMode);
  const setPanelMode = usePanelStore(state => state.setPanelMode);
  const components = useComponentStore(state => state.components);

  // Look up the documentation URL from the component definition
  const documentationUrl = useMemo(() => {
    if (!node?.data?.plugin || !components) return null;
    const component = components.find(c => c.plugin === node.data.plugin);
    return component?.documentationUrl || node.data?.documentationUrl || null;
  }, [node?.data?.plugin, node?.data?.documentationUrl, components]);
  
  // ── Picker-open freeze: state declared early so it can also gate the config
  // loader input below (a picker-armed session must not reload/unmount the
  // field while the [-token picker is open). ──────────────────────────────────
  const [pickerOpen, setPickerOpen] = useState(false);
  const handlePickerOpenChange = useCallback((open: boolean) => setPickerOpen(open), []);

  // Freeze the loader's `isReplayMode` input while the picker is open. The
  // config loader "always reloads in replay mode" (→ setLoading(true) → the
  // body swaps to the spinner → ContentEditableField UNMOUNTS → the picker's
  // freeze is released). Feeding it the open-time `isReplayMode` (false on a
  // fresh field) keeps the loader's input STABLE so it never triggers that
  // reload while the picker is open. Reconciles to live on close — but a
  // picker-initiated session further clamps the loader input to false even
  // after close (see `configLoaderReplayMode` below), so the reconcile does not
  // trigger the reload/repaint on token insert. Explicit Review (which clears
  // `pickerInitiatedSession`) drives the loader live, as before.
  const frozenIsReplayModeRef = useRef<boolean | null>(null);
  if (pickerOpen) {
    if (frozenIsReplayModeRef.current === null) {
      frozenIsReplayModeRef.current = isReplayMode;
    }
  } else if (frozenIsReplayModeRef.current !== null) {
    frozenIsReplayModeRef.current = null;
  }
  const passedIsReplayMode =
    pickerOpen && frozenIsReplayModeRef.current !== null ? frozenIsReplayModeRef.current : isReplayMode;

  // A picker-initiated session means the user is still editing this field's
  // properties (not reviewing the flow), so the configuration form must NOT
  // reload into replay mode — doing so swaps the panel body to the loading
  // spinner when the picker closes on insert (the "ugly repaint"). The freeze
  // above only holds WHILE the picker is open; on close it reconciles to the
  // live `true`, which would re-run the loader's "always reload in replay mode"
  // rule. Mirror the same picker-initiated intent used by `liveEffectiveMode`
  // below so the loader never enters replay-reload mode for a picker-driven
  // session, regardless of whether the picker is open or was just closed. An
  // EXPLICIT Review action clears `pickerInitiatedSession` in Flow, so genuine
  // Review still falls back to `passedIsReplayMode` and reloads as before.
  const configLoaderReplayMode = pickerInitiatedSession ? false : passedIsReplayMode;

  // Use extracted hooks for cleaner architecture
  const { configurationForm, loading } = useConfigurationLoader({
    node,
    settings,
    isReplayMode: configLoaderReplayMode,
  });

  const isStartNode = node?.type === 'start';
  const isNewModel = !!settings.modeler_api?.isNew;
  const canReplay = hasPermission(settings, 'replay');
  const hasReplayUrl = !isNewModel && !!settings.modeler_api?.replay_url && canReplay;

  // Review is available when the model has replay/test capability and is saved.
  // (Live listening requires a saved model — see Flow.requestReviewMode.)
  const reviewAvailable = hasAnyReplayCapability && !isNewModel;

  // ── Picker-open freeze ────────────────────────────────────────────────────
  // While the [-token picker (a modal) is open, the panel BEHIND it must not
  // repaint from session-derived state that a picker-armed session changes
  // (Review-button enabled state, event⇄review view). We capture the chrome
  // derivations at the moment the picker opens and render the frozen snapshot
  // until it closes, then reconcile to live values. The picker keeps receiving
  // LIVE token-source data (see `tokenSources`) so data still flows into it.
  // (`pickerOpen` + `handlePickerOpenChange` are declared earlier so they can
  // also gate the config-loader input above.)

  // `panelMode` is an INTERNAL view flag only (no persistent toggle UI).
  // Properties and Replay are two coexisting views: the Replay view stays bound
  // to the active session's event, while the Properties view reflects the
  // currently-selected node. We only stay in Replay when a session is active —
  // otherwise (no session) the panel always shows Properties.
  // A picker-initiated session (the [-token picker armed the session purely to
  // feed itself via loadStepDataForPicker) must NOT switch to the Replay view:
  // the user stays in the field they are editing while step data loads into the
  // picker. Only an EXPLICIT Review action (which clears `pickerInitiatedSession`
  // in Flow) shows the Replay view.
  const liveEffectiveMode: 'event' | 'review' =
    replaySessionActive && !pickerInitiatedSession ? panelMode : 'event';

  // The "Review flow" (go-to-replay) button is rendered only for a single
  // selected node (see the header render below); this boolean controls whether
  // it is ENABLED (clickable) vs disabled. Enabled when the selected node has an
  // event-flow to review:
  //   • EVENT/start node with review available → start/resume its own session.
  //   • NON-event node that traces back to a starting event, resolved either by
  //     an existing session (`reviewableEventId`) OR STRUCTURALLY from the graph
  //     alone (`pickerOwningEventIdProp` via Flow.findOwningEventId) — so the
  //     button works BEFORE any review session has been loaded. Still gated on
  //     `reviewAvailable` so it stays disabled on new/unsaved/no-capability
  //     models.
  //   • Isolated contexts (tests/stories) with no handler keep the legacy
  //     `replaySessionActive` fallback so the control is operable.
  // Disabled otherwise (new/unsaved model, orphaned node that reaches no event,
  // no replay capability).
  const liveReviewButtonEnabled =
    (!!node && isStartNode && reviewAvailable) ||
    (!!node && !isStartNode && reviewAvailable && (!!reviewableEventId || !!pickerOwningEventIdProp)) ||
    (replaySessionActive && !onRequestReviewMode);

  // Snapshot the chrome derivations while the picker is open; resume live when
  // it closes. The ref write happens during render ONLY on the open→render
  // transition (no setState), so it cannot cause a render loop.
  //
  // No-gap invariant: in the SAME render where `pickerOpen` is first seen true,
  // we populate `frozenChromeRef` BEFORE selecting `effectiveMode` below — so
  // whenever `pickerOpen` is true the ref is guaranteed non-null. We therefore
  // select on `pickerOpen` ALONE (not `pickerOpen && ref`), which means there is
  // no render where `pickerOpen===true` yet live values leak through.
  const frozenChromeRef = useRef<{ effectiveMode: 'event' | 'review'; reviewButtonEnabled: boolean } | null>(null);
  if (pickerOpen) {
    if (frozenChromeRef.current === null) {
      frozenChromeRef.current = {
        effectiveMode: liveEffectiveMode,
        reviewButtonEnabled: liveReviewButtonEnabled,
      };
    }
  } else if (frozenChromeRef.current !== null) {
    frozenChromeRef.current = null;
  }

  // While open, frozenChromeRef.current is guaranteed populated (set just above
  // in this same render). Fall back to live ONLY when not open.
  const frozenChrome = frozenChromeRef.current;
  const effectiveMode: 'event' | 'review' =
    pickerOpen && frozenChrome ? frozenChrome.effectiveMode : liveEffectiveMode;
  const isReviewMode = effectiveMode === 'review';
  const reviewButtonEnabled =
    pickerOpen && frozenChrome ? frozenChrome.reviewButtonEnabled : liveReviewButtonEnabled;

  // Belt-and-braces: while the picker is open, NEVER swap the body to the
  // loading spinner — that would unmount the field hosting the open picker.
  // (Freezing the loader input above should already prevent a reload, but an
  // in-flight load must not unmount the field mid-picker either.)
  const showingLoading = !pickerOpen && !!node && loading;

  // Go to the Replay view. Always delegate to Flow's onRequestReviewMode, which
  // resolves the per-event action: RESUME the selected event's existing session,
  // START a new session for it (guarded), or RETURN to the currently-active
  // session when a non-event node is selected. Falls back to a direct switch in
  // isolated contexts (tests/stories) where no handler is supplied.
  const goToReplay = useCallback(() => {
    if (onRequestReviewMode) {
      onRequestReviewMode();
    } else {
      setPanelMode('review');
    }
  }, [onRequestReviewMode, setPanelMode]);

  // Go to the Properties view (of the currently-selected node). Direct and
  // unguarded — the replay session stays active and resumes on return.
  const goToProperties = useCallback(() => {
    setPanelMode('event');
  }, [setPanelMode]);

  // Token sources shared with the in-field "[" token picker (Phase 3). Provided
  // here because PropertyPanel already receives the lifted replay state from
  // Flow.tsx. The "@" picker reuses the SAME shapes as the Review-mode tree.
  const hasStepData = !!stepData && Object.keys(stepData).length > 0;
  // Review is event-scoped: the @-picker's "Review the flow" hint offers entry
  // only when the SELECTED node has an event-flow to review — an event node
  // with review available, OR a non-event node whose owning event has a session
  // (`reviewableEventId`). Isolated contexts (no handler) keep the legacy
  // `replaySessionActive` fallback. Otherwise the hint's button is omitted
  // (no-op) rather than entering a context-less review.
  const canReviewFromHint =
    (reviewAvailable && !!selectedStartNodeId) ||
    !!reviewableEventId ||
    (replaySessionActive && !onRequestReviewMode);

  // Feature J: the event whose flow OWNS the selected node, used to gate and
  // drive the @-picker's Step-data category. Prefer Flow's session-agnostic
  // structural resolution (`pickerOwningEventIdProp` via findOwningEventId) so
  // the category shows BEFORE any session exists. Fall back to the legacy local
  // derivation for isolated contexts (tests/stories) that supply no prop:
  // the session-resolved owning event, else the selected event when review is
  // available. null → no Step-data category (never a context-less load).
  const pickerOwningEventId: string | null =
    pickerOwningEventIdProp ??
    reviewableEventId ??
    (reviewAvailable && selectedStartNodeId ? selectedStartNodeId : null);

  // Feature J: the picker is a THIN VIEW over Flow's per-event sessions. The
  // dataset dropdown / listen item route through the SAME handlers the Review
  // panel uses (onSelectReplayEntry / onSelectListenItem), and on-demand loads
  // go through onLoadStepData (Flow.enterReviewForNode). The picker NEVER calls
  // useTestRunner/startTest/loadReplayData — guaranteeing ONE listener total.
  const tokenSources = useMemo<TokenSourceValue>(() => ({
    globalTokens,
    templateTokens,
    isTemplate,
    stepData,
    stepDataPredicted,
    hasStepData,
    onReviewModel: canReviewFromHint ? goToReplay : undefined,
    reviewAvailable: canReviewFromHint,
    // Feature J fields/callbacks.
    owningEventId: pickerOwningEventId,
    replayEntries,
    selectedEntryIndex: selectedReplayEntryIndex,
    isLoadingStepData: isReplayLoading,
    isListening: selectedReplayEntryIndex === LISTEN_ITEM_INDEX || isTestRunning || isTestInitiating,
    onLoadStepData,
    onSelectDataset: onSelectReplayEntry,
    onStartListen: onSelectListenItem,
    // Stop listening on Back: reuse the SAME cancel mechanism the Review panel's
    // cancel-on-select uses (Flow.handleCancelReview), so the single listener is
    // stopped and selection moves off the listen item.
    onStopListen: onCancelTest,
    // Freeze signal: lets PropertyPanel hold its review chrome while the picker
    // is open. Does NOT affect the LIVE data fields above.
    onPickerOpenChange: handlePickerOpenChange,
  }), [
    globalTokens,
    templateTokens,
    isTemplate,
    stepData,
    stepDataPredicted,
    hasStepData,
    canReviewFromHint,
    goToReplay,
    pickerOwningEventId,
    replayEntries,
    selectedReplayEntryIndex,
    isReplayLoading,
    isTestRunning,
    isTestInitiating,
    onLoadStepData,
    onSelectReplayEntry,
    onSelectListenItem,
    onCancelTest,
    handlePickerOpenChange,
  ]);

  const { startResize } = usePanelResize({
    panelWidth,
    setPanelWidth,
    setPanelResizing: setPanelResizing,
    direction: 'left',
  });

  // Check if we have multiple selections
  const hasMultipleSelection: boolean = selectedNodes.length > 1 || selectedEdges.length > 1 ||
                               (selectedNodes.length > 0 && selectedEdges.length > 0);

  // Debounced field handlers for node label
  const handleNodeLabelChange = useCallback((value: string) => {
    if (node && onConfigurationChange && !isLocked) {
      onConfigurationChange(node.id, { _componentLabel: value });
    }
  }, [node, onConfigurationChange, isLocked]);

  const nodeLabelField = useDebouncedField({
    initialValue: node?.data?.label || '',
    onDebouncedChange: handleNodeLabelChange,
    disabled: isLocked,
  });

  // Debounced field handlers for node annotation
  const handleNodeAnnotationChange = useCallback((value: string) => {
    if (node && onNodeUpdate && !isLocked) {
      onNodeUpdate(node.id, { ...node.data, annotation: value });
    }
  }, [node, onNodeUpdate, isLocked]);

  const nodeAnnotationField = useDebouncedField({
    initialValue: node?.data?.annotation || '',
    onDebouncedChange: handleNodeAnnotationChange,
    disabled: isLocked,
  });

  // Debounced field handlers for edge annotation
  const handleEdgeAnnotationChange = useCallback((value: string) => {
    if (edge && onEdgeUpdate && !isLocked) {
      onEdgeUpdate(edge.id, { ...edge.data, annotation: value });
    }
  }, [edge, onEdgeUpdate, isLocked]);

  const edgeAnnotationField = useDebouncedField({
    initialValue: edge?.data?.annotation || '',
    onDebouncedChange: handleEdgeAnnotationChange,
    disabled: isLocked,
  });

  // Reset field values when node/edge changes.
  // Flush any pending debounced changes first so edits to the *previous*
  // node/edge are not silently discarded when the selection switches.
  useEffect(() => {
    nodeLabelField.flush();
    nodeAnnotationField.flush();
    if (node) {
      nodeLabelField.setValue(node.data?.label || '');
      nodeAnnotationField.setValue(node.data?.annotation || '');
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [node?.id]);

  useEffect(() => {
    edgeAnnotationField.flush();
    if (edge) {
      edgeAnnotationField.setValue(edge.data?.annotation || '');
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [edge?.id]);

  // Handle click on collapsed panel to expand
  const handlePanelClick = useCallback((e: React.MouseEvent) => {
    if (propertyPanelCollapsed) {
      e.stopPropagation();
      togglePropertyPanelCollapse();
    }
  }, [propertyPanelCollapsed, togglePropertyPanelCollapse]);

  // Info popup state
  const [showInfoPopup, setShowInfoPopup] = useState(false);

  // Build metadata items for the info popup
  const infoItems: InfoItem[] = useMemo(() => {
    if (node) {
      return [
        { label: t('ID'), value: node.id, show: true },
        { label: t('Type'), value: node.type || '', show: true },
        { label: t('Plugin ID'), value: node.data?.plugin || '', show: !!node.data?.plugin },
      ];
    }
    if (edge) {
      return [
        { label: t('Connection Type'), value: t('Edge'), show: true },
        { label: t('Edge ID'), value: edge.id, show: true },
        { label: t('Source'), value: edge.source, show: true },
        { label: t('Target'), value: edge.target, show: true },
      ];
    }
    return [];
  }, [node, edge]);

  // Close info popup when selection changes
  useEffect(() => {
    setShowInfoPopup(false);
  }, [node?.id, edge?.id]);

  return (
    <Profiler id="PropertyPanel" onRender={onRenderCallback}>
    <TokenSourceContext.Provider value={tokenSources}>
    <div
      className={`workflow-property-panel ${panelIsResizing ? 'resizing' : ''} ${propertyPanelCollapsed ? 'collapsed' : ''}`}
      style={{ width: propertyPanelCollapsed ? `${PANEL_DIMENSIONS.PROPERTY_PANEL.COLLAPSED_WIDTH}px` : `${panelWidth}px` }}
      onClick={handlePanelClick}
      title={propertyPanelCollapsed ? t('Click to expand') : undefined}
    >
      <div
        className="resize-handle"
        onMouseDown={startResize}
        title={t('Drag to resize')}
      />
      <button
        className="panel-collapse-widget"
        onClick={togglePropertyPanelCollapse}
        title={propertyPanelCollapsed ? t('Expand panel') : t('Collapse panel')}
        aria-label={propertyPanelCollapsed ? t('Expand panel') : t('Collapse panel')}
      >
        <span className="collapse-icon">
          {propertyPanelCollapsed ? <FiChevronLeft /> : <FiChevronRight />}
        </span>
      </button>
      {propertyPanelCollapsed && (
        <div className="panel-collapsed-label">
          <span>{t('Properties')}</span>
        </div>
      )}
      <div className="panel-content">

      {/* Fixed 3-zone header grid: [ LABEL | ICONS | SWITCH ].
          Every state renders all three zones (label flexes via 1fr; icons and
          switch are auto-width), so elements never jump horizontally between
          states. The switch is the LAST grid column, so it is always pinned to
          the true right edge - even when the icons zone (the middle column) is
          empty, since an empty middle `auto` column simply collapses without
          affecting the last column's position.

          Properties view: the component icon/type label, the documentation and
          metadata icons, and the "Review flow" button.
          Review view: the label zone holds the SINGLE back control (left arrow
          + "Back"); the icons and switch zones render empty (their cells stay
          so the grid geometry is identical in both views).

          The "Loading..." throbber is NOT in the header - it renders in the
          body below (see .panel-loading). */}
      <div className="panel-header">
        {/* Zone 1 - label (left-anchored). In Review view this is the ONLY
            interactive header element: the back control to Properties. */}
        <div className="panel-header-label">
          {isReviewMode ? (
            <button
              type="button"
              className="panel-header-back"
              onClick={goToProperties}
              aria-label={t('Back to properties')}
              title={t('Back to properties (Alt+Shift+R)')}
            >
              <FiArrowLeft aria-hidden="true" />
              <span>{t('Back')}</span>
            </button>
          ) : hasMultipleSelection ? (
            <h3>{t('Multiple Selection')}</h3>
          ) : node || edge ? (
            <span className="component-info">
              {node ? getComponentIcon(node.type || 'element') : <FiGitBranch />}
              <span className="component-type">
                {node ? getComponentTypeName(node.type || 'element') : getComponentLabel('link')}
              </span>
            </span>
          ) : null}
        </div>

        {/* Zone 2 — icons (middle slot). Properties view only; stays an empty
            reserved cell in Review / multi / empty states. Placed BEFORE the
            switch zone in the DOM so it occupies the middle grid column,
            leaving the switch as the true last column. */}
        <div className="panel-header-icons">
          {!isReviewMode && !hasMultipleSelection && (node || edge) && (
            <>
              {node && documentationUrl && (
                <DocumentationButton
                  url={documentationUrl}
                  title={node.data?.label || t('Component')}
                  className="header-documentation-btn"
                  size={16}
                />
              )}
              <button
                className="header-info-btn"
                aria-label={t('Show metadata')}
                onClick={() => setShowInfoPopup(prev => !prev)}
                title={t('Show metadata')}
              >
                <FiInfo />
              </button>
            </>
          )}
        </div>

        {/* Zone 3 - "Review flow" button (fixed LAST slot, Properties view).
            The cell is ALWAYS rendered so its content is always pinned to the
            true right edge - regardless of whether the icons zone has content -
            fixing the "button jumps left/right between views" bug. It stays an
            empty reserved cell in the Review view, whose only control is the
            back button in zone 1. */}
        <div className="panel-header-switch">
          {!isReviewMode && !hasMultipleSelection && !!node && reviewAvailable ? (
            /* "Review flow" (go-to-replay) button — rendered ONLY when EXACTLY
               ONE NODE is selected (not on empty, multi-select, or a single
               EDGE — `node` is null in those cases) AND the model has replay/
               test capability (a saved model with replay/test URL or embedded
               data). Within that case the button is ENABLED when the selected
               node has an event-flow to review; DISABLED otherwise, with an
               explanatory tooltip. When the button is hidden, the empty last
               cell above still renders so the header layout stays stable (no
               horizontal shift between states). */
            <button
              type="button"
              className="header-review-btn"
              onClick={goToReplay}
              disabled={!reviewButtonEnabled}
              aria-disabled={!reviewButtonEnabled}
              aria-label={t('Review flow')}
              title={
                reviewButtonEnabled
                  ? t('Review flow (Alt+Shift+R)')
                  : t('Review is available once this step belongs to an executable event flow.')
              }
            >
              <FiActivity aria-hidden="true" />
              <span>{t('Review flow')}</span>
            </button>
          ) : null}
        </div>
      </div>

      {!isReviewMode && showInfoPopup && infoItems.length > 0 && (
        <InfoPopup items={infoItems} onClose={() => setShowInfoPopup(false)} />
      )}

      {isReviewMode ? (
        <ReplayPanelContent
          replayData={replayData}
          isReplayMode={isReplayMode}
          onToggleReplay={onToggleReplay ?? (() => {})}
          onSelectStep={onSelectReplayStep ?? (() => {})}
          currentStep={currentReplayStep}
          stepData={stepData}
          stepDataPredicted={stepDataPredicted}
          stepInfo={stepInfo}
          edges={reviewEdges}
          nodes={reviewNodes}
          replayEntries={replayEntries}
          selectedEntryIndex={selectedReplayEntryIndex}
          onSelectReplayEntry={onSelectReplayEntry}
          onSelectListenItem={onSelectListenItem}
          backendMessage={backendMessage}
          selectedStartNodeId={selectedStartNodeId}
          hasReplayUrl={hasReplayUrl}
          hasTestUrl={hasTestUrl}
          isTestRunning={isTestRunning}
          isTestInitiating={isTestInitiating}
          testError={testError}
          onStartTest={onStartTest}
          onCancelTest={onCancelTest}
        />
      ) : hasMultipleSelection ? (
        <MultiSelectionPanel
          selectedNodes={selectedNodes}
          selectedEdges={selectedEdges}
          onDeleteSelected={onDeleteSelected}
          isLocked={isLocked}
        />
      ) : !node && !edge ? (
        <div className="panel-content empty">
          <p>{t('Select a component or connection to view its properties')}</p>
        </div>
      ) : showingLoading ? (
        /* Loading the node's configuration form: show a centered spinner in the
           panel BODY (the form area is empty anyway). The throbber deliberately
           lives here, not in the header, so no header element is displaced.
           Suppressed while the [-token picker is open (see `showingLoading`) so
           the field hosting the picker is never unmounted by a loading swap. */
        <div className="panel-loading" role="status" aria-live="polite">
          <span className="panel-loading-spinner" aria-hidden="true" />
          <span>{t('Loading...')}</span>
        </div>
      ) : node ? (
        <NodePropertiesPanel
          node={node}
          configurationForm={configurationForm}
          onConfigurationChange={onConfigurationChange}
          onNodeUpdate={onNodeUpdate}
          isLocked={isLocked}
          nodeLabelField={nodeLabelField}
          nodeAnnotationField={nodeAnnotationField}
        />
      ) : edge ? (
        <EdgePropertiesPanel
          edge={edge}
          onEdgeUpdate={onEdgeUpdate}
          isLocked={isLocked}
          edgeAnnotationField={edgeAnnotationField}
        />
      ) : null}
      </div>
    </div>
    </TokenSourceContext.Provider>
    </Profiler>
  );
};

export default PropertyPanel;
