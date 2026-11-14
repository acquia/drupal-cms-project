/**
 * Public Plugin API
 *
 * Creates the API object that external plugin panels use to interact with
 * the modeler state.  All returned data is deep-cloned so plugins cannot
 * mutate internal state.
 *
 * The API is created once when the modeler mounts and torn down on unmount.
 *
 * ## Query methods
 * Return deep-cloned snapshots of the current state.
 *
 * ## Mutation methods
 * Modify the graph (add/update/remove nodes and edges).  They are no-ops
 * in read-only mode and automatically integrate with undo/redo history
 * and unsaved-changes tracking via the callback hooks registered by
 * Flow.tsx.
 *
 * ## Event subscriptions
 * Return an `Unsubscribe` function; call it to stop receiving updates.
 */

import { useGraphStore } from '../store/useGraphStore';
import { useSelectionStore } from '../store/useSelectionStore';
import { useModelStore } from '../store/useModelStore';
import { useUISettingsStore } from '../store/useUISettingsStore';
import { useComponentStore } from '../store/useComponentStore';
import { useContextStore } from '../store/useContextStore';
import { useFilterStore } from '../store/useFilterStore';
import { useLabelStore } from '../store/useLabelStore';
import { useHistoryStore } from '../store/useHistoryStore';
import { useErrorStore } from '../store/useErrorStore';
import type { StoreNode as Node, StoreEdge as Edge } from '../types/settings';
import { routeParallelEdge } from '../utils/parallelEdgeRouter';
import type {
  ModelerPluginApi,
  PluginNode,
  PluginEdge,
  PluginModelData,
  PluginComponent,
  PluginComponentLabels,
  PluginContext,
  PluginHistoryState,
  PluginError,
  AddNodeDescriptor,
  UpdateNodeDescriptor,
  UpdateEdgeDescriptor,
  SetConditionDescriptor,
  SelectionChangeCallback,
  NodesChangeCallback,
  EdgesChangeCallback,
  ModelDataChangeCallback,
  DarkModeChangeCallback,
  ContextChangeCallback,
  ComponentsChangeCallback,
  ReadOnlyChangeCallback,
  Unsubscribe,
} from '../types/pluginApi';
import { resolveNodeType } from '../utils/componentUtils';
import { generateNodeId, generateEdgeId } from '../utils/clipboardUtils';
import { NODE_DIMENSIONS } from '../constants/dimensions';
import {
  applyFlowShifts,
  buildConditionInsertion,
  computeNewEventPosition,
  computeSuccessorPosition,
  isConditionNode,
  placeChainOnEdge,
} from '../utils/incrementalLayout';

// ── Helpers ───────────────────────────────────────────────────────────

/**
 * Deep-clone a value using structured clone (falls back to JSON round-trip).
 */
function deepClone<T>(value: T): T {
  if (value === null || value === undefined) return value;
  try {
    return structuredClone(value);
  } catch {
    return JSON.parse(JSON.stringify(value));
  }
}

/**
 * Collision-avoidance footprint for a node of the given resolved type.
 *
 * Gateways render as compact cards, so feeding the standard card height
 * into the placement primitives would reserve more vertical room than a
 * gateway actually occupies.  Every other type uses the uniform card box.
 */
function nodeFootprint(nodeType: string): { width: number; height: number } {
  if (nodeType === 'gateway') {
    return { width: NODE_DIMENSIONS.CARD_WIDTH, height: NODE_DIMENSIONS.GATEWAY_HEIGHT };
  }
  if (nodeType === 'start') {
    return {
      width: NODE_DIMENSIONS.START_NODE_WIDTH,
      height: NODE_DIMENSIONS.START_NODE_HEIGHT,
    };
  }
  return { width: NODE_DIMENSIONS.CARD_WIDTH, height: NODE_DIMENSIONS.CARD_HEIGHT };
}

/**
 * Project an internal node to the public PluginNode shape.
 */
function toPluginNode(node: Node): PluginNode {
  return deepClone({
    id: node.id,
    type: node.type,
    data: node.data as Record<string, unknown>,
    position: node.position,
  });
}

/**
 * Project an internal edge to the public PluginEdge shape.
 */
function toPluginEdge(edge: Edge): PluginEdge {
  return deepClone({
    id: edge.id,
    source: edge.source,
    target: edge.target,
    type: edge.type,
    data: edge.data as Record<string, unknown> | undefined,
  });
}

// ── Read-only flag management ─────────────────────────────────────────

let currentReadOnly = false;

/** Listeners notified when the read-only state changes. */
const readOnlyChangeListeners = new Set<ReadOnlyChangeCallback>();

/**
 * Called by the React app to update the read-only state that the API exposes.
 */
export function setApiReadOnly(readOnly: boolean): void {
  const prev = currentReadOnly;
  currentReadOnly = readOnly;
  if (prev !== readOnly) {
    readOnlyChangeListeners.forEach((fn) => {
      try {
        fn(readOnly);
      } catch (err) {
        console.error('Plugin onReadOnlyChange callback error:', err);
      }
    });
  }
}

// ── Mutation hooks ────────────────────────────────────────────────────
// Flow.tsx registers callbacks so that mutations performed via the plugin
// API trigger undo/redo history snapshots and mark the model as having
// unsaved changes.

type MutationHook = () => void;
let saveHistoryHook: MutationHook | null = null;
let markUnsavedHook: MutationHook | null = null;
let autoLayoutHook: MutationHook | null = null;

/**
 * Register callbacks that integrate mutations with undo/redo and
 * unsaved-changes tracking.  Called once by Flow.tsx on mount.
 */
export function setMutationHooks(hooks: {
  saveHistory: MutationHook;
  markUnsaved: MutationHook;
  autoLayout: MutationHook;
}): void {
  saveHistoryHook = hooks.saveHistory;
  markUnsavedHook = hooks.markUnsaved;
  autoLayoutHook = hooks.autoLayout;
}

/**
 * Clear mutation hooks (called on unmount).
 */
export function clearMutationHooks(): void {
  saveHistoryHook = null;
  markUnsavedHook = null;
  autoLayoutHook = null;
}

// ── Viewport hooks ───────────────────────────────────────────────────
// Flow.tsx registers viewport action callbacks so the plugin API can
// pan/zoom without importing React hooks directly.

type ViewportHookFocusNode = (nodeId: string) => void;
type ViewportHookFitView = () => void;
let viewportFocusNodeHook: ViewportHookFocusNode | null = null;
let viewportFitViewHook: ViewportHookFitView | null = null;

/**
 * Register viewport action callbacks.  Called once by Flow.tsx on mount.
 */
export function setViewportHooks(hooks: {
  focusNode: ViewportHookFocusNode;
  fitView: ViewportHookFitView;
}): void {
  viewportFocusNodeHook = hooks.focusNode;
  viewportFitViewHook = hooks.fitView;
}

/**
 * Clear viewport hooks (called on unmount).
 */
export function clearViewportHooks(): void {
  viewportFocusNodeHook = null;
  viewportFitViewHook = null;
}

/**
 * Save a history snapshot before a mutation.
 */
function beforeMutation(): void {
  if (saveHistoryHook) saveHistoryHook();
}

/**
 * Mark the model as having unsaved changes after a mutation.
 */
function afterMutation(): void {
  if (markUnsavedHook) markUnsavedHook();
}

// ── API factory ───────────────────────────────────────────────────────

/**
 * Create the public API instance.
 *
 * The returned object is stable — callers can hold a reference indefinitely.
 * It reads from the Zustand store at call time, so values are always current.
 */
export function createPluginApi(): ModelerPluginApi {
  const api: ModelerPluginApi = {
    // ── Getters ─────────────────────────────────────────────────────
    getNodes(): PluginNode[] {
      return useGraphStore.getState().nodes.map(toPluginNode);
    },

    getEdges(): PluginEdge[] {
      return useGraphStore.getState().edges.map(toPluginEdge);
    },

    getNodeById(nodeId: string): PluginNode | null {
      const node = useGraphStore.getState().nodes.find((n) => n.id === nodeId);
      return node ? toPluginNode(node) : null;
    },

    getEdgeById(edgeId: string): PluginEdge | null {
      const edge = useGraphStore.getState().edges.find((e) => e.id === edgeId);
      return edge ? toPluginEdge(edge) : null;
    },

    getSelectedNode(): PluginNode | null {
      const node = useSelectionStore.getState().selectedNode;
      return node ? toPluginNode(node) : null;
    },

    getSelectedEdge(): PluginEdge | null {
      const edge = useSelectionStore.getState().selectedEdge;
      return edge ? toPluginEdge(edge) : null;
    },

    getSelectedNodes(): PluginNode[] {
      const { selectedNodes } = useSelectionStore.getState();
      const { nodes } = useGraphStore.getState();
      const result: PluginNode[] = [];
      for (const nodeId of selectedNodes) {
        const node = nodes.find((candidate) => candidate.id === nodeId);
        if (node) result.push(toPluginNode(node));
      }
      return result;
    },

    getSelectedEdges(): PluginEdge[] {
      const { selectedEdges } = useSelectionStore.getState();
      const { edges } = useGraphStore.getState();
      const result: PluginEdge[] = [];
      for (const edgeId of selectedEdges) {
        const edge = edges.find((candidate) => candidate.id === edgeId);
        if (edge) result.push(toPluginEdge(edge));
      }
      return result;
    },

    getModelData(): PluginModelData | null {
      const data = useModelStore.getState().modelData;
      if (!data) return null;
      return deepClone({
        id: data.id,
        version: data.version,
        metadata: data.metadata,
      });
    },

    isReadOnly(): boolean {
      return currentReadOnly;
    },

    isDarkMode(): boolean {
      return useUISettingsStore.getState().darkMode;
    },

    getComponents(): PluginComponent[] {
      return deepClone(
        useComponentStore.getState().components.map((c) => ({
          plugin: c.plugin,
          label: c.label,
          type: c.type,
          provider: c.provider,
          description: c.description,
          documentationUrl: c.documentationUrl,
          componentType: c.componentType,
        })),
      );
    },

    getComponentLabels(): PluginComponentLabels {
      return deepClone(useLabelStore.getState().componentLabels);
    },

    getContexts(): PluginContext[] {
      return deepClone(
        useContextStore.getState().contexts.map((ctx) => ({
          id: ctx.id,
          topic: ctx.topic,
          model_owner: ctx.model_owner,
        })),
      );
    },

    getSelectedContextId(): string | null {
      return useContextStore.getState().selectedContextId;
    },

    getFilteredNodeIds(): string[] | null {
      const ids = useFilterStore.getState().visibleStartNodeIds;
      return ids ? [...ids] : null;
    },

    getHistoryState(): PluginHistoryState {
      const history = useHistoryStore.getState();
      return {
        canUndo: history.canUndo(),
        canRedo: history.canRedo(),
      };
    },

    getErrors(): PluginError[] {
      return useErrorStore.getState().errorLog.map((r) => ({
        id: r.id,
        message: r.message,
        dismissed: !!r.dismissed,
      }));
    },

    // ── Event subscriptions ─────────────────────────────────────────
    onSelectionChange(callback: SelectionChangeCallback): Unsubscribe {
      let prevNodeId: string | null = null;
      let prevEdgeId: string | null = null;
      let prevSelectedNodeIds: string[] = [];
      let prevSelectedEdgeIds: string[] = [];

      return useSelectionStore.subscribe((state) => {
        const nodeId = state.selectedNode?.id ?? null;
        const edgeId = state.selectedEdge?.id ?? null;
        // Element comparison preserves selection order without allocating a
        // joined key or risking delimiter collisions on every notification.
        const selectedNodesChanged =
          state.selectedNodes.length !== prevSelectedNodeIds.length ||
          state.selectedNodes.some((id, index) => id !== prevSelectedNodeIds[index]);
        const selectedEdgesChanged =
          state.selectedEdges.length !== prevSelectedEdgeIds.length ||
          state.selectedEdges.some((id, index) => id !== prevSelectedEdgeIds[index]);

        if (
          nodeId !== prevNodeId ||
          edgeId !== prevEdgeId ||
          selectedNodesChanged ||
          selectedEdgesChanged
        ) {
          prevNodeId = nodeId;
          prevEdgeId = edgeId;
          prevSelectedNodeIds = [...state.selectedNodes];
          prevSelectedEdgeIds = [...state.selectedEdges];
          try {
            callback(
              state.selectedNode ? toPluginNode(state.selectedNode) : null,
              state.selectedEdge ? toPluginEdge(state.selectedEdge) : null,
            );
          } catch (err) {
            console.error('Plugin onSelectionChange callback error:', err);
          }
        }
      });
    },

    onNodesChange(callback: NodesChangeCallback): Unsubscribe {
      let prevLength = -1;
      let prevFirstId: string | undefined;

      return useGraphStore.subscribe((state) => {
        const len = state.nodes.length;
        const firstId = state.nodes[0]?.id;
        if (len !== prevLength || firstId !== prevFirstId) {
          prevLength = len;
          prevFirstId = firstId;
          try {
            callback(state.nodes.map(toPluginNode));
          } catch (err) {
            console.error('Plugin onNodesChange callback error:', err);
          }
        }
      });
    },

    onEdgesChange(callback: EdgesChangeCallback): Unsubscribe {
      let prevLength = -1;
      let prevFirstId: string | undefined;

      return useGraphStore.subscribe((state) => {
        const len = state.edges.length;
        const firstId = state.edges[0]?.id;
        if (len !== prevLength || firstId !== prevFirstId) {
          prevLength = len;
          prevFirstId = firstId;
          try {
            callback(state.edges.map(toPluginEdge));
          } catch (err) {
            console.error('Plugin onEdgesChange callback error:', err);
          }
        }
      });
    },

    onModelDataChange(callback: ModelDataChangeCallback): Unsubscribe {
      let prevId: string | undefined;
      let prevVersion: string | undefined;

      return useModelStore.subscribe((state) => {
        const id = state.modelData?.id;
        const version = state.modelData?.version;
        if (id !== prevId || version !== prevVersion) {
          prevId = id;
          prevVersion = version;
          try {
            callback(api.getModelData());
          } catch (err) {
            console.error('Plugin onModelDataChange callback error:', err);
          }
        }
      });
    },

    onDarkModeChange(callback: DarkModeChangeCallback): Unsubscribe {
      let prevDark: boolean | undefined;

      return useUISettingsStore.subscribe((state) => {
        if (state.darkMode !== prevDark) {
          prevDark = state.darkMode;
          try {
            callback(state.darkMode);
          } catch (err) {
            console.error('Plugin onDarkModeChange callback error:', err);
          }
        }
      });
    },

    onContextChange(callback: ContextChangeCallback): Unsubscribe {
      let prevContextId: string | null | undefined;

      return useContextStore.subscribe((state) => {
        if (state.selectedContextId !== prevContextId) {
          prevContextId = state.selectedContextId;
          try {
            callback(state.selectedContextId);
          } catch (err) {
            console.error('Plugin onContextChange callback error:', err);
          }
        }
      });
    },

    onComponentsChange(callback: ComponentsChangeCallback): Unsubscribe {
      let prevLength = -1;

      return useComponentStore.subscribe((state) => {
        if (state.components.length !== prevLength) {
          prevLength = state.components.length;
          try {
            callback(api.getComponents());
          } catch (err) {
            console.error('Plugin onComponentsChange callback error:', err);
          }
        }
      });
    },

    onReadOnlyChange(callback: ReadOnlyChangeCallback): Unsubscribe {
      readOnlyChangeListeners.add(callback);
      return () => {
        readOnlyChangeListeners.delete(callback);
      };
    },

    // ── Selection & viewport actions ────────────────────────────────
    selectNode(nodeId: string | null): void {
      if (currentReadOnly) return;
      if (nodeId === null) {
        useSelectionStore.getState().clearSelection();
        return;
      }
      const node = useGraphStore.getState().nodes.find((n) => n.id === nodeId);
      if (node) {
        useSelectionStore.getState().selectNode(node);
      }
    },

    selectEdge(edgeId: string | null): void {
      if (currentReadOnly) return;
      if (edgeId === null) {
        useSelectionStore.getState().clearSelection();
        return;
      }
      const edge = useGraphStore.getState().edges.find((e) => e.id === edgeId);
      if (edge) {
        useSelectionStore.getState().selectEdge(edge);
      }
    },

    clearSelection(): void {
      useSelectionStore.getState().clearSelection();
    },

    focusNode(nodeId: string): void {
      const node = useGraphStore.getState().nodes.find((n) => n.id === nodeId);
      if (node && viewportFocusNodeHook) {
        viewportFocusNodeHook(nodeId);
      }
    },

    fitView(): void {
      if (viewportFitViewHook) {
        viewportFitViewHook();
      }
    },

    // ── Node mutations ──────────────────────────────────────────────
    addNode(descriptor: AddNodeDescriptor): string | null {
      if (currentReadOnly) return null;

      const nodeType = resolveNodeType(descriptor.componentType);
      const { nodes, edges } = useGraphStore.getState();

      // Resolve placement BEFORE any mutation so that a rejected descriptor
      // (unknown sourceNodeId) leaves no history entry behind — the same
      // validate-then-mutate ordering addEdge() uses for unknown endpoints.
      let position = descriptor.position;
      let shiftNodeIds: Set<string> | null = null;
      let shiftAmount = 0;

      if (!position) {
        if (descriptor.sourceNodeId !== undefined) {
          // Successor placement. Reject an unknown parent rather than lean on
          // computeSuccessorPosition's silent fallback, which would strand the
          // node at the legacy default corner with no hint anything went wrong.
          if (!nodes.some((n) => n.id === descriptor.sourceNodeId)) return null;

          // No siblingIndex / totalSiblings: the plugin API adds one node per
          // call and has no fan-out context, exactly like quick-add's
          // addSuccessorNode. Passing neither keeps both paths byte-identical.
          const placement = computeSuccessorPosition({
            nodes,
            edges,
            sourceNodeId: descriptor.sourceNodeId,
          });
          position = placement.position;
          shiftNodeIds = placement.shiftNodeIds;
          shiftAmount = placement.shiftAmount;
        } else {
          // Fresh event flow, to the right of everything already on canvas.
          position = computeNewEventPosition({ nodes, ...nodeFootprint(nodeType) });
        }
      }

      beforeMutation();

      const label = descriptor.label || descriptor.plugin.split('.').pop() || 'New Node';
      const newNodeId = generateNodeId(label, nodeType);

      const newNode: Node = {
        id: newNodeId,
        type: nodeType,
        position,
        data: {
          plugin: descriptor.plugin,
          label,
          componentType: descriptor.componentType,
          configuration: descriptor.configuration || {},
          description: descriptor.description,
          documentationUrl: descriptor.documentationUrl,
        },
      };

      // Make horizontal room by pushing neighboring flows aside, if the
      // placement primitive asked for it.  Unlike quick-add we do NOT
      // deselect: quick-add deselects because it immediately selects the
      // node the user just asked for, whereas a plugin adding a node in the
      // background must not silently clobber the user's current selection.
      if (shiftNodeIds && shiftAmount > 0) {
        const ids = shiftNodeIds;
        useGraphStore
          .getState()
          .setNodes((prev) => applyFlowShifts(prev, ids, shiftAmount, { deselectAll: false }));
      }

      useGraphStore.getState().addNode(newNode);
      afterMutation();
      return newNodeId;
    },

    updateNode(nodeId: string, updates: UpdateNodeDescriptor): boolean {
      if (currentReadOnly) return false;

      const { nodes } = useGraphStore.getState();
      const node = nodes.find((n) => n.id === nodeId);
      if (!node) return false;

      beforeMutation();

      // Build the partial update for the store.
      const storeUpdates: Partial<Node> = {};
      const dataUpdates: Record<string, unknown> = {};

      if (updates.position) {
        storeUpdates.position = { ...updates.position };
      }

      if (updates.label !== undefined) {
        dataUpdates.label = updates.label;
      }
      if (updates.configuration !== undefined) {
        // Shallow merge with existing configuration.
        dataUpdates.configuration = {
          ...(node.data.configuration || {}),
          ...updates.configuration,
        };
      }
      if (updates.annotation !== undefined) {
        dataUpdates.annotation = updates.annotation;
      }

      if (Object.keys(dataUpdates).length > 0) {
        storeUpdates.data = { ...node.data, ...dataUpdates };
      }

      useGraphStore.getState().updateNode(nodeId, storeUpdates);
      afterMutation();
      return true;
    },

    removeNode(nodeId: string): boolean {
      if (currentReadOnly) return false;

      const { nodes } = useGraphStore.getState();
      if (!nodes.find((n) => n.id === nodeId)) return false;

      beforeMutation();

      // Clear selection if the removed node was selected.
      const selection = useSelectionStore.getState();
      if (selection.selectedNode?.id === nodeId) {
        useSelectionStore.getState().clearSelection();
      }

      useGraphStore.getState().removeNode(nodeId);
      afterMutation();
      return true;
    },

    // ── Edge mutations ──────────────────────────────────────────────
    addEdge(sourceNodeId: string, targetNodeId: string): string | null {
      if (currentReadOnly) return null;

      const { nodes, edges } = useGraphStore.getState();
      const sourceExists = nodes.some((n) => n.id === sourceNodeId);
      const targetExists = nodes.some((n) => n.id === targetNodeId);
      if (!sourceExists || !targetExists) return null;

      beforeMutation();

      const newEdgeId = generateEdgeId(sourceNodeId, targetNodeId);
      const newEdge: Edge = {
        id: newEdgeId,
        source: sourceNodeId,
        target: targetNodeId,
        type: 'default',
        data: {},
      };

      // Route around any existing connection between the same endpoints,
      // mirroring the behavior of user-drag-to-connect (onConnect). The
      // router seeds controlOffset on the new edge and may rebalance
      // existing siblings whose offsets are still at the default zero.
      const route = routeParallelEdge({
        newEdge,
        edges: [...edges, newEdge],
        nodes,
      });

      const offsetUpdates = new Map<string, { x: number; y: number }>();
      for (const update of route.updates) {
        offsetUpdates.set(update.edgeId, update.controlOffset);
      }
      const newEdgeOffset = offsetUpdates.get(newEdgeId);
      if (newEdgeOffset) {
        newEdge.data = { ...newEdge.data, controlOffset: newEdgeOffset };
        offsetUpdates.delete(newEdgeId);
      }

      // Apply offset updates to existing siblings, then append the new edge.
      const store = useGraphStore.getState();
      if (offsetUpdates.size > 0) {
        store.setEdges((prev) =>
          prev.map((edge) => {
            const offset = offsetUpdates.get(edge.id);
            if (!offset) return edge;
            return {
              ...edge,
              data: { ...(edge.data || {}), controlOffset: offset },
            };
          }),
        );
      }
      store.addEdge(newEdge);
      afterMutation();
      return newEdgeId;
    },

    updateEdge(edgeId: string, updates: UpdateEdgeDescriptor): boolean {
      if (currentReadOnly) return false;

      const { edges } = useGraphStore.getState();
      const edge = edges.find((e) => e.id === edgeId);
      if (!edge) return false;

      beforeMutation();

      const dataUpdates: Record<string, unknown> = {};
      if (updates.annotation !== undefined) {
        dataUpdates.annotation = updates.annotation;
      }

      const storeUpdates: Partial<Edge> = {};
      if (Object.keys(dataUpdates).length > 0) {
        storeUpdates.data = { ...(edge.data || {}), ...dataUpdates };
      }

      useGraphStore.getState().updateEdge(edgeId, storeUpdates);
      afterMutation();
      return true;
    },

    removeEdge(edgeId: string): boolean {
      if (currentReadOnly) return false;

      const { edges } = useGraphStore.getState();
      if (!edges.find((e) => e.id === edgeId)) return false;

      beforeMutation();

      // Clear selection if the removed edge was selected.
      const selection = useSelectionStore.getState();
      if (selection.selectedEdge?.id === edgeId) {
        useSelectionStore.getState().clearSelection();
      }

      useGraphStore.getState().removeEdge(edgeId);
      afterMutation();
      return true;
    },

    /**
     * Insert a condition NODE on an existing edge.
     *
     * Conditions are first-class nodes (issue #3589093).  Rather than
     * mutating the edge (the old model, which only became a node after a
     * reload), this splits the target edge into source→condNode and
     * condNode→target — mirroring `handleAddCondition` in
     * `src/hooks/useNodeEdgeActions.ts`, the canonical implementation.
     *
     * The public signature and boolean contract are unchanged: `edgeId`
     * still identifies the edge to attach the condition to.  Returns
     * `false` when the edge is missing or the modeler is read-only.
     */
    setCondition(edgeId: string, condition: SetConditionDescriptor): boolean {
      if (currentReadOnly) return false;

      const { nodes, edges } = useGraphStore.getState();
      const edge = edges.find((e) => e.id === edgeId);
      if (!edge) return false;

      beforeMutation();

      const sourceNodeId = edge.source;
      const targetNodeId = edge.target;

      const conditionLabel = condition.label || condition.plugin.split('.').pop() || condition.plugin;
      const newNodeId = generateNodeId(conditionLabel, 'condition');

      const newNode: Node = {
        id: newNodeId,
        type: 'condition',
        // Temporary position — placeChainOnEdge will replace it.
        position: { x: 0, y: 0 },
        data: {
          label: conditionLabel,
          plugin: condition.plugin,
          configuration: condition.configuration || {},
          // New condition — export mints a UUID when conditionId is empty.
          conditionId: '',
          componentType: 5,
          __isConditionNode: true,
        },
      };

      // Enforce the "no two adjacent conditions" invariant (issue #3589093):
      // route through a gateway when either end of the edge is a condition
      // node, mirroring handleAddCondition in useNodeEdgeActions.
      const sourceNode = nodes.find((n) => n.id === sourceNodeId);
      const targetNode = nodes.find((n) => n.id === targetNodeId);
      const { nodesToAdd, edgesToAdd } = buildConditionInsertion({
        sourceNodeId,
        targetNodeId,
        conditionNode: newNode,
        sourceIsCondition: isConditionNode(sourceNode),
        targetIsCondition: isConditionNode(targetNode),
      });

      const allEdges = [
        ...edges.filter((e) => e.id !== edgeId),
        ...edgesToAdd,
      ];

      const positionedNodes = placeChainOnEdge(
        nodes,
        allEdges,
        nodesToAdd,
        sourceNodeId,
        targetNodeId,
      );

      const store = useGraphStore.getState();
      store.setNodes(positionedNodes);
      store.setEdges(allEdges);

      afterMutation();
      return true;
    },

    /**
     * Remove the condition associated with `edgeId`, collapsing the
     * condition node back into a single plain edge (the inverse of
     * `setCondition`).
     *
     * Conditions are nodes now, so the historic `edgeId` parameter is
     * interpreted as follows (in order):
     *   1. `edgeId` IS a condition node id → collapse that node.
     *   2. `edgeId` is an edge pointing INTO or OUT OF a condition node →
     *      collapse that adjacent condition node.
     *
     * Collapsing removes the condition node and its two split edges, then
     * adds one plain `default` edge from the condition's predecessor to its
     * successor.  Returns `false` when no condition can be resolved from
     * `edgeId` or the modeler is read-only.
     */
    removeCondition(edgeId: string): boolean {
      if (currentReadOnly) return false;

      const { nodes, edges } = useGraphStore.getState();

      const isConditionNode = (id: string): boolean => {
        const node = nodes.find((n) => n.id === id);
        return !!node && (node.type === 'condition' || node.data?.__isConditionNode === true);
      };

      // Resolve the condition node id from the supplied identifier.
      let condNodeId: string | null = null;
      if (isConditionNode(edgeId)) {
        condNodeId = edgeId;
      } else {
        const edge = edges.find((e) => e.id === edgeId);
        if (edge) {
          if (isConditionNode(edge.target)) {
            condNodeId = edge.target;
          } else if (isConditionNode(edge.source)) {
            condNodeId = edge.source;
          }
        }
      }

      if (!condNodeId) return false;

      // A condition node is created with exactly one inbound and one
      // outbound edge.  Resolve them to reconnect predecessor → successor.
      const inboundEdge = edges.find((e) => e.target === condNodeId);
      const outboundEdge = edges.find((e) => e.source === condNodeId);
      if (!inboundEdge || !outboundEdge) return false;

      const predecessorId = inboundEdge.source;
      const successorId = outboundEdge.target;

      beforeMutation();

      const reconnectEdge: Edge = {
        id: generateEdgeId(predecessorId, successorId),
        source: predecessorId,
        target: successorId,
        type: 'default',
        data: {},
      };

      const store = useGraphStore.getState();
      store.setEdges((prev) => [
        ...prev.filter((e) => e.source !== condNodeId && e.target !== condNodeId),
        reconnectEdge,
      ]);
      store.setNodes((prev) => prev.filter((n) => n.id !== condNodeId));

      // Clear selection if the collapsed condition node was selected.
      const selection = useSelectionStore.getState();
      if (selection.selectedNode?.id === condNodeId) {
        selection.clearSelection();
      }

      afterMutation();
      return true;
    },

    // ── Canvas actions ──────────────────────────────────────────────
    autoLayout(): void {
      if (currentReadOnly) return;
      if (autoLayoutHook) autoLayoutHook();
    },

    setDarkMode(enabled: boolean): void {
      const store = useUISettingsStore.getState();
      if (store.darkMode !== enabled) {
        store.toggleDarkMode();
      }
    },

    setFlowFilter(startNodeIds: string[] | null): void {
      useFilterStore.getState().setVisibleStartNodeIds(
        startNodeIds ? [...startNodeIds] : null,
      );
    },
  };

  return api;
}
