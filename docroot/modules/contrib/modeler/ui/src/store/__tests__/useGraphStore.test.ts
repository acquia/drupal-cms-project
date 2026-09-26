/**
 * Tests for useGraphStore — nodes, edges, and CRUD operations.
 */

import { useGraphStore } from '../useGraphStore';
import type { StoreNode, StoreEdge } from '../../types/settings';

// Helper to build a minimal StoreNode
const makeNode = (id: string, overrides: Partial<StoreNode> = {}): StoreNode =>
  ({
    id,
    position: { x: 0, y: 0 },
    data: { label: id },
    type: 'default',
    ...overrides,
  }) as StoreNode;

// Helper to build a minimal StoreEdge
const makeEdge = (id: string, source: string, target: string, overrides: Partial<StoreEdge> = {}): StoreEdge =>
  ({
    id,
    source,
    target,
    type: 'default',
    ...overrides,
  }) as StoreEdge;

describe('useGraphStore', () => {
  beforeEach(() => {
    useGraphStore.setState({ nodes: [], edges: [] });
  });

  describe('initial state', () => {
    it('should start with empty nodes and edges', () => {
      const { nodes, edges } = useGraphStore.getState();
      expect(nodes).toEqual([]);
      expect(edges).toEqual([]);
    });
  });

  describe('setNodes', () => {
    it('should set nodes from an array', () => {
      const nodes = [makeNode('n1'), makeNode('n2')];
      useGraphStore.getState().setNodes(nodes);
      expect(useGraphStore.getState().nodes).toEqual(nodes);
    });

    it('should set nodes from an updater function', () => {
      useGraphStore.setState({ nodes: [makeNode('n1')] });
      useGraphStore.getState().setNodes((prev) => [...prev, makeNode('n2')]);
      expect(useGraphStore.getState().nodes).toHaveLength(2);
    });
  });

  describe('setEdges', () => {
    it('should set edges from an array', () => {
      const edges = [makeEdge('e1', 'n1', 'n2')];
      useGraphStore.getState().setEdges(edges);
      expect(useGraphStore.getState().edges).toEqual(edges);
    });

    it('should set edges from an updater function', () => {
      useGraphStore.setState({ edges: [makeEdge('e1', 'n1', 'n2')] });
      useGraphStore.getState().setEdges((prev) => [...prev, makeEdge('e2', 'n2', 'n3')]);
      expect(useGraphStore.getState().edges).toHaveLength(2);
    });
  });

  describe('addNode', () => {
    it('should append a node', () => {
      useGraphStore.getState().addNode(makeNode('n1'));
      useGraphStore.getState().addNode(makeNode('n2'));
      expect(useGraphStore.getState().nodes).toHaveLength(2);
      expect(useGraphStore.getState().nodes[1].id).toBe('n2');
    });
  });

  describe('addEdge', () => {
    it('should append an edge', () => {
      useGraphStore.getState().addEdge(makeEdge('e1', 'n1', 'n2'));
      expect(useGraphStore.getState().edges).toHaveLength(1);
    });
  });

  describe('updateNode', () => {
    it('should update a specific node by id', () => {
      useGraphStore.setState({ nodes: [makeNode('n1'), makeNode('n2')] });
      useGraphStore.getState().updateNode('n1', { position: { x: 100, y: 200 } });
      const n1 = useGraphStore.getState().nodes.find((n) => n.id === 'n1');
      expect(n1?.position).toEqual({ x: 100, y: 200 });
    });

    it('should not modify other nodes', () => {
      const original = makeNode('n2');
      useGraphStore.setState({ nodes: [makeNode('n1'), original] });
      useGraphStore.getState().updateNode('n1', { position: { x: 999, y: 999 } });
      const n2 = useGraphStore.getState().nodes.find((n) => n.id === 'n2');
      expect(n2?.position).toEqual(original.position);
    });
  });

  describe('updateEdge', () => {
    it('should update a specific edge by id', () => {
      useGraphStore.setState({ edges: [makeEdge('e1', 'n1', 'n2')] });
      useGraphStore.getState().updateEdge('e1', { type: 'smoothstep' });
      expect(useGraphStore.getState().edges[0].type).toBe('smoothstep');
    });
  });

  describe('removeNode', () => {
    it('should remove the node from the list', () => {
      useGraphStore.setState({
        nodes: [makeNode('n1'), makeNode('n2')],
        edges: [],
      });
      useGraphStore.getState().removeNode('n1');
      expect(useGraphStore.getState().nodes).toHaveLength(1);
      expect(useGraphStore.getState().nodes[0].id).toBe('n2');
    });

    it('should remove edges connected to the deleted node', () => {
      useGraphStore.setState({
        nodes: [makeNode('n1'), makeNode('n2'), makeNode('n3')],
        edges: [
          makeEdge('e1', 'n1', 'n2'),
          makeEdge('e2', 'n2', 'n3'),
          makeEdge('e3', 'n3', 'n1'),
        ],
      });
      useGraphStore.getState().removeNode('n1');
      const remaining = useGraphStore.getState().edges;
      // e1 (source=n1) and e3 (target=n1) should be removed
      expect(remaining).toHaveLength(1);
      expect(remaining[0].id).toBe('e2');
    });

  });

  describe('removeEdge', () => {
    it('should remove the edge from the list', () => {
      useGraphStore.setState({
        nodes: [],
        edges: [makeEdge('e1', 'n1', 'n2'), makeEdge('e2', 'n2', 'n3')],
      });
      useGraphStore.getState().removeEdge('e1');
      expect(useGraphStore.getState().edges).toHaveLength(1);
      expect(useGraphStore.getState().edges[0].id).toBe('e2');
    });
  });

  describe('applyNodeChanges', () => {
    it('should apply position changes to nodes', () => {
      useGraphStore.setState({ nodes: [makeNode('n1')] });
      useGraphStore.getState().applyNodeChanges([
        { type: 'position', id: 'n1', position: { x: 50, y: 60 } },
      ]);
      const n1 = useGraphStore.getState().nodes.find((n) => n.id === 'n1');
      expect(n1?.position).toEqual({ x: 50, y: 60 });
    });

    it('should apply select changes to nodes', () => {
      useGraphStore.setState({ nodes: [makeNode('n1')] });
      useGraphStore.getState().applyNodeChanges([
        { type: 'select', id: 'n1', selected: true },
      ]);
      const n1 = useGraphStore.getState().nodes.find((n) => n.id === 'n1');
      expect(n1?.selected).toBe(true);
    });

    it('should apply remove changes to nodes', () => {
      useGraphStore.setState({ nodes: [makeNode('n1'), makeNode('n2')] });
      useGraphStore.getState().applyNodeChanges([
        { type: 'remove', id: 'n1' },
      ]);
      expect(useGraphStore.getState().nodes).toHaveLength(1);
      expect(useGraphStore.getState().nodes[0].id).toBe('n2');
    });
  });

  describe('applyEdgeChanges', () => {
    it('should apply select changes to edges', () => {
      useGraphStore.setState({ edges: [makeEdge('e1', 'n1', 'n2')] });
      useGraphStore.getState().applyEdgeChanges([
        { type: 'select', id: 'e1', selected: true },
      ]);
      const e1 = useGraphStore.getState().edges.find((e) => e.id === 'e1');
      expect(e1?.selected).toBe(true);
    });

    it('should apply remove changes to edges', () => {
      useGraphStore.setState({ edges: [makeEdge('e1', 'n1', 'n2'), makeEdge('e2', 'n2', 'n3')] });
      useGraphStore.getState().applyEdgeChanges([
        { type: 'remove', id: 'e1' },
      ]);
      expect(useGraphStore.getState().edges).toHaveLength(1);
      expect(useGraphStore.getState().edges[0].id).toBe('e2');
    });
  });
});
