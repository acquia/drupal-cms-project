/**
 * Tests for useTestRunner hook
 */

import { renderHook, act, waitFor } from '@testing-library/react';
import { useTestRunner } from '../useTestRunner';
import { showDrupalMessage } from '../../utils/drupalMessage';

jest.mock('../../utils/drupalMessage', () => ({
  showDrupalMessage: jest.fn(),
}));

// Mock fetch
const mockFetch = jest.fn();
global.fetch = mockFetch;

/** Helper: create a mock CSRF token response with validation-compatible shape */
const mockTokenResponse = (token = 'csrf-token') => ({
  ok: true,
  status: 200,
  statusText: 'OK',
  text: () => Promise.resolve(token),
});

describe('useTestRunner', () => {
  const defaultSettings = {
    modeler_api: {
      test_url: '/api/test',
      token_url: '/api/token',
    },
    modeler: {
      modelId: 'model-1',
    },
  };

  const mockOnReplayDataReceived = jest.fn();

  const defaultProps = {
    settings: defaultSettings,
    onReplayDataReceived: mockOnReplayDataReceived,
  };

  beforeEach(() => {
    jest.clearAllMocks();
    mockFetch.mockReset();
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  describe('initialization', () => {
    it('should initialize with idle state', () => {
      const { result } = renderHook(() => useTestRunner(defaultProps));

      expect(result.current.isTestRunning).toBe(false);
      expect(result.current.isTestInitiating).toBe(false);
      expect(result.current.testError).toBeNull();
    });

    it('should return startTest, cancelTest, and notifySaveComplete functions', () => {
      const { result } = renderHook(() => useTestRunner(defaultProps));

      expect(typeof result.current.startTest).toBe('function');
      expect(typeof result.current.cancelTest).toBe('function');
      expect(typeof result.current.notifySaveComplete).toBe('function');
    });
  });

  describe('startTest without unsaved changes', () => {
    it('should initiate test and receive jobId', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ jobId: 'job-123' }),
        });

      const { result } = renderHook(() => useTestRunner(defaultProps));

      await act(async () => {
        result.current.startTest('event-1');
      });

      // Verify token fetch
      expect(mockFetch).toHaveBeenCalledWith('/api/token', expect.objectContaining({
        signal: expect.any(AbortSignal),
      }));

      // Verify test initiation fetch
      expect(mockFetch).toHaveBeenCalledWith('/api/test', expect.objectContaining({
        method: 'POST',
        headers: expect.objectContaining({
          'Content-Type': 'application/json;charset=UTF-8',
          'X-CSRF-Token': 'csrf-token',
        }),
        body: JSON.stringify({ modelId: 'model-1', componentId: 'event-1' }),
      }));

      expect(result.current.isTestRunning).toBe(true);
      expect(result.current.isTestInitiating).toBe(false);
      expect(result.current.testError).toBeNull();
    });

    it('should set isTestInitiating during the initial request', async () => {
      let resolveToken: (value: any) => void;
      const tokenPromise = new Promise((resolve) => { resolveToken = resolve; });

      mockFetch.mockReturnValueOnce(tokenPromise);

      const { result } = renderHook(() => useTestRunner(defaultProps));

      act(() => {
        result.current.startTest('event-1');
      });

      await waitFor(() => {
        expect(result.current.isTestInitiating).toBe(true);
      });

      // Resolve the token and provide a job response
      await act(async () => {
        resolveToken!(mockTokenResponse());
        mockFetch.mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ jobId: 'job-123' }),
        });
      });

      await waitFor(() => {
        expect(result.current.isTestInitiating).toBe(false);
      });
    });

    it('should handle error response from backend', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ error: 'Model not found' }),
        });

      const { result } = renderHook(() => useTestRunner(defaultProps));

      await act(async () => {
        result.current.startTest('event-1');
      });

      expect(result.current.testError).toBe('Model not found');
      expect(result.current.isTestRunning).toBe(false);
      expect(result.current.isTestInitiating).toBe(false);
    });

    it('should handle warning response without stopping', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ jobId: 'job-123', warning: 'Model has issues' }),
        });

      const { result } = renderHook(() => useTestRunner(defaultProps));

      await act(async () => {
        result.current.startTest('event-1');
      });

      // Warning should not block — test should be running
      expect(result.current.isTestRunning).toBe(true);
      expect(result.current.testError).toBeNull();
    });

    it('should handle HTTP error response', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: false,
          statusText: 'Internal Server Error',
        });

      const { result } = renderHook(() => useTestRunner(defaultProps));

      await act(async () => {
        result.current.startTest('event-1');
      });

      expect(result.current.testError).toContain('Internal Server Error');
      expect(result.current.isTestRunning).toBe(false);
    });

    it('should handle missing jobId in response', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ status: 'ok' }),
        });

      const { result } = renderHook(() => useTestRunner(defaultProps));

      await act(async () => {
        result.current.startTest('event-1');
      });

      expect(result.current.testError).toContain('no job ID');
      expect(result.current.isTestRunning).toBe(false);
    });

    it('should handle network errors gracefully', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockRejectedValueOnce(new Error('Network error'));

      const { result } = renderHook(() => useTestRunner(defaultProps));

      await act(async () => {
        result.current.startTest('event-1');
      });

      expect(result.current.testError).toContain('Network error');
      expect(result.current.isTestRunning).toBe(false);
    });

    it('should handle missing test_url', async () => {
      const { result } = renderHook(() => useTestRunner({
        ...defaultProps,
        settings: { modeler_api: { token_url: '/api/token' } },
      }));

      await act(async () => {
        result.current.startTest('event-1');
      });

      expect(result.current.testError).toBeTruthy();
      expect(mockFetch).not.toHaveBeenCalled();
    });

    it('should handle missing token_url', async () => {
      const { result } = renderHook(() => useTestRunner({
        ...defaultProps,
        settings: { modeler_api: { test_url: '/api/test' } },
      }));

      await act(async () => {
        result.current.startTest('event-1');
      });

      expect(result.current.testError).toBeTruthy();
      expect(mockFetch).not.toHaveBeenCalled();
    });
  });

  // The unsaved-changes "save before testing" guard was removed at the source:
  // listening now proceeds directly even with unsaved changes (only structural
  // validation gates entry). notifySaveComplete is retained as a no-op.
  describe('startTest proceeds directly (no unsaved-changes guard)', () => {
    it('should initiate the test directly without any confirmation dialog', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ jobId: 'job-456' }),
        });

      const { result } = renderHook(() => useTestRunner(defaultProps));

      await act(async () => {
        result.current.startTest('event-1');
      });

      // No dialog was needed — the test request was sent straight away.
      expect(mockFetch).toHaveBeenCalledWith('/api/test', expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({ modelId: 'model-1', componentId: 'event-1' }),
      }));
      expect(result.current.isTestRunning).toBe(true);
    });

    it('notifySaveComplete is a harmless no-op (no pending test to resume)', async () => {
      const { result } = renderHook(() => useTestRunner(defaultProps));

      await act(async () => {
        result.current.notifySaveComplete();
      });

      // Nothing pending → no request made.
      expect(mockFetch).not.toHaveBeenCalled();
    });
  });

  describe('polling', () => {
    it('should continue polling on waiting status', async () => {
      // Initial request
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ jobId: 'job-123' }),
        });

      const { result } = renderHook(() => useTestRunner(defaultProps));

      await act(async () => {
        result.current.startTest('event-1');
      });

      expect(result.current.isTestRunning).toBe(true);

      // First poll — waiting
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ status: 'waiting' }),
        });

      await act(async () => {
        jest.advanceTimersByTime(1500);
      });

      // Should still be running
      expect(result.current.isTestRunning).toBe(true);
      expect(mockOnReplayDataReceived).not.toHaveBeenCalled();
    });

    it('should stop polling and deliver data on success', async () => {
      const replayData = [
        { id: 'event-1', type: 'started', data: {} },
        { id: 'action-1', type: 'execute', data: {} },
      ];

      // Initial request
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ jobId: 'job-123' }),
        });

      const { result } = renderHook(() => useTestRunner(defaultProps));

      await act(async () => {
        result.current.startTest('event-1');
      });

      // Poll returns data
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve(replayData),
        });

      await act(async () => {
        jest.advanceTimersByTime(1500);
      });

      await waitFor(() => {
        expect(result.current.isTestRunning).toBe(false);
      });

      expect(mockOnReplayDataReceived).toHaveBeenCalledWith(replayData);
      expect(result.current.testError).toBeNull();
    });

    it('should handle error during polling', async () => {
      // Initial request
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ jobId: 'job-123' }),
        });

      const { result } = renderHook(() => useTestRunner(defaultProps));

      await act(async () => {
        result.current.startTest('event-1');
      });

      // Poll returns error
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ error: 'Execution failed' }),
        });

      await act(async () => {
        jest.advanceTimersByTime(1500);
      });

      await waitFor(() => {
        expect(result.current.isTestRunning).toBe(false);
      });

      expect(result.current.testError).toBe('Execution failed');
      expect(mockOnReplayDataReceived).not.toHaveBeenCalled();
    });

    it('should handle warning during polling without stopping', async () => {
      const replayData = [{ id: 'event-1', type: 'started', data: {} }];

      // Initial request
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ jobId: 'job-123' }),
        });

      const { result } = renderHook(() => useTestRunner(defaultProps));

      await act(async () => {
        result.current.startTest('event-1');
      });

      // First poll returns warning + waiting
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ status: 'waiting', warning: 'Taking longer than usual' }),
        });

      await act(async () => {
        jest.advanceTimersByTime(1500);
      });

      // Should still be running
      expect(result.current.isTestRunning).toBe(true);

      // Second poll returns data
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve(replayData),
        });

      await act(async () => {
        jest.advanceTimersByTime(1500);
      });

      await waitFor(() => {
        expect(result.current.isTestRunning).toBe(false);
      });

      expect(mockOnReplayDataReceived).toHaveBeenCalledWith(replayData);
    });

    it('should handle HTTP error during polling', async () => {
      // Initial request
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ jobId: 'job-123' }),
        });

      const { result } = renderHook(() => useTestRunner(defaultProps));

      await act(async () => {
        result.current.startTest('event-1');
      });

      // Poll returns HTTP error
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: false,
          statusText: 'Bad Gateway',
        });

      await act(async () => {
        jest.advanceTimersByTime(1500);
      });

      await waitFor(() => {
        expect(result.current.isTestRunning).toBe(false);
      });

      expect(result.current.testError).toContain('Bad Gateway');
    });

    it('should handle unexpected response format during polling', async () => {
      // Initial request
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ jobId: 'job-123' }),
        });

      const { result } = renderHook(() => useTestRunner(defaultProps));

      await act(async () => {
        result.current.startTest('event-1');
      });

      // Poll returns unexpected format (object without status/error/array)
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ unexpected: true }),
        });

      await act(async () => {
        jest.advanceTimersByTime(1500);
      });

      await waitFor(() => {
        expect(result.current.isTestRunning).toBe(false);
      });

      expect(result.current.testError).toContain('Unexpected');
    });

    it('should send jobId in poll requests', async () => {
      // Initial request
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ jobId: 'job-xyz' }),
        });

      const { result } = renderHook(() => useTestRunner(defaultProps));

      await act(async () => {
        result.current.startTest('event-1');
      });

      // Poll
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ status: 'waiting' }),
        });

      await act(async () => {
        jest.advanceTimersByTime(1500);
      });

      // Third fetch call (index 2) should be the poll token, fourth (index 3) the poll
      const pollCall = mockFetch.mock.calls[3];
      expect(pollCall[0]).toBe('/api/test');
      expect(JSON.parse(pollCall[1].body)).toEqual({ jobId: 'job-xyz' });
    });
  });

  describe('cancelTest', () => {
    it('should stop polling and reset state', async () => {
      // Initial request
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ jobId: 'job-123' }),
        });

      const { result } = renderHook(() => useTestRunner(defaultProps));

      await act(async () => {
        result.current.startTest('event-1');
      });

      expect(result.current.isTestRunning).toBe(true);

      // Prepare mocks for the cancellation request (token fetch + cancel POST)
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({ ok: true, json: () => Promise.resolve({ status: 'cancelled' }) });

      await act(async () => {
        result.current.cancelTest();
      });

      expect(result.current.isTestRunning).toBe(false);
      expect(result.current.isTestInitiating).toBe(false);
      expect(result.current.testError).toBeNull();

      // Flush the fire-and-forget cancellation promise chain
      await act(async () => {
        await Promise.resolve();
      });

      // No more polls should fire
      mockFetch.mockClear();

      await act(async () => {
        jest.advanceTimersByTime(3000);
      });

      expect(mockFetch).not.toHaveBeenCalled();
    });

    it('should send cancellation notification to backend with jobId', async () => {
      // Initial request
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ jobId: 'job-cancel-test' }),
        });

      const { result } = renderHook(() => useTestRunner(defaultProps));

      await act(async () => {
        result.current.startTest('event-1');
      });

      expect(result.current.isTestRunning).toBe(true);

      // Prepare mocks for the cancellation request
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse('cancel-token'))
        .mockResolvedValueOnce({ ok: true, json: () => Promise.resolve({ status: 'cancelled' }) });

      await act(async () => {
        result.current.cancelTest();
      });

      // Flush the fire-and-forget cancellation promise chain
      await act(async () => {
        await Promise.resolve();
      });

      // Find the cancellation POST call (last fetch call with the test URL)
      const cancelCall = mockFetch.mock.calls.find(
        (call) => {
          try {
            const body = JSON.parse(call[1]?.body || '{}');
            return body.jobId && body.cancelled === true;
          } catch {
            return false;
          }
        }
      );

      expect(cancelCall).toBeDefined();
      expect(cancelCall![0]).toBe('/api/test');
      expect(JSON.parse(cancelCall![1].body)).toEqual({ jobId: 'job-cancel-test', cancelled: true });
      expect(cancelCall![1].headers['X-CSRF-Token']).toBe('cancel-token');
    });

    it('should not send cancellation request if no jobId is active', async () => {
      const { result } = renderHook(() => useTestRunner(defaultProps));

      // Cancel without starting — no jobId to send
      await act(async () => {
        result.current.cancelTest();
      });

      expect(mockFetch).not.toHaveBeenCalled();
    });

    it('should gracefully handle cancellation request failure', async () => {
      // Initial request
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ jobId: 'job-fail' }),
        });

      const { result } = renderHook(() => useTestRunner(defaultProps));

      await act(async () => {
        result.current.startTest('event-1');
      });

      // The cancellation token fetch will fail
      mockFetch.mockRejectedValueOnce(new Error('Network down'));

      await act(async () => {
        result.current.cancelTest();
      });

      // Flush the fire-and-forget cancellation promise chain
      await act(async () => {
        await Promise.resolve();
      });

      // State should still be cleanly reset despite the failed notification
      expect(result.current.isTestRunning).toBe(false);
      expect(result.current.testError).toBeNull();
    });
  });

  describe('abort handling', () => {
    it('should ignore AbortError during cancellation', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockRejectedValueOnce(new DOMException('The operation was aborted.', 'AbortError'));

      const { result } = renderHook(() => useTestRunner(defaultProps));

      await act(async () => {
        result.current.startTest('event-1');
      });

      // AbortError should not set error state
      expect(result.current.testError).toBeNull();
    });
  });

  describe('cleanup on unmount', () => {
    it('should clean up polling on unmount', async () => {
      // Initial request
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ jobId: 'job-123' }),
        });

      const { result, unmount } = renderHook(() => useTestRunner(defaultProps));

      await act(async () => {
        result.current.startTest('event-1');
      });

      expect(result.current.isTestRunning).toBe(true);

      // Unmount should not throw
      unmount();

      // Advancing timers after unmount should not cause errors
      await act(async () => {
        jest.advanceTimersByTime(3000);
      });
    });
  });

  describe('validateBeforeSave', () => {
    it('should block test start when validateBeforeSave returns an error string', () => {
      const propsWithValidation = {
        ...defaultProps,
        validateBeforeSave: () => 'Placeholder nodes must be resolved before testing.',
      };

      const { result } = renderHook(() => useTestRunner(propsWithValidation));

      act(() => {
        result.current.startTest('event-1');
      });

      // fetch should NOT have been called (no CSRF token fetch, no test initiation)
      expect(mockFetch).not.toHaveBeenCalled();
      // Test should not be running or initiating
      expect(result.current.isTestRunning).toBe(false);
      expect(result.current.isTestInitiating).toBe(false);
    });

    it('should call showDrupalMessage with the error message', () => {
      const propsWithValidation = {
        ...defaultProps,
        validateBeforeSave: () => 'Please resolve all placeholder nodes.',
      };

      const { result } = renderHook(() => useTestRunner(propsWithValidation));

      act(() => {
        result.current.startTest('event-1');
      });

      expect(showDrupalMessage).toHaveBeenCalledWith('Please resolve all placeholder nodes.', 'error');
    });

    it('should proceed normally when validateBeforeSave returns null', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ jobId: 'job-valid' }),
        });

      const propsWithValidation = {
        ...defaultProps,
        validateBeforeSave: () => null,
      };

      const { result } = renderHook(() => useTestRunner(propsWithValidation));

      await act(async () => {
        result.current.startTest('event-1');
      });

      // Should have proceeded to fetch (CSRF token + test initiation)
      expect(mockFetch).toHaveBeenCalled();
      expect(result.current.isTestRunning).toBe(true);
    });

    it('should proceed normally when validateBeforeSave is not provided', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ jobId: 'job-no-validate' }),
        });

      const { result } = renderHook(() => useTestRunner(defaultProps));

      await act(async () => {
        result.current.startTest('event-1');
      });

      // Should have proceeded to fetch
      expect(mockFetch).toHaveBeenCalled();
      expect(result.current.isTestRunning).toBe(true);
    });
  });
});
