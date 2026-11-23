import { renderHook, act } from '@testing-library/react';
import { useSearch } from '../useSearch';

// Mock viewport actions
const mockViewportActions = {
  panToNode: jest.fn(),
  panToNodeIfOffscreen: jest.fn(),
  fitToNodes: jest.fn(),
  topAlignNode: jest.fn(),
  focusNode: jest.fn(),
  fitToNodePair: jest.fn(),
  selectAndFocus: jest.fn(),
  setReady: jest.fn(),
};

// Mock the store
const mockSelectNode = jest.fn();
const mockSelectEdge = jest.fn();
const mockNodes = [
  { id: 'node1', position: { x: 0, y: 0 }, data: { label: 'Node 1' } },
  { id: 'node2', position: { x: 100, y: 100 }, data: { label: 'Node 2' } },
];

jest.mock('../../store/useGraphStore', () => ({
  useGraphStore: jest.fn((selector) => {
    const state = {
      nodes: mockNodes,
    };
    return selector(state);
  }),
}));

jest.mock('../../store/useSelectionStore', () => ({
  useSelectionStore: jest.fn((selector) => {
    const state = {
      selectNode: mockSelectNode,
      selectEdge: mockSelectEdge,
    };
    return selector(state);
  }),
}));

describe('useSearch', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('initial state', () => {
    it('should initialize with empty search term', () => {
      const { result } = renderHook(() => useSearch({ viewportActions: mockViewportActions }));
      expect(result.current.searchTerm).toBe('');
    });

    it('should initialize with no highlighted result', () => {
      const { result } = renderHook(() => useSearch({ viewportActions: mockViewportActions }));
      expect(result.current.highlightedSearchResult).toBeNull();
    });
  });

  describe('onSearchHighlight', () => {
    it('should set highlighted result', () => {
      const { result } = renderHook(() => useSearch({ viewportActions: mockViewportActions }));
      const mockResult = {
        id: 'node1',
        type: 'node',
        data: { id: 'node1', position: { x: 0, y: 0 }, data: { label: 'Test Node' } },
      };

      act(() => {
        result.current.onSearchHighlight(mockResult);
      });

      expect(result.current.highlightedSearchResult).toEqual(mockResult);
    });

    it('should select and center viewport for node results', () => {
      const { result } = renderHook(() => useSearch({ viewportActions: mockViewportActions }));
      const nodeData = { id: 'node1', position: { x: 0, y: 0 }, data: { label: 'Test Node' } };
      const mockResult = {
        id: 'node1',
        type: 'node',
        data: nodeData,
      };

      act(() => {
        result.current.onSearchHighlight(mockResult);
      });

      expect(mockSelectNode).toHaveBeenCalledWith(nodeData);
      expect(mockViewportActions.focusNode).toHaveBeenCalledWith('node1');
    });

    it('should select and center viewport on source node for edge results', () => {
      const { result } = renderHook(() => useSearch({ viewportActions: mockViewportActions }));
      const edgeData = { id: 'edge1', source: 'node1', target: 'node2', data: { condition: 'cond' } };
      const mockResult = {
        id: 'edge1',
        type: 'edge',
        data: edgeData,
      };

      act(() => {
        result.current.onSearchHighlight(mockResult);
      });

      expect(mockSelectEdge).toHaveBeenCalledWith(edgeData);
      expect(mockViewportActions.focusNode).toHaveBeenCalledWith('node1');
    });

    it('should not trigger viewport centering for results without data', () => {
      const { result } = renderHook(() => useSearch({ viewportActions: mockViewportActions }));
      const mockResult = {
        id: 'node1',
        type: 'node',
      };

      act(() => {
        result.current.onSearchHighlight(mockResult);
      });

      expect(mockViewportActions.focusNode).not.toHaveBeenCalled();
    });

    it('should handle null result', () => {
      const { result } = renderHook(() => useSearch({ viewportActions: mockViewportActions }));

      act(() => {
        result.current.onSearchHighlight({
          id: 'node1',
          type: 'node',
          data: { id: 'node1', position: { x: 0, y: 0 }, data: {} },
        });
        result.current.onSearchHighlight(null);
      });

      expect(result.current.highlightedSearchResult).toBeNull();
    });
  });

  describe('onSearchFocus', () => {
    it('should be a no-op (selection handled by onSearchHighlight)', () => {
      const { result } = renderHook(() => useSearch({ viewportActions: mockViewportActions }));

      act(() => {
        result.current.onSearchFocus({ type: 'node', id: 'node1' });
      });

      // onSearchFocus no longer drives viewport changes
      expect(mockViewportActions.focusNode).not.toHaveBeenCalled();
    });
  });

  describe('clearSearch', () => {
    it('should clear search term', () => {
      const { result } = renderHook(() => useSearch({ viewportActions: mockViewportActions }));

      act(() => {
        result.current.onSearchHighlight({
          id: 'node1',
          type: 'node',
          data: { id: 'node1', position: { x: 0, y: 0 }, data: {} },
        });
        result.current.clearSearch();
      });

      expect(result.current.searchTerm).toBe('');
    });

    it('should clear highlighted result', () => {
      const { result } = renderHook(() => useSearch({ viewportActions: mockViewportActions }));

      act(() => {
        result.current.onSearchHighlight({
          id: 'node1',
          type: 'node',
          data: { id: 'node1', position: { x: 0, y: 0 }, data: {} },
        });
        result.current.clearSearch();
      });

      expect(result.current.highlightedSearchResult).toBeNull();
    });

    it('should reset all search state at once', () => {
      const { result } = renderHook(() => useSearch({ viewportActions: mockViewportActions }));

      act(() => {
        result.current.onSearchHighlight({
          id: 'node1',
          type: 'node',
          data: { id: 'node1', position: { x: 0, y: 0 }, data: {} },
        });
        result.current.clearSearch();
      });

      expect(result.current.searchTerm).toBe('');
      expect(result.current.highlightedSearchResult).toBeNull();
    });
  });

  describe('return value structure', () => {
    it('should return all expected properties', () => {
      const { result } = renderHook(() => useSearch({ viewportActions: mockViewportActions }));

      // State
      expect(result.current).toHaveProperty('searchTerm');
      expect(result.current).toHaveProperty('highlightedSearchResult');

      // Actions
      expect(typeof result.current.onSearchHighlight).toBe('function');
      expect(typeof result.current.onSearchFocus).toBe('function');
      expect(typeof result.current.clearSearch).toBe('function');
    });
  });
});
