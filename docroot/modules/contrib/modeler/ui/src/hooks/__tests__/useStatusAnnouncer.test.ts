import { renderHook, act } from '@testing-library/react';
import { useStatusAnnouncer } from '../useStatusAnnouncer';

describe('useStatusAnnouncer', () => {
  beforeEach(() => {
    jest.useFakeTimers();

    // Mock requestAnimationFrame to execute synchronously
    jest.spyOn(window, 'requestAnimationFrame').mockImplementation(
      (cb: FrameRequestCallback) => {
        cb(0);
        return 0;
      },
    );
  });

  afterEach(() => {
    jest.useRealTimers();
    jest.restoreAllMocks();
  });

  test('should return empty message initially', () => {
    const { result } = renderHook(() => useStatusAnnouncer());
    expect(result.current.message).toBe('');
  });

  test('should return a stable announce function', () => {
    const { result, rerender } = renderHook(() => useStatusAnnouncer());
    const firstAnnounce = result.current.announce;
    rerender();
    expect(result.current.announce).toBe(firstAnnounce);
  });

  test('should set message when announce is called', () => {
    const { result } = renderHook(() => useStatusAnnouncer());

    act(() => {
      result.current.announce('Model saved');
    });

    expect(result.current.message).toBe('Model saved');
  });

  test('should auto-clear message after delay', () => {
    const { result } = renderHook(() => useStatusAnnouncer());

    act(() => {
      result.current.announce('Saved');
    });

    expect(result.current.message).toBe('Saved');

    act(() => {
      jest.advanceTimersByTime(5000);
    });

    expect(result.current.message).toBe('');
  });

  test('should not auto-clear when message is empty', () => {
    const { result } = renderHook(() => useStatusAnnouncer());

    act(() => {
      result.current.announce('');
    });

    expect(result.current.message).toBe('');

    // Advancing time should not cause errors
    act(() => {
      jest.advanceTimersByTime(10000);
    });

    expect(result.current.message).toBe('');
  });

  test('should cancel pending clear when new message arrives', () => {
    const { result } = renderHook(() => useStatusAnnouncer());

    act(() => {
      result.current.announce('First');
    });

    expect(result.current.message).toBe('First');

    // After 3 seconds, send a new message (before the 5s clear fires)
    act(() => {
      jest.advanceTimersByTime(3000);
    });

    act(() => {
      result.current.announce('Second');
    });

    expect(result.current.message).toBe('Second');

    // After 3 more seconds (6s total from First), the First's timer would
    // have fired but it was cancelled. Second is still showing.
    act(() => {
      jest.advanceTimersByTime(3000);
    });

    expect(result.current.message).toBe('Second');

    // After 5s from Second, it should auto-clear
    act(() => {
      jest.advanceTimersByTime(2000);
    });

    expect(result.current.message).toBe('');
  });

  test('should handle rapid successive announcements', () => {
    const { result } = renderHook(() => useStatusAnnouncer());

    act(() => {
      result.current.announce('A');
      result.current.announce('B');
      result.current.announce('C');
    });

    // Only the last one should be set
    expect(result.current.message).toBe('C');
  });

  test('should re-announce same message by briefly clearing', () => {
    const { result } = renderHook(() => useStatusAnnouncer());

    act(() => {
      result.current.announce('Same');
    });

    expect(result.current.message).toBe('Same');

    // Re-announce the same text — the hook clears to '' then sets back
    // Since we mock requestAnimationFrame to be synchronous, both happen in one act
    act(() => {
      result.current.announce('Same');
    });

    expect(result.current.message).toBe('Same');
  });

  test('should clear message when announce is called with empty string', () => {
    const { result } = renderHook(() => useStatusAnnouncer());

    act(() => {
      result.current.announce('Hello');
    });

    expect(result.current.message).toBe('Hello');

    act(() => {
      result.current.announce('');
    });

    expect(result.current.message).toBe('');
  });

  test('should clear the pending timer ref after auto-clear fires', () => {
    const { result } = renderHook(() => useStatusAnnouncer());

    act(() => {
      result.current.announce('Test');
    });

    expect(result.current.message).toBe('Test');

    // Let the auto-clear timer fire
    act(() => {
      jest.advanceTimersByTime(5000);
    });

    expect(result.current.message).toBe('');

    // Announce again — should work normally (timer ref was cleaned up)
    act(() => {
      result.current.announce('After clear');
    });

    expect(result.current.message).toBe('After clear');
  });

  test('should set auto-clear timer only for non-empty messages', () => {
    const setTimeoutSpy = jest.spyOn(global, 'setTimeout');

    const { result } = renderHook(() => useStatusAnnouncer());

    const callsBefore = setTimeoutSpy.mock.calls.length;

    act(() => {
      result.current.announce('');
    });

    // No new setTimeout should have been created for empty message
    const callsAfterEmpty = setTimeoutSpy.mock.calls.length;

    act(() => {
      result.current.announce('Non-empty');
    });

    const callsAfterNonEmpty = setTimeoutSpy.mock.calls.length;

    // The non-empty announce should have added a setTimeout
    expect(callsAfterNonEmpty).toBeGreaterThan(callsAfterEmpty);
    // The empty announce should NOT have added a setTimeout (or at most same count)
    expect(callsAfterEmpty).toBe(callsBefore);
  });

  test('should call clearTimeout when canceling a pending timer', () => {
    const clearTimeoutSpy = jest.spyOn(global, 'clearTimeout');

    const { result } = renderHook(() => useStatusAnnouncer());

    act(() => {
      result.current.announce('First');
    });

    const callsBefore = clearTimeoutSpy.mock.calls.length;

    // Announce again before the timer fires — should clear the old timer
    act(() => {
      result.current.announce('Second');
    });

    expect(clearTimeoutSpy.mock.calls.length).toBeGreaterThan(callsBefore);
  });

  test('should not call clearTimeout when there is no pending timer', () => {
    const clearTimeoutSpy = jest.spyOn(global, 'clearTimeout');

    const { result } = renderHook(() => useStatusAnnouncer());

    const callsBefore = clearTimeoutSpy.mock.calls.length;

    // First announcement with no pending timer
    act(() => {
      result.current.announce('First');
    });

    // clearTimeout should not have been called with a real timer ID
    // (it might be called with null, which is harmless, but
    //  the branch `if (clearTimerRef.current)` should not fire)
    expect(clearTimeoutSpy.mock.calls.length).toBe(callsBefore);
  });

  test('requestAnimationFrame callback sets the message', () => {
    // Use a real (non-synchronous) rAF mock to test the callback
    jest.restoreAllMocks();
    let rafCallback: FrameRequestCallback | null = null;
    jest.spyOn(window, 'requestAnimationFrame').mockImplementation(
      (cb: FrameRequestCallback) => {
        rafCallback = cb;
        return 1;
      },
    );

    const { result } = renderHook(() => useStatusAnnouncer());

    act(() => {
      result.current.announce('Delayed');
    });

    // Before rAF fires, message should be '' (the synchronous clear)
    expect(result.current.message).toBe('');

    // Now fire the rAF callback
    act(() => {
      if (rafCallback) rafCallback(0);
    });

    expect(result.current.message).toBe('Delayed');
  });
});
