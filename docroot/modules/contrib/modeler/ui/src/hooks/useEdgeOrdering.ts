import { useCallback, useRef } from 'react';
import { Node, Edge } from 'reactflow';
import type { EdgeOrderInfo } from '../types/settings';
import { NODE_DIMENSIONS } from '../constants/dimensions';

interface UseEdgeOrderingProps {
  edges: Edge[];
  nodes: Node[];
  setEdges: (updater: (edges: Edge[]) => Edge[]) => void;
  setHasUnsavedChanges: (value: boolean) => void;
}

interface UseEdgeOrderingReturn {
  handleDragStart: (event: React.DragEvent, edgeId: string) => void;
  handleDragEnd: () => void;
  handleEdgeOrderDrop: (event: React.DragEvent, targetEdgeId: string) => void;
  handleReorderEdge: (sourceNodeId: string, fromOrder: number, toOrder: number) => void;
  getEdgeOrderInfo: (edge: Edge, allEdges: Edge[]) => EdgeOrderInfo | null;
}

/**
 * Hook for managing edge ordering functionality.
 * Handles drag-and-drop reordering of successor edges and calculates edge order display info.
 */
export function useEdgeOrdering({
  edges,
  nodes,
  setEdges,
  setHasUnsavedChanges,
}: UseEdgeOrderingProps): UseEdgeOrderingReturn {
  const dragRef = useRef<string | null>(null);

  // Apply new edge order to the edges array, updating each edge's data.order
  const applyEdgeReorder = useCallback((sourceNodeId: string, newOrder: string[]) => {
    setEdges(prev => {
      // Get all edges from the same source node
      const sourceEdges = prev.filter(edge => edge.source === sourceNodeId);
      const otherEdges = prev.filter(edge => edge.source !== sourceNodeId);

      // Reorder the source edges and update data.order to persist the new positions
      const reorderedEdges = newOrder.map((edgeId, index) => {
        const edge = sourceEdges.find(e => e.id === edgeId);
        if (!edge) return null;
        return {
          ...edge,
          data: {
            ...edge.data,
            order: index,
          },
        };
      }).filter(Boolean) as Edge[];

      // Return all edges with the reordered source edges
      return [...otherEdges, ...reorderedEdges];
    });
    setHasUnsavedChanges(true);
  }, [setEdges, setHasUnsavedChanges]);

  // Drag start handler for edge order badges
  const handleDragStart = useCallback((event: React.DragEvent, edgeId: string) => {
    dragRef.current = edgeId;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', edgeId);
  }, []);

  // Drag end handler - clears the drag ref
  const handleDragEnd = useCallback(() => {
    dragRef.current = null;
  }, []);

  // Drop handler for edge order badges (used by FlowCanvas drag ref approach)
  const handleEdgeOrderDrop = useCallback((event: React.DragEvent, targetEdgeId: string) => {
    event.preventDefault();
    const draggedEdgeId = dragRef.current;

    if (draggedEdgeId && draggedEdgeId !== targetEdgeId) {
      const draggedEdge = edges.find(e => e.id === draggedEdgeId);
      const targetEdge = edges.find(e => e.id === targetEdgeId);

      // Only allow reordering within edges from the same source
      if (draggedEdge && targetEdge && draggedEdge.source === targetEdge.source) {
        const sourceNodeId = draggedEdge.source;
        const sourceEdges = edges.filter(e => e.source === sourceNodeId);

        const currentOrder = sourceEdges.map(e => e.id);
        const draggedIndex = currentOrder.indexOf(draggedEdgeId);
        const targetIndex = currentOrder.indexOf(targetEdgeId);

        // Reorder the array
        const newOrder = [...currentOrder];
        newOrder.splice(draggedIndex, 1);
        newOrder.splice(targetIndex, 0, draggedEdgeId);

        applyEdgeReorder(sourceNodeId, newOrder);
      }
    }
  }, [edges, applyEdgeReorder]);

  // Handler for edge order reordering (called from DefaultEdge drag/drop with order numbers)
  const handleReorderEdge = useCallback((sourceNodeId: string, fromOrder: number, toOrder: number) => {
    // Get all edges from the source node (same sorting as getEdgeOrderInfo)
    const sourceEdges = edges
      .filter(e => e.source === sourceNodeId)
      .sort((a, b) => {
        const aOrder = a.data?.order ?? parseInt(a.id.split('_').pop() || '0', 10);
        const bOrder = b.data?.order ?? parseInt(b.id.split('_').pop() || '0', 10);
        return aOrder - bOrder;
      });

    // Convert from 1-based order to 0-based index
    const fromIndex = fromOrder - 1;
    const toIndex = toOrder - 1;

    if (fromIndex < 0 || fromIndex >= sourceEdges.length || toIndex < 0 || toIndex >= sourceEdges.length) {
      return;
    }

    // Build the new order array by swapping the edges
    const currentOrder = sourceEdges.map(e => e.id);
    const newOrder = [...currentOrder];

    // Swap the two edges
    const temp = newOrder[fromIndex];
    newOrder[fromIndex] = newOrder[toIndex];
    newOrder[toIndex] = temp;

    applyEdgeReorder(sourceNodeId, newOrder);
  }, [edges, applyEdgeReorder]);

  // Calculate edge order information for display
  const getEdgeOrderInfo = useCallback((edge: Edge, allEdges: Edge[]): EdgeOrderInfo | null => {
    // Get all edges from the same source node
    const edgesFromSource = allEdges
      .filter(e => e.source === edge.source)
      .sort((a, b) => {
        // Primary sort by creation order (if order field exists) or edge ID
        const aOrder = a.data?.order ?? parseInt(a.id.split('_').pop() || '0', 10);
        const bOrder = b.data?.order ?? parseInt(b.id.split('_').pop() || '0', 10);
        return aOrder - bOrder;
      });

    if (edgesFromSource.length <= 1) {
      return null; // No ordering needed for single edges
    }

    // Find the index of this edge among unlocked edges
    const index = edgesFromSource.findIndex(e => e.id === edge.id);
    const order = index + 1; // 1-based numbering
    const totalEdges = edgesFromSource.length;

    // Find source and target nodes to calculate edge path
    const sourceNode = nodes.find(n => n.id === edge.source);
    const targetNode = nodes.find(n => n.id === edge.target);

    if (!sourceNode || !targetNode) {
      return {
        order,
        totalEdges,
        sourceNodeId: edge.source,
        pathX: 100,
        pathY: 100
      };
    }

    // Get the control point offset from edge data (if edge has been manipulated)
    const controlOffset = edge.data?.controlOffset || { x: 0, y: 0 };

    // Calculate positions from node centers (ReactFlow handles the exact handle positions)
    const sourceX = sourceNode.position.x + (sourceNode.width || NODE_DIMENSIONS.DEFAULT_WIDTH) / 2;
    const sourceY = sourceNode.position.y + (sourceNode.height || NODE_DIMENSIONS.DEFAULT_HEIGHT) / 2;
    const targetX = targetNode.position.x + (targetNode.width || NODE_DIMENSIONS.DEFAULT_WIDTH) / 2;
    const targetY = targetNode.position.y + (targetNode.height || NODE_DIMENSIONS.DEFAULT_HEIGHT) / 2;

    // Calculate the edge center (same as where conditions are placed)
    const edgeCenterX = (sourceX + targetX) / 2;
    const edgeCenterY = (sourceY + targetY) / 2;

    // Use the control point position if it exists (same as condition position)
    const pathX = edgeCenterX + controlOffset.x;
    const pathY = edgeCenterY + controlOffset.y;

    return {
      order,
      totalEdges,
      sourceNodeId: edge.source,
      pathX,
      pathY
    };
  }, [nodes]);

  return {
    handleDragStart,
    handleDragEnd,
    handleEdgeOrderDrop,
    handleReorderEdge,
    getEdgeOrderInfo,
  };
}


