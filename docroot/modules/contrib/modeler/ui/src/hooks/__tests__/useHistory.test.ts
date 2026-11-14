/**
 * Tests for useHistory hook — undo/redo integration with graph store
 */

import { renderHook, act } from '@testing-library/react';
import { useHistory } from '../useHistory';
import { useGraphStore } from '../../store/useGraphStore';
import { useHistoryStore } from '../../store/useHistoryStore';
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

describe('useHistory', () => {
  beforeEach(() => {
    useGraphStore.setState({ nodes: [], edges: [] });
    useHistoryStore.setState({ past: [], future: [], maxHistorySize: 50 });
  });

  describe('initial state', () => {
    it('should return canUndo false and canRedo false initially', () => {
      const { result } = renderHook(() => useHistory());
      
      expect(result.current.canUndo()).toBe(false);
      expect(result.current.canRedo()).toBe(false);
    });
  });

  describe('saveHistory', () => {
    it('should push current state to history', () => {
      useGraphStore.setState({
        nodes: [makeNode('n1')],
        edges: [makeEdge('e1', 'n1', 'n2')],
      });
      
      const { result } = renderHook(() => useHistory());
      
      act(() => {
        result.current.saveHistory();
      });
      
      expect(useHistoryStore.getState().past).toHaveLength(1);
    });

    it('should not push history when disabled', () => {
      useGraphStore.setState({ nodes: [makeNode('n1')] });
      
      const { result } = renderHook(() => useHistory({ enabled: false }));
      
      act(() => {
        result.current.saveHistory();
      });
      
      expect(useHistoryStore.getState().past).toHaveLength(0);
    });
  });

  describe('undo', () => {
    it('should restore previous state from history', () => {
      const previousNodes = [makeNode('n1')];
      const currentNodes = [makeNode('n2')];
      
      useGraphStore.setState({ nodes: currentNodes });
      useHistoryStore.setState({ past: [{ nodes: previousNodes, edges: [] }] });
      
      const { result } = renderHook(() => useHistory());
      
      act(() => {
        result.current.undo();
      });
      
      expect(useGraphStore.getState().nodes).toEqual(previousNodes);
    });

    it('should return null when nothing to undo', () => {
      const { result } = renderHook(() => useHistory());
      
      const undoResult = result.current.undo();
      
      expect(undoResult).toBeNull();
    });

    it('should return null when disabled', () => {
      const { result } = renderHook(() => useHistory({ enabled: false }));
      
      const undoResult = result.current.undo();
      
      expect(undoResult).toBeNull();
    });
  });

  describe('redo', () => {
    it('should restore next state from future', () => {
      const currentNodes = [makeNode('n1')];
      const nextNodes = [makeNode('n2')];
      
      useGraphStore.setState({ nodes: currentNodes });
      useHistoryStore.setState({ future: [{ nodes: nextNodes, edges: [] }] });
      
      const { result } = renderHook(() => useHistory());
      
      act(() => {
        result.current.redo();
      });
      
      expect(useGraphStore.getState().nodes).toEqual(nextNodes);
    });

    it('should return null when nothing to redo', () => {
      const { result } = renderHook(() => useHistory());
      
      const redoResult = result.current.redo();
      
      expect(redoResult).toBeNull();
    });

    it('should return null when disabled', () => {
      const { result } = renderHook(() => useHistory({ enabled: false }));
      
      const redoResult = result.current.redo();
      
      expect(redoResult).toBeNull();
    });
  });

  describe('canUndo/canRedo', () => {
    it('should reflect history store state', () => {
      useHistoryStore.setState({ past: [{ nodes: [], edges: [] }] });
      
      const { result } = renderHook(() => useHistory());
      
      expect(result.current.canUndo()).toBe(true);
      expect(result.current.canRedo()).toBe(false);
    });

    it('should return false when disabled regardless of history state', () => {
      useHistoryStore.setState({ past: [{ nodes: [], edges: [] }] });
      
      const { result } = renderHook(() => useHistory({ enabled: false }));
      
      expect(result.current.canUndo()).toBe(false);
    });
  });

  describe('clearHistory', () => {
    it('should clear history store', () => {
      useHistoryStore.setState({
        past: [{ nodes: [], edges: [] }],
        future: [{ nodes: [], edges: [] }],
      });
      
      const { result } = renderHook(() => useHistory());
      
      act(() => {
        result.current.clearHistory();
      });
      
      expect(useHistoryStore.getState().past).toEqual([]);
      expect(useHistoryStore.getState().future).toEqual([]);
    });
  });
});
