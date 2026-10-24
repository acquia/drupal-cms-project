import { useCallback, useRef } from 'react';
import { Node, Edge } from 'reactflow';
import { TIMING } from '../constants/dimensions';
import {
  ReplayStep,
  findReplayStepForElement as findStepForElement,
  findElementForReplayStep as findElementForStep,
  isNodeExecutionStep,
  isSuccessorStep,
} from '../utils/replayStepUtils';
import {
  clearNodeHighlights,
  clearEdgeHighlights,
  applyNodeHighlight,
  applyEdgeHighlight,
} from '../utils/replayHighlightUtils';

export type { ReplayStep };

interface UseSimpleReplaySyncProps {
  replayData: ReplayStep[];
  nodes: Node[];
  edges: Edge[];
  setNodes: (nodes: Node[] | ((nodes: Node[]) => Node[])) => void;
  setEdges: (edges: Edge[] | ((edges: Edge[]) => Edge[])) => void;
  setSelectedNode: (node: Node | null) => void;
  setSelectedEdge: (edge: Edge | null) => void;
  currentStep: number;
  setCurrentStep: (step: number) => void;
}

/**
 * Simple bidirectional replay synchronization hook
 * 
 * Provides reliable synchronization between the ReactFlow canvas and replay step list.
 * Prevents the auto-replay jumping issue by using a single isSyncing flag and smart element matching.
 * 
 * Key principles:
 * 1. Manual canvas selection finds first matching replay step
 * 2. Replay step selection highlights corresponding canvas element
 * 3. Auto-replay goes sequentially - never searches back to first occurrence
 * 4. Single isSyncing flag prevents feedback loops during programmatic navigation
 */
export const useSimpleReplaySync = ({
  replayData,
  nodes,
  edges,
  setNodes,
  setEdges,
  setSelectedNode,
  setSelectedEdge,
  currentStep,
  setCurrentStep,
}: UseSimpleReplaySyncProps) => {
  // Single flag to prevent feedback loops during programmatic navigation
  const isSyncing = useRef(false);
  // Dedicated flag for replay-to-canvas direction only (selectCanvasFromReplay).
  // Used by onSelectionChange to ignore stale ReactFlow selection events
  // during programmatic edge/node updates. Canvas-to-replay sync does NOT
  // set this, so onSelectionChange still fires normally for direct clicks.
  const isReplaySyncing = useRef(false);
  const lastSyncedStep = useRef<number>(-1);
  const lastSyncedElement = useRef<string>('');
  
  // Wrapper callbacks that use the extracted pure functions with current edges/nodes.
  // `nodes` is forwarded so that clicking a condition NODE on the canvas resolves
  // to the successor step that evaluated it instead of clearing the replay
  // selection (issue #3589108).
  const findReplayStepForElement = useCallback(
    (elementId: string, elementType: 'node' | 'edge' | 'condition'): number => {
      return findStepForElement(replayData, edges, elementId, elementType, nodes);
    },
    [replayData, edges, nodes]
  );
  
  const findElementForReplayStep = useCallback(
    (stepIndex: number): { type: 'node' | 'edge'; id: string } | null => {
      return findElementForStep(replayData, nodes, edges, stepIndex);
    },
    [replayData, nodes, edges]
  );
  
  // Select element in canvas from replay step
  const selectCanvasFromReplay = useCallback((stepIndex: number) => {
    if (isSyncing.current) return;
    
    // Skip if we're already on this step
    if (stepIndex === lastSyncedStep.current) return;
    
    isSyncing.current = true;
    isReplaySyncing.current = true;
    lastSyncedStep.current = stepIndex;
    
    // Batch all updates to prevent multiple re-renders
    requestAnimationFrame(() => {
      // Clear all selections and replay styling first
      setNodes(prevNodes => clearNodeHighlights(prevNodes));
      setEdges(prevEdges => clearEdgeHighlights(prevEdges));
      setSelectedNode(null);
      setSelectedEdge(null);
      
      // Find and select the element (null for stepIndex = -1, which leaves everything cleared)
      const element = findElementForReplayStep(stepIndex);
      
      // Debug logging for troubleshooting successor steps
      if (stepIndex >= 0 && !element) {
        const step = replayData[stepIndex];
        if (step && isSuccessorStep(step)) {
          console.warn('Could not find element for replay step:', {
            stepIndex,
            stepType: step.type,
            stepId: step.id,
            successorId: step.successorId,
            conditionId: step.conditionId,
            availableEdges: edges.length
          });
        }
      }
      
      if (element) {
        if (element.type === 'node') {
          const step = replayData[stepIndex];
          const isDanger = step.type === 'access denied' || step.type === 'exception';
          
          setNodes(prevNodes => applyNodeHighlight(
            prevNodes.map(n => ({ ...n, selected: n.id === element.id })),
            element.id,
            isDanger
          ));
          const node = nodes.find(n => n.id === element.id);
          if (node) {
            setSelectedNode(node);
          }
        } else if (element.type === 'edge') {
          const step = replayData[stepIndex];
          const isAdd = step.type === 'add successor';
          
          setEdges(prevEdges => applyEdgeHighlight(
            prevEdges.map(e => ({ ...e, selected: e.id === element.id })),
            element.id,
            isAdd
          ));
          
          const edge = edges.find(e => e.id === element.id);
          if (edge) {
            setSelectedEdge(edge);
          }
        }
      } else if (stepIndex >= 0) {
        // If we couldn't find an element for a valid step, check if it's an important step type
        const step = replayData[stepIndex];
        if (step && isSuccessorStep(step)) {
          console.warn('Refusing to update current step because element was not found for important step type:', step.type);
          setTimeout(() => {
            isSyncing.current = false;
            isReplaySyncing.current = false;
          }, TIMING.CLEANUP_DELAY);
          return;
        }
      }
      
      // Update current step only if we found an element OR if this is a clear operation (stepIndex = -1)
      setCurrentStep(stepIndex);
      
      // Clear syncing flags after a longer delay to cover edge selection events
      setTimeout(() => {
        isSyncing.current = false;
        isReplaySyncing.current = false;
      }, TIMING.CLEANUP_DELAY);
    });
  }, [findElementForReplayStep, setNodes, setEdges, setSelectedNode, setSelectedEdge, setCurrentStep, nodes, edges, replayData]);
  
  /**
   * Select replay step from canvas element
   * 
   * Handles canvas-to-replay synchronization. Prevents auto-replay jumping
   * by checking if we're already on the correct step before searching.
   * The isSyncing flag ensures this function is skipped during auto-replay navigation.
   */
  const selectReplayFromCanvas = useCallback((elementId: string, elementType: 'node' | 'edge') => {
    if (isSyncing.current) return;
    
    // Check if current step already matches this element - if so, don't search
    if (currentStep >= 0 && currentStep < replayData.length) {
      const currentStepData = replayData[currentStep];
      
      // For nodes, check if current step already has this node
      if (elementType === 'node' && currentStepData.id === elementId && isNodeExecutionStep(currentStepData)) {
        return; // Already on the right step
      }
      
      // For edges with conditions
      if (elementType === 'edge') {
        const edge = edges.find(e => e.id === elementId);
        if (edge?.data?.condition && currentStepData.conditionId === edge.data.condition) {
          return; // Already on the right step
        }
      }
    }
    
    isSyncing.current = true;
    
    // If it's an edge with a condition, check for condition first
    if (elementType === 'edge') {
      const edge = edges.find(e => e.id === elementId);
      if (edge?.data?.condition) {
        const stepIndex = findReplayStepForElement(edge.data.condition, 'condition');
        if (stepIndex !== -1) {
          setCurrentStep(stepIndex);
          lastSyncedElement.current = elementId;
          setTimeout(() => { 
            isSyncing.current = false;
            lastSyncedElement.current = ''; 
          }, TIMING.REPLAY_SYNC_DELAY);
          return;
        }
      }
    }
    
    // Find the first matching step
    const stepIndex = findReplayStepForElement(elementId, elementType);
    if (stepIndex !== -1) {
      setCurrentStep(stepIndex);
      lastSyncedElement.current = elementId;
    } else {
      // No match found, clear replay selection
      setCurrentStep(-1);
    }
    
    setTimeout(() => {
      isSyncing.current = false;
      lastSyncedElement.current = ''; 
    }, TIMING.REPLAY_SYNC_DELAY);
  }, [findReplayStepForElement, setCurrentStep, edges, currentStep, replayData]);
  
  // Handle canvas node click
  const handleCanvasNodeClick = useCallback((node: Node) => {
    if (!isSyncing.current) {
      selectReplayFromCanvas(node.id, 'node');
    }
  }, [selectReplayFromCanvas]);
  
  // Handle canvas edge click
  const handleCanvasEdgeClick = useCallback((edge: Edge) => {
    if (!isSyncing.current) {
      selectReplayFromCanvas(edge.id, 'edge');
    }
  }, [selectReplayFromCanvas]);
  
  // Handle replay step selection
  const handleReplayStepSelect = useCallback((stepIndex: number) => {
    if (!isSyncing.current) {
      selectCanvasFromReplay(stepIndex);
    }
  }, [selectCanvasFromReplay]);
  
  return {
    handleCanvasNodeClick,
    handleCanvasEdgeClick,
    handleReplayStepSelect,
    isSyncing: isSyncing.current,
    isSyncingRef: isSyncing,
    isReplaySyncingRef: isReplaySyncing,
  };
};
