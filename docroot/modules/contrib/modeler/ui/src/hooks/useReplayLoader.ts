/**
 * useReplayLoader - Hook for dynamically loading replay data from the backend
 *
 * Fetches replay execution data for a specific event component by POSTing
 * to the replay_url endpoint, similar to how useConfigurationLoader fetches
 * config forms from the config_url.
 */

import { useState, useCallback, useRef, useEffect } from 'react';
import { t } from '../utils/translation';
import { fetchValidatedCsrfToken, validateReplayEntries } from '../utils/validation';
import type { Settings, ReplayDataEntry } from '../types/settings';
import { showDrupalMessage } from '../utils/drupalMessage';

/**
 * Sentinel value for `selectedEntryIndex` meaning the persistent "listen item"
 * (the top dropdown entry) is selected — distinct from -1 ("no entry") and from
 * the 0..n-1 data-entry indices. While selected, the review body shows the
 * live-listener waiting state. Part of the per-event ReviewSession snapshot so
 * it survives event switches (without re-arming the listener — see Flow.tsx).
 */
export const LISTEN_ITEM_INDEX = -2;

export interface ReplayEntry {
  /** Model ID */
  model_id: string;
  /** Component ID that triggered this execution */
  component_id: string;
  /** Replay steps (same structure as drupalSettings.modeler.replayData) */
  history: ReplayDataEntry[];
  /** Timestamp of the execution (ISO string or Unix seconds) */
  timestamp: string | number;
  /** User who triggered the execution (may be string or object with name) */
  user: string | { name?: string; [key: string]: unknown };
  /** IP address of the request */
  ip: string;
  /** URL context of the execution */
  url: string;
}

interface UseReplayLoaderProps {
  settings?: Settings;
  /** Called once when new entries are successfully loaded */
  onEntriesLoaded?: (entries: ReplayEntry[]) => void;
  /**
   * Called whenever a load resolves with the backend's empty/warning message
   * (or null when the result is populated and no warning applies). Lets the
   * caller store the message per-event so each event surfaces its own.
   */
  onEmptyMessage?: (message: string | null) => void;
}

interface UseReplayLoaderReturn {
  /** List of replay entries returned from the backend */
  replayEntries: ReplayEntry[];
  /** Whether a fetch is in progress */
  loading: boolean;
  /** Error message if the fetch failed */
  error: string | null;
  /**
   * The backend's empty/warning message from the most recent load (the
   * `warning` field, or a generic "no data" notice when the result is empty),
   * or null when the result was populated. Surfaced as returnable state so the
   * review body can show it after the user cancels listening.
   */
  emptyMessage: string | null;
  /** Fetch replay data for a given component ID */
  loadReplayData: (componentId: string) => Promise<void>;
  /** Clear loaded replay entries */
  clearReplayEntries: () => void;
}

export function useReplayLoader({
  settings = {},
  onEntriesLoaded,
  onEmptyMessage,
}: UseReplayLoaderProps): UseReplayLoaderReturn {
  const [replayEntries, setReplayEntries] = useState<ReplayEntry[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [emptyMessage, setEmptyMessage] = useState<string | null>(null);
  const abortControllerRef = useRef<AbortController | null>(null);

  // Keep stable refs to the callbacks so loadReplayData never re-creates
  const onEntriesLoadedRef = useRef(onEntriesLoaded);
  useEffect(() => {
    onEntriesLoadedRef.current = onEntriesLoaded;
  }, [onEntriesLoaded]);
  const onEmptyMessageRef = useRef(onEmptyMessage);
  useEffect(() => {
    onEmptyMessageRef.current = onEmptyMessage;
  }, [onEmptyMessage]);

  const loadReplayData = useCallback(async (componentId: string) => {
    // Cancel any previous request
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
    }

    const abortController = new AbortController();
    abortControllerRef.current = abortController;

    const replayUrl = settings.modeler_api?.replay_url;
    if (!replayUrl) {
      setError(t('Replay URL not found in settings'));
      return;
    }

    const tokenUrl = settings.modeler_api?.token_url;
    if (!tokenUrl) {
      setError(t('Token URL not found in settings'));
      return;
    }

    const modelId = settings.modeler?.modelId || '';

    setLoading(true);
    setError(null);

    try {
      if (abortController.signal.aborted) return;

      // Get CSRF token (validated for non-empty, non-HTML response)
      const token = await fetchValidatedCsrfToken(tokenUrl, abortController.signal);

      if (abortController.signal.aborted) return;

      const response = await fetch(replayUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json;charset=UTF-8',
          'X-CSRF-Token': token,
        },
        body: JSON.stringify({
          modelId,
          componentId,
        }),
        signal: abortController.signal,
      });

      if (abortController.signal.aborted) return;

      if (response.ok) {
        const result = await response.json();

        if (result && typeof result === 'object' && !Array.isArray(result) && result.error) {
          const errorMessage = result.error;
          setError(errorMessage);
          setReplayEntries([]);
          // Show error message to user
          showDrupalMessage(errorMessage, 'error');
        } else {
          // Capture any backend warning (non-blocking — continue processing).
          const backendWarning =
            result && typeof result === 'object' && !Array.isArray(result) && typeof result.warning === 'string'
              ? result.warning
              : null;
          if (backendWarning) {
            showDrupalMessage(backendWarning, 'warning');
          }
          // The entries to validate: a bare array, an `entries` array on a
          // warning-bearing object, or (warning-only object) an empty list — so
          // a warning response surfaces as "empty with message" rather than an
          // "unexpected response format" error.
          const entriesPayload = Array.isArray(result)
            ? result
            : (result && typeof result === 'object' && Array.isArray(result.entries)
                ? result.entries
                : (backendWarning ? [] : result));
          // Validate that the response is an array with valid entries
          const validEntries = validateReplayEntries(entriesPayload) as ReplayEntry[];
          // Sort entries by timestamp descending (newest first)
          validEntries.sort((a, b) => {
            const dateA = typeof a.timestamp === 'number' ? a.timestamp * 1000 : new Date(a.timestamp).getTime();
            const dateB = typeof b.timestamp === 'number' ? b.timestamp * 1000 : new Date(b.timestamp).getTime();
            return dateB - dateA;
          });
          setReplayEntries(validEntries);
          setError(null);
          // Determine the empty/warning message to surface (per-event). Prefer
          // the explicit backend warning; otherwise a generic notice when there
          // is no data; null when entries are present.
          const genericEmpty = t('No replay data available for this event.');
          const message = validEntries.length === 0 ? (backendWarning || genericEmpty) : backendWarning;
          // NOTE: we intentionally do NOT raise a Drupal toast for the generic
          // empty case — the review empty body + per-event backendMessage already
          // convey it (the toast was redundant). The generic text still flows
          // into emptyMessage/onEmptyMessage for the empty-body-after-cancel UX.
          setEmptyMessage(message);
          // Notify parent immediately with the validated entries + message.
          onEntriesLoadedRef.current?.(validEntries);
          onEmptyMessageRef.current?.(message);
        }
      } else {
        setError(t('Failed to load replay data: @status', { '@status': response.statusText }));
        setReplayEntries([]);
      }
    } catch (err: unknown) {
      if (err instanceof Error && err.name !== 'AbortError') {
        setError(t('Error loading replay data: @message', { '@message': err.message }));
        setReplayEntries([]);
      }
    } finally {
      if (!abortController.signal.aborted) {
        setLoading(false);
      }
    }
  }, [settings.modeler_api?.replay_url, settings.modeler_api?.token_url, settings.modeler?.modelId]);

  const clearReplayEntries = useCallback(() => {
    setReplayEntries([]);
    setError(null);
    setEmptyMessage(null);
  }, []);

  // Abort any in-flight request on unmount so a pending fetch does not attempt
  // to update state after the component is gone.
  useEffect(() => {
    return () => {
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
        abortControllerRef.current = null;
      }
    };
  }, []);

  return {
    replayEntries,
    loading,
    error,
    emptyMessage,
    loadReplayData,
    clearReplayEntries,
  };
}
