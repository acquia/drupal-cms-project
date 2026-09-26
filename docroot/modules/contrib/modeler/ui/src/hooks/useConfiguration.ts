/**
 * Custom hook for node and edge configuration management
 * Handles configuration changes, updates, and auto-layout functionality
 */

import { useCallback } from 'react';
import { useGraphStore } from '../store/useGraphStore';
import { autoLayout } from '../utils/modelUtils';
import { getEdgeType } from '../utils/edgeTypeUtils';
import type { NodeData, EdgeData } from '../types/settings';

interface UseConfigurationProps {
  setHasUnsavedChanges: (value: boolean) => void;
  /** Callback to save history before making changes */
  saveHistory?: () => void;
}

export function useConfiguration({ setHasUnsavedChanges, saveHistory }: UseConfigurationProps) {
  const setNodes = useGraphStore(state => state.setNodes);
  const setEdges = useGraphStore(state => state.setEdges);

  // Configuration change handler - updates node configuration (primary callback)
  //
  // Deliberately does NOT write to the selection store (issue #3589111).
  // Refreshing the selected node object is useSelectionSync's job: it runs in
  // an effect keyed on the nodes array, so it reads fresh state after the
  // commit that follows setNodes() and only writes when the object identity
  // actually changed. Doing it here as well would mean reading a render-time
  // snapshot that setNodes() has not updated, pushing the pre-update node back
  // into the property panel and clobbering debounced fields mid-edit.
  const onConfigurationChange = useCallback((nodeId: string, configuration: Record<string, unknown>) => {
    if (saveHistory) saveHistory();
    setNodes(prev => prev.map(node => 
      node.id === nodeId 
        ? { 
            ...node, 
            data: { 
              ...(node.data || {}), // Ensure data is never undefined
              // Store configuration in the configuration object
              configuration: { ...(node.data?.configuration || {}), ...configuration },
              // Also map _componentLabel to label for display
              ...(configuration._componentLabel ? { label: String(configuration._componentLabel) } : {}),
            }
          }
        : node
    ));

    setHasUnsavedChanges(true);
  }, [setNodes, setHasUnsavedChanges, saveHistory]);

  // Node update handler (for broader updates than just config)
  const onNodeUpdate = useCallback((nodeId: string, newData: Partial<NodeData>) => {
    if (saveHistory) saveHistory();
    setNodes(prev => prev.map(node => 
      node.id === nodeId 
        ? { ...node, data: { ...node.data, ...newData } }
        : node
    ));
    setHasUnsavedChanges(true);
  }, [setNodes, setHasUnsavedChanges, saveHistory]);

  // Edge update handler (for broader updates than just config)
  const onEdgeUpdate = useCallback((edgeId: string, newData: Partial<EdgeData>) => {
    if (saveHistory) saveHistory();
    setEdges(prev => prev.map(edge => 
      edge.id === edgeId 
        ? { 
            ...edge, 
            data: { ...edge.data, ...newData }, // Merge with existing data instead of replacing
            // Update edge type based on merged data
            type: getEdgeType({ ...edge.data, ...newData })
          }
        : edge
    ));
    setHasUnsavedChanges(true);
  }, [setEdges, setHasUnsavedChanges, saveHistory]);

  // Endpoint reconnection handler (issue #3585553).
  //
  // Unlike onEdgeUpdate (which merges into edge `.data`), reconnection changes
  // TOP-LEVEL edge fields — `source`, `target`, `sourceHandle`, `targetHandle`.
  // A pure reconnect does NOT change edge data, so the edge `type` is left
  // untouched (no getEdgeType recompute). saveHistory() runs first so undo/redo
  // and unsaved-changes tracking work exactly like other mutations. Only the
  // fields present in `updates` are applied; the unchanged end is preserved.
  const onReconnectEdge = useCallback(
    (
      edgeId: string,
      updates: {
        source?: string;
        sourceHandle?: string | null;
        target?: string;
        targetHandle?: string | null;
      },
    ) => {
      if (saveHistory) saveHistory();
      setEdges(prev =>
        prev.map(edge =>
          edge.id === edgeId
            ? {
                ...edge,
                ...(updates.source !== undefined ? { source: updates.source } : {}),
                ...(updates.target !== undefined ? { target: updates.target } : {}),
                ...(updates.sourceHandle !== undefined ? { sourceHandle: updates.sourceHandle } : {}),
                ...(updates.targetHandle !== undefined ? { targetHandle: updates.targetHandle } : {}),
              }
            : edge,
        ),
      );
      setHasUnsavedChanges(true);
    },
    [setEdges, setHasUnsavedChanges, saveHistory],
  );

  // Auto-layout handler
  //
  // Reads the live graph from the store at call time instead of closing over
  // a render-time snapshot (issue #3589109). The plugin API mutates the store
  // synchronously, so a plugin can add nodes and call autoLayout() within a
  // single tick — before React has re-rendered. A captured snapshot would
  // therefore be stale, and because setNodes() replaces the whole array when
  // given a plain array, writing that snapshot back deleted every node added
  // in the same tick and left orphaned edges behind. Reading getState() here
  // also removes nodes/edges from the dependency array, making the callback
  // stable for the plugin-API hook registered in Flow.tsx.
  const handleAutoLayout = useCallback(() => {
    if (saveHistory) saveHistory();
    const { nodes: currentNodes, edges: currentEdges } = useGraphStore.getState();
    const layoutedNodes = autoLayout(currentNodes, currentEdges);
    if (layoutedNodes) {
      setNodes(layoutedNodes);
      setHasUnsavedChanges(true);
    }
  }, [setNodes, setHasUnsavedChanges, saveHistory]);

  return {
    onConfigurationChange,
    onNodeUpdate,
    onEdgeUpdate,
    onReconnectEdge,
    handleAutoLayout
  };
}