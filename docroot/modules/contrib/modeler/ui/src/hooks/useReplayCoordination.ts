import { useCallback, useRef, useMemo, useEffect } from 'react';
import { Node, Edge } from 'reactflow';
import { ReplayStep, findMatchingReplayStepForSelection } from '../utils/replayStepUtils';
import { TIMING } from '../constants/dimensions';

interface UseReplayCoordinationProps {
  replayData: ReplayStep[];
  nodes: Node[];
  edges: Edge[];
  isReplayMode: boolean;
  currentReplayStep: number;
  setIsReplayMode: (mode: boolean) => void;
  setCurrentReplayStep: (step: number) => void;
  selectedNode: Node | null;
  selectedEdge: Edge | null;
  isSyncing: boolean;
  handleReplayStepSelect: (step: number) => void;
}

export const useReplayCoordination = ({
  replayData,
  nodes: _nodes,
  edges,
  isReplayMode,
  currentReplayStep,
  setIsReplayMode,
  setCurrentReplayStep,
  selectedNode,
  selectedEdge,
  isSyncing,
  handleReplayStepSelect,
}: UseReplayCoordinationProps) => {
  const syncTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Find matching replay step for canvas selection (delegates to shared utility)
  const findMatchingReplayStep = useCallback((node: Node | null, edge: Edge | null): number => {
    return findMatchingReplayStepForSelection(replayData, edges, node, edge);
  }, [replayData, edges]);

  // Debounced sync function to prevent excessive calls
  const syncNodeToReplayDebounced = useCallback((node: Node | null) => {
    if (syncTimeoutRef.current) {
      clearTimeout(syncTimeoutRef.current);
    }
    
    syncTimeoutRef.current = setTimeout(() => {
      const stepIndex = findMatchingReplayStep(node, null);
      if (stepIndex !== -1) {
        setCurrentReplayStep(stepIndex);
      }
    }, TIMING.REPLAY_SYNC_DELAY);
  }, [findMatchingReplayStep, setCurrentReplayStep]);

  // Clear any pending debounced sync timeout on unmount.
  useEffect(() => {
    return () => {
      if (syncTimeoutRef.current) {
        clearTimeout(syncTimeoutRef.current);
        syncTimeoutRef.current = null;
      }
    };
  }, []);

  // Toggle replay mode
  const toggleReplayMode = useCallback(() => {
    if (isReplayMode) {
      // Exit replay mode
      setIsReplayMode(false);
      setCurrentReplayStep(-1);
      handleReplayStepSelect(-1);
    } else {
      // Enter replay mode - try to find matching step for current selection
      const stepIndex = findMatchingReplayStep(selectedNode, selectedEdge);
      setIsReplayMode(true);
      if (stepIndex !== -1) {
        setCurrentReplayStep(stepIndex);
        handleReplayStepSelect(stepIndex);
      }
    }
  }, [isReplayMode, setIsReplayMode, setCurrentReplayStep, handleReplayStepSelect, 
      findMatchingReplayStep, selectedNode, selectedEdge]);

  // Check if replay data is available
  const hasReplayData = useMemo(() => {
    return replayData && replayData.length > 0;
  }, [replayData]);

  // Auto-sync canvas selection to replay when not in active replay mode
  const autoSyncToReplay = useCallback((node: Node | null) => {
    if (!isSyncing && hasReplayData && (!isReplayMode || currentReplayStep === -1)) {
      syncNodeToReplayDebounced(node);
      if (!isReplayMode) {
        setIsReplayMode(true);
      }
    }
  }, [isSyncing, hasReplayData, isReplayMode, currentReplayStep, syncNodeToReplayDebounced, setIsReplayMode]);

  return {
    toggleReplayMode,
    hasReplayData,
    autoSyncToReplay,
  };
};
