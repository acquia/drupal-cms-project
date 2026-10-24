/**
 * replayStepUtils - Shared utilities for replay step display and matching
 * 
 * Provides:
 * - Step type predicates for consistent step-type checks across replay hooks
 * - Element-matching functions to find canvas elements for replay steps and vice versa
 * - Icon rendering and label generation for replay steps
 * 
 * Used by ReplayPanel, ReplayTab, useSimpleReplaySync, useReplayController,
 * useReplayCoordination, and useReplayStepFilter.
 */

import React from 'react';
import { FiChevronRight, FiAlertTriangle, FiXCircle, FiClock } from 'react-icons/fi';
import type { IconType } from 'react-icons';
import type { StoreNode as Node, StoreEdge as Edge, ReplayDataEntry } from '../types/settings';
import { NODE_TYPE_ICONS, DEFAULT_NODE_ICON, getNodeTypeIcon } from './nodeIcons';
import { t } from './translation';

/**
 * Alias for {@link ReplayDataEntry} — used throughout the replay subsystem.
 * Kept as a named export so existing consumers (`useSimpleReplaySync`,
 * `FlowCanvas`, etc.) do not need renaming.
 */
export type ReplayStep = ReplayDataEntry;

// ============ Step Type Predicates ============

/** Check if a step represents direct node execution (started, execute, access denied, exception) */
export function isNodeExecutionStep(step: ReplayStep): boolean {
  return step.type === 'started' || step.type === 'execute' || step.type === 'access denied' || step.type === 'exception';
}

/** Check if a step is a successor step (add successor or ignore successor) */
export function isSuccessorStep(step: ReplayStep): boolean {
  return step.type === 'add successor' || step.type === 'ignore successor';
}

/** Check if a step is an 'add successor' step */
export function isAddSuccessorStep(step: ReplayStep): boolean {
  return step.type === 'add successor';
}

/** Check if a step is an 'access denied' step */
export function isAccessDeniedStep(step: ReplayStep): boolean {
  return step.type === 'access denied';
}

/** Check if a step is an 'exception' step */
export function isExceptionStep(step: ReplayStep): boolean {
  return step.type === 'exception';
}

/** Check if a step is a condition step (successor step with conditionId) */
export function isConditionStep(step: ReplayStep): boolean {
  return isSuccessorStep(step) && !!step.conditionId;
}

// ============ Condition Node Helpers ============

/**
 * Minimal structural shape required to match a canvas node against a replay
 * step's `conditionId`.
 *
 * Declared structurally (rather than as `StoreNode`) so that callers holding
 * plain ReactFlow `Node` objects — such as `replayHighlightUtils` — can use
 * the same helpers without a cast.
 */
export interface ConditionNodeLike {
  id: string;
  type?: string;
  data?: {
    plugin?: unknown;
    conditionId?: unknown;
    __isConditionNode?: unknown;
  };
}

/**
 * A node is a condition node (issue #3589093) when its type is `condition`
 * or its data carries the synthesized `__isConditionNode` marker.
 */
export function isConditionNode(node: ConditionNodeLike | null | undefined): boolean {
  if (!node) return false;
  return node.type === 'condition' || node.data?.__isConditionNode === true;
}

/**
 * Return every identifier a condition node can be matched by, in priority
 * order, when comparing against a replay step's `conditionId`.
 *
 * 1. `data.conditionId` — the ECA condition *config* id. This is what
 *    `ProcessDebugger` puts into `step.conditionId` and what the modeler
 *    backend plugin round-trips as `edge.conditionId`, so it is the correct
 *    primary key.
 * 2. `data.plugin` — the condition *plugin* id. Retained only as a legacy
 *    fallback for replay data recorded before the config id was emitted.
 *
 * Empty/blank identifiers are omitted so they can never match a blank
 * `conditionId` by accident.
 */
export function getConditionNodeIdentifiers(node: ConditionNodeLike): string[] {
  const identifiers: string[] = [];
  const conditionId = node.data?.conditionId;
  if (typeof conditionId === 'string' && conditionId !== '') {
    identifiers.push(conditionId);
  }
  const plugin = node.data?.plugin;
  if (typeof plugin === 'string' && plugin !== '') {
    identifiers.push(plugin);
  }
  return identifiers;
}

/**
 * Find the condition NODE that a replay step's `conditionId` refers to.
 *
 * Conditions are first-class nodes now (issue #3589093), so a condition step
 * resolves to a condition node rather than a (nonexistent) condition edge.
 * `step.conditionId` is ECA's condition *config* id (see
 * `Drupal\eca\Entity\Eca::getSuccessors()`), which the modeler backend plugin
 * emits as `edge.conditionId` and which `promoteConditionEdges()` copies onto
 * the synthesized node as `data.conditionId`. That is therefore matched FIRST;
 * `data.plugin` is only a legacy fallback for older replay recordings.
 *
 * Returns `undefined` when the step carries no `conditionId` or nothing matches.
 */
export function findConditionNodeForStep<T extends ConditionNodeLike>(
  nodes: readonly T[],
  step: ReplayStep
): T | undefined {
  const conditionId = step.conditionId;
  if (!conditionId) return undefined;

  // Priority 1: the backend round-tripped ECA condition config id.
  const byConditionId = nodes.find(
    n => isConditionNode(n) && n.data?.conditionId === conditionId
  );
  if (byConditionId) return byConditionId;

  // Priority 2 (legacy): the condition plugin id.
  return nodes.find(n => isConditionNode(n) && n.data?.plugin === conditionId);
}

// ============ Element Matching ============

/**
 * Find the first replay step index that matches a given canvas element.
 * Used for canvas-to-replay synchronization.
 *
 * Pass `nodes` to make the lookup condition-aware (issue #3589108). Condition
 * nodes are never the `step.id` of an execution step — the step id is always
 * the *predecessor* component — so a condition node can only be resolved by
 * matching `step.conditionId` against the node's own condition identifiers
 * (see {@link getConditionNodeIdentifiers}). Without `nodes` the function
 * keeps its previous, condition-blind behavior so existing callers that have
 * no graph at hand are unaffected.
 */
export function findReplayStepForElement(
  replayData: ReplayStep[],
  edges: Edge[],
  elementId: string,
  elementType: 'node' | 'edge' | 'condition',
  nodes: Node[] = []
): number {
  if (!replayData || replayData.length === 0) return -1;

  // Resolve the selected node once; a condition node changes how we match.
  const selectedNode = nodes.find(n => n.id === elementId);
  const selectedIsCondition = isConditionNode(selectedNode);
  const conditionIdentifiers = selectedNode ? getConditionNodeIdentifiers(selectedNode) : [];

  for (let i = 0; i < replayData.length; i++) {
    const step = replayData[i];
    
    if (elementType === 'node') {
      if (selectedIsCondition) {
        // A condition node is covered by the successor step that evaluated it.
        if (step.conditionId && isConditionStep(step) && conditionIdentifiers.includes(step.conditionId)) {
          return i;
        }
      } else if (step.id === elementId && isNodeExecutionStep(step)) {
        // Priority: Find steps where this node is the main actor
        return i;
      }
    } else if (elementType === 'edge') {
      // For edges, check if this edge matches the step's source->target
      const edge = edges.find(e => e.id === elementId);
      if (edge && step.id === edge.source && step.successorId === edge.target) {
        return i;
      }
    } else if (elementType === 'condition') {
      // When `elementId` is a condition NODE id, resolve through the node's own
      // condition identifiers; `step.conditionId` never equals a node id.
      if (selectedIsCondition) {
        if (step.conditionId && conditionIdentifiers.includes(step.conditionId)) {
          return i;
        }
      } else if (step.conditionId === elementId) {
        // Legacy: `elementId` already IS a raw condition identifier.
        return i;
      }
    }
  }
  
  return -1;
}

/**
 * Resolve which replay step's token data a SELECTED node should display.
 *
 * {@link findReplayStepForElement} answers with the FIRST step a node executed
 * at, which is the right answer for a manual canvas click but the wrong one
 * while stepping through a model that LOOPS (issue #3589126): a node inside a
 * loop executes once per iteration, and `selectCanvasFromReplay()` selects that
 * node on every one of those steps. Anchoring on the first occurrence therefore
 * made every iteration display iteration 1's token data.
 *
 * The rule is a narrow preference on top of the existing lookup: when the
 * replay is parked on a step that belongs to the selected node itself, that
 * step wins. Everything else keeps the previous behavior. A manual canvas
 * click still lands on the node's first execution, though by a different
 * route: `selectReplayFromCanvas()` has already synced the current step to
 * that same first occurrence, so the preference returns it unchanged.
 *
 * "Belongs to the selected node" is evaluated with exactly the matching rules
 * {@link findReplayStepForElement} uses for `elementType: 'node'`, so the two
 * stay symmetric:
 * - A regular node matches a node-execution step carrying its id.
 * - A condition node is never a step's `id` (the step id is the predecessor),
 *   so it matches the successor step whose `conditionId` is one of the node's
 *   own condition identifiers (see {@link getConditionNodeIdentifiers}).
 *   Conditions inside a loop are re-evaluated per iteration, so they need the
 *   same preference.
 *
 * @param replayData
 *   The ordered replay steps.
 * @param edges
 *   The canvas edges, forwarded to the fallback lookup.
 * @param nodeId
 *   The id of the currently selected node.
 * @param nodes
 *   The canvas nodes; required for condition-node matching (issue #3589108).
 *   Omitting them keeps the previous condition-blind behavior.
 * @param currentStep
 *   The step the replay is currently parked on, or a negative/out-of-range
 *   value when there is none.
 *
 * @returns The index of the step to display, or -1 when the node is not
 *   covered by the replay at all.
 */
export function resolveStepForSelectedNode(
  replayData: ReplayStep[],
  edges: Edge[],
  nodeId: string,
  nodes: Node[] = [],
  currentStep = -1
): number {
  if (!replayData || replayData.length === 0) return -1;

  if (currentStep >= 0 && currentStep < replayData.length) {
    const step = replayData[currentStep];
    const selectedNode = nodes.find(n => n.id === nodeId);

    if (selectedNode && isConditionNode(selectedNode)) {
      const identifiers = getConditionNodeIdentifiers(selectedNode);
      if (step.conditionId && isConditionStep(step) && identifiers.includes(step.conditionId)) {
        return currentStep;
      }
    } else if (step.id === nodeId && isNodeExecutionStep(step)) {
      return currentStep;
    }
  }

  // No usable current step: fall back to the node's first covering step.
  return findReplayStepForElement(replayData, edges, nodeId, 'node', nodes);
}

/**
 * Find the canvas element (node or edge) that corresponds to a replay step.
 * Used for replay-to-canvas synchronization.
 */
export function findElementForReplayStep(
  replayData: ReplayStep[],
  nodes: Node[],
  edges: Edge[],
  stepIndex: number
): { type: 'node' | 'edge'; id: string } | null {
  if (stepIndex < 0 || stepIndex >= replayData.length) return null;
  
  const step = replayData[stepIndex];

  // Conditions are first-class NODES now (issue #3589093).  A condition step
  // therefore resolves to the condition NODE rather than a (nonexistent)
  // condition edge.  See findConditionNodeForStep() for the matching rules.

  // For successor steps, handle condition node finding.
  if (isSuccessorStep(step)) {
    // Priority 1: If step has conditionId, find the condition node.
    const condNode = findConditionNodeForStep(nodes, step);
    if (condNode) {
      return { type: 'node', id: condNode.id };
    }

    // Priority 2: If step has successorId, find the edge by source/target.
    if (step.successorId) {
      const edge = edges.find(e => e.source === step.id && e.target === step.successorId);
      if (edge) {
        return { type: 'edge', id: edge.id };
      }
    }
  }

  // Priority 3: For other steps with conditionId, find the condition node.
  const otherCondNode = findConditionNodeForStep(nodes, step);
  if (otherCondNode) {
    return { type: 'node', id: otherCondNode.id };
  }

  // Priority 4: Find the node by ID.
  if (step.id) {
    const node = nodes.find(n => n.id === step.id);
    if (node) {
      return { type: 'node', id: step.id };
    }
  }
  
  return null;
}

/**
 * Find the matching replay step for a given node or edge selection.
 * More flexible matcher used by useReplayCoordination.
 */
export function findMatchingReplayStepForSelection(
  replayData: ReplayStep[],
  edges: Edge[],
  node: Node | null,
  edge: Edge | null
): number {
  if (!replayData || replayData.length === 0) return -1;
  
  if (node) {
    // Find steps where this node is the main actor
    return replayData.findIndex(step =>
      step.id === node.id && isNodeExecutionStep(step)
    );
  }
  
  if (edge) {
    // For edges with conditions, find by conditionId first
    if (edge.data?.condition) {
      const stepIndex = replayData.findIndex(step => step.conditionId === edge.data!.condition);
      if (stepIndex !== -1) return stepIndex;
    }
    
    // Fallback to source/target matching
    return replayData.findIndex(step =>
      step.id === edge.source && step.successorId === edge.target
    );
  }
  
  return -1;
}

/**
 * Resolve a canvas node to the icon it renders on the canvas.
 *
 * Condition nodes are checked first because a promoted condition node may be
 * flagged only by `data.__isConditionNode` without a `condition` type
 * (issue #3589093). Otherwise the ReactFlow `type` wins, falling back to the
 * internal `data.nodeType` (which is what carries `gateway` for nodes that
 * ReactFlow types generically).
 */
function resolveNodeIcon(node: Node | undefined): IconType {
  if (!node) return DEFAULT_NODE_ICON;
  if (isConditionNode(node)) return NODE_TYPE_ICONS.condition;
  return getNodeTypeIcon(node.type ?? node.data?.nodeType);
}

/**
 * Get the appropriate icon for a replay step.
 *
 * Icons mirror the CANVAS node icons (shared {@link NODE_TYPE_ICONS} map) so a
 * step row always shows the same glyph as the node it describes. Error states
 * (`access denied`, `exception`) and the unknown-type fallback keep their own
 * icons because they describe a state, not a node type.
 *
 * `nodes` is optional and trailing so existing callers keep compiling; without
 * it the node-derived cases degrade to sensible type-based defaults.
 */
export function getStepIcon(step: ReplayStep, nodes: Node[] = []): React.ReactNode {
  switch (step.type) {
    case 'started': {
      // The process always starts at an event node.
      const StartIcon = NODE_TYPE_ICONS.start;
      return <StartIcon className="step-icon started" />;
    }
    case 'execute': {
      // Whatever component actually executed — action, subprocess, gateway...
      const ExecuteIcon = resolveNodeIcon(nodes.find(n => n.id === step.id));
      return <ExecuteIcon className="step-icon execute" />;
    }
    case 'add successor':
    case 'ignore successor': {
      const isIgnore = step.type === 'ignore successor';
      // A condition-gated step is LABELED with the condition's name, so it must
      // also carry the condition icon. Otherwise fall back to the successor's
      // own icon (which covers gateways via resolveNodeIcon), and finally to
      // the original chevron when nothing resolves.
      const conditionNode = findConditionNodeForStep(nodes, step);
      const successorNode = step.successorId
        ? nodes.find(n => n.id === step.successorId)
        : undefined;

      let SuccessorIcon: IconType;
      if (conditionNode) {
        SuccessorIcon = NODE_TYPE_ICONS.condition;
      } else if (successorNode) {
        SuccessorIcon = resolveNodeIcon(successorNode);
      } else {
        SuccessorIcon = FiChevronRight;
      }

      // Keep the TRUE/FALSE distinction: the modifier class drives a distinct
      // color and the dimming keeps ignored successors visually recessive.
      return (
        <SuccessorIcon
          className={`step-icon ${isIgnore ? 'ignore-successor' : 'add-successor'}`}
          style={isIgnore ? { opacity: 0.5 } : undefined}
        />
      );
    }
    case 'access denied':
      return <FiAlertTriangle className="step-icon access-denied" />;
    case 'exception':
      return <FiXCircle className="step-icon exception" />;
    default:
      return <FiClock className="step-icon default" />;
  }
}

/**
 * Get a human-readable label for a replay step
 */
export function getStepLabel(
  step: ReplayStep,
  index: number,
  nodes: Node[],
  edges: Edge[]
): string {
  // Helper to get node label by ID
  const getNodeLabel = (nodeId: string): string => {
    const node = nodes.find(n => n.id === nodeId);
    return node?.data?.label || nodeId || t('Component');
  };

  switch (step.type) {
    case 'started':
      // For started, show the event node label that triggered the process
      return `${index + 1}: ${getNodeLabel(step.id || '')}`;
    case 'execute':
      return `${index + 1}: ${getNodeLabel(step.id || '')}`;
    case 'add successor':
    case 'ignore successor': {
      // `step.conditionId` is ECA's condition *config* id — never an edge id
      // and never a node id.  Conditions are first-class NODES (issue
      // #3589093), so the step must be labeled with the condition NODE's own
      // label; falling through to the successor's label was issue #3589108.
      if (step.conditionId) {
        const conditionNode = findConditionNodeForStep(nodes, step);
        if (conditionNode?.data?.label) {
          return `${index + 1}: ${conditionNode.data.label}`;
        }

        // Legacy fallback: pre-promotion replay data where the condition still
        // lived on an edge whose id happened to be the conditionId.
        const edge = edges.find(e => e.id === step.conditionId);
        const conditionLabel = edge?.data?.conditionLabel || edge?.data?.condition;
        if (conditionLabel) {
          return `${index + 1}: ${conditionLabel}`;
        }
      }

      // If no conditionId or no label found, try to find edge between the nodes
      const edge = edges.find(e =>
        (e.source === step.id && e.target === step.successorId) ||
        (e.target === step.id && e.source === step.successorId)
      );
      const conditionLabel = edge?.data?.conditionLabel || edge?.data?.condition;
      if (conditionLabel) {
        return `${index + 1}: ${conditionLabel}`;
      }

      // Last fallback: show the successor node label
      const nodeLabel = getNodeLabel(step.successorId || '');
      return `${index + 1}: ${nodeLabel}`;
    }
    case 'access denied':
      return `${index + 1}: ${t('Access Denied')}`;
    case 'exception': {
      const label = getNodeLabel(step.id || '');
      const msg = step.exception && typeof step.exception === 'object' && 'message' in step.exception
        ? `: ${String(step.exception.message)}`
        : '';
      return `${index + 1}: ${label}${msg}`;
    }
    default:
      return `${index + 1}: ${step.type}`;
  }
}
