/**
 * Tests for useHistoryStore — undo/redo state management
 */

import { useHistoryStore } from '../useHistoryStore';
import type { StoreNode, StoreEdge } from '../../types/settings';

const makeNode = (id: string): StoreNode =>
  ({
    id,
    position: { x: 0, y: 0 },
    data: { label: id },
    type: 'default',
  }) as StoreNode;

const makeEdge = (id: string, source: string, target: string): StoreEdge =>
  ({
    id,
    source,
    target,
    type: 'default',
  }) as StoreEdge;

describe('useHistoryStore', () => {
  beforeEach(() => {
    useHistoryStore.setState({ past: [], future: [], maxHistorySize: 50 });
  });

  describe('initial state', () => {
    it('should start with empty past and future stacks', () => {
      const { past, future } = useHistoryStore.getState();
      expect(past).toEqual([]);
      expect(future).toEqual([]);
    });

    it('should have maxHistorySize of 50', () => {
      expect(useHistoryStore.getState().maxHistorySize).toBe(50);
    });
  });

  describe('pushHistory', () => {
    it('should add a new state to the past stack', () => {
      const nodes = [makeNode('n1')];
      const edges = [makeEdge('e1', 'n1', 'n2')];
      
      useHistoryStore.getState().pushHistory({ nodes, edges });
      
      const { past, future } = useHistoryStore.getState();
      expect(past).toHaveLength(1);
      expect(past[0].nodes).toEqual(nodes);
      expect(past[0].edges).toEqual(edges);
      expect(future).toEqual([]);
    });

    it('should clear future stack when new history is pushed', () => {
      useHistoryStore.setState({
        past: [{ nodes: [makeNode('n1')], edges: [] }],
        future: [{ nodes: [makeNode('n2')], edges: [] }],
      });
      
      useHistoryStore.getState().pushHistory({ nodes: [makeNode('n3')], edges: [] });
      
      const { past, future } = useHistoryStore.getState();
      expect(future).toEqual([]);
      expect(past).toHaveLength(2);
    });

    it('should respect maxHistorySize and remove oldest entries', () => {
      useHistoryStore.setState({ maxHistorySize: 3 });
      
      for (let i = 1; i <= 5; i++) {
        useHistoryStore.getState().pushHistory({ nodes: [makeNode(`n${i}`)], edges: [] });
      }
      
      const { past } = useHistoryStore.getState();
      expect(past).toHaveLength(3);
      expect(past[0].nodes[0].id).toBe('n3');
    });
  });

  describe('undo', () => {
    it('should return null when past stack is empty', () => {
      const result = useHistoryStore.getState().undo({ nodes: [], edges: [] });
      expect(result).toBeNull();
    });

    it('should return previous state and move it to future', () => {
      const previousState = { nodes: [makeNode('n1')], edges: [] };
      const currentState = { nodes: [makeNode('n2')], edges: [] };
      
      useHistoryStore.setState({ past: [previousState] });
      
      const result = useHistoryStore.getState().undo(currentState);
      
      expect(result).toEqual(previousState);
      
      const { past, future } = useHistoryStore.getState();
      expect(past).toHaveLength(0);
      expect(future).toHaveLength(1);
      expect(future[0].nodes).toEqual(currentState.nodes);
    });
  });

  describe('redo', () => {
    it('should return null when future stack is empty', () => {
      const result = useHistoryStore.getState().redo({ nodes: [], edges: [] });
      expect(result).toBeNull();
    });

    it('should return next state and move it to past', () => {
      const currentState = { nodes: [makeNode('n1')], edges: [] };
      const nextState = { nodes: [makeNode('n2')], edges: [] };
      
      useHistoryStore.setState({ future: [nextState] });
      
      const result = useHistoryStore.getState().redo(currentState);
      
      expect(result).toEqual(nextState);
      
      const { past, future } = useHistoryStore.getState();
      expect(future).toHaveLength(0);
      expect(past).toHaveLength(1);
      expect(past[0].nodes).toEqual(currentState.nodes);
    });
  });

  describe('canUndo', () => {
    it('should return false when past stack is empty', () => {
      expect(useHistoryStore.getState().canUndo()).toBe(false);
    });

    it('should return true when past stack has entries', () => {
      useHistoryStore.setState({ past: [{ nodes: [], edges: [] }] });
      expect(useHistoryStore.getState().canUndo()).toBe(true);
    });
  });

  describe('canRedo', () => {
    it('should return false when future stack is empty', () => {
      expect(useHistoryStore.getState().canRedo()).toBe(false);
    });

    it('should return true when future stack has entries', () => {
      useHistoryStore.setState({ future: [{ nodes: [], edges: [] }] });
      expect(useHistoryStore.getState().canRedo()).toBe(true);
    });
  });

  describe('clearHistory', () => {
    it('should clear both past and future stacks', () => {
      useHistoryStore.setState({
        past: [{ nodes: [makeNode('n1')], edges: [] }],
        future: [{ nodes: [makeNode('n2')], edges: [] }],
      });
      
      useHistoryStore.getState().clearHistory();
      
      const { past, future } = useHistoryStore.getState();
      expect(past).toEqual([]);
      expect(future).toEqual([]);
    });
  });
});
