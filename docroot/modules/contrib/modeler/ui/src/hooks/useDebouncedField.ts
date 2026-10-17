/**
 * useDebouncedField - Hook for managing debounced input field updates
 * 
 * Provides local state management with debounced callbacks for expensive operations
 * like API calls or state updates. Handles cleanup on unmount, ensures final
 * values are saved on blur, and flushes pending changes when the component
 * unmounts or when the caller explicitly requests it.
 */

import { useState, useCallback, useEffect, useRef } from 'react';
import { TIMING } from '../constants/dimensions';

interface UseDebouncedFieldProps {
  /** Initial value for the field */
  initialValue: string;
  /** Callback to invoke with the debounced value */
  onDebouncedChange: (value: string) => void;
  /** Whether the field is disabled */
  disabled?: boolean;
  /** Debounce delay in milliseconds */
  debounceDelay?: number;
}

interface UseDebouncedFieldReturn {
  /** Current local value */
  value: string;
  /** Set the local value directly (for resetting from external source) */
  setValue: (value: string) => void;
  /** onChange handler for input elements */
  onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => void;
  /** onBlur handler to ensure final value is saved */
  onBlur: (e: React.FocusEvent<HTMLInputElement | HTMLTextAreaElement>) => void;
  /** Flush any pending debounced change immediately */
  flush: () => void;
}

export function useDebouncedField({
  initialValue,
  onDebouncedChange,
  disabled = false,
  debounceDelay = TIMING.DEBOUNCE_DELAY,
}: UseDebouncedFieldProps): UseDebouncedFieldReturn {
  const [value, setValue] = useState(initialValue);
  const debounceTimer = useRef<number | null>(null);

  // Keep refs to the latest value and disabled flag so commitPending() below
  // stays free of dependencies and still reads current values.
  const valueRef = useRef(value);
  valueRef.current = value;
  const disabledRef = useRef(disabled);
  disabledRef.current = disabled;

  // The onDebouncedChange that was current when the pending timer was armed.
  //
  // onDebouncedChange is bound to the component being edited - PropertyPanel
  // rebuilds it whenever the selection changes - so a pending edit has to be
  // committed through the handler from the render where the user typed, not
  // through whatever is current when the commit happens. The timer path gets
  // this right for free, because the setTimeout closure below captures that
  // handler. flush() and the unmount cleanup used the current one, so a commit
  // triggered after the selection had already moved would write the typed text
  // onto the NEWLY selected component and leave the edited one unchanged
  // (issue #3589115).
  const pendingChangeRef = useRef<((value: string) => void) | null>(null);

  // Sync the local value when the source value changes.
  //
  // This effect cannot simply be removed, even though the consumer usually
  // resets the field itself when the selection changes. Undo, redo and model
  // reload replace the node behind an unchanged selection, and the consumer's
  // reset effects are keyed on the selected element's id, which those paths do
  // not change - so this is the only thing that can refresh the input for
  // them. See useDebouncedFieldUndoSync.test.tsx, which fails if this is
  // deleted.
  //
  // The sync is skipped while a debounce is pending, because in that window
  // the user has keystrokes that are not saved yet, and the local value must
  // win over the incoming one. Without the guard, any store write that
  // changes the source value and then restores it discards in-flight typing:
  // the round trip is two dependency changes, and the second setValue
  // overwrites whatever was typed since (issue #3589113).
  //
  // The guard covers a window only; it never sticks. A pending debounce always
  // ends by writing the local value to the source, so the two converge and
  // later changes sync normally.
  useEffect(() => {
    if (debounceTimer.current !== null) {
      return;
    }
    setValue(initialValue);
  }, [initialValue]);

  // Commit whatever edit is pending, through the handler the edit belongs to.
  //
  // Deliberately shared by flush() and the unmount cleanup rather than written
  // out twice: letting those two exits differ is exactly what issue #3589115
  // was. Stable (no dependencies) because everything it needs is held in refs.
  const commitPending = useCallback(() => {
    if (debounceTimer.current === null) {
      return;
    }
    clearTimeout(debounceTimer.current);
    debounceTimer.current = null;
    const pendingChange = pendingChangeRef.current;
    pendingChangeRef.current = null;
    if (!disabledRef.current && pendingChange) {
      pendingChange(valueRef.current);
    }
  }, []);

  // Flush pending changes and cleanup timer on unmount, so no edits are lost.
  useEffect(() => {
    return () => {
      commitPending();
    };
  }, [commitPending]);

  const flush = commitPending;

  const onChange = useCallback((e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const newValue = e.target.value;
    setValue(newValue);

    if (!disabled) {
      // Clear existing timer
      if (debounceTimer.current) {
        clearTimeout(debounceTimer.current);
      }

      // Remember which handler this edit belongs to, so flush() and the
      // unmount cleanup commit it exactly where the timer below would have.
      pendingChangeRef.current = onDebouncedChange;

      // Set new debounced update
      debounceTimer.current = setTimeout(() => {
        onDebouncedChange(newValue);
        debounceTimer.current = null;
        pendingChangeRef.current = null;
      }, debounceDelay) as unknown as number;
    }
  }, [disabled, debounceDelay, onDebouncedChange]);

  const onBlur = useCallback((e: React.FocusEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    // Ensure final value is saved on blur.
    //
    // This deliberately keeps using the CURRENT handler, unlike flush() and
    // the unmount cleanup. Blur fires while the edited component is still the
    // selected one - a native click blurs during mousedown, before the click
    // handler that moves the selection - so the current handler is the right
    // one here, and e.target.value is more recent than the debounced state.
    if (debounceTimer.current) {
      clearTimeout(debounceTimer.current);
      debounceTimer.current = null;
      pendingChangeRef.current = null;
    }
    if (!disabled) {
      onDebouncedChange(e.target.value);
    }
  }, [disabled, onDebouncedChange]);

  return {
    value,
    setValue,
    onChange,
    onBlur,
    flush,
  };
}
