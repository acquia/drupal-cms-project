import { create } from 'zustand';
import type { StoreNode, StoreEdge } from '../types/settings';

interface GraphSnapshot {
  nodes: StoreNode[];
  edges: StoreEdge[];
}

/**
 * Deep-clone a heavy, plain-data value (e.g. a node's `configuration` or an
 * edge's `replayData`) so a snapshot does not retain a reference to the live,
 * mutable object held by the graph store.  Falls back to the original
 * reference when the value cannot be structurally cloned (e.g. it contains
 * functions), which keeps snapshotting safe in every environment.
 */
const cloneHeavy = <T,>(value: T): T => {
  if (value === undefined || value === null) {
    return value;
  }
  try {
    return structuredClone(value);
  } catch {
    return value;
  }
};

/**
 * Clone a node shell for the history snapshot.  The node and its `data` are
 * shallow-cloned so structural sharing of the live mutable objects is broken,
 * and the large `configuration` object is deep-cloned (guarded) so deleted
 * nodes do not keep heavy payloads alive across up to `maxHistorySize`
 * snapshots.
 */
const snapshotNode = (node: StoreNode): StoreNode => ({
  ...node,
  data: {
    ...node.data,
    configuration: cloneHeavy(node.data?.configuration),
  },
});

/**
 * Clone an edge shell for the history snapshot.  The edge and its `data` are
 * shallow-cloned (preserving function callbacks such as `onAddCondition` and
 * `onToggleAnnotation`, which `structuredClone` cannot handle), while the
 * heavy `replayData` array is deep-cloned (guarded) to break retention of the
 * live mutable payload.
 */
const snapshotEdge = (edge: StoreEdge): StoreEdge => ({
  ...edge,
  data: edge.data
    ? {
        ...edge.data,
        replayData: cloneHeavy(edge.data.replayData),
      }
    : edge.data,
});

/**
 * Produce a snapshot that breaks structural sharing with the live graph store
 * so retained snapshots do not pin deleted nodes' large `configuration`
 * objects (or edges' heavy `replayData`) in memory.
 */
const snapshotGraph = (state: GraphSnapshot): GraphSnapshot => ({
  nodes: state.nodes.map(snapshotNode),
  edges: state.edges.map(snapshotEdge),
});

interface HistoryState {
  past: GraphSnapshot[];
  future: GraphSnapshot[];
  maxHistorySize: number;
  pushHistory: (state: GraphSnapshot) => void;
  undo: (currentState: GraphSnapshot) => GraphSnapshot | null;
  redo: (currentState: GraphSnapshot) => GraphSnapshot | null;
  canUndo: () => boolean;
  canRedo: () => boolean;
  clearHistory: () => void;
}

export const useHistoryStore = create<HistoryState>((set, get) => ({
  past: [],
  future: [],
  maxHistorySize: 50,

  pushHistory: (state) => set((historyState) => {
    const newPast = [...historyState.past, snapshotGraph(state)];

    if (newPast.length > historyState.maxHistorySize) {
      newPast.shift();
    }
    
    return {
      past: newPast,
      future: [],
    };
  }),

  undo: (currentState) => {
    const { past } = get();
    if (past.length === 0) return null;

    const newPast = [...past];
    const previousState = newPast.pop()!;
    
    set((state) => ({ 
      past: newPast,
      future: [snapshotGraph(currentState), ...state.future],
    }));
    return previousState;
  },

  redo: (currentState) => {
    const { future } = get();
    if (future.length === 0) return null;

    const newFuture = [...future];
    const nextState = newFuture.shift()!;
    
    set((state) => ({ 
      past: [...state.past, snapshotGraph(currentState)],
      future: newFuture,
    }));
    return nextState;
  },

  canUndo: () => get().past.length > 0,

  canRedo: () => get().future.length > 0,

  clearHistory: () => set({ past: [], future: [] }),
}));
