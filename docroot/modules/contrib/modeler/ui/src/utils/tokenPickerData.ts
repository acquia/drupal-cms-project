/**
 * tokenPickerData - Builds the categorized token tree consumed by the "["
 * TokenPicker popup.
 *
 * Reuses the SAME data shapes and `transformGlobalToken` transformation as the
 * Review-mode token tree (ReplayDataRenderer), so the picker and the tree stay
 * in sync. The normalized node shape is `{ label, token?, value?, data? }`.
 */

import { transformGlobalToken } from '../components/ReplayDataRenderer';
import type { GlobalToken } from '../types/settings';

/** A normalized token-tree node (matches ReplayDataRenderer's expected shape). */
export interface TokenNode {
  label: string;
  /** Raw token string like "[user:name]". Present only on leaf/usable tokens. */
  token?: string;
  /** Optional resolved value (for display). */
  value?: unknown;
  /** Child nodes keyed by an arbitrary id. */
  data?: Record<string, TokenNode>;
  /**
   * Whether this token was PREDICTED from a replay-covered predecessor of the
   * selected node (rather than confirmed by a replay run on the node itself).
   * When `true`, the UI renders a subtle "predicted" badge + tooltip. Confirmed
   * tokens leave this `undefined`/`false` so their rendering is unchanged
   * (issue #3577207).
   */
  predicted?: boolean;
}

/**
 * Safely convert a leaf token's `value` (typed `unknown`, runtime data) into a
 * display string for the picker. Primitives are stringified via `String()`;
 * objects/arrays via `JSON.stringify` guarded in a try/catch (e.g. circular
 * refs) with a `String()` fallback. `null`/`undefined` and anything that
 * stringifies to an empty string yields `''`, which the caller treats as
 * "no value" and renders no subtext line. Returned as plain text (React
 * escapes it) — never used as HTML.
 */
export function formatTokenValue(value: unknown): string {
  if (value === null || value === undefined) return '';
  if (typeof value === 'object') {
    try {
      return JSON.stringify(value);
    } catch {
      try {
        return String(value);
      } catch {
        return '';
      }
    }
  }
  return String(value);
}

/**
 * Approximate height (px) of a single token row (`.token-picker-option`):
 * font-size-md line (~20px) + 6px top/bottom padding ≈ 32px. Used to size the
 * popup so a minimum number of rows is visible before the list scrolls.
 */
export const PICKER_ROW_HEIGHT = 32;

/** Always aim to show this many list rows when a token list is present. */
export const PICKER_MIN_LIST_ROWS = 10;

/**
 * Approximate combined height (px) of the static chrome above the list:
 *  - the modal header (~37px),
 *  - the in-picker SEARCH row (`.token-picker-search`: input ~32px + ~8px
 *    vertical padding ≈ 40px) — always rendered between the header and body,
 *  - one more chrome row — breadcrumb/heading (~33px),
 *  - plus the list's own 4px top/bottom padding.
 * ~120px is a deliberate slight over-estimate so the row target is comfortably
 * met (any extra simply lets the list show a little more; it is still capped to
 * the viewport by computePickerPlacement).
 */
export const PICKER_CHROME_HEIGHT = 120;

/**
 * Generous minimum (px) for the LISTEN/WAITING step view so the dataset selector
 * dropdown (opened ABOVE the spinner) has room INSIDE the popup and is not
 * cramped/clipped. Math: chrome (~120, incl. the search row) + the open dropdown
 * region — the dataset selector toggle (~37) plus the open list with the
 * "Listen…" item and ~4 datasets at ~30px plus the spinner area (~200 total).
 * The popup never overflows: this is a TARGET clamped to the viewport by
 * computePickerPlacement.
 */
export const PICKER_WAITING_MIN = PICKER_CHROME_HEIGHT + 200;

/** The view kinds that drive the popup's minimum target height. */
export interface PickerMinHeightOpts {
  /** A `.token-picker-list` of tokens is rendered (5-row floor). */
  showingTokenList: boolean;
  /** The listen/waiting step view is rendered (generous floor for the selector). */
  showingWaiting: boolean;
}

/**
 * The popup's minimum target height for the current view:
 *  - a token LIST → reserve chrome + ~5 rows so several rows are visible;
 *  - the LISTEN/WAITING view → a generous floor so the open dataset selector
 *    dropdown fits inside the popup;
 *  - otherwise (empty / hint-only) → the smaller compact floor.
 * If both list and waiting somehow apply, take the LARGER (Math.max) to be safe.
 * This is a TARGET fed to `computePickerPlacement` as its `min`; the placement
 * logic still clamps to the available viewport space (never overflows).
 *
 * Pure (no DOM) for unit-testing.
 */
export function pickerMinHeight(opts: PickerMinHeightOpts, compactMin: number): number {
  const { showingTokenList, showingWaiting } = opts;
  const listFloor = showingTokenList ? PICKER_MIN_LIST_ROWS * PICKER_ROW_HEIGHT + PICKER_CHROME_HEIGHT : 0;
  const waitingFloor = showingWaiting ? PICKER_WAITING_MIN : 0;
  const target = Math.max(listFloor, waitingFloor);
  return target > 0 ? target : compactMin;
}

/** Parts for rendering the picker breadcrumb with middle/left truncation. */
export interface Breadcrumb {
  /** Leading region: category + any middle crumbs, joined with ' / ' (ellipsized). */
  lead: string;
  /** Trailing region: the last (rightmost) crumb — kept fully visible. */
  tail: string;
  /** The full ' / '-joined path for the hover tooltip. */
  title: string;
  /** Whether a trailing crumb exists (i.e. there is ≥1 path segment). */
  hasTrailing: boolean;
}

/**
 * Build the breadcrumb regions so the RIGHTMOST crumb stays fully visible while
 * the category + middle crumbs shrink/ellipsize when cramped (middle/left
 * truncation, which CSS `text-overflow` cannot do alone). `tail` is the last
 * path label; `lead` is the category plus every crumb before the last; `title`
 * is the full path for the hover tooltip.
 *
 * Pure (no DOM) for unit-testing.
 */
export function buildBreadcrumb(categoryLabel: string, pathLabels: string[]): Breadcrumb {
  const all = [categoryLabel, ...pathLabels];
  const title = all.join(' / ');
  if (pathLabels.length === 0) {
    return { lead: categoryLabel, tail: '', title, hasTrailing: false };
  }
  const tail = pathLabels[pathLabels.length - 1];
  const lead = [categoryLabel, ...pathLabels.slice(0, -1)].join(' / ');
  return { lead, tail, title, hasTrailing: true };
}

/** Identifier for the top-level token categories. */
export type TokenCategoryId = 'step' | 'global' | 'template';

export interface TokenCategory {
  id: TokenCategoryId;
  label: string;
  /** Top-level child nodes for this category. */
  nodes: TokenNode[];
  /** Number of immediate child nodes (shown as a count badge). */
  count: number;
}

/**
 * Normalize a single step-data entry into a TokenNode. Step data entries may
 * already be in `{label,...}` shape, or be a bare `{key: value}` — mirror the
 * coercion StepDataContainer performs.
 */
function normalizeStepEntry(key: string, value: unknown): TokenNode {
  if (value && typeof value === 'object' && 'label' in (value as Record<string, unknown>)) {
    return value as TokenNode;
  }
  if (value && typeof value === 'object') {
    return { label: key, ...(value as Record<string, unknown>) } as TokenNode;
  }
  return { label: key, value };
}

/**
 * Return a NON-mutating deep copy of a token node with `predicted: true`
 * stamped on the node AND, recursively, on every descendant inside its nested
 * `data` objects.
 *
 * The picker drills into structured step data via `node.data`, so the visible
 * leaf rows after drilling are the CHILD nodes — stamping only the top level
 * (the old behavior) left those children unflagged and the badge never showed.
 * Deep-stamping guarantees the predicted indicator is available at every level
 * the picker can render (issue #3577207).
 *
 * Pure: the input node is never mutated; a fresh node (and fresh `data` map) is
 * returned.
 *
 * @param node
 *   The token node to stamp (read-only).
 *
 * @returns A deep-cloned node with `predicted: true` on it and all descendants.
 */
export function markPredictedDeep(node: TokenNode): TokenNode {
  const stamped: TokenNode = { ...node, predicted: true };
  if (node.data) {
    const children: Record<string, TokenNode> = {};
    for (const [childKey, child] of Object.entries(node.data)) {
      children[childKey] = markPredictedDeep(child);
    }
    stamped.data = children;
  }
  return stamped;
}

/**
 * Build the step-data category nodes from the raw expanded step data.
 *
 * @param stepData
 *   The expanded step-data object (key -> entry), or null/undefined.
 * @param predicted
 *   When `true`, every produced {@link TokenNode} — and recursively every
 *   nested descendant — is stamped `predicted: true` so the UI can render the
 *   "predicted from a predecessor" badge + tooltip at any drill level (issue
 *   #3577207). Defaults to `false`, leaving confirmed tokens unchanged.
 *
 * @returns The normalized top-level token nodes for the step-data category.
 */
export function buildStepNodes(
  stepData?: Record<string, unknown> | null,
  predicted = false,
): TokenNode[] {
  if (!stepData) return [];
  return Object.entries(stepData).map(([key, value]) => {
    const node = normalizeStepEntry(key, value);
    // Only stamp the flag for predicted data; confirmed nodes stay byte-for-byte
    // identical (no `predicted` key added anywhere) so existing rendering is
    // unchanged. Predicted data is deep-stamped so nested leaves carry it too.
    return predicted ? markPredictedDeep(node) : node;
  });
}

/**
 * Build the global/template category nodes from Drupal token entries.
 */
export function buildGlobalNodes(tokens?: Record<string, GlobalToken>): TokenNode[] {
  if (!tokens) return [];
  return Object.values(tokens).map((entry) => transformGlobalToken(entry as Record<string, any>) as TokenNode);
}

interface BuildCategoriesArgs {
  stepData?: Record<string, unknown> | null;
  globalTokens?: Record<string, GlobalToken>;
  templateTokens?: Record<string, GlobalToken>;
  isTemplate?: boolean;
  /**
   * Feature J: whether a Step-data category should be offered at all. True for
   * non-template models whose selected node has a resolvable OWNING event — the
   * category then shows even with ZERO cached step nodes (the picker loads data
   * on demand). False/omitted preserves the legacy behavior (step shown only
   * when cached step data exists). Template models never get a step category.
   */
  canResolveStepData?: boolean;
  /**
   * Whether the step data was PREDICTED from a replay-covered predecessor of the
   * selected node (issue #3577207). When `true`, every step-data {@link TokenNode}
   * is stamped `predicted: true` so the picker renders the predicted badge.
   * Defaults to `false` (confirmed step data, unchanged rendering).
   */
  stepPredicted?: boolean;
  /** Translation function (injected to avoid importing t() into a pure util test). */
  labels: { step: string; global: string; template: string };
}

/**
 * Build the ordered list of top-level token categories for the picker.
 * Categories with zero nodes are still returned for global/template so the
 * picker can always offer them.
 *
 * The step category is included when EITHER there is cached step data OR
 * (Feature J) the selected node has a resolvable owning event AND the model is
 * NOT a template (`canResolveStepData`) — in the latter case it may have zero
 * nodes and the picker loads data on demand. Template models never get a step
 * category.
 */
export function buildTokenCategories({
  stepData,
  globalTokens,
  templateTokens,
  isTemplate,
  canResolveStepData = false,
  stepPredicted = false,
  labels,
}: BuildCategoriesArgs): TokenCategory[] {
  const categories: TokenCategory[] = [];

  const stepNodes = buildStepNodes(stepData, stepPredicted);
  // Feature J: offer the step category for non-template models with a resolvable
  // owning event even when there are no cached nodes yet (load-on-demand). Never
  // for templates. Otherwise fall back to the legacy "only when data" rule.
  const includeStep = !isTemplate && (canResolveStepData || stepNodes.length > 0);
  if (includeStep) {
    categories.push({ id: 'step', label: labels.step, nodes: stepNodes, count: stepNodes.length });
  }

  const globalNodes = buildGlobalNodes(globalTokens);
  if (globalNodes.length > 0) {
    categories.push({ id: 'global', label: labels.global, nodes: globalNodes, count: globalNodes.length });
  }

  if (isTemplate) {
    const templateNodes = buildGlobalNodes(templateTokens);
    if (templateNodes.length > 0) {
      categories.push({ id: 'template', label: labels.template, nodes: templateNodes, count: templateNodes.length });
    }
  }

  return categories;
}

/**
 * Recursively flatten a list of token nodes to only the usable (leaf) tokens —
 * i.e. nodes that carry a `token` string. Used when filtering by query so the
 * user can match deeply-nested tokens without drilling.
 */
export function flattenUsableTokens(nodes: TokenNode[]): TokenNode[] {
  const out: TokenNode[] = [];
  const walk = (list: TokenNode[]): void => {
    for (const node of list) {
      if (node.token) {
        out.push(node);
      }
      if (node.data) {
        walk(Object.values(node.data));
      }
    }
  };
  walk(nodes);
  return out;
}

/**
 * Case-insensitive match of a token node against a query string. Matches on
 * the display label OR the raw token string.
 */
export function tokenMatchesQuery(node: TokenNode, query: string): boolean {
  if (!query) return true;
  const q = query.toLowerCase();
  return (
    (node.label || '').toLowerCase().includes(q) ||
    (node.token || '').toLowerCase().includes(q)
  );
}

/** Inputs for {@link computePickerPlacement} (all viewport-relative px). */
export interface PickerPlacementInput {
  /** Top edge of the caret/anchor (viewport y). */
  anchorTop: number;
  /** Bottom edge of the caret/anchor (viewport y) — the default top when below. */
  anchorBottom: number;
  /** Current viewport height (window.innerHeight). */
  viewportHeight: number;
  /** Edge margin to keep between the popup and the viewport edges. */
  margin: number;
  /** Preferred (natural) popup height, e.g. its measured content height. */
  desired: number;
  /** Minimum usable popup height before relying purely on internal scroll. */
  min: number;
}

/** Result of {@link computePickerPlacement}. */
export interface PickerPlacement {
  /** Whether the popup sits below the caret (default) or flips above it. */
  placement: 'below' | 'above';
  /** Viewport `top` (px) at which to render the popup. */
  top: number;
  /** Capped popup height (px): fits the chosen side; internal scroll handles overflow. */
  maxHeight: number;
}

/**
 * Decide whether the "[" token picker should render BELOW (default) or flip
 * ABOVE the caret, and cap its height to the available space so it never
 * overflows the viewport (the popup's body scrolls internally for the rest).
 *
 * Pure (no DOM) so it can be unit-tested. The picker is `position: fixed`, so
 * inputs and the returned `top` are all viewport-relative.
 *
 * Rule: anchor BELOW when there is enough room below for the desired height, or
 * when below has at least as much room as above; otherwise flip ABOVE. The
 * chosen side's space is the HARD ceiling on `maxHeight` — the popup never
 * overflows the viewport (CLAMP). Within that ceiling we prefer `min` (a target
 * floor, e.g. enough room for ~5 list rows) and never exceed `desired`. When the
 * available space is smaller than `min`, the popup shrinks to the available
 * space and the body scrolls internally — `min` never causes overflow.
 */
export function computePickerPlacement(input: PickerPlacementInput): PickerPlacement {
  const { anchorTop, anchorBottom, viewportHeight, margin, desired, min } = input;
  const spaceBelow = Math.max(0, viewportHeight - anchorBottom - margin);
  const spaceAbove = Math.max(0, anchorTop - margin);

  const placeBelow = spaceBelow >= desired || spaceBelow >= spaceAbove;
  const chosenSpace = placeBelow ? spaceBelow : spaceAbove;

  // Prefer `min` as a floor and cap to `desired`, but `chosenSpace` is the hard
  // ceiling so the popup never overflows the viewport: when space < min, use the
  // available space (the list scrolls). When space >= min, raise to at least
  // `min` (so e.g. ~5 rows show) up to `desired`.
  const maxHeight = Math.min(chosenSpace, Math.max(min, Math.min(desired, chosenSpace)));

  if (placeBelow) {
    return { placement: 'below', top: anchorBottom, maxHeight };
  }
  // Flip above: bottom sits just above the caret top; clamp top to the margin.
  const top = Math.max(margin, anchorTop - maxHeight);
  return { placement: 'above', top, maxHeight };
}
