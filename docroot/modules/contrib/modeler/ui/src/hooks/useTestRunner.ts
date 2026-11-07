/**
 * useTestRunner - Hook for running a live test of a workflow event
 *
 * Initiates a test by POSTing to the test_url endpoint, then polls for results.
 * When replay data is returned, it passes it to the parent for display in the
 * ReplayPanel. Listening proceeds even with unsaved changes (no save guard);
 * only structural validation (placeholder nodes) blocks entry.
 */

import { useState, useCallback, useRef, useEffect } from 'react';
import { t } from '../utils/translation';
import { fetchValidatedCsrfToken } from '../utils/validation';
import type { Settings } from '../types/settings';
import { showDrupalMessage } from '../utils/drupalMessage';

/** Polling interval in milliseconds */
const POLL_INTERVAL = 1500;

interface UseTestRunnerProps {
  settings?: Settings;
  /** Called when polling returns replay data (array of replay steps) */
  onReplayDataReceived: (data: any[]) => void;
  /**
   * Pre-save validation callback.  Return an error message string to block
   * the operation and display the error, or `null` to allow proceeding.
   */
  validateBeforeSave?: () => string | null;
}

interface UseTestRunnerReturn {
  /** Whether a test is currently running (polling for results) */
  isTestRunning: boolean;
  /** Whether the initial test request is in flight */
  isTestInitiating: boolean;
  /** Error message if the test failed */
  testError: string | null;
  /** Start a test for the given event component ID */
  startTest: (componentId: string) => void;
  /** Cancel the running test */
  cancelTest: () => void;
  /**
   * Retained for caller compatibility (Flow's save-complete handler). Listening
   * no longer defers to save, so this is a no-op.
   */
  notifySaveComplete: () => void;
}

export function useTestRunner({
  settings = {},
  onReplayDataReceived,
  validateBeforeSave,
}: UseTestRunnerProps): UseTestRunnerReturn {
  const [isTestRunning, setIsTestRunning] = useState(false);
  const [isTestInitiating, setIsTestInitiating] = useState(false);
  const [testError, setTestError] = useState<string | null>(null);

  const abortControllerRef = useRef<AbortController | null>(null);
  const pollIntervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const activeJobIdRef = useRef<string | null>(null);

  // Keep stable refs for callbacks
  const onReplayDataReceivedRef = useRef(onReplayDataReceived);
  useEffect(() => {
    onReplayDataReceivedRef.current = onReplayDataReceived;
  }, [onReplayDataReceived]);

  /** Stop polling and abort any in-flight request */
  const cleanup = useCallback(() => {
    if (pollIntervalRef.current) {
      clearInterval(pollIntervalRef.current);
      pollIntervalRef.current = null;
    }
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
      abortControllerRef.current = null;
    }
  }, []);

  /** Cancel the running test and reset all state */
  const cancelTest = useCallback(() => {
    const jobId = activeJobIdRef.current;
    cleanup();
    setIsTestRunning(false);
    setIsTestInitiating(false);
    setTestError(null);
    activeJobIdRef.current = null;

    // Notify the backend that polling has been cancelled so it can clean up
    // (e.g. reset debug mode). This is fire-and-forget — we don't block on it.
    const testUrl = settings.modeler_api?.test_url;
    const tokenUrl = settings.modeler_api?.token_url;
    if (jobId && testUrl && tokenUrl) {
      fetchValidatedCsrfToken(tokenUrl)
        .then((token) =>
          fetch(testUrl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json;charset=UTF-8',
              'X-CSRF-Token': token,
            },
            body: JSON.stringify({ jobId, cancelled: true }),
          })
        )
        .catch(() => {
          // Best-effort — ignore errors on the cancellation request.
        });
    }
  }, [cleanup, settings.modeler_api?.test_url, settings.modeler_api?.token_url]);

  /** Send the test request and start polling */
  const proceedWithTest = useCallback(async (componentId: string) => {
    cleanup();

    const testUrl = settings.modeler_api?.test_url;
    if (!testUrl) {
      setTestError(t('Test URL not found in settings'));
      return;
    }

    const tokenUrl = settings.modeler_api?.token_url;
    if (!tokenUrl) {
      setTestError(t('Token URL not found in settings'));
      return;
    }

    const modelId = settings.modeler?.modelId || '';

    const abortController = new AbortController();
    abortControllerRef.current = abortController;

    setIsTestInitiating(true);
    setIsTestRunning(false);
    setTestError(null);

    try {
      if (abortController.signal.aborted) return;

      // Get CSRF token
      const token = await fetchValidatedCsrfToken(tokenUrl, abortController.signal);

      if (abortController.signal.aborted) return;

      // Initiate the test
      const response = await fetch(testUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json;charset=UTF-8',
          'X-CSRF-Token': token,
        },
        body: JSON.stringify({ modelId, componentId }),
        signal: abortController.signal,
      });

      if (abortController.signal.aborted) return;

      if (!response.ok) {
        const errorMsg = t('Failed to start test: @status', { '@status': response.statusText });
        setTestError(errorMsg);
        showDrupalMessage(errorMsg, 'error');
        setIsTestInitiating(false);
        return;
      }

      const result = await response.json();

      if (abortController.signal.aborted) return;

      // Check for error response
      if (result && typeof result === 'object' && !Array.isArray(result) && result.error) {
        setTestError(result.error);
        showDrupalMessage(result.error, 'error');
        setIsTestInitiating(false);
        return;
      }

      // Check for warning response (non-blocking — show message but continue)
      if (result && typeof result === 'object' && !Array.isArray(result) && result.warning) {
        showDrupalMessage(result.warning, 'warning');
      }

      // Expect a jobId in the response
      if (!result || !result.jobId) {
        const errorMsg = t('Invalid test response: no job ID received');
        setTestError(errorMsg);
        showDrupalMessage(errorMsg, 'error');
        setIsTestInitiating(false);
        return;
      }

      const jobId = result.jobId;
      activeJobIdRef.current = jobId;
      setIsTestInitiating(false);
      setIsTestRunning(true);

      // Start polling for results
      const poll = async () => {
        // Get a fresh abort controller check — the original may have been cancelled
        if (!abortControllerRef.current || abortControllerRef.current.signal.aborted) {
          if (pollIntervalRef.current) {
            clearInterval(pollIntervalRef.current);
            pollIntervalRef.current = null;
          }
          return;
        }

        try {
          const pollToken = await fetchValidatedCsrfToken(tokenUrl, abortControllerRef.current.signal);

          if (!abortControllerRef.current || abortControllerRef.current.signal.aborted) return;

          const pollResponse = await fetch(testUrl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json;charset=UTF-8',
              'X-CSRF-Token': pollToken,
            },
            body: JSON.stringify({ jobId }),
            signal: abortControllerRef.current.signal,
          });

          if (!abortControllerRef.current || abortControllerRef.current.signal.aborted) return;

          if (!pollResponse.ok) {
            const errorMsg = t('Test polling failed: @status', { '@status': pollResponse.statusText });
            setTestError(errorMsg);
            showDrupalMessage(errorMsg, 'error');
            setIsTestRunning(false);
            if (pollIntervalRef.current) {
              clearInterval(pollIntervalRef.current);
              pollIntervalRef.current = null;
            }
            return;
          }

          const pollResult = await pollResponse.json();

          if (!abortControllerRef.current || abortControllerRef.current.signal.aborted) return;

          // Check for error response
          if (pollResult && typeof pollResult === 'object' && !Array.isArray(pollResult) && pollResult.error) {
            setTestError(pollResult.error);
            showDrupalMessage(pollResult.error, 'error');
            setIsTestRunning(false);
            if (pollIntervalRef.current) {
              clearInterval(pollIntervalRef.current);
              pollIntervalRef.current = null;
            }
            return;
          }

          // Check for warning response (non-blocking — show message but continue)
          if (pollResult && typeof pollResult === 'object' && !Array.isArray(pollResult) && pollResult.warning) {
            showDrupalMessage(pollResult.warning, 'warning');
          }

          // Check for "waiting" status
          if (pollResult && typeof pollResult === 'object' && !Array.isArray(pollResult) && pollResult.status === 'waiting') {
            // Still waiting — continue polling
            return;
          }

          // Otherwise, we have replay data
          if (pollIntervalRef.current) {
            clearInterval(pollIntervalRef.current);
            pollIntervalRef.current = null;
          }
          activeJobIdRef.current = null;
          setIsTestRunning(false);
          setTestError(null);

          // Pass the replay data to the parent
          if (Array.isArray(pollResult)) {
            onReplayDataReceivedRef.current(pollResult);
          } else {
            const errorMsg = t('Unexpected test response format');
            setTestError(errorMsg);
            showDrupalMessage(errorMsg, 'error');
          }
        } catch (err: unknown) {
          if (err instanceof Error && err.name !== 'AbortError') {
            const errorMsg = t('Test polling error: @message', { '@message': err.message });
            setTestError(errorMsg);
            showDrupalMessage(errorMsg, 'error');
            setIsTestRunning(false);
            if (pollIntervalRef.current) {
              clearInterval(pollIntervalRef.current);
              pollIntervalRef.current = null;
            }
          }
        }
      };

      pollIntervalRef.current = setInterval(poll, POLL_INTERVAL);
    } catch (err: unknown) {
      if (err instanceof Error && err.name !== 'AbortError') {
        const errorMsg = t('Error starting test: @message', { '@message': err.message });
        setTestError(errorMsg);
        showDrupalMessage(errorMsg, 'error');
      }
      setIsTestInitiating(false);
    }
  }, [settings.modeler_api?.test_url, settings.modeler_api?.token_url, settings.modeler?.modelId, cleanup]);

  /**
   * Start a test (arm the live listener) for the given event component.
   *
   * Structural validation still gates entry (placeholder nodes block), and the
   * isNewModel gate is enforced by the caller (Flow). There is intentionally NO
   * unsaved-changes guard: listening proceeds even with unsaved changes.
   */
  const startTest = useCallback((componentId: string) => {
    // Block testing when placeholder nodes exist (structural validation only).
    if (validateBeforeSave) {
      const validationError = validateBeforeSave();
      if (validationError) {
        showDrupalMessage(validationError, 'error');
        return;
      }
    }
    // No unsaved-changes guard: listening proceeds even with unsaved changes.
    proceedWithTest(componentId);
  }, [proceedWithTest, validateBeforeSave]);

  /**
   * Retained for caller compatibility (Flow's save-complete handler). Tests no
   * longer defer to save, so there is nothing pending to resume — this is a
   * no-op.
   */
  const notifySaveComplete = useCallback(() => {}, []);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      cleanup();
    };
  }, [cleanup]);

  return {
    isTestRunning,
    isTestInitiating,
    testError,
    startTest,
    cancelTest,
    notifySaveComplete,
  };
}
