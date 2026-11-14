/**
 * replayExpansion - Lazy, non-mutating expansion of compact replay markers.
 *
 * ECA normalizes replay/debug token data with three dedup markers to keep the
 * payload compact (see Drupal\eca\ProcessDebugger):
 *
 *   - `@prev`  A step whose token data is identical to the previous step
 *              stores the string marker `@prev` in place of the full payload.
 *   - `@ref`   A token entry whose content entity already appeared under
 *              another token key stores `{ label, token, '@ref': <key> }`
 *              instead of repeating the full data tree.  `<key>` is the name
 *              of a sibling token entry within the same step's data.
 *   - `@same`  A token entry whose fully-normalized sub-tree is CONTENT-
 *              identical to a sub-tree already emitted earlier in the history —
 *              either earlier in the same step or in an earlier step — stores
 *              `{ label, token, '@same': { step, path } }` in place of the
 *              `data` (or `value`) it would otherwise carry.  Unlike `@ref`
 *              (intra-step entity identity) and `@prev` (whole-step equality),
 *              `@same` collapses content-identical sub-trees across steps and
 *              at nested levels.
 *
 *              `step` is the ZERO-BASED index of the first occurrence's step.
 *              `path` is a slash-separated locator of the referenced sub-tree
 *              within that step's token-data root, interleaving the literal
 *              `data` segment for every level of descent so it mirrors the
 *              normalized array structure:
 *
 *                - Top-level: `{ step: 0, path: 'node' }` resolves to
 *                  `steps[0].data['node']`.
 *                - Nested:    `{ step: 0, path: 'entity/data/user_picture/data/entity' }`
 *                  resolves to
 *                  `steps[0].data['entity']['data']['user_picture']['data']['entity']`.
 *
 *              The resolved NODE's own `data` is spliced onto the `@same`
 *              entry (`entry.data = resolved.data`), and the marker is removed.
 *
 * Historically ECA expanded these markers server-side before handing the data
 * to the modeler.  ECA now returns the COMPACT marker-bearing form, so the
 * modeler must expand them in the frontend — but ONLY at display time, and
 * NEVER in place: the in-memory `replayData` is serialized verbatim by the
 * JSON export, which must keep the compact markers.  Every function here
 * therefore operates on deep copies and never mutates its inputs.
 *
 * The expansion semantics mirror ProcessDebugger::expandHistory(),
 * ProcessDebugger::expandRefs(), and ProcessDebugger::expandSame() exactly,
 * including the phase ORDER: `@prev` then `@ref` then `@same` LAST.  `@same`
 * runs last and reads from the already-`@prev`/`@ref`-expanded data of the
 * referenced step, because a `@same` marker may point at any earlier step
 * whose real `data` only exists after its own `@prev`/`@ref` expansion.
 */

import type { ReplayDataEntry } from '../types/settings';

/** Marker stored in place of a step's token data when it equals the previous step. */
export const TOKEN_DATA_PREV = '@prev';

/** Key holding a reference to a sibling token entry whose data should be reused. */
export const TOKEN_DATA_REF = '@ref';

/**
 * Key holding a cross-step content-identity reference.
 *
 * The marker value is a `{ step, path }` locator of the first occurrence whose
 * `data` sub-tree this entry reuses (see the module docblock for the path
 * scheme).  Mirrors ProcessDebugger::TOKEN_DATA_SAME.
 */
export const TOKEN_DATA_SAME = '@same';

/**
 * A cross-step content-identity reference (`@same` marker payload).
 *
 * Locates the first occurrence whose `data` sub-tree this entry reuses.
 * Mirrors the `['step' => int, 'path' => string]` shape ECA emits.
 */
export interface ReplaySameReference {
  /** Zero-based index of the first occurrence's step. */
  step: number;
  /** Slash-separated locator within that step's token-data root. */
  path: string;
}

/**
 * A single token entry within a step's `data` object.
 *
 * Non-reference entries carry the full normalized token tree under `data`.
 * Reference entries carry a `@ref` marker pointing at a sibling token key
 * whose `data` should be reused instead of repeating the tree.  Cross-step
 * entries carry a `@same` marker pointing at a `(step, path)` locator whose
 * resolved `data` should be reused instead.
 */
export interface ReplayTokenEntry {
  /** Human-readable token label. */
  label?: string;
  /** Raw token string (e.g. `[node:title]`), used for drag-and-drop. */
  token?: string;
  /** Scalar token value, when present. */
  value?: unknown;
  /** Full normalized token data tree, when present. */
  data?: unknown;
  /** Reference marker pointing at a sibling token key in the same step. */
  [TOKEN_DATA_REF]?: string;
  /** Cross-step content-identity marker pointing at an earlier `(step, path)`. */
  [TOKEN_DATA_SAME]?: ReplaySameReference;
  /** Allow additional backend-provided properties. */
  [key: string]: unknown;
}

/**
 * A step's token data, keyed by token name.
 *
 * In the compact form a step's `data` field is either this object or the
 * `@prev` marker string.  After expansion it is always this object.
 */
export type ReplayStepData = Record<string, ReplayTokenEntry>;

/**
 * Deep-clones a JSON-serializable value.
 *
 * Replay data always originates from JSON (either `drupalSettings` or an XHR
 * response), so a structured JSON round-trip is both correct and free of any
 * dependency on `structuredClone`, which is not part of the project's ES2020
 * lib target.  Returning a fresh copy guarantees the caller can mutate the
 * result without ever touching the original input.
 *
 * @param value
 *   The value to clone.
 *
 * @returns A deep copy of the value.
 */
function deepClone<T>(value: T): T {
  return JSON.parse(JSON.stringify(value)) as T;
}

/**
 * Type guard: whether a step's `data` field is the `@prev` marker.
 *
 * @param data
 *   The raw `data` field of a replay step.
 *
 * @returns TRUE when the field is the `@prev` marker string.
 */
function isPrevMarker(data: unknown): data is typeof TOKEN_DATA_PREV {
  return data === TOKEN_DATA_PREV;
}

/**
 * Type guard: whether a value is a token-data object (keyed token entries).
 *
 * @param data
 *   The candidate value.
 *
 * @returns TRUE when the value is a non-null, non-array object.
 */
function isStepDataObject(data: unknown): data is ReplayStepData {
  return typeof data === 'object' && data !== null && !Array.isArray(data);
}

/**
 * Expands `@ref` markers within a single step's token data, in place.
 *
 * Mirrors ProcessDebugger::expandRefs(): for every token entry that carries a
 * `@ref` marker, the referenced sibling key's `data` is copied onto the entry
 * and the marker is removed.  An entry whose referenced sibling is missing (or
 * has no `data`) is left untouched, exactly as the PHP implementation does.
 *
 * This operates on the passed object directly; callers must pass a copy when
 * the original must be preserved.  {@link expandRefs} is the non-mutating
 * public wrapper.
 *
 * @param data
 *   The step's token data object to expand in place.
 */
function expandRefsInPlace(data: ReplayStepData): void {
  for (const entry of Object.values(data)) {
    const refKey = entry[TOKEN_DATA_REF];
    if (typeof refKey === 'string') {
      const target = data[refKey];
      if (target !== undefined && 'data' in target) {
        entry.data = target.data;
        delete entry[TOKEN_DATA_REF];
      }
    }
  }
}

/**
 * Returns a copy of a step's token data with all `@ref` markers expanded.
 *
 * The input is never mutated; a deep copy is expanded and returned.  Mirrors
 * ProcessDebugger::expandRefs() semantics on the copy.
 *
 * @param stepData
 *   A step's compact token data object, possibly containing `@ref` markers.
 *
 * @returns A new object with references resolved to their sibling data.
 */
export function expandRefs(stepData: ReplayStepData): ReplayStepData {
  const copy = deepClone(stepData);
  expandRefsInPlace(copy);
  return copy;
}

/**
 * Resolves the node referenced by a `@same` `(step, path)` locator.
 *
 * Mirrors ProcessDebugger::resolveSamePath(): the path is the slash-separated
 * sequence of object keys to index from the referenced step's already-expanded
 * token-data root (interleaving the literal `data` segment for each level of
 * descent — see the module docblock).  An empty path, an out-of-range step, or
 * a missing/non-object segment yields `null` (defensive, never throws), exactly
 * as the PHP implementation returns NULL.
 *
 * @param resolveStep
 *   A resolver returning the `@prev`/`@ref`-expanded token data for a step
 *   index, or `null` when that index is out of range.
 * @param step
 *   The zero-based step index of the first occurrence.
 * @param path
 *   The slash-separated locator within that step's token data.
 *
 * @returns The referenced node, or `null` when it cannot be resolved.
 */
function resolveSamePath(
  resolveStep: (index: number) => ReplayStepData | null,
  step: number,
  path: string,
): ReplayTokenEntry | null {
  const stepData = resolveStep(step);
  if (path === '' || stepData === null) {
    return null;
  }
  let current: unknown = stepData;
  for (const segment of path.split('/')) {
    if (!isStepDataObject(current) || !(segment in current)) {
      return null;
    }
    current = current[segment];
  }
  return isStepDataObject(current) ? current : null;
}

/**
 * Resolves a single node's `@same` marker and recurses into its children.
 *
 * Mirrors ProcessDebugger::expandSameNode(): when the node carries a `@same`
 * marker, the referenced node's `data` is spliced onto it (when resolvable) and
 * the marker is removed unconditionally — an unresolvable reference simply
 * leaves the entry without `data`.  Either way the children of the (possibly
 * just-spliced) `data` sub-tree are visited so nested `@same` markers resolve
 * too.  Operates in place; callers pass deep copies.
 *
 * @param node
 *   The token entry to resolve, mutated in place.
 * @param resolveStep
 *   A resolver returning the `@prev`/`@ref`-expanded token data for a step.
 */
function expandSameNode(
  node: ReplayTokenEntry,
  resolveStep: (index: number) => ReplayStepData | null,
): void {
  const reference = node[TOKEN_DATA_SAME];
  if (reference !== undefined) {
    const resolved = resolveSamePath(resolveStep, reference.step, reference.path);
    if (resolved !== null && 'data' in resolved) {
      // Deep-clone the resolved sub-tree: the target lives in an earlier step's
      // memo entry, and the subsequent `@ref`/nested-`@same` passes mutate the
      // spliced `data` in place. Sharing the reference would corrupt the cached
      // earlier step (and break non-mutation guarantees).
      node.data = deepClone(resolved.data);
    }
    delete node[TOKEN_DATA_SAME];
  }
  if (isStepDataObject(node.data)) {
    for (const child of Object.values(node.data)) {
      expandSameNode(child, resolveStep);
    }
  }
}

/**
 * Resolves `@same` markers throughout a displayed step's token data, in place.
 *
 * Mirrors ProcessDebugger::expandSame() restricted to a single step: every
 * top-level entry — and, recursively, every nested entry — that carries a
 * `@same` marker is resolved against the `@prev`/`@ref`-expanded data of the
 * referenced step.  Operates on the passed object directly; callers pass a copy.
 *
 * @param data
 *   The displayed step's token data, already `@prev`/`@ref`-expanded.
 * @param resolveStep
 *   A resolver returning the `@prev`/`@ref`-expanded token data for a step.
 */
function expandSameInPlace(
  data: ReplayStepData,
  resolveStep: (index: number) => ReplayStepData | null,
): void {
  for (const node of Object.values(data)) {
    expandSameNode(node, resolveStep);
  }
}

/**
 * Resolves the fully-expanded token data for a single replay step.
 *
 * Mirrors ProcessDebugger::expandHistory() across all three markers, in the
 * exact phase ORDER the PHP implementation uses — `@prev` then `@same` then
 * `@ref`:
 *
 *   1. `@prev` — a step whose `data` is the `@prev` marker reuses the previous
 *      step's already-fully-expanded data (which is therefore NOT re-expanded,
 *      matching the PHP behavior where `@prev` is assigned the previously
 *      stored expanded data).
 *   2. `@same` — the step's `@same` markers (top-level AND nested) are resolved
 *      against the already-fully-expanded data of the referenced (necessarily
 *      earlier or same-step) step.  This MUST run before `@ref` because an
 *      intra-step `@ref` may point at a sibling that is itself a `@same`
 *      marker; resolving `@same` first gives that sibling its real `data`.
 *   3. `@ref`  — the step's `@ref` markers are expanded LAST against sibling
 *      keys within the same step.  By now every sibling — including any that
 *      were `@same` markers — carries real `data`, so the `@ref` resolves.
 *
 * Because `@prev` resolution depends on the running expanded state and `@same`
 * may reference any earlier step, the prefix of steps up to and including
 * `stepIndex` is replayed.  A per-call memo caches each step's FULLY-expanded
 * data (all three markers resolved) so resolving many `@same` markers against
 * the same step stays linear rather than O(n^2), and so a later step's `@same`
 * reads from an earlier step that is itself already `@ref`-expanded.  Any
 * non-object `data` shape (absent, scalar, array) yields an empty object for
 * that step.
 *
 * Nothing in `steps` is mutated; all work happens on deep copies.
 *
 * @param steps
 *   The ordered, compact replay steps (the in-memory `replayData`).
 * @param stepIndex
 *   The index of the step whose token data should be expanded.
 *
 * @returns The expanded token data for the requested step, or `null` when the
 *   index is out of range.
 */
export function expandReplayStep(
  steps: readonly ReplayDataEntry[],
  stepIndex: number,
): ReplayStepData | null {
  if (stepIndex < 0 || stepIndex >= steps.length) {
    return null;
  }

  // Memoize each step's FULLY-expanded token data (all three markers resolved)
  // so that resolving `@same` markers against earlier steps does not re-walk
  // the prefix per reference (avoids O(n^2) on large histories) and always
  // reads a `@ref`-expanded target. The prefix is replayed in ascending index
  // order, mirroring ProcessDebugger::expandHistory(): because a `@same` marker
  // can only reference an earlier (or same) step, every target is already in
  // the memo by the time it is read.
  const memo = new Map<number, ReplayStepData>();

  /**
   * Returns the fully-expanded token data for a step, building the memo up to
   * and including it on demand.
   *
   * @param index
   *   The step index to resolve.
   *
   * @returns The expanded token data, or `null` when the index is out of range.
   */
  const getExpandedStepData = (index: number): ReplayStepData | null => {
    if (index < 0 || index >= steps.length) {
      return null;
    }
    const cached = memo.get(index);
    if (cached !== undefined) {
      return cached;
    }
    // Build every step from the lowest un-memoized index up to `index`, so the
    // `@prev` accumulator and every `@same` target are available in order.
    let lastData: ReplayStepData = memo.get(0) ?? {};
    for (let i = 0; i <= index; i++) {
      const cachedPrefix = memo.get(i);
      if (cachedPrefix !== undefined) {
        lastData = cachedPrefix;
        continue;
      }
      const raw = steps[i].data;
      let expanded: ReplayStepData;
      if (isPrevMarker(raw)) {
        // `@prev`: reuse the previous step's already-fully-expanded data.
        expanded = lastData;
      } else if (isStepDataObject(raw)) {
        expanded = deepClone(raw);
        // `@same` BEFORE `@ref`: resolve cross-step markers first so that an
        // intra-step `@ref` pointing at a former `@same` sibling finds real
        // `data`. `@same` targets are earlier steps, already in the memo.
        // Seed the memo for `i` first so a same-step `@same` can resolve via
        // the resolver without re-entering this loop for `i`.
        memo.set(i, expanded);
        expandSameInPlace(expanded, getExpandedStepData);
        // `@ref` LAST: every sibling now carries real `data`.
        expandRefsInPlace(expanded);
      } else {
        // Absent, scalar, or array data: treat as no token data for this step.
        expanded = {};
      }
      memo.set(i, expanded);
      lastData = expanded;
    }
    // The loop always memoizes `index` on its final iteration.
    return memo.get(index) ?? null;
  };

  // The memo holds the fully-expanded form; return a defensive deep copy so the
  // caller (and React) can treat the result as owned without ever risking the
  // memo or the original `steps` being mutated downstream.
  const expanded = getExpandedStepData(stepIndex);
  if (expanded === null) {
    return null;
  }
  return deepClone(expanded);
}
