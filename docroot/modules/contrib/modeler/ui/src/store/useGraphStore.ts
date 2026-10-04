import { create } from 'zustand';
import { applyNodeChanges, applyEdgeChanges, NodeChange, EdgeChange } from 'reactflow';
import type { StoreNode, StoreEdge } from '../types/settings';

interface GraphState {
  nodes: StoreNode[];
  edges: StoreEdge[];
  setNodes: (nodes: StoreNode[] | ((nodes: StoreNode[]) => StoreNode[])) => void;
  setEdges: (edges: StoreEdge[] | ((edges: StoreEdge[]) => StoreEdge[])) => void;
  applyNodeChanges: (changes: NodeChange[]) => void;
  applyEdgeChanges: (changes: EdgeChange[]) => void;
  addNode: (node: StoreNode) => void;
  addEdge: (edge: StoreEdge) => void;
  removeNode: (nodeId: string) => void;
  removeEdge: (edgeId: string) => void;
  updateNode: (nodeId: string, updates: Partial<StoreNode>) => void;
  updateEdge: (edgeId: string, updates: Partial<StoreEdge>) => void;
}

export const useGraphStore = create<GraphState>((set) => ({
  nodes: [],
  edges: [],

  setNodes: (nodes) => set((state) => ({
    nodes: typeof nodes === 'function' ? nodes(state.nodes) : nodes,
  })),

  setEdges: (edges) => set((state) => ({
    edges: typeof edges === 'function' ? edges(state.edges) : edges,
  })),

  applyNodeChanges: (changes) => set((state) => ({
    nodes: applyNodeChanges(changes, state.nodes) as StoreNode[],
  })),

  applyEdgeChanges: (changes) => set((state) => ({
    edges: applyEdgeChanges(changes, state.edges) as StoreEdge[],
  })),

  addNode: (node) => set((state) => ({
    nodes: [...state.nodes, node],
  })),

  addEdge: (edge) => set((state) => ({
    edges: [...state.edges, edge],
  })),

  removeNode: (nodeId) => set((state) => ({
    nodes: state.nodes.filter((n) => n.id !== nodeId),
    edges: state.edges.filter((e) => e.source !== nodeId && e.target !== nodeId),
  })),

  removeEdge: (edgeId) => set((state) => ({
    edges: state.edges.filter((e) => e.id !== edgeId),
  })),

  updateNode: (nodeId, updates) => set((state) => ({
    nodes: state.nodes.map((n) => (n.id === nodeId ? { ...n, ...updates } : n)),
  })),

  updateEdge: (edgeId, updates) => set((state) => ({
    edges: state.edges.map((e) => (e.id === edgeId ? { ...e, ...updates } : e)),
  })),
}));
