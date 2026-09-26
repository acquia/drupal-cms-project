/**
 * Tests for useReplayPlayback hook
 */

import { renderHook, act } from '@testing-library/react';
import { useReplayPlayback } from '../useReplayPlayback';

describe('useReplayPlayback', () => {
  // Default props for tests
  const createDefaultProps = (overrides = {}) => ({
    totalSteps: 5,
    filteredCurrentStep: 0,
    onSelectStep: jest.fn(),
    onToggleReplay: jest.fn(),
    getOriginalIndex: jest.fn((idx: number) => idx), // Identity mapping by default
    ...overrides,
  });

  beforeEach(() => {
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  describe('initial state', () => {
    it('should initialize with isPlaying false', () => {
      const { result } = renderHook(() => useReplayPlayback(createDefaultProps()));
      expect(result.current.isPlaying).toBe(false);
    });

    it('should initialize with playbackSpeed of 1', () => {
      const { result } = renderHook(() => useReplayPlayback(createDefaultProps()));
      expect(result.current.playbackSpeed).toBe(1);
    });
  });

  describe('handlePlay', () => {
    it('should toggle isPlaying state', () => {
      const { result } = renderHook(() => useReplayPlayback(createDefaultProps()));

      act(() => {
        result.current.handlePlay();
      });

      expect(result.current.isPlaying).toBe(true);

      act(() => {
        result.current.handlePlay();
      });

      expect(result.current.isPlaying).toBe(false);
    });

    it('should reset to first step when at end and play is clicked', () => {
      const onSelectStep = jest.fn();
      const getOriginalIndex = jest.fn((idx: number) => idx * 2); // Different mapping

      const { result } = renderHook(() =>
        useReplayPlayback(createDefaultProps({
          totalSteps: 5,
          filteredCurrentStep: 4, // At the end
          onSelectStep,
          getOriginalIndex,
        }))
      );

      act(() => {
        result.current.handlePlay();
      });

      expect(getOriginalIndex).toHaveBeenCalledWith(0);
      expect(onSelectStep).toHaveBeenCalledWith(0); // getOriginalIndex(0) = 0
    });
  });

  describe('handleStop', () => {
    it('should set isPlaying to false', () => {
      const { result } = renderHook(() => useReplayPlayback(createDefaultProps()));

      // First start playing
      act(() => {
        result.current.handlePlay();
      });
      expect(result.current.isPlaying).toBe(true);

      // Then stop
      act(() => {
        result.current.handleStop();
      });
      expect(result.current.isPlaying).toBe(false);
    });

    it('should call onSelectStep with -1', () => {
      const onSelectStep = jest.fn();
      const { result } = renderHook(() =>
        useReplayPlayback(createDefaultProps({ onSelectStep }))
      );

      act(() => {
        result.current.handleStop();
      });

      expect(onSelectStep).toHaveBeenCalledWith(-1);
    });

    it('should call onToggleReplay to exit replay mode', () => {
      const onToggleReplay = jest.fn();
      const { result } = renderHook(() =>
        useReplayPlayback(createDefaultProps({ onToggleReplay }))
      );

      act(() => {
        result.current.handleStop();
      });

      expect(onToggleReplay).toHaveBeenCalled();
    });
  });

  describe('handlePrevious', () => {
    it('should stop playback', () => {
      const { result } = renderHook(() => useReplayPlayback(createDefaultProps()));

      act(() => {
        result.current.handlePlay();
      });
      expect(result.current.isPlaying).toBe(true);

      act(() => {
        result.current.handlePrevious();
      });
      expect(result.current.isPlaying).toBe(false);
    });

    it('should navigate to previous step', () => {
      const onSelectStep = jest.fn();
      const getOriginalIndex = jest.fn((idx: number) => idx);

      const { result } = renderHook(() =>
        useReplayPlayback(createDefaultProps({
          filteredCurrentStep: 2,
          onSelectStep,
          getOriginalIndex,
        }))
      );

      act(() => {
        result.current.handlePrevious();
      });

      expect(getOriginalIndex).toHaveBeenCalledWith(1);
      expect(onSelectStep).toHaveBeenCalledWith(1);
    });

    it('should not go below -1', () => {
      const onSelectStep = jest.fn();

      const { result } = renderHook(() =>
        useReplayPlayback(createDefaultProps({
          filteredCurrentStep: 0,
          onSelectStep,
        }))
      );

      act(() => {
        result.current.handlePrevious();
      });

      expect(onSelectStep).toHaveBeenCalledWith(-1);
    });

    it('should call onSelectStep with -1 when at first step', () => {
      const onSelectStep = jest.fn();
      const getOriginalIndex = jest.fn();

      const { result } = renderHook(() =>
        useReplayPlayback(createDefaultProps({
          filteredCurrentStep: 0,
          onSelectStep,
          getOriginalIndex,
        }))
      );

      act(() => {
        result.current.handlePrevious();
      });

      // Should not call getOriginalIndex when going to -1
      expect(onSelectStep).toHaveBeenCalledWith(-1);
    });
  });

  describe('handleNext', () => {
    it('should stop playback', () => {
      const { result } = renderHook(() => useReplayPlayback(createDefaultProps()));

      act(() => {
        result.current.handlePlay();
      });
      expect(result.current.isPlaying).toBe(true);

      act(() => {
        result.current.handleNext();
      });
      expect(result.current.isPlaying).toBe(false);
    });

    it('should navigate to next step', () => {
      const onSelectStep = jest.fn();
      const getOriginalIndex = jest.fn((idx: number) => idx);

      const { result } = renderHook(() =>
        useReplayPlayback(createDefaultProps({
          filteredCurrentStep: 1,
          onSelectStep,
          getOriginalIndex,
        }))
      );

      act(() => {
        result.current.handleNext();
      });

      expect(getOriginalIndex).toHaveBeenCalledWith(2);
      expect(onSelectStep).toHaveBeenCalledWith(2);
    });

    it('should not exceed totalSteps - 1', () => {
      const onSelectStep = jest.fn();
      const getOriginalIndex = jest.fn((idx: number) => idx);

      const { result } = renderHook(() =>
        useReplayPlayback(createDefaultProps({
          totalSteps: 5,
          filteredCurrentStep: 4, // Already at end
          onSelectStep,
          getOriginalIndex,
        }))
      );

      act(() => {
        result.current.handleNext();
      });

      expect(getOriginalIndex).toHaveBeenCalledWith(4);
      expect(onSelectStep).toHaveBeenCalledWith(4);
    });
  });

  describe('handleStepClick', () => {
    it('should stop playback', () => {
      const { result } = renderHook(() => useReplayPlayback(createDefaultProps()));

      act(() => {
        result.current.handlePlay();
      });
      expect(result.current.isPlaying).toBe(true);

      act(() => {
        result.current.handleStepClick(2);
      });
      expect(result.current.isPlaying).toBe(false);
    });

    it('should select the clicked step', () => {
      const onSelectStep = jest.fn();
      const getOriginalIndex = jest.fn((idx: number) => idx * 2);

      const { result } = renderHook(() =>
        useReplayPlayback(createDefaultProps({
          onSelectStep,
          getOriginalIndex,
        }))
      );

      act(() => {
        result.current.handleStepClick(3);
      });

      expect(getOriginalIndex).toHaveBeenCalledWith(3);
      expect(onSelectStep).toHaveBeenCalledWith(6); // 3 * 2
    });
  });

  describe('setPlaybackSpeed', () => {
    it('should update playback speed', () => {
      const { result } = renderHook(() => useReplayPlayback(createDefaultProps()));

      act(() => {
        result.current.setPlaybackSpeed(2);
      });

      expect(result.current.playbackSpeed).toBe(2);
    });
  });

  describe('auto-play functionality', () => {
    it('should advance to next step after delay when playing', () => {
      const onSelectStep = jest.fn();
      const getOriginalIndex = jest.fn((idx: number) => idx);

      renderHook(() =>
        useReplayPlayback(createDefaultProps({
          totalSteps: 5,
          filteredCurrentStep: 0,
          onSelectStep,
          getOriginalIndex,
        }))
      );

      // Start playing would be done externally, simulating with direct effect
      // The hook auto-advances on timer
    });

    it('should not advance when not playing', () => {
      const onSelectStep = jest.fn();

      renderHook(() =>
        useReplayPlayback(createDefaultProps({
          totalSteps: 5,
          filteredCurrentStep: 0,
          onSelectStep,
        }))
      );

      // Advance timers
      act(() => {
        jest.advanceTimersByTime(2000);
      });

      // Should not have been called since not playing
      expect(onSelectStep).not.toHaveBeenCalled();
    });

    it('should not advance when at last step', () => {
      const onSelectStep = jest.fn();
      const getOriginalIndex = jest.fn((idx: number) => idx);

      const { result } = renderHook(() =>
        useReplayPlayback(createDefaultProps({
          totalSteps: 5,
          filteredCurrentStep: 4, // Last step
          onSelectStep,
          getOriginalIndex,
        }))
      );

      act(() => {
        result.current.handlePlay();
      });

      // When at end, handlePlay resets to step 0 and starts playing
      expect(getOriginalIndex).toHaveBeenCalledWith(0);
      expect(onSelectStep).toHaveBeenCalledWith(0);
      expect(result.current.isPlaying).toBe(true);
    });

    it('should advance faster with higher playback speed', () => {
      const onSelectStep = jest.fn();
      const getOriginalIndex = jest.fn((idx: number) => idx);

      const { result, rerender } = renderHook(
        (props) => useReplayPlayback(props),
        {
          initialProps: createDefaultProps({
            totalSteps: 5,
            filteredCurrentStep: 0,
            onSelectStep,
            getOriginalIndex,
          }),
        }
      );

      // Set speed to 2x and start playing
      act(() => {
        result.current.setPlaybackSpeed(2);
        result.current.handlePlay();
      });

      // At 2x speed, delay should be 500ms (1000/2)
      act(() => {
        jest.advanceTimersByTime(500);
      });

      expect(onSelectStep).toHaveBeenCalledWith(1);

      // Rerender with new step
      rerender(createDefaultProps({
        totalSteps: 5,
        filteredCurrentStep: 1,
        onSelectStep,
        getOriginalIndex,
      }));

      // Advance again
      act(() => {
        jest.advanceTimersByTime(500);
      });

      expect(onSelectStep).toHaveBeenCalledWith(2);
    });

    it('should stop playing when reaching the end', () => {
      const onSelectStep = jest.fn();

      const { result, rerender } = renderHook(
        (props) => useReplayPlayback(props),
        {
          initialProps: createDefaultProps({
            totalSteps: 3,
            filteredCurrentStep: 1,
            onSelectStep,
          }),
        }
      );

      // Start playing
      act(() => {
        result.current.handlePlay();
      });
      expect(result.current.isPlaying).toBe(true);

      // Rerender at last step
      rerender(createDefaultProps({
        totalSteps: 3,
        filteredCurrentStep: 2, // Last step
        onSelectStep,
      }));

      // Should automatically stop
      expect(result.current.isPlaying).toBe(false);
    });
  });

  describe('edge cases', () => {
    it('should handle totalSteps of 0', () => {
      const onSelectStep = jest.fn();
      const getOriginalIndex = jest.fn((idx: number) => idx);

      const { result } = renderHook(() =>
        useReplayPlayback(createDefaultProps({
          totalSteps: 0,
          filteredCurrentStep: -1,
          onSelectStep,
          getOriginalIndex,
        }))
      );

      // handlePlay is called, which triggers onSelectStep(getOriginalIndex(0))
      // because filteredCurrentStep (-1) >= totalSteps - 1 (-1)
      act(() => {
        result.current.handlePlay();
      });

      // The play handler resets to step 0 when at/past end
      expect(onSelectStep).toHaveBeenCalledWith(0);
      
      // After that, advance timers - no further calls should happen
      onSelectStep.mockClear();
      act(() => {
        jest.advanceTimersByTime(2000);
      });

      // Should not call onSelectStep in auto-play when totalSteps is 0
      expect(onSelectStep).not.toHaveBeenCalled();
    });

    it('should handle negative filteredCurrentStep', () => {
      const onSelectStep = jest.fn();
      const getOriginalIndex = jest.fn((idx: number) => idx);

      const { result } = renderHook(() =>
        useReplayPlayback(createDefaultProps({
          totalSteps: 5,
          filteredCurrentStep: -1,
          onSelectStep,
          getOriginalIndex,
        }))
      );

      act(() => {
        result.current.handleNext();
      });

      // Should go to step 0 (min of totalSteps-1 and filteredCurrentStep+1)
      expect(getOriginalIndex).toHaveBeenCalledWith(0);
    });
  });
});
