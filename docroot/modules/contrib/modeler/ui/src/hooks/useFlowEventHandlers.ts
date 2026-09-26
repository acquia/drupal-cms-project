/**
 * Custom hook for ReactFlow event handlers
 * Handles node/edge interactions, connections, selections, and canvas events
 */

import { useCallback, useRef } from 'react';
import type { OnConnectStartParams } from 'reactflow';
import { useGraphStore } from '../store/useGraphStore';
import { useSelectionStore } from '../store/useSelectionStore';
import type { StoreNode as Node, StoreEdge as Edge, ModelConstraints } from '../types/settings';
import { generateUniqueEdgeId } from '../utils/clipboardUtils';
import { routeParallelEdge } from '../utils/parallelEdgeRouter';
import { computeConditionReconnectEdges } from '../utils/conditionReconnect';
import { isConditionNode } from '../utils/incrementalLayout';
import { isValidConnection as isValidConnectionShared } from '../utils/connectionValidation';
import { hitTestDropTarget, DESTINATION_HANDLE_ID } from './useEndpointDrag';
import { t } from '../utils/translation';

interface UseFlowEventHandlersProps {
  handleCanvasNodeClick: (node: Node) => void;
  handleCanvasEdgeClick: (edge: Edge) => void;
  setHasUnsavedChanges: (value: boolean) => void;
  /** General syncing flag (both directions) — used to guard autoSyncToReplay */
  isSyncing: boolean;
  /** Replay-to-canvas syncing ref — used to skip stale onSelectionChange events */
  isReplaySyncingRef: React.RefObject<boolean>;
  hasReplayData: boolean;
  isReplayMode: boolean;
  currentReplayStep: number;
  autoSyncToReplay: (node: Node | null) => void;
  /** Screen reader announcement callback */
  announce?: (text: string) => void;
  /** Callback to save history before making changes */
  saveHistory?: () => void;
  /**
   * Successor-cardinality / wiring constraints. Used to validate a NEW edge
   * created by dropping onto a node BODY (issue #3585553 follow-on UX), with
   * the SAME shared rules as the canvas `isValidConnection` prop and the
   * endpoint-reconnect commit path — so all three can never diverge.
   */
  modelConstraints?: ModelConstraints;
}

export function useFlowEventHandlers({
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
}: UseFlowEventHandlersProps) {
  
  // Store state
  const nodes = useGraphStore(state => state.nodes);
  const edges = useGraphStore(state => state.edges);
  const setNodes = useGraphStore(state => state.setNodes);
  const setEdges = useGraphStore(state => state.setEdges);
  const removeNode = useGraphStore(state => state.removeNode);
  const applyNodeChangesToStore = useGraphStore(state => state.applyNodeChanges);
  const applyEdgeChangesToStore = useGraphStore(state => state.applyEdgeChanges);
  const setSelectedNode = useSelectionStore(state => state.setSelectedNode);
  const setSelectedEdge = useSelectionStore(state => state.setSelectedEdge);
  const setSelectedNodes = useSelectionStore(state => state.setSelectedNodes);
  const setSelectedEdges = useSelectionStore(state => state.setSelectedEdges);

  // Guard against stale onSelectionChange events after a pane click.
  // ReactFlow may fire onSelectionChange with the previously selected
  // node/edge *after* onPaneClick has already cleared the selection.
  // The ref is set by onPaneClick and consumed (reset) by the next
  // onSelectionChange that carries an empty selection.
  const paneClickedRef = useRef(false);

  // Snapshot node position at drag start so onNodeDragStop can detect
  // whether the node actually moved.  ReactFlow fires onNodeDragStop even
  // on simple clicks (zero-movement "drags"), so we need this comparison.
  const dragStartPositions = useRef<Record<string, { x: number; y: number }>>({});

  // New-edge "drop onto node body" support (issue #3585553 follow-on UX).
  //
  // React Flow fires `onConnect` ONLY when a new-edge drag is released over a
  // valid HANDLE; it does nothing when released over a node's body (off-handle)
  // or empty canvas. To match the reconnect drop-onto-node behavior, we:
  //  - record the gesture's origin in `onConnectStart` (connectStartRef),
  //  - mark `connectMadeRef` true inside `onConnect` (a handle WAS hit), and
  //  - in `onConnectEnd` (which fires on ANY release), if `onConnect` did NOT
  //    fire, hit-test the node under the pointer and create the edge to its
  //    target/input handle — using the SAME validation as onConnect.
  // The flags live in refs (not state) so they survive across the synchronous
  // onConnectStart → [onConnect] → onConnectEnd sequence without re-rendering.
  const connectStartRef = useRef<{ nodeId: string; handleId: string | null } | null>(null);
  const connectMadeRef = useRef(false);

  // ReactFlow change handlers
  const onNodesChange = useCallback((changes: any[]) => {
    applyNodeChangesToStore(changes);
  }, [applyNodeChangesToStore]);

  const onEdgesChange = useCallback((changes: any[]) => {
    applyEdgeChangesToStore(changes);
  }, [applyEdgeChangesToStore]);

  // Handle selection changes from canvas
  const onSelectionChange = useCallback(({ nodes: selectedNodes, edges: selectedEdges }: { nodes: Node[]; edges: Edge[] }) => {
    // During replay-to-canvas sync (clicking a step in the replay panel),
    // programmatic node/edge updates cause ReactFlow to fire onSelectionChange
    // with stale data (e.g. previously selected node still appearing alongside
    // the newly selected edge). The selection store is already set correctly
    // by the sync code, so skip ReactFlow selection events while the
    // replay-to-canvas sync is in progress.
    if (isReplaySyncingRef.current) {
      return;
    }

    const isEmpty = selectedNodes.length === 0 && selectedEdges.length === 0;

    if (paneClickedRef.current) {
      if (isEmpty) {
        // This is the confirming empty event after the pane click — consume
        // the flag and proceed normally to clear selection state.
        paneClickedRef.current = false;
      } else {
        // Stale event carrying the previous selection — ignore it.
        return;
      }
    }

    // Handle single selection for primary property display
    const newSelectedNode = selectedNodes[0] || null;
    const newSelectedEdge = selectedEdges[0] || null;
    
    setSelectedNode(newSelectedNode);
    setSelectedEdge(newSelectedEdge);
    
    // Handle multi-selection for property panel
    const nodeIds = selectedNodes.map((node: Node) => node.id);
    const edgeIds = selectedEdges.map((edge: Edge) => edge.id);
    
    setSelectedNodes(nodeIds);
    setSelectedEdges(edgeIds);
    
    // Auto-sync to replay if not already syncing
    if (!isSyncing && hasReplayData && (!isReplayMode || currentReplayStep === -1)) {
      autoSyncToReplay(newSelectedNode);
    }
  }, [
    setSelectedNode,
    setSelectedEdge,
    setSelectedNodes,
    setSelectedEdges,
    isSyncing,
    isReplaySyncingRef,
    hasReplayData,
    isReplayMode,
    currentReplayStep,
    autoSyncToReplay
  ]);

  // Handle node clicks
  const onNodeClick = useCallback((event: React.MouseEvent, node: Node) => {
    handleCanvasNodeClick(node);
  }, [handleCanvasNodeClick]);

  // Handle edge clicks  
  const onEdgeClick = useCallback((event: React.MouseEvent, edge: Edge) => {
    handleCanvasEdgeClick(edge);
  }, [handleCanvasEdgeClick]);

  // Handle individual node deletion (from node component).
  //
  // When the deleted node is a condition node (issue #3589093) with exactly
  // one inbound and one outbound edge, we must reconnect its predecessor
  // directly to its successor so the downstream branch is not orphaned —
  // mirroring pluginApi.removeCondition.  For all other nodes we delegate to
  // useGraphStore.removeNode (drops the node and its connected edges).
  // Selection cleanup happens automatically via useSelectionStore's
  // subscription to graph-state changes.
  const onDeleteNode = useCallback((nodeId: string) => {
    const reconnectEdges = computeConditionReconnectEdges(
      nodes,
      edges,
      new Set([nodeId]),
    );
    if (reconnectEdges.length > 0) {
      setNodes(prev => prev.filter(n => n.id !== nodeId));
      setEdges(prev => [
        ...prev.filter(e => e.source !== nodeId && e.target !== nodeId),
        ...reconnectEdges,
      ]);
    } else {
      removeNode(nodeId);
    }
    setHasUnsavedChanges(true);
    if (announce) {
      announce(t('Element deleted.'));
    }
  }, [nodes, edges, setNodes, setEdges, removeNode, setHasUnsavedChanges, announce]);

  // Handle individual edge deletion (from the edge's trash affordance).
  //
  // Deleting an edge is simply removing it — unlike onDeleteNode there is NO
  // condition-reconnect logic (that is node-specific).  Mirrors the keyboard
  // delete path's history/dirty/announce behavior so undo works identically.
  const onDeleteEdge = useCallback((edgeId: string) => {
    if (saveHistory) saveHistory();
    setEdges(prev => prev.filter(e => e.id !== edgeId));
    setHasUnsavedChanges(true);
    if (announce) {
      announce(t('Connection deleted.'));
    }
  }, [setEdges, setHasUnsavedChanges, announce, saveHistory]);

  // Handle delete selected elements.
  //
  // Condition nodes (issue #3589093) being deleted are reconnected
  // predecessor → successor so the graph stays connected — but only when
  // both endpoints survive the deletion (computeConditionReconnectEdges
  // skips reconnects whose predecessor or successor is also being deleted,
  // avoiding dangling edges).  All removals are applied in a single
  // setNodes/setEdges pass.  Selection cleanup happens automatically via
  // useSelectionStore's subscription to graph-state changes.
  const handleDeleteSelected = useCallback(() => {
    const nodesToDelete = nodes.filter(node => node.selected);
    const edgesToDelete = edges.filter(edge => edge.selected);

    if (nodesToDelete.length === 0 && edgesToDelete.length === 0) return;

    if (saveHistory) saveHistory();

    const nodeIdsToDelete = new Set(nodesToDelete.map(n => n.id));
    const edgeIdsToDelete = new Set(edgesToDelete.map(e => e.id));

    // Reconnect predecessor → successor for any well-formed condition node
    // being deleted (skips dangling cases internally).
    const reconnectEdges = computeConditionReconnectEdges(
      nodes,
      edges,
      nodeIdsToDelete,
    );

    setNodes(prev => prev.filter(n => !nodeIdsToDelete.has(n.id)));
    setEdges(prev => [
      ...prev.filter(
        e =>
          !edgeIdsToDelete.has(e.id) &&
          !nodeIdsToDelete.has(e.source) &&
          !nodeIdsToDelete.has(e.target),
      ),
      ...reconnectEdges,
    ]);

    setHasUnsavedChanges(true);

    // Announce deletion count to screen readers
    if (announce) {
      const totalDeleted = nodesToDelete.length + edgesToDelete.length;
      announce(t('@count elements deleted.', { '@count': String(totalDeleted) }));
    }
  }, [
    nodes,
    edges,
    setNodes,
    setEdges,
    setHasUnsavedChanges,
    announce,
    saveHistory
  ]);

  // Handle new connections between nodes.
  //
  // When the user drags a new edge between two nodes that already have a
  // connection (either directly via a parallel edge, or indirectly via a
  // chain of intermediate nodes), the new edge would visually overlap the
  // existing connection. We delegate to `routeParallelEdge` to compute a
  // sideways `controlOffset` for the new edge — and, when appropriate, to
  // rebalance offsets across the sibling group — so that all connections
  // remain visually distinguishable without moving any nodes.
  // Core edge-creation routine shared by BOTH the handle-drop path (onConnect)
  // and the node-body-drop path (onConnectEnd → resolved node). Validates with
  // the SAME shared rules, then creates the edge with parallel-edge routing.
  // Returns true when an edge was created, false when the connection was
  // rejected (invalid / duplicate-guarded).
  const createNewEdge = useCallback((connection: {
    source: string | null;
    target: string | null;
    sourceHandle?: string | null;
    targetHandle?: string | null;
  }): boolean => {
    if (!connection.source || !connection.target) return false;

    // Shared wiring validation (condition→condition block, condition 1-outbound,
    // successor cardinality). The SAME function backs the canvas
    // isValidConnection prop and the reconnect commit path, so a new edge
    // dropped on a node body can never bypass a rule the handle path enforces.
    if (!isValidConnectionShared({
      connection: {
        source: connection.source,
        target: connection.target,
        sourceHandle: connection.sourceHandle ?? null,
        targetHandle: connection.targetHandle ?? null,
      },
      nodes,
      edges,
      modelConstraints,
    })) {
      return false;
    }

    // Defensive 1-outbound guard for condition nodes (issue #3589093).
    // isValidConnectionShared already enforces this, but keep the explicit
    // guard so the rule is obvious at the creation site.
    const connectionSource = connection.source;
    const sourceNode = nodes.find(n => n.id === connectionSource);
    if (isConditionNode(sourceNode)) {
      const sourceOutgoing = edges.filter(e => e.source === connectionSource).length;
      if (sourceOutgoing >= 1) {
        return false;
      }
    }

    if (saveHistory) saveHistory();
    const id = generateUniqueEdgeId();
    const newEdge: Edge = {
      id,
      source: connection.source,
      target: connection.target,
      // Persist the canonical handles so a stored edge always carries them
      // (each node exposes one source `output` and one target `input` handle).
      // The handle-drop path passes them through React Flow; the node-body
      // drop path infers them — either way the saved edge is consistent and
      // round-trips identically to model-loaded edges.
      sourceHandle: connection.sourceHandle ?? 'output',
      targetHandle: connection.targetHandle ?? 'input',
      type: 'default',
      data: {},
    };

    // Compute parallel-edge routing against the current graph state. The
    // result is empty when no collision exists; otherwise it contains
    // controlOffset updates for the new edge and (when rebalancing) for
    // existing siblings whose offsets are still at the default zero.
    const route = routeParallelEdge({
      newEdge,
      edges: [...edges, newEdge],
      nodes,
    });

    // Build an id → controlOffset lookup so we can apply updates in a
    // single setEdges pass without iterating multiple times.
    const offsetUpdates = new Map<string, { x: number; y: number }>();
    for (const update of route.updates) {
      offsetUpdates.set(update.edgeId, update.controlOffset);
    }

    // Seed the new edge's controlOffset directly on the object we append.
    const newEdgeOffset = offsetUpdates.get(id);
    if (newEdgeOffset) {
      newEdge.data = { ...newEdge.data, controlOffset: newEdgeOffset };
      offsetUpdates.delete(id);
    }

    setEdges(prev => {
      // Apply offset updates to existing edges (rebalanced siblings).
      const updated = offsetUpdates.size > 0
        ? prev.map(edge => {
            const offset = offsetUpdates.get(edge.id);
            if (!offset) return edge;
            return {
              ...edge,
              data: { ...(edge.data || {}), controlOffset: offset },
            };
          })
        : prev;
      return [...updated, newEdge];
    });
    setHasUnsavedChanges(true);
    return true;
  }, [edges, nodes, modelConstraints, setEdges, setHasUnsavedChanges, saveHistory]);

  const onConnect = useCallback((connection: { source: string | null; target: string | null; sourceHandle?: string | null; targetHandle?: string | null }) => {
    // A valid HANDLE was hit — record it so onConnectEnd does NOT also try to
    // create an edge from the node-body fallback (avoids a duplicate edge).
    connectMadeRef.current = true;
    createNewEdge(connection);
  }, [createNewEdge]);

  // New-edge gesture start: remember the originating node + handle so the
  // node-body fallback in onConnectEnd knows the source of the prospective
  // edge. Reset the "handle was hit" flag for this fresh gesture.
  const onConnectStart = useCallback((
    _event: React.MouseEvent | React.TouchEvent,
    params: OnConnectStartParams,
  ) => {
    connectMadeRef.current = false;
    connectStartRef.current = params.nodeId
      ? { nodeId: params.nodeId, handleId: params.handleId ?? null }
      : null;
  }, []);

  // New-edge gesture end: fires on ANY release. If onConnect already created
  // the edge (a handle was hit), do nothing. Otherwise resolve the NODE under
  // the pointer and create the edge to its target/input handle — matching the
  // reconnect "drop onto node body" behavior (issue #3585553 follow-on UX).
  // Snap-back (no edge) when released over empty canvas or on an invalid target.
  const onConnectEnd = useCallback((event: MouseEvent | TouchEvent) => {
    const start = connectStartRef.current;
    connectStartRef.current = null;
    // Handle was hit → onConnect already handled it; nothing to do here.
    if (connectMadeRef.current) {
      connectMadeRef.current = false;
      return;
    }
    if (!start) return;

    // Resolve the client coordinates of the release (mouse or touch).
    const point = 'changedTouches' in event && event.changedTouches.length > 0
      ? event.changedTouches[0]
      : (event as MouseEvent);
    const clientX = point.clientX;
    const clientY = point.clientY;

    // A new edge's destination is always the target end, so hit-test as the
    // 'target' endpoint — reusing the SAME node-level DOM hit-test (and its
    // grip-overlay hardening) as reconnect. Returns the node under the cursor
    // with its canonical input handle inferred.
    const drop = hitTestDropTarget(clientX, clientY, 'target');
    // Released over empty canvas / no node → snap back (no edge).
    if (!drop) return;
    // No self-loops: dropping back on the source node creates nothing.
    if (drop.nodeId === start.nodeId) return;

    const created = createNewEdge({
      source: start.nodeId,
      sourceHandle: start.handleId ?? DESTINATION_HANDLE_ID.source,
      target: drop.nodeId,
      targetHandle: drop.handleId ?? DESTINATION_HANDLE_ID.target,
    });

    if (created && announce) {
      announce(t('Connection created.'));
    }
  }, [createNewEdge, announce]);

  // Handle canvas pane clicks (deselect all)
  const onPaneClick = useCallback(() => {
    paneClickedRef.current = true;
    setSelectedNode(null);
    setSelectedEdge(null);
  }, [setSelectedNode, setSelectedEdge]);

  // Handle the start of a rubber-band (mouse-drag) selection gesture.
  //
  // Bug fix (issue #3589101 follow-up): the paneClickedRef guard in
  // onSelectionChange swallows the stale NON-empty selection echo React Flow
  // fires right after a pane *click* (a click with no drag). That guard is
  // cleared only by the subsequent confirming EMPTY onSelectionChange — but
  // when the user pane-clicks and then immediately starts a NEW drag-select,
  // React Flow's pane mousedown calls resetSelectedElements() while the
  // selection is already empty, so NO empty onSelectionChange fires to clear
  // the flag. The guard then wrongly discards the drag-select's legitimate
  // non-empty event, leaving the store (and thus Copy / the multi-selection
  // panel) untouched.
  //
  // React Flow fires onSelectionStart from the pane mousedown that BEGINS a
  // rubber-band drag (Pane.onMouseDown), strictly BEFORE onMouseMove produces
  // the selection. Clearing the guard here means any real drag-select cancels
  // a pending post-pane-click guard, so its real selection event is honored —
  // while a stale echo after a plain pane click (which has no onSelectionStart)
  // is still suppressed.
  const onSelectionStart = useCallback(() => {
    paneClickedRef.current = false;
  }, []);

  // Handle node drag start — snapshot the position so we can detect actual movement
  // Save history here (before the drag changes any positions) so that undo
  // restores the pre-drag state.  The snapshot is only committed when
  // onNodeDragStop detects that the node actually moved.
  const onNodeDragStart = useCallback((_event: React.MouseEvent, node: Node) => {
    dragStartPositions.current[node.id] = { x: node.position.x, y: node.position.y };
    if (saveHistory) saveHistory();
  }, [saveHistory]);

  // Handle node drag stop — mark dirty only if the node actually moved
  const onNodeDragStop = useCallback((_event: React.MouseEvent, node: Node) => {
    const startPos = dragStartPositions.current[node.id];
    delete dragStartPositions.current[node.id];
    if (startPos && (startPos.x !== node.position.x || startPos.y !== node.position.y)) {
      setHasUnsavedChanges(true);
    }
  }, [setHasUnsavedChanges]);

  return {
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
  };
}