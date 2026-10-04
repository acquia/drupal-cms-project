import { useCallback } from 'react';
import type { StoreNode as Node, StoreEdge as Edge } from '../types/settings';
import { copyElements, pasteElements } from '../utils/clipboardUtils';
import { t } from '../utils/translation';

interface UseClipboardProps {
  selectedNode: Node | null;
  selectedEdge: Edge | null;
  /** IDs of all selected nodes (multi-selection) */
  selectedNodeIds: string[];
  /** IDs of all selected edges (multi-selection) */
  selectedEdgeIds: string[];
  nodes: Node[];
  edges: Edge[];
  setNodes: (nodes: Node[] | ((nodes: Node[]) => Node[])) => void;
  setEdges: (edges: Edge[] | ((edges: Edge[]) => Edge[])) => void;
  setSelectedNode: (node: Node | null) => void;
  setSelectedEdge: (edge: Edge | null) => void;
  setSelectedNodes: (nodes: string[]) => void;
  setSelectedEdges: (edges: string[]) => void;
  /** Callback to mark model as having unsaved changes */
  setHasUnsavedChanges: (value: boolean) => void;
  /** Screen reader announcement callback */
  announce?: (text: string) => void;
  /** Callback to save history before making changes */
  saveHistory?: () => void;
}

export const useClipboard = ({
  selectedNode,
  selectedEdge,
  selectedNodeIds,
  selectedEdgeIds,
  nodes,
  edges,
  setNodes,
  setEdges,
  setSelectedNode,
  setSelectedEdge,
  setSelectedNodes,
  setSelectedEdges,
  setHasUnsavedChanges,
  announce,
  saveHistory,
}: UseClipboardProps) => {
  const handleCopy = useCallback(() => {
    // Resolve full node/edge objects from multi-selection IDs
    const nodesToCopy = selectedNodeIds.length > 0
      ? nodes.filter(n => selectedNodeIds.includes(n.id))
      : selectedNode ? [selectedNode] : [];
    const edgesToCopy = selectedEdgeIds.length > 0
      ? edges.filter(e => selectedEdgeIds.includes(e.id))
      : selectedEdge ? [selectedEdge] : [];

    if (nodesToCopy.length === 0 && edgesToCopy.length === 0) return;

    copyElements(nodesToCopy, edgesToCopy);
    if (announce) {
      const count = nodesToCopy.length + edgesToCopy.length;
      announce(t('@count elements copied.', { '@count': String(count) }));
    }
  }, [selectedNode, selectedEdge, selectedNodeIds, selectedEdgeIds, nodes, edges, announce]);

  const handlePaste = useCallback(async () => {
    if (saveHistory) saveHistory();
    // Just use null to let pasteElements use its default offset (50, 50)
    // This will paste elements slightly offset from their original position
    const result = await pasteElements(nodes, edges, null);

    if (result && result.nodes.length > 0) {
      // Deselect all previously selected nodes and merge pasted elements
      setNodes(prev => [
        ...prev.map(n => n.selected ? { ...n, selected: false } : n),
        ...result.nodes.map(n => ({ ...n, selected: true })),
      ]);
      setEdges(prev => [
        ...prev.map(e => e.selected ? { ...e, selected: false } : e),
        ...result.edges.map(e => ({ ...e, selected: true })),
      ]);

      // Update single selection (first pasted node for property panel)
      setSelectedNode(result.nodes[0]);
      setSelectedEdge(null);

      // Update multi-selection arrays to reflect pasted elements
      setSelectedNodes(result.nodes.map(n => n.id));
      setSelectedEdges(result.edges.map(e => e.id));

      setHasUnsavedChanges(true);

      if (announce) {
        const count = result.nodes.length + result.edges.length;
        announce(t('@count elements pasted.', { '@count': String(count) }));
      }
    }
  }, [nodes, edges, setNodes, setEdges, setSelectedNode, setSelectedEdge, setSelectedNodes, setSelectedEdges, setHasUnsavedChanges, announce, saveHistory]);

  const canCopy = selectedNodeIds.length > 0 || selectedEdgeIds.length > 0 ||
    selectedNode !== null || selectedEdge !== null;
  const canPaste = true; // pasteElements handles clipboard validation

  return {
    handleCopy,
    handlePaste,
    canCopy,
    canPaste,
  };
};