/**
 * TokenSourceContext - Shared access to the token data sources for the "["
 * token picker inside property fields.
 *
 * Provided high up (in PropertyPanel, which already receives the lifted replay
 * state from Flow.tsx) and consumed by ContentEditableField's TokenPicker. This
 * avoids prop-drilling the token sources through NodePropertiesPanel →
 * ConfigurationForm → FormFieldRenderer → ContentEditableField.
 *
 * The data shapes mirror exactly what the Review-mode token tree consumes
 * (globalTokens / templateTokens / stepData), so the picker and the tree stay
 * in sync via the shared `transformGlobalToken` transformation.
 */

import { createContext, useContext } from 'react';
import type { GlobalToken } from '../types/settings';
import type { ReplayEntry } from '../hooks/useReplayLoader';

export interface TokenSourceValue {
  /** Global tokens (always available) keyed by token string. */
  globalTokens?: Record<string, GlobalToken>;
  /** Template tokens (present when the model is a template). */
  templateTokens?: Record<string, GlobalToken>;
  /** Whether the current model is a template (gates template tokens). */
  isTemplate?: boolean;
  /** Expanded step-data tokens for the currently selected replay step. */
  stepData?: Record<string, unknown> | null;
  /**
   * Whether {@link stepData} was PREDICTED from a replay-covered predecessor of
   * the selected node (issue #3577207) rather than confirmed by a replay run on
   * the node. When `true`, the picker stamps each step token `predicted` so a
   * subtle badge + tooltip renders. Defaults to `false` (confirmed).
   */
  stepDataPredicted?: boolean;
  /** Whether any step data is currently cached/available. */
  hasStepData?: boolean;
  /** Optional session timestamp (ISO string or Unix seconds) for the step data. */
  stepDataTimestamp?: string | number;
  /**
   * Switch the unified panel to "Review flow" mode. Used by the picker's
   * empty-step-data hint so the user can fetch richer step-data tokens.
   * Undefined when no review affordance is available.
   */
  onReviewModel?: () => void;
  /** Whether review/replay is available at all (controls the hint affordance). */
  reviewAvailable?: boolean;

  // ── Feature J: on-demand step-data in the [-token picker ──────────────────
  // The picker is a THIN VIEW over Flow's existing per-event session machinery
  // (NOT an independent loader). These fields/callbacks read/write the SAME
  // per-event session the Review panel uses, routed through Flow-provided
  // handlers. The picker NEVER calls useTestRunner/startTest/loadReplayData.
  /**
   * The id of the event whose flow OWNS the selected node (resolved by Flow via
   * findOwningReviewedEventId / selectedStartNodeId). When null/undefined, the
   * Step-data category is NOT offered (no context-less load). Gates the whole
   * Feature-J affordance.
   */
  owningEventId?: string | null;
  /**
   * Loaded replay entries (datasets) for the owning event's session, newest
   * first. Same array the Review panel renders.
   */
  replayEntries?: ReplayEntry[];
  /**
   * Index of the currently selected dataset: LISTEN_ITEM_INDEX (-2) = the
   * persistent "Listen…" item, -1 = none, 0..n-1 = a data entry. Same selection
   * the Review panel uses for the owning event.
   */
  selectedEntryIndex?: number;
  /** Whether the owning event's history load is currently in flight. */
  isLoadingStepData?: boolean;
  /**
   * Whether the owning event's live listener is currently armed (listen item
   * selected). Drives the picker's inline "Listening for event…" waiting state.
   */
  isListening?: boolean;
  /**
   * Enter/refresh the owning event's review session to load step data on demand
   * (routes through Flow.enterReviewForNode — starts the SINGLE listener + loads
   * history). Called when the user opens an as-yet-unloaded Step-data category.
   */
  onLoadStepData?: (eventId: string) => void;
  /**
   * Select a different dataset for the owning event (routes through Flow's
   * handleSelectReplayEntry — the Review panel reflects it and vice-versa).
   */
  onSelectDataset?: (index: number) => void;
  /**
   * Select the persistent "Listen…" item — (re)arms the SINGLE live listener
   * for the owning event (routes through Flow.handleSelectListenItem). The
   * listener keeps running even if the picker is dismissed.
   */
  onStartListen?: () => void;
  /**
   * STOP the owning event's live listener (routes through Flow's cancel path,
   * the SAME mechanism cancel-on-select uses — Flow.handleCancelReview). Cancels
   * the running listener, clears the listening component ref, and moves the
   * selection OFF the listen item (to the newest entry if any, else none),
   * matching the Review panel's "cancel listening" semantics. Used by the picker
   * when the user navigates Back out of the step-data category while listening,
   * so listen state does not linger. Preserves the single-listener invariant.
   */
  onStopListen?: () => void;
  /**
   * Notify the provider (PropertyPanel) whenever the [-token picker opens
   * (`true`) or closes (`false`). PropertyPanel uses this to FREEZE its
   * review-chrome derivations (Review-button enabled state, effective view
   * mode) while the picker is open, so the panel behind the modal does not
   * repaint when a picker-armed session changes session-derived state. The
   * picker's own LIVE token-source data (replayEntries, stepData, …) is NOT
   * frozen, so data still flows into the picker. Undefined outside the
   * PropertyPanel-provided context (e.g. ConfigurationForm in isolation) — the
   * caller must treat it as a no-op.
   */
  onPickerOpenChange?: (open: boolean) => void;
}

/**
 * Default value: no token sources. ContentEditableField treats an empty
 * context as "global/template only" (and typically nothing to show), which is
 * the correct behavior when no provider is mounted (e.g. in isolated tests).
 */
export const TokenSourceContext = createContext<TokenSourceValue>({});

/** Convenience hook for consuming the token sources. */
export function useTokenSources(): TokenSourceValue {
  return useContext(TokenSourceContext);
}
