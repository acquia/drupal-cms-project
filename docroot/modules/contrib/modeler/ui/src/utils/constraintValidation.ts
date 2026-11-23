/**
 * Pure constraint validation logic extracted from Flow.tsx.
 *
 * These functions validate model-level cardinality constraints
 * (including the parallel-successor condition rule) and return
 * human-readable error strings.  They are intentionally side-effect
 * free so they can be unit-tested without mounting React components.
 */

import type { StoreNode, StoreEdge, ModelConstraints, ComponentLabels } from '../types/settings';
import { t } from './translation';
import { getComponentLabel, getComponentLabelPlural } from './componentUtils';

/**
 * A node is a condition node (issue #3589093) when its type is `condition`
 * or its data carries the `__isConditionNode` flag.
 */
function isConditionNode(node: StoreNode | undefined): boolean {
  if (!node) return false;
  return node.type === 'condition' || node.data?.__isConditionNode === true;
}

/**
 * Structural-invariant check: two condition nodes may never be directly
 * connected (issue #3589093).  Scans all edges and, for any edge whose
 * source AND target both resolve to condition nodes, emits one translated
 * error.  Offending pairs are deduped (by source/target node id) so a
 * duplicated edge between the same two conditions reports a single error.
 *
 * Pure and side-effect free.
 */
export function validateNoAdjacentConditions(
  nodes: StoreNode[],
  edges: StoreEdge[],
): string[] {
  const nodeById = new Map<string, StoreNode>();
  for (const node of nodes) nodeById.set(node.id, node);

  const errors: string[] = [];
  const seenPairs = new Set<string>();

  for (const edge of edges) {
    const source = nodeById.get(edge.source);
    const target = nodeById.get(edge.target);
    if (!isConditionNode(source) || !isConditionNode(target)) continue;

    const pairKey = `${edge.source}\u0000${edge.target}`;
    if (seenPairs.has(pairKey)) continue;
    seenPairs.add(pairKey);

    const firstName = source?.data?.label ?? edge.source;
    const secondName = target?.data?.label ?? edge.target;
    errors.push(t(
      'Two conditions cannot be directly connected. Insert a gateway between "@first" and "@second".',
      { '@first': firstName, '@second': secondName },
    ));
  }

  return errors;
}

/**
 * Structural-invariant check: a condition node may have multiple inbound edges
 * (fan-in for a reused condition) but AT MOST ONE outbound edge (issue
 * #3589093).  Scans all condition nodes and emits one translated error per
 * condition node that has more than one outgoing edge.
 *
 * This is a HARD invariant, NOT an owner-configurable constraint, so
 * validateBeforeSave (Flow.tsx) calls it UNCONDITIONALLY — like
 * {@link validateNoAdjacentConditions}.
 *
 * Pure and side-effect free.
 */
export function validateConditionOutdegree(
  nodes: StoreNode[],
  edges: StoreEdge[],
): string[] {
  // Count outgoing edges per condition node.
  const conditionNodeById = new Map<string, StoreNode>();
  for (const node of nodes) {
    if (isConditionNode(node)) conditionNodeById.set(node.id, node);
  }
  if (conditionNodeById.size === 0) return [];

  const outdegree = new Map<string, number>();
  for (const edge of edges) {
    if (conditionNodeById.has(edge.source)) {
      outdegree.set(edge.source, (outdegree.get(edge.source) ?? 0) + 1);
    }
  }

  const errors: string[] = [];
  for (const [nodeId, count] of outdegree) {
    if (count > 1) {
      const node = conditionNodeById.get(nodeId);
      const label = node?.data?.label ?? nodeId;
      errors.push(t(
        'A condition can have only one outgoing connection. "@label" has @count.',
        { '@label': label, '@count': String(count) },
      ));
    }
  }

  return errors;
}

/**
 * Validate model-level cardinality constraints against the current graph.
 *
 * Returns an array of translated error strings.  An empty array means
 * the model satisfies all constraints.
 *
 * @param nodes   - Current workflow nodes.
 * @param edges   - Current workflow edges.
 * @param constraints - Model-owner-provided cardinality constraints.
 */
export function validateModelConstraints(
  nodes: StoreNode[],
  edges: StoreEdge[],
  constraints: ModelConstraints,
): string[] {
  const typeCounts: Record<string, number> = {};
  for (const node of nodes) {
    if (node.type && node.type !== 'placeholder') {
      typeCounts[node.type] = (typeCounts[node.type] ?? 0) + 1;
    }
  }
  const errors: string[] = [];

  // NOTE: the "no two adjacent conditions" structural invariant (issue
  // #3589093) is intentionally NOT validated here.  It is a hard invariant,
  // NOT an owner-configurable constraint, so it must run regardless of
  // whether `constraints` are supplied.  validateBeforeSave (Flow.tsx) calls
  // validateNoAdjacentConditions unconditionally as the single source of that
  // check — keeping it out of here avoids duplicate error reporting.

  for (const [typeName, constraint] of Object.entries(constraints)) {
    const count = typeCounts[typeName] ?? 0;
    const label = getComponentLabel(typeName as keyof ComponentLabels);
    const labelPlural = getComponentLabelPlural(typeName as keyof ComponentLabels);

    if (constraint.min !== undefined && count < constraint.min) {
      errors.push(constraint.min === 1
        ? t('A model requires at least one @label.', { '@label': label })
        : t('A model requires at least @min @label_plural.', { '@min': String(constraint.min), '@label_plural': labelPlural }),
      );
    }
    if (constraint.max !== undefined && count > constraint.max) {
      errors.push(constraint.max === 1
        ? t('A model allows at most one @label.', { '@label': label })
        : t('A model allows at most @max @label_plural.', { '@max': String(constraint.max), '@label_plural': labelPlural }),
      );
    }

    // Validate successor cardinality per node.
    if (constraint.successors) {
      const sConstraint = constraint.successors;
      for (const node of nodes) {
        if (node.type !== typeName) continue;
        const outgoing = edges.filter(e => e.source === node.id).length;

        if (sConstraint.min !== undefined && outgoing < sConstraint.min) {
          errors.push(t('@label "@name" requires at least @min successor(s).', {
            '@label': label,
            '@name': node.data?.label ?? node.id,
            '@min': String(sConstraint.min),
          }));
        }
        if (sConstraint.max !== undefined && outgoing > sConstraint.max) {
          errors.push(sConstraint.max === 0
            ? t('@label "@name" must not have any successors.', {
              '@label': label,
              '@name': node.data?.label ?? node.id,
            })
            : t('@label "@name" allows at most @max successor(s).', {
              '@label': label,
              '@name': node.data?.label ?? node.id,
              '@max': String(sConstraint.max),
            }),
          );
        }

        // Validate parallel-successor condition constraint: when the
        // flag is set, every parallel path from the source to the same
        // resolved target must route through a condition.
        //
        // Conditions are first-class NODES now (issue #3589093), so a
        // "conditional path" is the chain source -> conditionNode -> target.
        // For each outgoing edge source -> X:
        //   * If X is a condition node, the path is CONDITIONAL and its
        //     resolved target is the target of the condition node's single
        //     outgoing edge (condNode -> T).
        //   * Otherwise X is an ordinary node, the path is UNCONDITIONAL and
        //     its resolved target is X itself.
        // Paths are grouped by resolved target; any group with >= 2 paths
        // where at least one path is unconditional is a violation.  This
        // mirrors the rule the PHP backend (modeler_api Api.php) still
        // enforces on the demoted edge data, so the UI catches it pre-save.
        if (sConstraint.requireConditionWhenParallel) {
          const outgoingEdges = edges.filter(e => e.source === node.id);

          interface SuccessorPath {
            conditional: boolean;
            resolvedTargetId: string;
          }
          const paths: SuccessorPath[] = [];

          for (const edge of outgoingEdges) {
            const direct = nodes.find(n => n.id === edge.target);
            const isConditionNode =
              direct?.type === 'condition' || direct?.data?.__isConditionNode === true;
            if (isConditionNode && direct) {
              // Resolve through the condition node's single outgoing edge.
              const condOut = edges.find(e => e.source === direct.id);
              if (!condOut) {
                // Dangling condition node (no outgoing edge): treat its own id
                // as the resolved target so it still groups deterministically.
                paths.push({ conditional: true, resolvedTargetId: direct.id });
                continue;
              }
              paths.push({ conditional: true, resolvedTargetId: condOut.target });
            } else {
              paths.push({ conditional: false, resolvedTargetId: edge.target });
            }
          }

          // Group paths by resolved target.
          const byResolvedTarget: Record<string, SuccessorPath[]> = {};
          for (const p of paths) {
            (byResolvedTarget[p.resolvedTargetId] ??= []).push(p);
          }

          for (const [targetId, group] of Object.entries(byResolvedTarget)) {
            if (group.length < 2) continue;
            const hasUnconditional = group.some(p => !p.conditional);
            if (hasUnconditional) {
              const targetNode = nodes.find(n => n.id === targetId);
              const targetName = targetNode?.data?.label ?? targetId;
              errors.push(t(
                '@label "@source" has parallel successors to "@target" without a condition on every edge.',
                {
                  '@label': label,
                  '@source': node.data?.label ?? node.id,
                  '@target': targetName,
                },
              ));
            }
          }
        }
      }
    }
  }

  return errors;
}
