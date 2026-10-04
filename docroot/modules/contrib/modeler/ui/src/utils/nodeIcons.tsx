/**
 * nodeIcons - The single source of truth for node iconography.
 *
 * Both the canvas node components (`components/nodes/*.tsx`) and the replay
 * step list (`getStepIcon` in `replayStepUtils.tsx`) resolve their icons
 * through this map, so a step row can never show a different glyph than the
 * node it describes.
 *
 * When adding a new node type, add it here FIRST and then consume the map —
 * never import an icon directly into a node component.
 */

import { FiZap, FiActivity, FiGitBranch, FiFilter, FiLayers } from 'react-icons/fi';
import type { IconType } from 'react-icons';

/** The ReactFlow node types that render on the canvas. */
export type CanvasNodeType = 'start' | 'element' | 'gateway' | 'condition' | 'subprocess';

/**
 * Canvas icon per node type.
 *
 * - `start`      — event / trigger node
 * - `element`    — action node
 * - `gateway`    — branching gateway
 * - `condition`  — promoted condition node (issue #3589093)
 * - `subprocess` — nested sub-flow
 */
export const NODE_TYPE_ICONS: Record<CanvasNodeType, IconType> = {
  start: FiZap,
  element: FiActivity,
  gateway: FiGitBranch,
  condition: FiFilter,
  subprocess: FiLayers,
};

/**
 * Icon used when a node's type cannot be resolved. Actions are by far the most
 * common node type, so their icon is the least surprising fallback.
 */
export const DEFAULT_NODE_ICON: IconType = NODE_TYPE_ICONS.element;

/**
 * Alternative spellings accepted for a node type. ReactFlow's `node.type` and
 * the internal `node.data.nodeType` do not always agree (e.g. an event node may
 * be typed `start` by ReactFlow but `event` in the model data).
 */
const NODE_TYPE_ALIASES: Record<string, CanvasNodeType> = {
  start: 'start',
  event: 'start',
  element: 'element',
  action: 'element',
  gateway: 'gateway',
  condition: 'condition',
  link: 'condition',
  subprocess: 'subprocess',
};

/**
 * Resolve a node type string to its canvas icon.
 *
 * Returns {@link DEFAULT_NODE_ICON} for unknown, empty, or missing types so
 * callers never have to null-check the result.
 */
export function getNodeTypeIcon(nodeType?: string | null): IconType {
  if (!nodeType) return DEFAULT_NODE_ICON;
  const canonical = NODE_TYPE_ALIASES[nodeType];
  return canonical ? NODE_TYPE_ICONS[canonical] : DEFAULT_NODE_ICON;
}
