/**
 * Tests for useSelectionStore — primary selection, multi-selection,
 * convenience helpers, clearSelection, and graph-change pruning.
 */

import { useSelectionStore } from '../useSelectionStore';
import { useGraphStore } from '../useGraphStore';
import type { StoreNode, StoreEdge } from '../../types/settings';

const makeNode = (id: string): StoreNode =>
  ({ id, position: { x: 0, y: 0 }, data: { label: id }, type: 'default' }) as StoreNode;

const makeEdge = (id: string, source = 'a', target = 'b'): StoreEdge =>
  ({ id, source, target, type: 'default' }) as StoreEdge;

describe('useSelectionStore', () => {
  beforeEach(() => {
    useSelectionStore.setState({
      selectedNode: null,
      selectedEdge: null,
      selectedNodes: [],
      selectedEdges: [],
    });
    useGraphStore.setState({ nodes: [], edges: [] });
  });

  describe('initial state', () => {
    it('should have no selection', () => {
      const s = useSelectionStore.getState();
      expect(s.selectedNode).toBeNull();
      expect(s.selectedEdge).toBeNull();
      expect(s.selectedNodes).toEqual([]);
      expect(s.selectedEdges).toEqual([]);
    });
  });

  describe('setSelectedNode / setSelectedEdge', () => {
    it('should set and clear a selected node', () => {
      const node = makeNode('n1');
      useSelectionStore.getState().setSelectedNode(node);
      expect(useSelectionStore.getState().selectedNode?.id).toBe('n1');

      useSelectionStore.getState().setSelectedNode(null);
      expect(useSelectionStore.getState().selectedNode).toBeNull();
    });

    it('should set and clear a selected edge', () => {
      const edge = makeEdge('e1');
      useSelectionStore.getState().setSelectedEdge(edge);
      expect(useSelectionStore.getState().selectedEdge?.id).toBe('e1');

      useSelectionStore.getState().setSelectedEdge(null);
      expect(useSelectionStore.getState().selectedEdge).toBeNull();
    });
  });

  describe('multi-selection arrays', () => {
    it('should set selectedNodes and selectedEdges', () => {
      useSelectionStore.getState().setSelectedNodes(['n1', 'n2']);
      useSelectionStore.getState().setSelectedEdges(['e1']);
      expect(useSelectionStore.getState().selectedNodes).toEqual(['n1', 'n2']);
      expect(useSelectionStore.getState().selectedEdges).toEqual(['e1']);
    });
  });

  describe('selectNode', () => {
    it('should set both selectedNode and selectedNodes, clear edge selection', () => {
      // Pre-populate with edge selection
      useSelectionStore.setState({
        selectedEdge: makeEdge('e1'),
        selectedEdges: ['e1'],
      });

      const node = makeNode('n1');
      useSelectionStore.getState().selectNode(node);

      const s = useSelectionStore.getState();
      expect(s.selectedNode?.id).toBe('n1');
      expect(s.selectedNodes).toEqual(['n1']);
      expect(s.selectedEdge).toBeNull();
      expect(s.selectedEdges).toEqual([]);
    });
  });

  describe('selectEdge', () => {
    it('should set both selectedEdge and selectedEdges, clear node selection', () => {
      // Pre-populate with node selection
      useSelectionStore.setState({
        selectedNode: makeNode('n1'),
        selectedNodes: ['n1'],
      });

      const edge = makeEdge('e1');
      useSelectionStore.getState().selectEdge(edge);

      const s = useSelectionStore.getState();
      expect(s.selectedEdge?.id).toBe('e1');
      expect(s.selectedEdges).toEqual(['e1']);
      expect(s.selectedNode).toBeNull();
      expect(s.selectedNodes).toEqual([]);
    });
  });

  describe('clearSelection', () => {
    it('should reset all selection state', () => {
      useSelectionStore.setState({
        selectedNode: makeNode('n1'),
        selectedEdge: makeEdge('e1'),
        selectedNodes: ['n1', 'n2'],
        selectedEdges: ['e1', 'e2'],
      });

      useSelectionStore.getState().clearSelection();

      const s = useSelectionStore.getState();
      expect(s.selectedNode).toBeNull();
      expect(s.selectedEdge).toBeNull();
      expect(s.selectedNodes).toEqual([]);
      expect(s.selectedEdges).toEqual([]);
    });
  });

  describe('graph-change pruning (subscription)', () => {
    it('should clear selectedNode when the node is removed from the graph', () => {
      const node = makeNode('n1');
      useGraphStore.setState({ nodes: [node], edges: [] });
      useSelectionStore.setState({ selectedNode: node });

      useGraphStore.getState().removeNode('n1');
      expect(useSelectionStore.getState().selectedNode).toBeNull();
    });

    it('should not clear selectedNode when a different node is removed', () => {
      const n1 = makeNode('n1');
      const n2 = makeNode('n2');
      useGraphStore.setState({ nodes: [n1, n2], edges: [] });
      useSelectionStore.setState({ selectedNode: n2 });

      useGraphStore.getState().removeNode('n1');
      expect(useSelectionStore.getState().selectedNode?.id).toBe('n2');
    });

    it('should remove a nodeId from selectedNodes when the node is removed', () => {
      useGraphStore.setState({ nodes: [makeNode('n1'), makeNode('n2')], edges: [] });
      useSelectionStore.setState({ selectedNodes: ['n1', 'n2'] });

      useGraphStore.getState().removeNode('n1');
      expect(useSelectionStore.getState().selectedNodes).toEqual(['n2']);
    });

    it('should clear selectedEdge when the edge is removed from the graph', () => {
      const edge = makeEdge('e1', 'n1', 'n2');
      useGraphStore.setState({ nodes: [], edges: [edge] });
      useSelectionStore.setState({ selectedEdge: edge });

      useGraphStore.getState().removeEdge('e1');
      expect(useSelectionStore.getState().selectedEdge).toBeNull();
    });

    it('should not clear selectedEdge when a different edge is removed', () => {
      const e1 = makeEdge('e1', 'n1', 'n2');
      const e2 = makeEdge('e2', 'n2', 'n3');
      useGraphStore.setState({ nodes: [], edges: [e1, e2] });
      useSelectionStore.setState({ selectedEdge: e2 });

      useGraphStore.getState().removeEdge('e1');
      expect(useSelectionStore.getState().selectedEdge?.id).toBe('e2');
    });

    it('should remove an edgeId from selectedEdges when the edge is removed', () => {
      useGraphStore.setState({ nodes: [], edges: [makeEdge('e1', 'n1', 'n2'), makeEdge('e2', 'n2', 'n3')] });
      useSelectionStore.setState({ selectedEdges: ['e1', 'e2'] });

      useGraphStore.getState().removeEdge('e1');
      expect(useSelectionStore.getState().selectedEdges).toEqual(['e2']);
    });

    it('should clear selected edges connected to a removed node', () => {
      const e1 = makeEdge('e1', 'n1', 'n2');
      useGraphStore.setState({ nodes: [makeNode('n1'), makeNode('n2')], edges: [e1] });
      useSelectionStore.setState({ selectedEdge: e1, selectedEdges: ['e1'] });

      // removeNode also removes connected edges from the graph store
      useGraphStore.getState().removeNode('n1');
      expect(useSelectionStore.getState().selectedEdge).toBeNull();
      expect(useSelectionStore.getState().selectedEdges).toEqual([]);
    });

    it('should not update selection when graph changes do not affect selected items', () => {
      const n1 = makeNode('n1');
      const n2 = makeNode('n2');
      useGraphStore.setState({ nodes: [n1, n2], edges: [] });
      useSelectionStore.setState({ selectedNode: n1, selectedNodes: ['n1'] });

      // Remove n2, which is not selected
      useGraphStore.getState().removeNode('n2');
      expect(useSelectionStore.getState().selectedNode?.id).toBe('n1');
      expect(useSelectionStore.getState().selectedNodes).toEqual(['n1']);
    });
  });
});
