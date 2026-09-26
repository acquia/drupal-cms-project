import { create } from 'zustand';
import type { StoreNode, StoreEdge } from '../types/settings';
import { useGraphStore } from './useGraphStore';

interface SelectionState {
  selectedNode: StoreNode | null;
  setSelectedNode: (node: StoreNode | null) => void;
  selectedEdge: StoreEdge | null;
  setSelectedEdge: (edge: StoreEdge | null) => void;
  selectedNodes: string[];
  selectedEdges: string[];
  setSelectedNodes: (nodes: string[]) => void;
  setSelectedEdges: (edges: string[]) => void;
  clearSelection: () => void;
  selectNode: (node: StoreNode) => void;
  selectEdge: (edge: StoreEdge) => void;
}

export const useSelectionStore = create<SelectionState>((set) => ({
  selectedNode: null,
  setSelectedNode: (node) => set({ selectedNode: node }),

  selectedEdge: null,
  setSelectedEdge: (edge) => set({ selectedEdge: edge }),

  selectedNodes: [],
  selectedEdges: [],
  setSelectedNodes: (nodes) => set({ selectedNodes: nodes }),
  setSelectedEdges: (edges) => set({ selectedEdges: edges }),
  clearSelection: () =>
    set({
      selectedNodes: [],
      selectedEdges: [],
      selectedNode: null,
      selectedEdge: null,
    }),
  selectNode: (node) =>
    set({
      selectedNode: node,
      selectedEdge: null,
      selectedNodes: [node.id],
      selectedEdges: [],
    }),
  selectEdge: (edge) =>
    set({
      selectedNode: null,
      selectedEdge: edge,
      selectedNodes: [],
      selectedEdges: [edge.id],
    }),
}));

/**
 * React to graph changes: when nodes or edges are removed from useGraphStore,
 * prune any stale references from the selection state.
 *
 * This keeps the dependency direction correct — the selection store owns its
 * own cleanup logic and subscribes to the graph store, rather than the graph
 * store reaching into the selection store.
 */
useGraphStore.subscribe((state, prevState) => {
  if (state.nodes === prevState.nodes && state.edges === prevState.edges) {
    return;
  }

  const selectionState = useSelectionStore.getState();

  const nodeIds = new Set(state.nodes.map((n) => n.id));
  const edgeIds = new Set(state.edges.map((e) => e.id));

  const updates: Partial<ReturnType<typeof useSelectionStore.getState>> = {};

  // Clear primary selected node if it no longer exists
  if (selectionState.selectedNode && !nodeIds.has(selectionState.selectedNode.id)) {
    updates.selectedNode = null;
  }

  // Clear primary selected edge if it no longer exists
  if (selectionState.selectedEdge && !edgeIds.has(selectionState.selectedEdge.id)) {
    updates.selectedEdge = null;
  }

  // Prune multi-selection arrays
  const prunedNodes = selectionState.selectedNodes.filter((id) => nodeIds.has(id));
  if (prunedNodes.length !== selectionState.selectedNodes.length) {
    updates.selectedNodes = prunedNodes;
  }

  const prunedEdges = selectionState.selectedEdges.filter((id) => edgeIds.has(id));
  if (prunedEdges.length !== selectionState.selectedEdges.length) {
    updates.selectedEdges = prunedEdges;
  }

  if (Object.keys(updates).length > 0) {
    useSelectionStore.setState(updates);
  }
});
