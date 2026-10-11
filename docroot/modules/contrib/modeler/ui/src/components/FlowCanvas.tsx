import React, { Profiler, useCallback, useMemo } from 'react';
import ReactFlow, {
  Node,
  Edge,
  Connection,
  NodeChange,
  EdgeChange,
  ConnectionLineType,
  MarkerType,
  SelectionMode,
  OnSelectionChangeParams,
  OnConnectStartParams,
  MiniMap,
  Viewport,
  PanOnScrollMode,
  EdgeLabelRenderer,
} from 'reactflow';
import type { ReplayStep } from '../hooks/useSimpleReplaySync';
import type { StoreComponent, EdgeData, NodeData, ModelConstraints } from '../types/settings';
import { useEdgeOrdering } from '../hooks/useEdgeOrdering';
import { useSuppressNodesSelectionBox } from '../hooks/useSuppressNodesSelectionBox';
import { isConditionNode } from '../utils/incrementalLayout';
import { isValidConnection as isValidConnectionShared } from '../utils/connectionValidation';
import { useUISettingsStore } from '../store/useUISettingsStore';

// Node Components
import CustomNode from './nodes/CustomNode';
import StartNode from './nodes/StartNode';
import GatewayNode from './nodes/GatewayNode';
import SubprocessNode from './nodes/SubprocessNode';
import PlaceholderNode from './nodes/PlaceholderNode';
import ConditionNode from './nodes/ConditionNode';

// Edge Components
import DefaultEdge from './edges/DefaultEdge';
import ConnectionLine from './edges/ConnectionLine';

import { onRenderCallback } from '../utils/profiling';
import { VIEWPORT } from '../constants/dimensions';

/** ReactFlow event handlers passed through to the canvas. */
interface FlowCanvasEventHandlers {
  onNodesChange: (changes: NodeChange[]) => void;
  onEdgesChange: (changes: EdgeChange[]) => void;
  onConnect: (connection: Connection) => void;
  onSelectionChange: (params: OnSelectionChangeParams) => void;
  onConnectStart: (event: React.MouseEvent | React.TouchEvent, params: OnConnectStartParams) => void;
  onConnectEnd: (event: MouseEvent | TouchEvent) => void;
  onNodeClick: (event: React.MouseEvent, node: Node) => void;
  onEdgeClick: (event: React.MouseEvent, edge: Edge) => void;
  onPaneClick: (event: React.MouseEvent) => void;
  onSelectionStart: (event: React.MouseEvent) => void;
  onNodeDragStart: (event: React.MouseEvent, node: Node) => void;
  onNodeDragStop: (event: React.MouseEvent, node: Node) => void;
  onInit: (instance: Record<string, unknown>) => void;
}

/** Callbacks for updating individual nodes/edges. */
interface FlowCanvasElementCallbacks {
  onEdgeUpdate?: (edgeId: string, updates: Partial<EdgeData>) => void;
  onNodeUpdate?: (nodeId: string, newData: Partial<NodeData>) => void;
  onDeleteNode?: (nodeId: string) => void;
  // onDeleteEdge lives in elementCallbacks (next to onDeleteNode) because it
  // is an element-deletion callback — the semantic peer of onDeleteNode —
  // not a quick-add affordance. It is threaded into each edge's data below.
  onDeleteEdge?: (edgeId: string) => void;
  /**
   * Commit an endpoint reconnection (issue #3585553). Updates top-level edge
   * fields (`source`/`target`/`sourceHandle`/`targetHandle`). Threaded into
   * each edge's data so the rendered grips can commit a move.
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
}

/** Keyboard modifier key state. */
interface FlowCanvasModifierKeys {
  isShiftPressed: boolean;
  isCtrlPressed: boolean;
  isAltPressed: boolean;
}

/** Toggle flags controlling canvas UI features. */
interface FlowCanvasUIState {
  isDragActive: boolean;
  isLocked: boolean;
  showEdgeOrderNumbers: boolean;
  showAllAnnotations: boolean;
}

/** Search-related state. */
interface FlowCanvasSearchState {
  searchTerm: string;
  highlightedSearchResult: { id: string; type: string } | null;
}

/** Replay visualization state. */
interface FlowCanvasReplayState {
  replayData: ReplayStep[];
  currentReplayStep: number;
  isReplayMode: boolean;
  replayIndicators: Array<{
    id: string;
    x: number;
    y: number;
    color: string;
  }>;
}

/** Quick-add node/condition callbacks. */
interface FlowCanvasQuickAddProps {
  onQuickAdd?: (component: StoreComponent, sourceNodeId: string) => void;
  onAddCondition?: (edgeId: string, component: StoreComponent) => void;
  onAddActionOnEdge?: (edgeId: string, component: StoreComponent) => void;
  onReplacePlaceholder?: (nodeId: string, component: StoreComponent) => void;
}

interface FlowCanvasProps {
  // Data
  nodes: Node[];
  edges: Edge[];

  // ReactFlow event handlers
  eventHandlers: FlowCanvasEventHandlers;

  // Element update callbacks
  elementCallbacks: FlowCanvasElementCallbacks;

  // Viewport
  viewport: Viewport;

  // Modifier keys
  modifierKeys: FlowCanvasModifierKeys;

  // UI state
  uiState: FlowCanvasUIState;

  // Search
  search: FlowCanvasSearchState;

  // Replay
  replay: FlowCanvasReplayState;

  // Edge ordering
  setEdges: (updater: (edges: Edge[]) => Edge[]) => void;
  setHasUnsavedChanges: (value: boolean) => void;

  // Quick add
  quickAdd: FlowCanvasQuickAddProps;

  // Model constraints (successor cardinality per component type)
  modelConstraints?: ModelConstraints;
}

// Node types configuration
const nodeTypes = {
  customNode: CustomNode,
  element: CustomNode,  // 'element' type uses CustomNode component
  start: StartNode,
  gateway: GatewayNode,
  subprocess: SubprocessNode,
  placeholder: PlaceholderNode,
  condition: ConditionNode,  // conditions are first-class nodes (issue #3589093)
};

// Edge types configuration
const edgeTypes = {
  default: DefaultEdge,
};

const FlowCanvas: React.FC<FlowCanvasProps> = ({
  nodes,
  edges,
  eventHandlers,
  elementCallbacks,
  viewport: _viewport,
  modifierKeys,
  uiState,
  search,
  replay,
  setEdges,
  setHasUnsavedChanges,
  quickAdd,
  modelConstraints,
}) => {
  // Destructure grouped props for use in the component body
  const {
    onNodesChange, onEdgesChange, onConnect, onSelectionChange,
    onConnectStart, onConnectEnd, onNodeClick, onEdgeClick, onPaneClick,
    onSelectionStart, onNodeDragStart, onNodeDragStop, onInit,
  } = eventHandlers;
  const { onEdgeUpdate, onNodeUpdate, onDeleteNode, onDeleteEdge, onReconnectEdge } = elementCallbacks;
  const { isShiftPressed: _isShiftPressed, isCtrlPressed: _isCtrlPressed, isAltPressed: _isAltPressed } = modifierKeys;
  const { isDragActive, isLocked, showEdgeOrderNumbers, showAllAnnotations } = uiState;
  const { searchTerm, highlightedSearchResult } = search;
  const { replayData, currentReplayStep, isReplayMode, replayIndicators } = replay;
  const { onQuickAdd, onAddCondition, onAddActionOnEdge, onReplacePlaceholder } = quickAdd;
  // Edge ordering hook - handles drag/drop reordering and order info calculation
  const {
    handleDragStart,
    handleDragEnd,
    handleEdgeOrderDrop,
    handleReorderEdge,
    getEdgeOrderInfo,
  } = useEdgeOrdering({ edges, nodes, setEdges, setHasUnsavedChanges });

  // Selection mode: always partial so partially-overlapped nodes are included
  const selectionMode = SelectionMode.Partial;

  // Make rubber-band (mouse-drag) multi-select behave identically to
  // Shift+click multi-select (issue #3589101): React Flow activates the
  // NodesSelection bounding box on a drag-select mouse-up, but not on
  // Shift+click. This handler clears that flag when the drag-select ends so
  // no bounding box appears and both gestures land in the same state.
  const { onSelectionEnd } = useSuppressNodesSelectionBox();

  // True while an endpoint reconnect drag is in progress (issue #3585553).
  // Drives the `reconnect-dragging` wrapper class so CSS makes ALL grips
  // non-interactive during the drag, letting the drop hit-test reach the
  // node/handle beneath any non-dragged grip overlay.
  const reconnectDragActive = useUISettingsStore((s) => s.reconnectDragActive);

  // No modifier-based cursor classes needed with the Figma-like gesture scheme.
  // ReactFlow handles cursors internally (default for selection, grab for Space+drag).

  // Per-handle counts of SELECTED edge endpoints (issue #3585553).
  //
  // Endpoint reconnection is per-handle and selection-gated: an endpoint is a
  // draggable grip IFF its handle hosts EXACTLY ONE selected edge endpoint.
  // We tally, across the selected edges only, how many use each source handle
  // and each target handle. A handle key combines the node id with the handle
  // id (null handle → 'null') so distinct handles on the same node are
  // counted separately. Both ends of every selected edge are tallied
  // independently.
  //
  // CRITICAL (bug fix for sequential shift-select): selection truth here is
  // React Flow's own per-edge `selected` flag on the `edges` array — the SAME
  // flag that drives `isEdgeSelected` (and therefore grip visibility) inside
  // DefaultEdge. Using the store `selectedEdges` array instead would let the
  // count and the visibility diverge (the store array can lag the `selected`
  // flag during multiselect), causing a shared handle to wrongly keep its grip
  // when 2+ selected edges use it. Tallying over `edge.selected` keeps the
  // count and the grip render perfectly consistent regardless of selection
  // order or which selection path fired.
  const selectedHandleCounts = useMemo(() => {
    const sourceCounts: Record<string, number> = {};
    const targetCounts: Record<string, number> = {};
    const key = (nodeId: string, handleId?: string | null) => `${nodeId}|${handleId ?? 'null'}`;
    for (const edge of edges) {
      if (!edge.selected) continue;
      const sKey = key(edge.source, edge.sourceHandle);
      const tKey = key(edge.target, edge.targetHandle);
      sourceCounts[sKey] = (sourceCounts[sKey] ?? 0) + 1;
      targetCounts[tKey] = (targetCounts[tKey] ?? 0) + 1;
    }
    return { sourceCounts, targetCounts, key };
  }, [edges]);

  // Validate a reconnection drop using the SAME logic as the new-edge
  // isValidConnection prop, excluding the edge being moved from outbound
  // counts (so moving a target off a node does not count the edge against its
  // own source's limits).
  const makeValidateReconnect = useCallback(
    (edgeId: string) => (connection: Connection) =>
      isValidConnectionShared({ connection, nodes, edges, modelConstraints, excludeEdgeId: edgeId }),
    [nodes, edges, modelConstraints],
  );

  // Enhanced edges with order numbers and drag handlers
  const enhancedEdges = useMemo(() => {
    return edges.map((edge, _index) => {
      const edgeOrderInfo = getEdgeOrderInfo(edge, edges);

      // Endpoint reconnection grip eligibility (issue #3585553). A grip is
      // shown only when this edge is selected AND it is the SOLE selected edge
      // using that handle (count === 1). When 2+ selected edges share a handle
      // the count is >= 2 and the handle is ambiguous → no grip there.
      // `isSelected` uses React Flow's per-edge `selected` flag (the same truth
      // as the tally and as DefaultEdge's `isEdgeSelected`) so eligibility and
      // visibility can never diverge.
      const isSelected = !!edge.selected;
      const sourceKey = selectedHandleCounts.key(edge.source, edge.sourceHandle);
      const targetKey = selectedHandleCounts.key(edge.target, edge.targetHandle);
      const sourceGripEnabled =
        !isLocked && isSelected && selectedHandleCounts.sourceCounts[sourceKey] === 1;
      const targetGripEnabled =
        !isLocked && isSelected && selectedHandleCounts.targetCounts[targetKey] === 1;

      // Conditions are first-class nodes now (issue #3589093), so no rendered
      // edge ever carries a condition.  Every edge is plain and can always
      // offer the "add condition" / "add action" quick-add affordances.

      return {
        ...edge,
        data: {
          ...edge.data,
          // Global lock state (canvas locked / read-only / standalone).
          // Distinct from per-edge isLocked/locked which tracks individual
          // element locks.  Used by edge components to disable interactions
          // (e.g. edge order reorder) without hiding visual indicators.
          globalLocked: isLocked,
          edgeOrdersVisible: showEdgeOrderNumbers,
          edgeOrderInfo: edgeOrderInfo,
          showOrderNumbers: showEdgeOrderNumbers,
          showAnnotations: showAllAnnotations,
          // Individual annotation visibility (stored in edge data)
          isAnnotationVisible: showAllAnnotations || edge.data?.isAnnotationVisible || false,
          onToggleAnnotation: () => {
            // Toggle individual annotation visibility
            if (onEdgeUpdate) {
              const newAnnotationVisible = !edge.data?.isAnnotationVisible;
              onEdgeUpdate(edge.id, {
                ...edge.data,
                isAnnotationVisible: newAnnotationVisible
              });
            }
          },
          onDragStart: handleDragStart,
          onDragEnd: handleDragEnd,
          onDrop: handleEdgeOrderDrop,
          onReorderEdge: handleReorderEdge,
          onEdgeUpdate: onEdgeUpdate,
          // Every edge is plain, so quick-add (condition node / action node)
          // is always available unless the canvas is locked.
          onAddCondition: onAddCondition && !isLocked ? onAddCondition : undefined,
          onAddActionOnEdge: onAddActionOnEdge && !isLocked ? onAddActionOnEdge : undefined,
          // Edge delete (trash affordance) — gated by !isLocked exactly like
          // onAddCondition so locked/read-only canvases cannot delete edges.
          onDeleteEdge: onDeleteEdge && !isLocked ? onDeleteEdge : undefined,
          // Endpoint reconnection (issue #3585553): per-handle grip eligibility
          // plus the commit + validation callbacks. Gated by !isLocked so a
          // locked/read-only/standalone canvas cannot reconnect.
          sourceGripEnabled,
          targetGripEnabled,
          onReconnectEdge: onReconnectEdge && !isLocked ? onReconnectEdge : undefined,
          validateReconnect: !isLocked ? makeValidateReconnect(edge.id) : undefined,
          searchTerm,
          isHighlighted: highlightedSearchResult?.id === edge.id,
          // Replay state
          replayData,
          currentReplayStep,
          isReplayMode,
        },
      };
    });
  }, [
    edges,
    getEdgeOrderInfo,
    showEdgeOrderNumbers,
    showAllAnnotations,
    handleDragStart,
    handleDragEnd,
    handleEdgeOrderDrop,
    handleReorderEdge,
    onEdgeUpdate,
    onAddCondition,
    onAddActionOnEdge,
    onDeleteEdge,
    onReconnectEdge,
    makeValidateReconnect,
    selectedHandleCounts,
    isLocked,
    searchTerm,
    highlightedSearchResult,
    replayData,
    currentReplayStep,
    isReplayMode
  ]);

  // Pre-compute outgoing edge counts per node for successor constraint checks.
  const outgoingEdgeCounts = useMemo(() => {
    const counts: Record<string, number> = {};
    for (const edge of edges) {
      counts[edge.source] = (counts[edge.source] ?? 0) + 1;
    }
    return counts;
  }, [edges]);

  // Enhanced nodes with search highlighting and replay state
  const enhancedNodes = useMemo(() => {
    return nodes.map(node => {
      // Check successor constraint: should we suppress quick-add and source handle?
      const typeConstraint = node.type ? modelConstraints?.[node.type as keyof ModelConstraints] : undefined;
      const maxSuccessors = typeConstraint?.successors?.max;
      const outgoing = outgoingEdgeCounts[node.id] ?? 0;
      const atMaxSuccessors = maxSuccessors !== undefined && outgoing >= maxSuccessors;

      // Condition 1-outbound rule (issue #3589093): a condition node may have
      // AT MOST ONE outgoing edge. Once it has one, disable its source handle so
      // the drag cannot even start (better UX than rejecting the wire afterward).
      const conditionAtMaxOut = isConditionNode(node) && outgoing >= 1;

      // Reconnect reservation (issue #3585553): when one or more SELECTED edges
      // use this node's source handle, the handle must not start a NEW edge.
      // - exactly one selected edge → that handle hosts a "move source" grip,
      //   so a new-edge drag from the same handle would conflict with the grip.
      // - two or more selected edges → the handle is ambiguous (gesture
      //   conflict), so it is inert for new edges too.
      // Either way we suppress new-edge connectability. (We count across ALL of
      // the node's source-handle keys, since a node may expose only one source
      // handle today but the check is handle-agnostic at the node level.)
      const sourceReconnectReserved = Object.entries(selectedHandleCounts.sourceCounts).some(
        ([k, count]) => count >= 1 && k.startsWith(`${node.id}|`),
      );

      // Any rule disables the source handle for NEW edges; all stay in effect.
      const sourceHandleDisabled = atMaxSuccessors || conditionAtMaxOut || sourceReconnectReserved;
      // The condition rule takes precedence in the tooltip message.
      const sourceHandleDisabledReason = conditionAtMaxOut
        ? 'condition-single-out'
        : atMaxSuccessors
          ? 'max-successors'
          : undefined;

      return {
        ...node,
        data: {
          ...node.data,
          showAnnotations: showAllAnnotations,
          // Individual annotation visibility (stored in node data)
          isAnnotationVisible: showAllAnnotations || node.data?.isAnnotationVisible || false,
          onToggleAnnotation: () => {
            // Toggle individual annotation visibility
            if (onNodeUpdate) {
              const newAnnotationVisible = !node.data?.isAnnotationVisible;
              onNodeUpdate(node.id, {
                ...node.data,
                isAnnotationVisible: newAnnotationVisible
              });
            }
          },
          searchTerm,
          isHighlighted: highlightedSearchResult?.id === node.id,
          // Replay state
          replayData,
          currentReplayStep,
          isReplayMode,
          // Node operations
          onDelete: onDeleteNode ? () => onDeleteNode(node.id) : undefined,
          onQuickAdd: onQuickAdd && !isLocked && !atMaxSuccessors && !conditionAtMaxOut
            ? (component: StoreComponent) => onQuickAdd(component, node.id)
            : undefined,
          onReplacePlaceholder: node.type === 'placeholder' && onReplacePlaceholder && !isLocked
            ? (component: StoreComponent) => onReplacePlaceholder(node.id, component)
            : undefined,
          sourceHandleDisabled,
          sourceHandleDisabledReason,
          isLocked,
        },
      };
    });
  }, [
    nodes,
    outgoingEdgeCounts,
    selectedHandleCounts,
    modelConstraints,
    showAllAnnotations, 
    onNodeUpdate,
    searchTerm, 
    highlightedSearchResult,
    replayData,
    currentReplayStep,
    isReplayMode,
    onDeleteNode,
    onQuickAdd,
    onReplacePlaceholder,
    isLocked
  ]);

  return (
    <Profiler id="FlowCanvas" onRender={onRenderCallback}>
    <div 
      className={`reactflow-wrapper ${isDragActive ? 'drag-active' : ''}${reconnectDragActive ? ' reconnect-dragging' : ''}`}
    >
      <ReactFlow
        nodes={enhancedNodes}
        edges={enhancedEdges}
        onNodesChange={onNodesChange}
        onEdgesChange={onEdgesChange}
        onConnect={onConnect}
        onSelectionChange={onSelectionChange}
        // Clear the post-pane-click stale-selection guard when a new
        // rubber-band selection begins, so a drag-select that follows a pane
        // click is not wrongly swallowed (issue #3589101 follow-up).
        onSelectionStart={onSelectionStart}
        // Suppress the NodesSelection bounding box on rubber-band multi-select
        // so drag-select matches Shift+click exactly (issue #3589101).
        onSelectionEnd={onSelectionEnd}
        onConnectStart={onConnectStart}
        onConnectEnd={onConnectEnd}
        onNodeClick={onNodeClick}
        onEdgeClick={onEdgeClick}
        onPaneClick={onPaneClick}
        onNodeDragStart={onNodeDragStart}
        onNodeDragStop={onNodeDragStop}
        onInit={onInit}
        nodeTypes={nodeTypes}
        edgeTypes={edgeTypes}
        // New-edge live preview (issue #3585553 follow-on UX): use a custom
        // connection-line component so the new-edge preview is the SAME dashed
        // cubic-bezier as the endpoint-reconnect preview (see ConnectionLine).
        // `connectionLineType` stays as a Bezier fallback only — the component
        // takes precedence whenever it is provided. (Previously this was
        // ConnectionLineType.SmoothStep, which rendered the disliked cornered
        // line.)
        connectionLineComponent={ConnectionLine}
        connectionLineType={ConnectionLineType.Bezier}
        selectionMode={selectionMode}
        defaultMarkerColor="var(--modeler-color-edge-default)"
        defaultEdgeOptions={{
          type: 'default',
          markerEnd: {
            type: MarkerType.Arrow,
            strokeWidth: 1.5,
            color: 'var(--modeler-color-edge-default)',
          },
        }}
        isValidConnection={(connection) =>
          // Shared with the reconnect commit path (issue #3585553) so new-edge
          // and reconnect validation can never diverge. No edge is excluded
          // here because this validates a brand-new edge being dragged.
          isValidConnectionShared({ connection, nodes, edges, modelConstraints })
        }
        nodesDraggable={!isLocked}
        nodesConnectable={!isLocked}
        elementsSelectable={true}  // Allow selection even when locked for viewing properties
        selectNodesOnDrag={false}
        // Figma-like gesture scheme:
        // - Wheel/trackpad scroll = pan (smooth, natural for touchpads/Magic Mouse)
        // - Ctrl/Cmd+wheel = zoom
        // - Pinch-to-zoom = zoom (trackpad native)
        // - Left-click+drag on empty canvas = selection rectangle
        // - Middle-click or right-click+drag = pan
        // - Space+drag = pan (hand tool)
        panOnScroll={true}
        panOnScrollMode={PanOnScrollMode.Free}
        panOnScrollSpeed={0.5}
        zoomOnScroll={false}
        zoomOnPinch={true}
        zoomOnDoubleClick={false}
        minZoom={VIEWPORT.MIN_ZOOM}
        maxZoom={VIEWPORT.MAX_ZOOM}
        panOnDrag={[1, 2]}
        selectionOnDrag={!isLocked}
        multiSelectionKeyCode="Shift"
        deleteKeyCode={null} // Handled by useKeyboardShortcuts
        fitView={false}
        attributionPosition="bottom-left"
      >
        {/* MiniMap is always visible in the bottom-left corner */}
        <MiniMap
          style={{
            backgroundColor: 'var(--modeler-color-minimap-bg)',
            border: '1px solid var(--modeler-color-minimap-border)',
          }}
          nodeColor={(node: Node) => {
            if (node.selected) return 'var(--modeler-color-minimap-selected)';
            return 'var(--modeler-color-minimap-node)';
          }}
          maskColor="var(--modeler-color-minimap-mask)"
          position="bottom-left"
          pannable
        />
        {/* Replay condition indicators rendered inside EdgeLabelRenderer so
            they automatically follow canvas pan/zoom transforms. */}
        {replayIndicators.length > 0 && (
          <EdgeLabelRenderer>
            {replayIndicators.map(indicator => (
              <div
                key={indicator.id}
                className="replay-indicator"
                style={{
                  transform: `translate(-50%, -50%) translate(${indicator.x}px, ${indicator.y}px)`,
                  backgroundColor: indicator.color,
                }}
              />
            ))}
          </EdgeLabelRenderer>
        )}
      </ReactFlow>

    </div>
    </Profiler>
  );
};

export default FlowCanvas;