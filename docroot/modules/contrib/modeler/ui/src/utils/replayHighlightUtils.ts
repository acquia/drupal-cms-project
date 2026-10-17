/**
 * replayHighlightUtils - Pure functions for replay step highlighting
 * 
 * Provides node/edge state transformations for applying and clearing
 * replay visual highlights. These are pure functions (no React hooks)
 * that transform node/edge arrays, shared by useSimpleReplaySync
 * and useReplayController.
 */

import { Node, Edge } from 'reactflow';
import { ReplayStep, isNodeExecutionStep, isSuccessorStep, isAccessDeniedStep, isExceptionStep, isConditionStep, findConditionNodeForStep } from './replayStepUtils';

// ============ Clear Highlights ============

/** Remove all replay-related highlighting from nodes */
export function clearNodeHighlights(nodes: Node[]): Node[] {
  return nodes.map(n => ({
    ...n,
    selected: false,
    data: { ...n.data, highlighted: false },
  }));
}

/** Remove all replay-related highlighting from edges */
export function clearEdgeHighlights(edges: Edge[]): Edge[] {
  return edges.map(e => ({
    ...e,
    selected: false,
    data: {
      ...e.data,
      highlighted: false,
      replayHighlight: undefined,
      fromReplay: false,
    },
    style: e.style ? { ...e.style, stroke: undefined, strokeWidth: undefined } : undefined,
    animated: false,
    className: e.className?.replace(/replay-highlighted|replay-add|replay-ignore/g, '').trim() || undefined,
  }));
}

// ============ Apply Highlights ============

/** Apply replay highlighting to a specific node */
export function applyNodeHighlight(
  nodes: Node[],
  nodeId: string,
  isAccessDenied: boolean
): Node[] {
  return nodes.map(n =>
    n.id === nodeId
      ? {
          ...n,
          selected: true,
          data: {
            ...n.data,
            highlighted: true,
            fromReplay: true,
          },
          style: isAccessDenied
            ? {
                ...n.style,
                border: '2px solid var(--modeler-color-danger-soft)',
                boxShadow: 'var(--modeler-shadow-danger-glow)',
              }
            : n.style,
        }
      : n
  );
}

/** Apply replay highlighting to a specific edge */
export function applyEdgeHighlight(
  edges: Edge[],
  edgeId: string,
  isAdd: boolean
): Edge[] {
  const highlightColor = isAdd
    ? 'var(--modeler-color-success)'
    : 'var(--modeler-color-danger-soft)';

  return edges.map(e =>
    e.id === edgeId
      ? {
          ...e,
          // Keep the incoming selected state (set by the caller) so that
          // ReactFlow fires onSelectionChange with this edge, which in turn
          // populates the selection store and the property panel.
          data: {
            ...e.data,
            highlighted: true,
            replayHighlight: highlightColor,
            fromReplay: true,
            replayType: isAdd ? 'add' : 'ignore',
          },
          style: {
            ...e.style,
            stroke: highlightColor,
            strokeWidth: 3,
            opacity: 1,
          },
          animated: true,
          className: `replay-highlighted replay-${isAdd ? 'add' : 'ignore'}`,
        }
      : e
  );
}

// ============ Composite Operations ============

/**
 * Apply full replay step highlighting to nodes.
 * Clears existing highlights and applies new ones based on the step.
 */
export function highlightNodesForStep(
  nodes: Node[],
  step: ReplayStep
): Node[] {
  const cleared = clearNodeHighlights(nodes);

  // For condition steps, highlight the condition NODE (issue #3589093).
  // Conditions used to be edges; they are nodes now, so this is where the
  // condition highlight lives.  The shared findConditionNodeForStep() helper
  // resolves via the ECA condition config id, then the legacy plugin id.
  if (isConditionStep(step)) {
    const conditionNode = findConditionNodeForStep(cleared, step);
    if (!conditionNode) return cleared;
    return applyNodeHighlight(cleared, conditionNode.id, false);
  }

  if (!step.id) return cleared;

  // For node execution steps (started, execute, access denied, exception)
  if (isNodeExecutionStep(step)) {
    return applyNodeHighlight(cleared, step.id, isAccessDeniedStep(step) || isExceptionStep(step));
  }

  // For successor steps without conditionId (treated as node steps)
  if (isSuccessorStep(step) && !step.conditionId) {
    return applyNodeHighlight(cleared, step.id, false);
  }

  return cleared;
}

/**
 * Apply full replay step highlighting to edges.
 *
 * Conditions are nodes now (issue #3589093), so condition steps no longer
 * highlight an edge — that work has moved to {@link highlightNodesForStep}.
 * This function is retained because non-condition steps may still rely on
 * edge clearing, and to keep a stable API for callers; for condition steps
 * it simply clears edge highlights (a no-op beyond clearing).
 */
export function highlightEdgesForStep(
  edges: Edge[],
  _step: ReplayStep
): Edge[] {
  return clearEdgeHighlights(edges);
}
