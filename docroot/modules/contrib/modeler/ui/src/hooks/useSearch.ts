/**
 * Custom hook for search functionality in the modeler
 * Manages search terms, highlighting, and focus.
 *
 * When a search result is selected the corresponding element is selected on
 * the canvas (node or edge) and the viewport pans to bring it into view.
 * For edges (conditions) the viewport centers on the edge's source node
 * since edges don't have a fixed position of their own.
 */

import { useState, useCallback } from 'react';
import { useGraphStore } from '../store/useGraphStore';
import { useSelectionStore } from '../store/useSelectionStore';
import type { StoreNode as Node, StoreEdge as Edge } from '../types/settings';
import type { ViewportActions } from './useViewportActions';

interface UseSearchProps {
  /** Unified viewport actions for pan/zoom operations */
  viewportActions: ViewportActions;
}

export function useSearch({ viewportActions }: UseSearchProps) {
  const selectNode = useSelectionStore(state => state.selectNode);
  const selectEdge = useSelectionStore(state => state.selectEdge);
  const nodes = useGraphStore(state => state.nodes);

  // Search state
  const [searchTerm, setSearchTerm] = useState('');
  const [highlightedSearchResult, setHighlightedSearchResult] = useState<any>(null);

  /**
   * Center the viewport on the given node (by id).
   * Preserves the current zoom level.
   */
  const centerOnNode = useCallback((nodeId: string) => {
    viewportActions.focusNode(nodeId);
  }, [viewportActions]);

  /**
   * Select a search result on the canvas and pan to it.
   *
   * - Nodes are selected directly and the viewport centers on them.
   * - Edges (conditions) are selected and the viewport centers on the
   *   edge's source node.
   */
  const selectAndCenter = useCallback((result: { id: string; type: 'node' | 'edge'; data: Node | Edge }) => {
    if (result.type === 'node') {
      selectNode(result.data as Node);
      centerOnNode(result.id);
    } else {
      const edge = result.data as Edge;
      selectEdge(edge);
      // Center on the source node of the edge
      const sourceNode = nodes.find(n => n.id === edge.source);
      if (sourceNode) {
        centerOnNode(sourceNode.id);
      }
    }
  }, [selectNode, selectEdge, nodes, centerOnNode]);

  // Handle search result highlighting (hover / auto-select single result)
  const onSearchHighlight = useCallback((result: any) => {
    setHighlightedSearchResult(result);
    if (result?.data) {
      selectAndCenter(result);
    }
  }, [selectAndCenter]);

  // Handle search focus (when clicking on search results)
  const onSearchFocus = useCallback((_data: any) => {
    // Selection + centering is already handled by onSearchHighlight via
    // handleResultSelect in SearchBar.  This callback is kept for
    // backward compatibility but no longer needs to duplicate the logic.
  }, []);

  // Clear search
  const clearSearch = useCallback(() => {
    setSearchTerm('');
    setHighlightedSearchResult(null);
  }, []);

  return {
    // State
    searchTerm,
    highlightedSearchResult,
    
    // Actions
    onSearchHighlight,
    onSearchFocus,
    clearSearch,
  };
}
