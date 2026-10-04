/**
 * Tests for useDebouncedField hook
 */

import { renderHook, act } from '@testing-library/react';
import { useDebouncedField } from '../useDebouncedField';

// Mock timers
jest.useFakeTimers();

describe('useDebouncedField', () => {
  const defaultProps = {
    initialValue: 'initial',
    onDebouncedChange: jest.fn(),
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  afterEach(() => {
    jest.clearAllTimers();
  });

  describe('initialization', () => {
    it('should initialize with the provided initial value', () => {
      const { result } = renderHook(() => useDebouncedField(defaultProps));
      
      expect(result.current.value).toBe('initial');
    });

    it('should initialize with empty string if initial value is empty', () => {
      const { result } = renderHook(() => useDebouncedField({
        ...defaultProps,
        initialValue: '',
      }));
      
      expect(result.current.value).toBe('');
    });
  });

  describe('value synchronization', () => {
    // The contract, in short: an incoming initialValue wins unless the user
    // has local edits that are not saved yet, in which case the local value
    // wins until the pending debounce settles. See issue #3589113.

    it('should sync local value when initialValue prop changes and no edit is pending', () => {
      const { result, rerender } = renderHook(
        ({ initialValue }) => useDebouncedField({ ...defaultProps, initialValue }),
        { initialProps: { initialValue: 'initial' } }
      );
      
      expect(result.current.value).toBe('initial');
      
      rerender({ initialValue: 'new value' });
      
      expect(result.current.value).toBe('new value');
    });

    it('should keep in-flight typing when initialValue changes and reverts', () => {
      // Issue #3589113. A store write that changes the source value and then
      // restores it registers as two dependency changes. The second one used
      // to overwrite whatever the user had typed since, so the field did not
      // need to end up wrong for the edit to be destroyed.
      const onDebouncedChange = jest.fn();
      const { result, rerender } = renderHook(
        ({ initialValue }) => useDebouncedField({
          ...defaultProps,
          initialValue,
          onDebouncedChange,
        }),
        { initialProps: { initialValue: 'committed' } }
      );

      // The user types. This edit is not saved yet - its debounce is pending.
      act(() => {
        result.current.onChange({ target: { value: 'committed extra' } } as React.ChangeEvent<HTMLInputElement>);
      });
      expect(result.current.value).toBe('committed extra');

      // A there-and-back-again write to the source value.
      rerender({ initialValue: 'stale' });
      rerender({ initialValue: 'committed' });

      // The round trip must not have touched the local value.
      expect(result.current.value).toBe('committed extra');

      // And the pending debounce still commits what the user typed.
      act(() => {
        jest.advanceTimersByTime(300);
      });
      expect(onDebouncedChange).toHaveBeenCalledTimes(1);
      expect(onDebouncedChange).toHaveBeenCalledWith('committed extra');
    });

    it('should suppress a one-way sync while a debounce is pending', () => {
      // While an edit is not saved yet the local value must win over the
      // incoming prop, so the sync is skipped whatever the incoming value is -
      // not only on a there-and-back-again.
      const { result, rerender } = renderHook(
        ({ initialValue }) => useDebouncedField({ ...defaultProps, initialValue }),
        { initialProps: { initialValue: 'committed' } }
      );

      act(() => {
        result.current.onChange({ target: { value: 'typed' } } as React.ChangeEvent<HTMLInputElement>);
      });

      rerender({ initialValue: 'from elsewhere' });

      expect(result.current.value).toBe('typed');
    });

    it('should resume syncing once the pending debounce has settled', () => {
      // The guard covers a window only; it never sticks. Once the edit is
      // saved the field must accept external updates again, otherwise
      // undo/redo would stop updating the input for the rest of its lifetime.
      const { result, rerender } = renderHook(
        ({ initialValue }) => useDebouncedField({ ...defaultProps, initialValue }),
        { initialProps: { initialValue: 'committed' } }
      );

      act(() => {
        result.current.onChange({ target: { value: 'typed' } } as React.ChangeEvent<HTMLInputElement>);
      });
      act(() => {
        jest.advanceTimersByTime(300);
      });

      rerender({ initialValue: 'from elsewhere' });

      expect(result.current.value).toBe('from elsewhere');
    });

    it('should resume syncing once flush has cleared a pending debounce', () => {
      const { result, rerender } = renderHook(
        ({ initialValue }) => useDebouncedField({ ...defaultProps, initialValue }),
        { initialProps: { initialValue: 'committed' } }
      );

      act(() => {
        result.current.onChange({ target: { value: 'typed' } } as React.ChangeEvent<HTMLInputElement>);
      });
      act(() => {
        result.current.flush();
      });

      rerender({ initialValue: 'from elsewhere' });

      expect(result.current.value).toBe('from elsewhere');
    });

    it('should still sync while disabled, where no debounce can be pending', () => {
      // A locked field never starts a timer, so it must never suppress.
      const { result, rerender } = renderHook(
        ({ initialValue }) => useDebouncedField({
          ...defaultProps,
          initialValue,
          disabled: true,
        }),
        { initialProps: { initialValue: 'committed' } }
      );

      act(() => {
        result.current.onChange({ target: { value: 'typed' } } as React.ChangeEvent<HTMLInputElement>);
      });

      rerender({ initialValue: 'from elsewhere' });

      expect(result.current.value).toBe('from elsewhere');
    });

    it('should allow direct setValue calls', () => {
      const { result } = renderHook(() => useDebouncedField(defaultProps));
      
      act(() => {
        result.current.setValue('direct value');
      });
      
      expect(result.current.value).toBe('direct value');
    });
  });

  describe('handler identity across a component switch', () => {
    // Issue #3589115. onDebouncedChange is bound to the component being
    // edited - PropertyPanel rebuilds it whenever the selection changes. So
    // every exit path has to commit a pending edit through the handler that
    // was current when the user TYPED, not through whatever is current when
    // the commit happens. Getting this wrong writes the typed text onto
    // whichever component happens to be selected by then, and leaves the
    // edited one unchanged.
    //
    // The timer path was always correct, because the setTimeout closure in
    // onChange captures the handler from the keystroke's render. flush() and
    // the unmount cleanup were not.

    const typeThenSwitch = () => {
      /** Belongs to the component the user actually edited. */
      const editedHandler = jest.fn();
      /** Belongs to the component selected while the edit was still pending. */
      const nextHandler = jest.fn();

      const view = renderHook(
        ({ onDebouncedChange, initialValue }) =>
          useDebouncedField({ initialValue, onDebouncedChange }),
        { initialProps: { onDebouncedChange: editedHandler, initialValue: 'Alpha' } }
      );

      act(() => {
        view.result.current.onChange({ target: { value: 'Alpha EDITED' } } as React.ChangeEvent<HTMLInputElement>);
      });

      // The selection switches while the edit is still pending.
      view.rerender({ onDebouncedChange: nextHandler, initialValue: 'Beta' });

      return { editedHandler, nextHandler, ...view };
    };

    it('should commit through the edited handler when the timer fires', () => {
      const { editedHandler, nextHandler } = typeThenSwitch();

      act(() => {
        jest.advanceTimersByTime(300);
      });

      expect(editedHandler).toHaveBeenCalledWith('Alpha EDITED');
      expect(nextHandler).not.toHaveBeenCalled();
    });

    it('should commit through the edited handler on flush', () => {
      const { editedHandler, nextHandler, result } = typeThenSwitch();

      act(() => {
        result.current.flush();
      });

      expect(editedHandler).toHaveBeenCalledWith('Alpha EDITED');
      expect(nextHandler).not.toHaveBeenCalled();
    });

    it('should commit through the edited handler on unmount', () => {
      const { editedHandler, nextHandler, unmount } = typeThenSwitch();

      unmount();

      expect(editedHandler).toHaveBeenCalledWith('Alpha EDITED');
      expect(nextHandler).not.toHaveBeenCalled();
    });

    it('should not commit twice when flush is followed by the timer', () => {
      const { editedHandler, nextHandler, result } = typeThenSwitch();

      act(() => {
        result.current.flush();
      });
      act(() => {
        jest.advanceTimersByTime(300);
      });

      expect(editedHandler).toHaveBeenCalledTimes(1);
      expect(nextHandler).not.toHaveBeenCalled();
    });
  });

  describe('onChange handling', () => {
    it('should update local value immediately on change', () => {
      const { result } = renderHook(() => useDebouncedField(defaultProps));
      
      act(() => {
        result.current.onChange({ target: { value: 'new value' } } as React.ChangeEvent<HTMLInputElement>);
      });
      
      expect(result.current.value).toBe('new value');
    });

    it('should call onDebouncedChange after debounce delay', () => {
      const onDebouncedChange = jest.fn();
      const { result } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
      }));
      
      act(() => {
        result.current.onChange({ target: { value: 'new value' } } as React.ChangeEvent<HTMLInputElement>);
      });
      
      // Should not be called immediately
      expect(onDebouncedChange).not.toHaveBeenCalled();
      
      // Fast-forward timers
      act(() => {
        jest.advanceTimersByTime(300);
      });
      
      expect(onDebouncedChange).toHaveBeenCalledWith('new value');
    });

    it('should debounce multiple rapid changes', () => {
      const onDebouncedChange = jest.fn();
      const { result } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
      }));
      
      act(() => {
        result.current.onChange({ target: { value: 'a' } } as React.ChangeEvent<HTMLInputElement>);
      });
      act(() => {
        jest.advanceTimersByTime(100);
      });
      act(() => {
        result.current.onChange({ target: { value: 'ab' } } as React.ChangeEvent<HTMLInputElement>);
      });
      act(() => {
        jest.advanceTimersByTime(100);
      });
      act(() => {
        result.current.onChange({ target: { value: 'abc' } } as React.ChangeEvent<HTMLInputElement>);
      });
      
      // Should not have been called yet
      expect(onDebouncedChange).not.toHaveBeenCalled();
      
      // Fast-forward past debounce delay
      act(() => {
        jest.advanceTimersByTime(300);
      });
      
      // Should only be called once with final value
      expect(onDebouncedChange).toHaveBeenCalledTimes(1);
      expect(onDebouncedChange).toHaveBeenCalledWith('abc');
    });

    it('should not call onDebouncedChange when disabled', () => {
      const onDebouncedChange = jest.fn();
      const { result } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
        disabled: true,
      }));
      
      act(() => {
        result.current.onChange({ target: { value: 'new value' } } as React.ChangeEvent<HTMLInputElement>);
      });
      
      // Fast-forward timers
      act(() => {
        jest.advanceTimersByTime(300);
      });
      
      expect(onDebouncedChange).not.toHaveBeenCalled();
      // But local value should still update
      expect(result.current.value).toBe('new value');
    });
  });

  describe('onBlur handling', () => {
    it('should call onDebouncedChange immediately on blur', () => {
      const onDebouncedChange = jest.fn();
      const { result } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
      }));
      
      act(() => {
        result.current.onBlur({ target: { value: 'blur value' } } as React.FocusEvent<HTMLInputElement>);
      });
      
      expect(onDebouncedChange).toHaveBeenCalledWith('blur value');
    });

    it('should cancel pending debounce on blur and use blur value', () => {
      const onDebouncedChange = jest.fn();
      const { result } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
      }));
      
      // Start typing
      act(() => {
        result.current.onChange({ target: { value: 'typing' } } as React.ChangeEvent<HTMLInputElement>);
      });
      
      // Should not be called yet
      expect(onDebouncedChange).not.toHaveBeenCalled();
      
      // Blur before debounce completes
      act(() => {
        result.current.onBlur({ target: { value: 'final value' } } as React.FocusEvent<HTMLInputElement>);
      });
      
      // Should be called with blur value
      expect(onDebouncedChange).toHaveBeenCalledTimes(1);
      expect(onDebouncedChange).toHaveBeenCalledWith('final value');
      
      // Fast-forward - should not call again
      act(() => {
        jest.advanceTimersByTime(300);
      });
      
      expect(onDebouncedChange).toHaveBeenCalledTimes(1);
    });

    it('should not call onDebouncedChange on blur when disabled', () => {
      const onDebouncedChange = jest.fn();
      const { result } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
        disabled: true,
      }));
      
      act(() => {
        result.current.onBlur({ target: { value: 'blur value' } } as React.FocusEvent<HTMLInputElement>);
      });
      
      expect(onDebouncedChange).not.toHaveBeenCalled();
    });
  });

  describe('custom debounce delay', () => {
    it('should respect custom debounce delay', () => {
      const onDebouncedChange = jest.fn();
      const { result } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
        debounceDelay: 500,
      }));
      
      act(() => {
        result.current.onChange({ target: { value: 'new value' } } as React.ChangeEvent<HTMLInputElement>);
      });
      
      // Should not be called at 300ms
      act(() => {
        jest.advanceTimersByTime(300);
      });
      expect(onDebouncedChange).not.toHaveBeenCalled();
      
      // Should be called at 500ms
      act(() => {
        jest.advanceTimersByTime(200);
      });
      expect(onDebouncedChange).toHaveBeenCalledWith('new value');
    });
  });

  describe('cleanup', () => {
    it('should flush pending value on unmount', () => {
      const onDebouncedChange = jest.fn();
      const { result, unmount } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
      }));
      
      act(() => {
        result.current.onChange({ target: { value: 'new value' } } as React.ChangeEvent<HTMLInputElement>);
      });
      
      // Unmount before debounce completes - should flush immediately
      unmount();
      
      expect(onDebouncedChange).toHaveBeenCalledTimes(1);
      expect(onDebouncedChange).toHaveBeenCalledWith('new value');
    });

    it('should not flush on unmount when disabled', () => {
      const onDebouncedChange = jest.fn();
      const { result, unmount } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
        disabled: true,
      }));
      
      act(() => {
        result.current.onChange({ target: { value: 'new value' } } as React.ChangeEvent<HTMLInputElement>);
      });
      
      unmount();
      
      expect(onDebouncedChange).not.toHaveBeenCalled();
    });

    it('should not flush on unmount when no pending timer', () => {
      const onDebouncedChange = jest.fn();
      const { unmount } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
      }));
      
      // Unmount without any changes
      unmount();
      
      expect(onDebouncedChange).not.toHaveBeenCalled();
    });
  });

  describe('flush', () => {
    it('should flush pending debounced value immediately', () => {
      const onDebouncedChange = jest.fn();
      const { result } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
      }));
      
      act(() => {
        result.current.onChange({ target: { value: 'pending value' } } as React.ChangeEvent<HTMLInputElement>);
      });
      
      // Flush before debounce completes
      act(() => {
        result.current.flush();
      });
      
      expect(onDebouncedChange).toHaveBeenCalledTimes(1);
      expect(onDebouncedChange).toHaveBeenCalledWith('pending value');
      
      // After flush, debounce should not fire again
      act(() => {
        jest.advanceTimersByTime(300);
      });
      
      expect(onDebouncedChange).toHaveBeenCalledTimes(1);
    });

    it('should do nothing when no pending timer', () => {
      const onDebouncedChange = jest.fn();
      const { result } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
      }));
      
      act(() => {
        result.current.flush();
      });
      
      expect(onDebouncedChange).not.toHaveBeenCalled();
    });

    it('should not flush when disabled', () => {
      const onDebouncedChange = jest.fn();
      const { result } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
        disabled: true,
      }));
      
      act(() => {
        result.current.onChange({ target: { value: 'new value' } } as React.ChangeEvent<HTMLInputElement>);
      });
      
      act(() => {
        result.current.flush();
      });
      
      expect(onDebouncedChange).not.toHaveBeenCalled();
    });
  });
});
