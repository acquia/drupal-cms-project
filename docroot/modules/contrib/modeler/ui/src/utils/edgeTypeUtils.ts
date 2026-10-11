/**
 * edgeTypeUtils - Shared edge type determination logic
 *
 * Provides a single source of truth for computing edge types based on
 * edge data (condition). Used by modelUtils promote detection and the
 * useConfiguration annotation path.
 *
 * There are only two edge types:
 * - 'condition': edge has a condition attached
 * - 'default': edge has no condition
 *
 * Annotations belong to conditions, not edges. An edge without a condition
 * cannot have an annotation.
 */

import type { EdgeData } from '../types/settings';

/**
 * Check whether a conditionConfiguration value contains actual data.
 * An empty object `{}` or null/undefined means "no configuration".
 */
function hasConditionConfig(config: Record<string, unknown> | null | undefined): boolean {
  return !!config && Object.keys(config).length > 0;
}

/**
 * Determine the edge type based on its data.
 * 
 * - If edge has a condition (any condition-related field), returns 'condition'
 * - Otherwise returns 'default'
 */
export function getEdgeType(data: EdgeData | undefined | null): string {
  if (!data) return 'default';

  const hasCondition = data.condition || data.conditionLabel || hasConditionConfig(data.conditionConfiguration);

  if (hasCondition) {
    return 'condition';
  }
  return 'default';
}
