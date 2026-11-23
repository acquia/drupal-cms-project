/**
 * Custom hook that synchronizes selected node/edge objects when the underlying
 * nodes or edges arrays change. Ensures the PropertyPanel always shows the
 * current state of the selected element.
 */
import { useEffect } from 'react';
import { useGraphStore } from '../store/useGraphStore';
import { useSelectionStore } from '../store/useSelectionStore';

export function useSelectionSync() {
  const nodes = useGraphStore(state => state.nodes);
  const edges = useGraphStore(state => state.edges);
  const selectedNode = useSelectionStore(state => state.selectedNode);
  const selectedEdge = useSelectionStore(state => state.selectedEdge);
  const setSelectedNode = useSelectionStore(state => state.setSelectedNode);
  const setSelectedEdge = useSelectionStore(state => state.setSelectedEdge);

  useEffect(() => {
    if (selectedNode) {
      const updatedNode = nodes.find(n => n.id === selectedNode.id);
      if (updatedNode && updatedNode !== selectedNode) {
        setSelectedNode(updatedNode);
      }
    }
    if (selectedEdge) {
      const updatedEdge = edges.find(e => e.id === selectedEdge.id);
      if (updatedEdge && updatedEdge !== selectedEdge) {
        setSelectedEdge(updatedEdge);
      }
    }
  }, [nodes, edges, selectedNode, selectedEdge, setSelectedNode, setSelectedEdge]);
}
