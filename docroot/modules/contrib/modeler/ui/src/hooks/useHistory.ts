import { useCallback } from 'react';
import { useGraphStore } from '../store/useGraphStore';
import { useHistoryStore } from '../store/useHistoryStore';

interface UseHistoryOptions {
  enabled?: boolean;
  setHasUnsavedChanges?: (value: boolean) => void;
}

export const useHistory = ({ enabled = true, setHasUnsavedChanges }: UseHistoryOptions = {}) => {
  const setNodes = useGraphStore(state => state.setNodes);
  const setEdges = useGraphStore(state => state.setEdges);
  
  const pushHistory = useHistoryStore(state => state.pushHistory);
  const undo = useHistoryStore(state => state.undo);
  const redo = useHistoryStore(state => state.redo);
  const canUndoState = useHistoryStore(state => state.canUndo);
  const canRedoState = useHistoryStore(state => state.canRedo);
  const clearHistory = useHistoryStore(state => state.clearHistory);

  const saveHistory = useCallback(() => {
    if (!enabled) return;
    // Read fresh state from the store to avoid stale closures
    const { nodes: currentNodes, edges: currentEdges } = useGraphStore.getState();
    pushHistory({ nodes: currentNodes, edges: currentEdges });
  }, [pushHistory, enabled]);

  const undoAction = useCallback(() => {
    if (!enabled) return null;
    
    // Read fresh state from the store to avoid stale closures
    const { nodes: currentNodes, edges: currentEdges } = useGraphStore.getState();
    const currentState = { nodes: currentNodes, edges: currentEdges };
    const previousState = undo(currentState);
    
    if (!previousState) return null;
    
    setNodes(previousState.nodes);
    setEdges(previousState.edges);
    if (setHasUnsavedChanges) setHasUnsavedChanges(true);
    
    return previousState;
  }, [undo, setNodes, setEdges, enabled, setHasUnsavedChanges]);

  const redoAction = useCallback(() => {
    if (!enabled) return null;
    
    // Read fresh state from the store to avoid stale closures
    const { nodes: currentNodes, edges: currentEdges } = useGraphStore.getState();
    const currentState = { nodes: currentNodes, edges: currentEdges };
    const nextState = redo(currentState);
    
    if (!nextState) return null;
    
    setNodes(nextState.nodes);
    setEdges(nextState.edges);
    if (setHasUnsavedChanges) setHasUnsavedChanges(true);
    
    return nextState;
  }, [redo, setNodes, setEdges, enabled, setHasUnsavedChanges]);

  const canUndo = useCallback(() => {
    return enabled && canUndoState();
  }, [enabled, canUndoState]);

  const canRedo = useCallback(() => {
    return enabled && canRedoState();
  }, [enabled, canRedoState]);

  const clear = useCallback(() => {
    clearHistory();
  }, [clearHistory]);

  return {
    saveHistory,
    undo: undoAction,
    redo: redoAction,
    canUndo,
    canRedo,
    clearHistory: clear,
  };
};
