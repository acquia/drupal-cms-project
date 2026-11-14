/**
 * Tests for useReplayLoader hook
 */

import { renderHook, act, waitFor } from '@testing-library/react';
import { useReplayLoader } from '../useReplayLoader';
import { showDrupalMessage } from '../../utils/drupalMessage';

// Mock the Drupal toast so we can assert which messages are (not) raised.
jest.mock('../../utils/drupalMessage', () => ({
  showDrupalMessage: jest.fn(),
}));
const mockShowDrupalMessage = showDrupalMessage as jest.MockedFunction<typeof showDrupalMessage>;

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

describe('useReplayLoader', () => {
  const defaultSettings = {
    modeler_api: {
      replay_url: '/api/replay',
      token_url: '/api/token',
    },
    modeler: {
      modelId: 'model-1',
    },
  };

  const mockReplayEntries = [
    {
      model_id: 'model-1',
      component_id: 'event-1',
      timestamp: '2026-02-09 10:00:00',
      user: 'admin',
      ip: '127.0.0.1',
      url: '/node/1',
      history: [
        { id: 'event-1', type: 'started', data: {} },
        { id: 'action-1', type: 'execute', data: {} },
      ],
    },
    {
      model_id: 'model-1',
      component_id: 'event-1',
      timestamp: '2026-02-09 11:00:00',
      user: 'editor',
      ip: '192.168.1.1',
      url: '/node/2',
      history: [
        { id: 'event-1', type: 'started', data: {} },
        { id: 'action-2', type: 'execute', data: {} },
        { id: 'action-3', type: 'execute', data: {} },
      ],
    },
  ];

  beforeEach(() => {
    jest.clearAllMocks();
    mockFetch.mockReset();
  });

  describe('initialization', () => {
    it('should initialize with empty replayEntries and no loading/error', () => {
      const { result } = renderHook(() => useReplayLoader({
        settings: defaultSettings,
      }));

      expect(result.current.replayEntries).toEqual([]);
      expect(result.current.loading).toBe(false);
      expect(result.current.error).toBeNull();
    });

    it('should return loadReplayData and clearReplayEntries functions', () => {
      const { result } = renderHook(() => useReplayLoader({
        settings: defaultSettings,
      }));

      expect(typeof result.current.loadReplayData).toBe('function');
      expect(typeof result.current.clearReplayEntries).toBe('function');
    });
  });

  describe('loadReplayData', () => {
    it('should fetch replay data with correct URL and body', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse()) // token
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve(mockReplayEntries),
        });

      const { result } = renderHook(() => useReplayLoader({
        settings: defaultSettings,
      }));

      await act(async () => {
        await result.current.loadReplayData('event-1');
      });

      // Verify token fetch (now includes signal for abort support)
      expect(mockFetch).toHaveBeenCalledWith('/api/token', expect.objectContaining({
        signal: expect.any(AbortSignal),
      }));

      // Verify replay data fetch
      expect(mockFetch).toHaveBeenCalledWith('/api/replay', expect.objectContaining({
        method: 'POST',
        headers: expect.objectContaining({
          'Content-Type': 'application/json;charset=UTF-8',
          'X-CSRF-Token': 'csrf-token',
        }),
        body: JSON.stringify({
          modelId: 'model-1',
          componentId: 'event-1',
        }),
      }));

      // Entries should be sorted by timestamp descending (newest first)
      expect(result.current.replayEntries).toEqual([mockReplayEntries[1], mockReplayEntries[0]]);
      expect(result.current.loading).toBe(false);
      expect(result.current.error).toBeNull();
    });

    it('should set loading state during fetch', async () => {
      let resolveToken: (value: any) => void;
      const tokenPromise = new Promise((resolve) => { resolveToken = resolve; });

      mockFetch.mockReturnValueOnce(tokenPromise);

      const { result } = renderHook(() => useReplayLoader({
        settings: defaultSettings,
      }));

      // Start loading
      act(() => {
        result.current.loadReplayData('event-1');
      });

      // Should be loading
      await waitFor(() => {
        expect(result.current.loading).toBe(true);
      });

      // Resolve the promise
      await act(async () => {
        resolveToken!(mockTokenResponse('token'));
        // Mock the second fetch call
        mockFetch.mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve([]),
        });
      });

      await waitFor(() => {
        expect(result.current.loading).toBe(false);
      });
    });

    it('should handle error response from backend', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ error: 'No executions found' }),
        });

      const { result } = renderHook(() => useReplayLoader({
        settings: defaultSettings,
      }));

      await act(async () => {
        await result.current.loadReplayData('event-1');
      });

      expect(result.current.error).toBe('No executions found');
      expect(result.current.replayEntries).toEqual([]);
    });

    it('should handle HTTP error response', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: false,
          statusText: 'Internal Server Error',
        });

      const { result } = renderHook(() => useReplayLoader({
        settings: defaultSettings,
      }));

      await act(async () => {
        await result.current.loadReplayData('event-1');
      });

      expect(result.current.error).toContain('Internal Server Error');
      expect(result.current.replayEntries).toEqual([]);
    });

    it('should handle missing replay_url', async () => {
      const { result } = renderHook(() => useReplayLoader({
        settings: { modeler_api: { token_url: '/api/token' } },
      }));

      await act(async () => {
        await result.current.loadReplayData('event-1');
      });

      expect(result.current.error).toBeTruthy();
      expect(mockFetch).not.toHaveBeenCalled();
    });

    it('should handle missing token_url', async () => {
      const { result } = renderHook(() => useReplayLoader({
        settings: { modeler_api: { replay_url: '/api/replay' } },
      }));

      await act(async () => {
        await result.current.loadReplayData('event-1');
      });

      expect(result.current.error).toBeTruthy();
      expect(mockFetch).not.toHaveBeenCalled();
    });

    it('should handle network errors gracefully', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockRejectedValueOnce(new Error('Network error'));

      const { result } = renderHook(() => useReplayLoader({
        settings: defaultSettings,
      }));

      await act(async () => {
        await result.current.loadReplayData('event-1');
      });

      expect(result.current.error).toContain('Network error');
      expect(result.current.replayEntries).toEqual([]);
    });

    it('should handle empty array response', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve([]),
        });

      const { result } = renderHook(() => useReplayLoader({
        settings: defaultSettings,
      }));

      await act(async () => {
        await result.current.loadReplayData('event-1');
      });

      expect(result.current.replayEntries).toEqual([]);
      expect(result.current.error).toBeNull();
    });

    it('should handle warning response without blocking data processing', async () => {
      // A warning can appear alongside valid replay entries
      // The response is still an object but includes both warning and valid data
      // In practice the backend wraps warning in the entries array response
      const entriesWithWarning = [
        {
          model_id: 'model-1',
          component_id: 'event-1',
          timestamp: '2026-02-09 10:00:00',
          user: 'admin',
          ip: '127.0.0.1',
          url: '/node/1',
          history: [{ id: 'event-1', type: 'started', data: {} }],
          warning: 'Data may be incomplete',
        },
      ];

      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve(entriesWithWarning),
        });

      const mockOnEntriesLoaded = jest.fn();
      const { result } = renderHook(() => useReplayLoader({
        settings: defaultSettings,
        onEntriesLoaded: mockOnEntriesLoaded,
      }));

      await act(async () => {
        await result.current.loadReplayData('event-1');
      });

      // Warning should not set error state
      expect(result.current.error).toBeNull();
      // Entries should be loaded successfully
      expect(result.current.replayEntries.length).toBe(1);
      // onEntriesLoaded should still be called
      expect(mockOnEntriesLoaded).toHaveBeenCalled();
    });

    it('should handle unexpected response format', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve('unexpected string'),
        });

      const { result } = renderHook(() => useReplayLoader({
        settings: defaultSettings,
      }));

      await act(async () => {
        await result.current.loadReplayData('event-1');
      });

      expect(result.current.error).toBeTruthy();
      expect(result.current.replayEntries).toEqual([]);
    });

    it('should use empty string for modelId when not provided', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve([]),
        });

      const { result } = renderHook(() => useReplayLoader({
        settings: {
          modeler_api: {
            replay_url: '/api/replay',
            token_url: '/api/token',
          },
        },
      }));

      await act(async () => {
        await result.current.loadReplayData('event-1');
      });

      expect(mockFetch).toHaveBeenCalledWith('/api/replay', expect.objectContaining({
        body: JSON.stringify({
          modelId: '',
          componentId: 'event-1',
        }),
      }));
    });
  });

  describe('clearReplayEntries', () => {
    it('should clear entries and error', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve(mockReplayEntries),
        });

      const { result } = renderHook(() => useReplayLoader({
        settings: defaultSettings,
      }));

      // Load data first
      await act(async () => {
        await result.current.loadReplayData('event-1');
      });

      expect(result.current.replayEntries).toHaveLength(2);

      // Clear
      act(() => {
        result.current.clearReplayEntries();
      });

      expect(result.current.replayEntries).toEqual([]);
      expect(result.current.error).toBeNull();
    });
  });

  describe('abort handling', () => {
    it('should ignore AbortError during cancellation', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockRejectedValueOnce(new DOMException('The operation was aborted.', 'AbortError'));

      const { result } = renderHook(() => useReplayLoader({
        settings: defaultSettings,
      }));

      await act(async () => {
        await result.current.loadReplayData('event-1');
      });

      // AbortError should not set error state
      expect(result.current.error).toBeNull();
    });

    it('should clear error when making a successful follow-up request', async () => {
      // First request fails
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve({ error: 'First error' }),
        });

      const { result } = renderHook(() => useReplayLoader({
        settings: defaultSettings,
      }));

      await act(async () => {
        await result.current.loadReplayData('event-1');
      });
      expect(result.current.error).toBe('First error');

      // Second request succeeds
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve(mockReplayEntries),
        });

      await act(async () => {
        await result.current.loadReplayData('event-1');
      });

      expect(result.current.error).toBeNull();
      // Entries should be sorted by timestamp descending (newest first)
      expect(result.current.replayEntries).toEqual([mockReplayEntries[1], mockReplayEntries[0]]);
    });
  });

  describe('sorting', () => {
    it('should sort entries by timestamp descending with ISO strings', async () => {
      const entries = [
        { ...mockReplayEntries[0], timestamp: '2026-01-01T08:00:00Z' },
        { ...mockReplayEntries[1], timestamp: '2026-01-01T12:00:00Z' },
        { ...mockReplayEntries[0], timestamp: '2026-01-01T10:00:00Z' },
      ];

      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve(entries),
        });

      const { result } = renderHook(() => useReplayLoader({
        settings: defaultSettings,
      }));

      await act(async () => {
        await result.current.loadReplayData('event-1');
      });

      expect(result.current.replayEntries[0].timestamp).toBe('2026-01-01T12:00:00Z');
      expect(result.current.replayEntries[1].timestamp).toBe('2026-01-01T10:00:00Z');
      expect(result.current.replayEntries[2].timestamp).toBe('2026-01-01T08:00:00Z');
    });

    it('should sort entries by timestamp descending with Unix timestamps', async () => {
      const entries = [
        { ...mockReplayEntries[0], timestamp: 1000 },
        { ...mockReplayEntries[1], timestamp: 3000 },
        { ...mockReplayEntries[0], timestamp: 2000 },
      ];

      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({
          ok: true,
          json: () => Promise.resolve(entries),
        });

      const { result } = renderHook(() => useReplayLoader({
        settings: defaultSettings,
      }));

      await act(async () => {
        await result.current.loadReplayData('event-1');
      });

      expect(result.current.replayEntries[0].timestamp).toBe(3000);
      expect(result.current.replayEntries[1].timestamp).toBe(2000);
      expect(result.current.replayEntries[2].timestamp).toBe(1000);
    });
  });

  describe('emptyMessage (Rework H / A49)', () => {
    it('should initialize emptyMessage as null', () => {
      const { result } = renderHook(() => useReplayLoader({ settings: defaultSettings }));
      expect(result.current.emptyMessage).toBeNull();
    });

    it('should set a generic emptyMessage when the result is empty WITHOUT raising a Drupal toast (Rework I)', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({ ok: true, json: () => Promise.resolve([]) });

      const onEmptyMessage = jest.fn();
      const { result } = renderHook(() => useReplayLoader({ settings: defaultSettings, onEmptyMessage }));

      await act(async () => {
        await result.current.loadReplayData('event-1');
      });

      // The generic text still flows into emptyMessage / onEmptyMessage (A47/A49)…
      expect(result.current.emptyMessage).toBe('No replay data available for this event.');
      expect(onEmptyMessage).toHaveBeenLastCalledWith('No replay data available for this event.');
      // …but the redundant generic toast is NOT shown (Rework I).
      expect(mockShowDrupalMessage).not.toHaveBeenCalledWith('No replay data available for this event.', 'warning');
    });

    it('should surface the backend warning as emptyMessage AND still raise the warning toast', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({ ok: true, json: () => Promise.resolve({ warning: 'Listener stopped before any event fired.' }) });

      const onEmptyMessage = jest.fn();
      const { result } = renderHook(() => useReplayLoader({ settings: defaultSettings, onEmptyMessage }));

      await act(async () => {
        await result.current.loadReplayData('event-1');
      });

      expect(result.current.emptyMessage).toBe('Listener stopped before any event fired.');
      expect(onEmptyMessage).toHaveBeenLastCalledWith('Listener stopped before any event fired.');
      // The explicit backend-warning toast is unchanged (still shown).
      expect(mockShowDrupalMessage).toHaveBeenCalledWith('Listener stopped before any event fired.', 'warning');
    });

    it('should clear emptyMessage (null) when the result is populated', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({ ok: true, json: () => Promise.resolve(mockReplayEntries) });

      const onEmptyMessage = jest.fn();
      const { result } = renderHook(() => useReplayLoader({ settings: defaultSettings, onEmptyMessage }));

      await act(async () => {
        await result.current.loadReplayData('event-1');
      });

      expect(result.current.emptyMessage).toBeNull();
      expect(onEmptyMessage).toHaveBeenLastCalledWith(null);
    });

    it('should clear emptyMessage on clearReplayEntries', async () => {
      mockFetch
        .mockResolvedValueOnce(mockTokenResponse())
        .mockResolvedValueOnce({ ok: true, json: () => Promise.resolve([]) });

      const { result } = renderHook(() => useReplayLoader({ settings: defaultSettings }));

      await act(async () => {
        await result.current.loadReplayData('event-1');
      });
      expect(result.current.emptyMessage).toBe('No replay data available for this event.');

      act(() => {
        result.current.clearReplayEntries();
      });
      expect(result.current.emptyMessage).toBeNull();
    });
  });
});
