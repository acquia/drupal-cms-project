/**
 * useStatusAnnouncer - Manages an aria-live region for screen reader announcements.
 *
 * Provides an `announce` function that sets a status message into a hidden
 * aria-live="polite" region.  Screen readers will read the message aloud
 * without interrupting the user's current task.
 *
 * The message auto-clears after a configurable delay to avoid stale
 * announcements on subsequent reads.
 */

import { useState, useCallback, useRef, useEffect } from 'react';

interface UseStatusAnnouncerReturn {
  /** The current announcement message (empty string = nothing to announce) */
  message: string;
  /** Set a new announcement.  Pass '' to clear. */
  announce: (text: string) => void;
}

const CLEAR_DELAY = 5000; // ms before auto-clearing the message

export function useStatusAnnouncer(): UseStatusAnnouncerReturn {
  const [message, setMessage] = useState('');
  const clearTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const announce = useCallback((text: string) => {
    // Clear any pending auto-clear timer
    if (clearTimerRef.current) {
      clearTimeout(clearTimerRef.current);
      clearTimerRef.current = null;
    }

    // If same message, briefly clear then re-set so screen readers re-announce
    setMessage('');
    requestAnimationFrame(() => {
      setMessage(text);

      // Auto-clear after delay
      if (text) {
        clearTimerRef.current = setTimeout(() => {
          setMessage('');
          clearTimerRef.current = null;
        }, CLEAR_DELAY);
      }
    });
  }, []);

  // Clear any pending auto-clear timer on unmount.
  useEffect(() => {
    return () => {
      if (clearTimerRef.current) {
        clearTimeout(clearTimerRef.current);
        clearTimerRef.current = null;
      }
    };
  }, []);

  return { message, announce };
}
