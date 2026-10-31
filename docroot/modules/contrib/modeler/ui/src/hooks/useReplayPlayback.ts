/**
 * useReplayPlayback - Hook for managing replay playback controls
 * 
 * Handles play/pause state, playback speed, auto-play functionality,
 * and step navigation (previous/next).
 */

import { useState, useEffect, useCallback, useRef } from 'react';

interface UseReplayPlaybackProps {
  /** Total number of steps in filtered replay data */
  totalSteps: number;
  /** Current step index in filtered data */
  filteredCurrentStep: number;
  /** Callback to select a step (receives original index) */
  onSelectStep: (step: number) => void;
  /** Callback to toggle replay mode (for stop action) - optional */
  onToggleReplay?: () => void;
  /** Function to convert filtered index to original index */
  getOriginalIndex: (filteredIndex: number) => number;
  /** Ref to step elements for auto-scrolling */
  stepRefs?: React.MutableRefObject<Record<number, HTMLDivElement | null>>;
  /** Ref to steps container for scroll calculations */
  stepsContainerRef?: React.RefObject<HTMLDivElement | null>;
}

interface UseReplayPlaybackReturn {
  /** Whether playback is currently active */
  isPlaying: boolean;
  /** Current playback speed multiplier */
  playbackSpeed: number;
  /** Set playback speed */
  setPlaybackSpeed: (speed: number) => void;
  /** Toggle play/pause */
  handlePlay: () => void;
  /** Stop playback and reset */
  handleStop: () => void;
  /** Go to previous step */
  handlePrevious: () => void;
  /** Go to next step */
  handleNext: () => void;
  /** Handle clicking on a specific step */
  handleStepClick: (filteredIndex: number) => void;
}

export function useReplayPlayback({
  totalSteps,
  filteredCurrentStep,
  onSelectStep,
  onToggleReplay,
  getOriginalIndex,
  stepRefs,
  stepsContainerRef,
}: UseReplayPlaybackProps): UseReplayPlaybackReturn {
  const [isPlaying, setIsPlaying] = useState(false);
  const [playbackSpeed, setPlaybackSpeed] = useState(1);
  
  // Use ref to track latest values for auto-play effect
  const stateRef = useRef({ filteredCurrentStep, totalSteps, getOriginalIndex });
  stateRef.current = { filteredCurrentStep, totalSteps, getOriginalIndex };

  // Auto-play functionality
  useEffect(() => {
    if (!isPlaying || totalSteps === 0 || filteredCurrentStep >= totalSteps - 1) {
      return;
    }

    const timeout = setTimeout(() => {
      const { filteredCurrentStep: currentStep, getOriginalIndex: getIdx } = stateRef.current;
      const nextFilteredIndex = currentStep + 1;
      const nextOriginalIndex = getIdx(nextFilteredIndex);
      onSelectStep(nextOriginalIndex);
    }, 1000 / playbackSpeed);

    return () => clearTimeout(timeout);
  }, [isPlaying, filteredCurrentStep, totalSteps, playbackSpeed, onSelectStep]);

  // Stop playing when we reach the end
  useEffect(() => {
    if (filteredCurrentStep >= totalSteps - 1) {
      setIsPlaying(false);
    }
  }, [filteredCurrentStep, totalSteps]);

  // Auto-scroll to current step
  useEffect(() => {
    if (filteredCurrentStep >= 0 && stepRefs?.current[filteredCurrentStep]) {
      const stepElement = stepRefs.current[filteredCurrentStep];
      const container = stepsContainerRef?.current;

      if (stepElement && container) {
        // Calculate if the element is visible
        const containerRect = container.getBoundingClientRect();
        const elementRect = stepElement.getBoundingClientRect();

        // Check if element is outside visible area
        if (elementRect.top < containerRect.top || elementRect.bottom > containerRect.bottom) {
          // Scroll the element into view with smooth scrolling
          stepElement.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
          });
        }
      }
    }
  }, [filteredCurrentStep, stepRefs, stepsContainerRef]);

  const handlePlay = useCallback(() => {
    if (filteredCurrentStep >= totalSteps - 1) {
      onSelectStep(getOriginalIndex(0));
    }
    setIsPlaying(prev => !prev);
  }, [filteredCurrentStep, totalSteps, onSelectStep, getOriginalIndex]);

  const handleStop = useCallback(() => {
    setIsPlaying(false);
    onSelectStep(-1);
    onToggleReplay?.(); // Actually exit replay mode (if callback provided)
  }, [onSelectStep, onToggleReplay]);

  const handlePrevious = useCallback(() => {
    setIsPlaying(false);
    const prevFilteredIndex = Math.max(-1, filteredCurrentStep - 1);
    const prevOriginalIndex = prevFilteredIndex === -1 ? -1 : getOriginalIndex(prevFilteredIndex);
    onSelectStep(prevOriginalIndex);
  }, [filteredCurrentStep, onSelectStep, getOriginalIndex]);

  const handleNext = useCallback(() => {
    setIsPlaying(false);
    const nextFilteredIndex = Math.min(totalSteps - 1, filteredCurrentStep + 1);
    const nextOriginalIndex = getOriginalIndex(nextFilteredIndex);
    onSelectStep(nextOriginalIndex);
  }, [filteredCurrentStep, totalSteps, onSelectStep, getOriginalIndex]);

  const handleStepClick = useCallback((filteredIndex: number) => {
    setIsPlaying(false);
    const originalIndex = getOriginalIndex(filteredIndex);
    onSelectStep(originalIndex);
  }, [onSelectStep, getOriginalIndex]);

  return {
    isPlaying,
    playbackSpeed,
    setPlaybackSpeed,
    handlePlay,
    handleStop,
    handlePrevious,
    handleNext,
    handleStepClick,
  };
}
