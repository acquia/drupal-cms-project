import { renderHook } from '@testing-library/react';
import { useSelectionSync } from '../useSelectionSync';

// Mock store state
let mockNodes: any[] = [];
let mockEdges: any[] = [];
let mockSelectedNode: any = null;
let mockSelectedEdge: any = null;
const mockSetSelectedNode = jest.fn();
const mockSetSelectedEdge = jest.fn();

jest.mock('../../store/useGraphStore', () => ({
  useGraphStore: jest.fn((selector) => {
    const state = {
      nodes: mockNodes,
      edges: mockEdges,
    };
    return selector(state);
  }),
}));

jest.mock('../../store/useSelectionStore', () => ({
  useSelectionStore: jest.fn((selector) => {
    const state = {
      selectedNode: mockSelectedNode,
      selectedEdge: mockSelectedEdge,
      setSelectedNode: mockSetSelectedNode,
      setSelectedEdge: mockSetSelectedEdge,
    };
    return selector(state);
  }),
}));

describe('useSelectionSync', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockNodes = [];
    mockEdges = [];
    mockSelectedNode = null;
    mockSelectedEdge = null;
  });

  it('should not update when no node or edge is selected', () => {
    renderHook(() => useSelectionSync());
    expect(mockSetSelectedNode).not.toHaveBeenCalled();
    expect(mockSetSelectedEdge).not.toHaveBeenCalled();
  });

  it('should update selected node when node data changes', () => {
    const oldNode = { id: 'node-1', data: { label: 'Old' } };
    const updatedNode = { id: 'node-1', data: { label: 'New' } };
    
    mockNodes = [updatedNode];
    mockSelectedNode = oldNode;
    
    renderHook(() => useSelectionSync());
    expect(mockSetSelectedNode).toHaveBeenCalledWith(updatedNode);
  });

  it('should not update when selected node reference is the same', () => {
    const node = { id: 'node-1', data: { label: 'Test' } };
    mockNodes = [node];
    mockSelectedNode = node; // Same reference
    
    renderHook(() => useSelectionSync());
    expect(mockSetSelectedNode).not.toHaveBeenCalled();
  });

  it('should update selected edge when edge data changes', () => {
    const oldEdge = { id: 'edge-1', data: { condition: 'old' } };
    const updatedEdge = { id: 'edge-1', data: { condition: 'new' } };
    
    mockEdges = [updatedEdge];
    mockSelectedEdge = oldEdge;
    
    renderHook(() => useSelectionSync());
    expect(mockSetSelectedEdge).toHaveBeenCalledWith(updatedEdge);
  });

  it('should not update when selected edge reference is the same', () => {
    const edge = { id: 'edge-1', data: {} };
    mockEdges = [edge];
    mockSelectedEdge = edge; // Same reference
    
    renderHook(() => useSelectionSync());
    expect(mockSetSelectedEdge).not.toHaveBeenCalled();
  });

  it('should not update node if selected node is not found in nodes array', () => {
    mockNodes = [{ id: 'node-2', data: {} }];
    mockSelectedNode = { id: 'node-1', data: {} };
    
    renderHook(() => useSelectionSync());
    expect(mockSetSelectedNode).not.toHaveBeenCalled();
  });

  it('should sync both node and edge simultaneously', () => {
    const oldNode = { id: 'node-1', data: { label: 'Old' } };
    const updatedNode = { id: 'node-1', data: { label: 'New' } };
    const oldEdge = { id: 'edge-1', data: { condition: 'old' } };
    const updatedEdge = { id: 'edge-1', data: { condition: 'new' } };
    
    mockNodes = [updatedNode];
    mockEdges = [updatedEdge];
    mockSelectedNode = oldNode;
    mockSelectedEdge = oldEdge;
    
    renderHook(() => useSelectionSync());
    expect(mockSetSelectedNode).toHaveBeenCalledWith(updatedNode);
    expect(mockSetSelectedEdge).toHaveBeenCalledWith(updatedEdge);
  });
});
