/**
 * Custom hook for inserting condition nodes on edges, inserting action
 * nodes on edges, and adding event nodes to the canvas.
 *
 * All node placement decisions are delegated to the shared incremental
 * layout primitives in utils/incrementalLayout.ts so that interactive
 * edits produce visually identical output to auto-layout (see issue
 * #3588454).
 */
import { useCallback } from 'react';
import { useGraphStore } from '../store/useGraphStore';
import type { StoreNode as Node, StoreEdge as Edge, StoreComponent } from '../types/settings';
import { generateNodeId, generateEdgeId } from '../utils/clipboardUtils';

import { NODE_DIMENSIONS } from '../constants/dimensions';
import {
  computeNewEventPosition,
  placeNodeOnEdge,
  buildConditionInsertion,
  isConditionNode,
  placeChainOnEdge,
} from '../utils/incrementalLayout';
import { t } from '../utils/translation';
import type { ViewportActions } from './useViewportActions';

interface UseNodeEdgeActionsProps {
  setHasUnsavedChanges: (value: boolean) => void;
  /** Callback to save history before making changes */
  saveHistory?: () => void;
  /** Unified viewport actions for pan/zoom operations */
  viewportActions: ViewportActions;
}

export function useNodeEdgeActions({ setHasUnsavedChanges, saveHistory, viewportActions }: UseNodeEdgeActionsProps) {
  const nodes = useGraphStore(state => state.nodes);
  const edges = useGraphStore(state => state.edges);
  const setNodes = useGraphStore(state => state.setNodes);
  const setEdges = useGraphStore(state => state.setEdges);

  // ── Insert a condition NODE on an existing edge ───────────────────
  // Conditions are first-class nodes (issue #3589093).  "Add condition"
  // inserts a real condition node on the target edge — mirroring the
  // load-time translation in parseModelData — so the condition is
  // immediately a draggable, selectable node instead of an edge mutation
  // that only becomes a node after a reload.  This uses the exact same
  // edge-split + placement flow as handleAddActionOnEdge below.
  const handleAddCondition = useCallback((edgeId: string, component: StoreComponent) => {
    if (saveHistory) saveHistory();

    const targetEdge = edges.find(e => e.id === edgeId);
    if (!targetEdge) {
      console.error('Edge not found:', edgeId);
      return;
    }

    const sourceNodeId = targetEdge.source;
    const targetNodeId = targetEdge.target;

    const conditionLabel = component.label || component.plugin?.split('.').pop() || component.plugin;
    const newNodeId = generateNodeId(conditionLabel, 'condition');

    const newNode: Node = {
      id: newNodeId,
      type: 'condition',
      // Temporary position — placeChainOnEdge will replace it.
      position: { x: 0, y: 0 },
      selected: true,
      data: {
        label: conditionLabel,
        plugin: component.plugin,
        configuration: {},
        // New condition — export will mint a UUID when conditionId is empty.
        conditionId: '',
        componentType: 5,
        __isConditionNode: true,
      },
    };

    // Enforce the "no two adjacent conditions" invariant (issue #3589093):
    // if either end of the target edge is a condition node, route through a
    // gateway so we never produce a condition -> condition edge.
    const sourceNode = nodes.find(n => n.id === sourceNodeId);
    const targetNode = nodes.find(n => n.id === targetNodeId);
    const { nodesToAdd, edgesToAdd } = buildConditionInsertion({
      sourceNodeId,
      targetNodeId,
      conditionNode: newNode,
      sourceIsCondition: isConditionNode(sourceNode),
      targetIsCondition: isConditionNode(targetNode),
    });

    const allEdges = [
      ...edges.filter(e => e.id !== edgeId).map(e => e.selected ? { ...e, selected: false } : e),
      ...edgesToAdd,
    ];

    const deselectedNodes = nodes.map(n => n.selected ? { ...n, selected: false } : n);
    // Position the whole chain (condition + any gateway) below the source,
    // shifting the target and its descendants down to make room.
    const positionedNodes = placeChainOnEdge(
      deselectedNodes,
      allEdges,
      nodesToAdd,
      sourceNodeId,
      targetNodeId,
    );

    setNodes(positionedNodes);
    setEdges(allEdges);
    setHasUnsavedChanges(true);
    viewportActions.panToNodeIfOffscreen(newNodeId);
  }, [nodes, edges, setNodes, setEdges, setHasUnsavedChanges, saveHistory, viewportActions]);

  // ── Add an event/start node to the canvas ─────────────────────────
  const handleAddEvent = useCallback((component: StoreComponent) => {
    if (saveHistory) saveHistory();
    const label = component.label || component.plugin?.split('.').pop() || t('New Event');
    const newNodeId = generateNodeId(label, 'start');

    // Shared primitive places the new event flow to the right of all
    // existing flows — identical behavior to auto-layout's start-node
    // reservation logic.
    const freePosition = computeNewEventPosition({
      nodes,
      width: NODE_DIMENSIONS.START_NODE_WIDTH,
      height: NODE_DIMENSIONS.START_NODE_HEIGHT,
    });

    const newNode: Node = {
      id: newNodeId,
      type: 'start',
      position: freePosition,
      selected: true,
      data: {
        plugin: component.plugin,
        label,
        componentType: component.componentType,
        description: component.description,
        documentationUrl: component.documentationUrl,
      },
    };

    setNodes(prev => [...prev.map(n => n.selected ? { ...n, selected: false } : n), newNode]);
    setEdges(prev => {
      const hasSelected = prev.some(e => e.selected);
      return hasSelected ? prev.map(e => e.selected ? { ...e, selected: false } : e) : prev;
    });

    setHasUnsavedChanges(true);
    viewportActions.panToNodeIfOffscreen(newNodeId);
  }, [nodes, setNodes, setEdges, setHasUnsavedChanges, saveHistory, viewportActions]);

  // ── Insert an action/gateway node on an existing edge ─────────────
  const handleAddActionOnEdge = useCallback((edgeId: string, component: StoreComponent) => {
    if (saveHistory) saveHistory();

    const targetEdge = edges.find(e => e.id === edgeId);
    if (!targetEdge) {
      console.error('Edge not found:', edgeId);
      return;
    }

    const sourceNodeId = targetEdge.source;
    const targetNodeId = targetEdge.target;

    const nodeType = component.type === 'gateway' ? 'gateway' : 'element';
    const label = component.label || component.plugin?.split('.').pop() || t('New Node');
    const newNodeId = generateNodeId(label, nodeType);

    const newNode: Node = {
      id: newNodeId,
      type: nodeType,
      // Temporary position — placeNodeOnEdge will replace it.
      position: { x: 0, y: 0 },
      selected: true,
      data: {
        plugin: component.plugin,
        label,
        componentType: component.componentType,
        description: component.description,
        documentationUrl: component.documentationUrl,
      },
    };

    const edgeToNew: Edge = {
      id: generateEdgeId(sourceNodeId, newNodeId),
      source: sourceNodeId,
      target: newNodeId,
      type: 'default' as const,
      data: {},
    };

    const edgeFromNew: Edge = {
      id: generateEdgeId(newNodeId, targetNodeId),
      source: newNodeId,
      target: targetNodeId,
      type: 'default' as const,
      data: {},
    };

    const allEdges = [
      ...edges.filter(e => e.id !== edgeId).map(e => e.selected ? { ...e, selected: false } : e),
      edgeToNew,
      edgeFromNew,
    ];

    const deselectedNodes = nodes.map(n => n.selected ? { ...n, selected: false } : n);
    const positionedNodes = placeNodeOnEdge(
      deselectedNodes,
      allEdges,
      newNode,
      sourceNodeId,
      targetNodeId,
    );

    setNodes(positionedNodes);
    setEdges(allEdges);
    setHasUnsavedChanges(true);
    viewportActions.panToNodeIfOffscreen(newNodeId);
  }, [nodes, edges, setNodes, setEdges, setHasUnsavedChanges, saveHistory, viewportActions]);

  return {
    handleAddCondition,
    handleAddEvent,
    handleAddActionOnEdge,
  };
}
