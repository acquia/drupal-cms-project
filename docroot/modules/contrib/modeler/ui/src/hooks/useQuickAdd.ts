/**
 * Custom hook for quick-add functionality
 * Handles creating new nodes and connecting them to existing nodes.
 *
 * All node placement decisions are delegated to the shared incremental
 * layout primitives in utils/incrementalLayout.ts so that quick-add
 * produces visually identical output to auto-layout (issue #3588454).
 */

import { useCallback } from 'react';
import { useGraphStore } from '../store/useGraphStore';
import type { StoreComponent as Component } from '../types/settings';
import { generateNodeId, generateEdgeId } from '../utils/clipboardUtils';

import { LAYOUT, NODE_DIMENSIONS } from '../constants/dimensions';
import { shiftNodesDown } from '../utils/positionUtils';
import {
  computeSuccessorPosition,
  applyFlowShifts,
  isConditionNode,
} from '../utils/incrementalLayout';
import { t } from '../utils/translation';
import type { ViewportActions } from './useViewportActions';

interface UseQuickAddProps {
  setHasUnsavedChanges: (value: boolean) => void;
  /** Callback to save history before making changes */
  saveHistory?: () => void;
  /** Unified viewport actions for pan/zoom operations */
  viewportActions: ViewportActions;
}

export function useQuickAdd({ setHasUnsavedChanges, saveHistory, viewportActions }: UseQuickAddProps) {
  const nodes = useGraphStore(state => state.nodes);
  const edges = useGraphStore(state => state.edges);
  const setNodes = useGraphStore(state => state.setNodes);
  const setEdges = useGraphStore(state => state.setEdges);

  /**
   * Add a new node as a successor to an existing node.
   *
   * Position is computed by the shared {@link computeSuccessorPosition}
   * primitive — same code path as auto-layout.
   */
  const addSuccessorNode = useCallback((component: Component, sourceNodeId: string) => {
    if (saveHistory) saveHistory();
    const sourceNode = nodes.find(n => n.id === sourceNodeId);
    if (!sourceNode) {
      console.error('Source node not found:', sourceNodeId);
      return;
    }

    const { position: newPosition, shiftNodeIds, shiftAmount } = computeSuccessorPosition({
      nodes,
      edges,
      sourceNodeId,
    });

    // Determine node type from component
    const nodeType = component.type === 'start' ? 'start' :
                     component.type === 'gateway' ? 'gateway' :
                     'element';

    const label = component.label || component.plugin?.split('.').pop() || 'New Node';
    const newNodeId = generateNodeId(label, nodeType);

    const newNode = {
      id: newNodeId,
      type: nodeType,
      position: newPosition,
      selected: true,
      data: {
        plugin: component.plugin,
        label,
        componentType: component.componentType,
        description: component.description,
        documentationUrl: component.documentationUrl,
      },
    };

    const newEdgeId = generateEdgeId(sourceNodeId, newNodeId);
    const newEdge = {
      id: newEdgeId,
      source: sourceNodeId,
      target: newNodeId,
      type: 'default',
      data: {},
    };

    setNodes(prev => {
      const updated = applyFlowShifts(prev, shiftNodeIds, shiftAmount);
      return [...updated, newNode];
    });
    setEdges(prev => [...prev.map(e => e.selected ? { ...e, selected: false } : e), newEdge]);

    setHasUnsavedChanges(true);
    viewportActions.panToNodeIfOffscreen(newNodeId);
  }, [nodes, edges, setNodes, setEdges, setHasUnsavedChanges, saveHistory, viewportActions]);

  /**
   * Add a condition NODE with a downstream placeholder action node.
   *
   * Conditions are first-class nodes now (issue #3589093), so condition-first
   * authoring creates a real condition node followed by a placeholder action
   * node: `source -> conditionNode -> placeholder`.  The condition node carries
   * the same data shape minted by {@link handleAddCondition} and the plugin
   * API's `setCondition` (type `condition`, `__isConditionNode: true`,
   * `componentType: 5`).  This supports the flow where users want to define a
   * condition before choosing an action, while keeping the condition as a
   * draggable, selectable node — no code path creates a condition edge.
   *
   * Both new nodes are laid out as a normal 2-node successor chain via the
   * shared {@link computeSuccessorPosition} primitive (one hop per node), so
   * the spacing matches auto-layout.  The condition node is auto-selected so
   * its configuration panel opens immediately (mirroring the prior intent
   * where the condition was selected for configuration).
   */
  const addConditionWithPlaceholder = useCallback((component: Component, sourceNodeId: string) => {
    if (saveHistory) saveHistory();

    const sourceNode = nodes.find(n => n.id === sourceNodeId);
    if (!sourceNode) {
      console.error('Source node not found:', sourceNodeId);
      return;
    }

    // ── 0. Enforce the "no two adjacent conditions" invariant ──────────
    // (issue #3589093) If the source is itself a condition node, a gateway
    // must separate it from the new condition node, producing
    // source(condition) -> gateway -> conditionNode -> placeholder.  The
    // placeholder target is always fresh and never a condition, so only the
    // source side can ever be adjacent here.
    const sourceIsCondition = isConditionNode(sourceNode);

    // The "parent" the condition node hangs from: the gateway when one is
    // inserted, otherwise the source directly.
    let conditionParentId = sourceNodeId;
    let workingNodes = nodes;
    let gatewayNode: {
      id: string;
      type: 'gateway';
      position: { x: number; y: number };
      selected: boolean;
      data: { componentType: number; plugin: string; label: string };
    } | null = null;

    if (sourceIsCondition) {
      const {
        position: gatewayPosition,
        shiftNodeIds: gwShiftNodeIds,
        shiftAmount: gwShiftAmount,
      } = computeSuccessorPosition({
        nodes: workingNodes,
        edges,
        sourceNodeId,
      });

      const gatewayNodeId = generateNodeId(t('Gateway'), 'gateway');
      gatewayNode = {
        id: gatewayNodeId,
        type: 'gateway' as const,
        position: gatewayPosition,
        selected: false,
        data: {
          componentType: 6,
          plugin: 'gateway',
          label: t('Gateway'),
        },
      };

      workingNodes = [
        ...applyFlowShifts(workingNodes, gwShiftNodeIds, gwShiftAmount),
        gatewayNode,
      ];
      conditionParentId = gatewayNodeId;
    }

    // ── 1. Place the condition node as its parent's successor ──────────
    const {
      position: conditionPosition,
      shiftNodeIds,
      shiftAmount,
    } = computeSuccessorPosition({
      nodes: workingNodes,
      edges,
      sourceNodeId: conditionParentId,
    });

    const conditionLabel = component.label || component.plugin?.split('.').pop() || component.plugin;
    const conditionNodeId = generateNodeId(conditionLabel, 'condition');

    const conditionNode = {
      id: conditionNodeId,
      type: 'condition' as const,
      position: conditionPosition,
      selected: true,
      data: {
        label: conditionLabel,
        plugin: component.plugin,
        configuration: {},
        // New condition — export mints a UUID when conditionId is empty.
        conditionId: '',
        componentType: 5,
        __isConditionNode: true,
      },
    };

    // Apply horizontal flow shifts for neighboring flows around the condition
    // node, deselecting any previously selected nodes in the process.
    const nodesWithCondition = [
      ...applyFlowShifts(workingNodes, shiftNodeIds, shiftAmount),
      conditionNode,
    ];

    // ── 2. Place the placeholder as the condition node's successor ─────
    const {
      position: placeholderPosition,
      shiftNodeIds: phShiftNodeIds,
      shiftAmount: phShiftAmount,
    } = computeSuccessorPosition({
      nodes: nodesWithCondition,
      edges,
      sourceNodeId: conditionNodeId,
    });

    const placeholderLabel = t('Select action...');
    const placeholderNodeId = generateNodeId(placeholderLabel, 'placeholder');

    const placeholderNode = {
      id: placeholderNodeId,
      type: 'placeholder' as const,
      position: placeholderPosition,
      selected: false,
      data: {
        label: placeholderLabel,
      },
    };

    // Apply horizontal flow shifts for the placeholder hop.  Do not deselect
    // here so the condition node stays selected for configuration.
    let updatedNodes = applyFlowShifts(
      nodesWithCondition,
      phShiftNodeIds,
      phShiftAmount,
      { deselectAll: false },
    );

    // Shift everything at or below the placeholder's Y downward so nothing
    // overlaps the newly added condition + placeholder chain.  Exclude the
    // source and the two new nodes themselves.
    const placeholderBottom = placeholderPosition.y + NODE_DIMENSIONS.CARD_HEIGHT;
    const verticalShift = placeholderBottom + LAYOUT.NODE_SPACING_Y;
    // Exclude the source, the new chain nodes (gateway if any, condition,
    // placeholder) from the downstream shift.
    const newChainIds = new Set<string>([sourceNodeId, conditionNodeId, placeholderNodeId]);
    if (gatewayNode) newChainIds.add(gatewayNode.id);
    updatedNodes = shiftNodesDown(
      updatedNodes,
      placeholderPosition.y,
      Math.max(0, verticalShift - placeholderPosition.y),
      newChainIds,
    );

    const allNodes = [...updatedNodes, placeholderNode];

    // ── 3. Wire the chain with plain edges, inserting a gateway hop when
    // the source is a condition node so we never create a condition ->
    // condition edge (issue #3589093):
    //   * no gateway: source -> condition -> placeholder
    //   * gateway:    source -> gateway -> condition -> placeholder
    const edgeToPlaceholder = {
      id: generateEdgeId(conditionNodeId, placeholderNodeId),
      source: conditionNodeId,
      target: placeholderNodeId,
      type: 'default' as const,
      data: {},
    };

    const chainEdges = gatewayNode
      ? [
          {
            id: generateEdgeId(sourceNodeId, gatewayNode.id),
            source: sourceNodeId,
            target: gatewayNode.id,
            type: 'default' as const,
            data: {},
          },
          {
            id: generateEdgeId(gatewayNode.id, conditionNodeId),
            source: gatewayNode.id,
            target: conditionNodeId,
            type: 'default' as const,
            data: {},
          },
          edgeToPlaceholder,
        ]
      : [
          {
            id: generateEdgeId(sourceNodeId, conditionNodeId),
            source: sourceNodeId,
            target: conditionNodeId,
            type: 'default' as const,
            data: {},
          },
          edgeToPlaceholder,
        ];

    const allEdges = [
      ...edges.map(e => e.selected ? { ...e, selected: false } : e),
      ...chainEdges,
    ];

    setNodes(allNodes);
    setEdges(allEdges);
    setHasUnsavedChanges(true);

    // Pan to show the source node together with the new condition +
    // placeholder chain so the whole insertion is visible.
    viewportActions.fitToNodePair(sourceNodeId, placeholderNodeId);
  }, [nodes, edges, setNodes, setEdges, setHasUnsavedChanges, saveHistory, viewportActions]);

  return {
    addSuccessorNode,
    addConditionWithPlaceholder,
  };
}
