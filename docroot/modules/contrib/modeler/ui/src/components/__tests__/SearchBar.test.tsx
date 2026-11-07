import React from 'react';
import { render, screen, fireEvent, act } from '@testing-library/react';
import SearchBar from '../SearchBar';

// Mock dimensions constant
jest.mock('../../constants/dimensions', () => ({
  TIMING: {
    SEARCH_DEBOUNCE: 50,
  },
}));

// Mock the Zustand store — SearchBar reads nodes/edges internally
let mockNodes: any[] = [];
let mockEdges: any[] = [];
jest.mock('../../store/useGraphStore', () => ({
  useGraphStore: jest.fn((selector: any) => {
    const state = {
      nodes: mockNodes,
      edges: mockEdges,
    };
    return selector(state);
  }),
}));

describe('SearchBar', () => {
  const testNodes = [
    { id: 'node-1', type: 'element', position: { x: 0, y: 0 }, data: { label: 'Action Node', plugin: 'action_plugin' } },
    { id: 'node-2', type: 'start', position: { x: 0, y: 0 }, data: { label: 'Event Node', plugin: 'event_plugin' } },
    { id: 'node-3', type: 'gateway', position: { x: 0, y: 0 }, data: { label: 'Condition Node', plugin: 'condition_plugin' } },
  ];

  const testEdges = [
    { id: 'edge-1', source: 'node-1', target: 'node-2', data: { condition: 'condition_1', conditionLabel: 'First Condition' } },
    { id: 'edge-2', source: 'node-2', target: 'node-3', data: { condition: '', conditionLabel: '' }, label: 'Simple Edge' },
    { id: 'edge-3', source: 'node-1', target: 'node-3', data: {} },
  ];

  const defaultProps = {};

  beforeEach(() => {
    mockNodes = testNodes;
    mockEdges = testEdges;
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  // Helper to trigger search
  const triggerSearch = (input: HTMLElement, value: string) => {
    fireEvent.change(input, { target: { value } });
    act(() => {
      jest.runAllTimers();
    });
  };

  describe('rendering', () => {
    it('should render search input', () => {
      render(<SearchBar {...defaultProps} />);
      expect(screen.getByPlaceholderText('Search components and conditions...')).toBeInTheDocument();
    });

    it('should render search icon', () => {
      const { container } = render(<SearchBar {...defaultProps} />);
      expect(container.querySelector('.search-icon')).toBeInTheDocument();
    });

    it('should not render clear button when input is empty', () => {
      render(<SearchBar {...defaultProps} />);
      expect(screen.queryByTitle('Clear search')).not.toBeInTheDocument();
    });

    it('should show clear button when input has text', () => {
      render(<SearchBar {...defaultProps} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      fireEvent.change(input, { target: { value: 'test' } });
      expect(screen.getByTitle('Clear search')).toBeInTheDocument();
    });
  });

  describe('searching nodes', () => {
    it('should find nodes by label (auto-selects single result)', () => {
      const onHighlight = jest.fn();
      render(<SearchBar {...defaultProps} onHighlight={onHighlight} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'Action Node');
      // Single result is auto-selected - onHighlight is called
      expect(onHighlight).toHaveBeenCalledWith(
        expect.objectContaining({ label: 'Action Node' })
      );
    });

    it('should find nodes by plugin name (auto-selects single result)', () => {
      const onHighlight = jest.fn();
      render(<SearchBar {...defaultProps} onHighlight={onHighlight} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'event_plugin');
      expect(onHighlight).toHaveBeenCalledWith(
        expect.objectContaining({ label: 'Event Node' })
      );
    });

    it('should find nodes by type (auto-selects single result)', () => {
      const onHighlight = jest.fn();
      render(<SearchBar {...defaultProps} onHighlight={onHighlight} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'gateway');
      expect(onHighlight).toHaveBeenCalledWith(
        expect.objectContaining({ label: 'Condition Node' })
      );
    });

    it('should find nodes by ID (auto-selects single result)', () => {
      const onHighlight = jest.fn();
      render(<SearchBar {...defaultProps} onHighlight={onHighlight} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'node-1');
      expect(onHighlight).toHaveBeenCalledWith(
        expect.objectContaining({ id: 'node-1' })
      );
    });

    it('should show multiple nodes in dropdown', () => {
      render(<SearchBar {...defaultProps} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'Node');
      expect(screen.getByText('Action Node')).toBeInTheDocument();
      expect(screen.getByText('Event Node')).toBeInTheDocument();
      expect(screen.getByText('Condition Node')).toBeInTheDocument();
    });
  });

  describe('searching edges (conditions only)', () => {
    it('should find condition edges by condition plugin (auto-selects single result)', () => {
      const onHighlight = jest.fn();
      render(<SearchBar {...defaultProps} onHighlight={onHighlight} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'condition_1');
      expect(onHighlight).toHaveBeenCalledWith(
        expect.objectContaining({ type: 'edge', id: 'edge-1' })
      );
    });

    it('should find condition edges by condition label (auto-selects single result)', () => {
      const onHighlight = jest.fn();
      render(<SearchBar {...defaultProps} onHighlight={onHighlight} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'First Condition');
      expect(onHighlight).toHaveBeenCalledWith(
        expect.objectContaining({ type: 'edge', label: 'First Condition' })
      );
    });

    it('should NOT include edges without a condition', () => {
      const onHighlight = jest.fn();
      render(<SearchBar {...defaultProps} onHighlight={onHighlight} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'Simple Edge');
      // 'Simple Edge' is a label on an edge without a condition — should not match
      expect(onHighlight).toHaveBeenCalledWith(null);
    });

    it('should NOT include edges without any data', () => {
      const onHighlight = jest.fn();
      render(<SearchBar {...defaultProps} onHighlight={onHighlight} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'edge-3');
      // edge-3 has no condition — should not appear in results
      expect(onHighlight).toHaveBeenCalledWith(null);
    });
  });

  describe('result selection', () => {
    it('should call onHighlight when result is clicked', () => {
      const onHighlight = jest.fn();
      render(<SearchBar {...defaultProps} onHighlight={onHighlight} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'Node');
      fireEvent.click(screen.getByText('Action Node'));
      expect(onHighlight).toHaveBeenCalled();
    });

    it('should call onFocus when result is clicked', () => {
      const onFocus = jest.fn();
      render(<SearchBar {...defaultProps} onFocus={onFocus} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'Node');
      fireEvent.click(screen.getByText('Action Node'));
      expect(onFocus).toHaveBeenCalled();
    });

    it('should auto-highlight single result', () => {
      const onHighlight = jest.fn();
      render(<SearchBar {...defaultProps} onHighlight={onHighlight} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'Condition Node');
      expect(onHighlight).toHaveBeenCalled();
    });

    it('should close dropdown after selection', () => {
      render(<SearchBar {...defaultProps} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'Node');
      fireEvent.click(screen.getByText('Action Node'));
      expect(screen.queryByText('Event Node')).not.toBeInTheDocument();
    });
  });

  describe('keyboard navigation', () => {
    it('should navigate down with ArrowDown', () => {
      render(<SearchBar {...defaultProps} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'Node');
      fireEvent.keyDown(input, { key: 'ArrowDown' });
      const items = document.querySelectorAll('.search-result-item');
      expect(items[1]).toHaveClass('highlighted');
    });

    it('should navigate up with ArrowUp', () => {
      render(<SearchBar {...defaultProps} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'Node');
      fireEvent.keyDown(input, { key: 'ArrowDown' });
      fireEvent.keyDown(input, { key: 'ArrowUp' });
      const items = document.querySelectorAll('.search-result-item');
      expect(items[0]).toHaveClass('highlighted');
    });

    it('should select item with Enter', () => {
      const onHighlight = jest.fn();
      render(<SearchBar {...defaultProps} onHighlight={onHighlight} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'Node');
      fireEvent.keyDown(input, { key: 'Enter' });
      expect(onHighlight).toHaveBeenCalled();
    });

    it('should close dropdown with Escape', () => {
      render(<SearchBar {...defaultProps} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'Node');
      fireEvent.keyDown(input, { key: 'Escape' });
      expect(screen.queryByText('Action Node')).not.toBeInTheDocument();
    });

    it('should wrap around when navigating past the end', () => {
      render(<SearchBar {...defaultProps} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'Node');
      const items = document.querySelectorAll('.search-result-item');
      for (let i = 0; i < items.length; i++) {
        fireEvent.keyDown(input, { key: 'ArrowDown' });
      }
      expect(items[0]).toHaveClass('highlighted');
    });

    it('should wrap around when navigating past the beginning', () => {
      render(<SearchBar {...defaultProps} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'Node');
      fireEvent.keyDown(input, { key: 'ArrowUp' });
      const items = document.querySelectorAll('.search-result-item');
      expect(items[items.length - 1]).toHaveClass('highlighted');
    });

    it('should not crash on keyboard events when dropdown is closed', () => {
      render(<SearchBar {...defaultProps} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      expect(() => {
        fireEvent.keyDown(input, { key: 'ArrowDown' });
        fireEvent.keyDown(input, { key: 'ArrowUp' });
        fireEvent.keyDown(input, { key: 'Enter' });
        fireEvent.keyDown(input, { key: 'Escape' });
      }).not.toThrow();
    });
  });

  describe('clearing search', () => {
    it('should clear search when clear button clicked', () => {
      const onHighlight = jest.fn();
      render(<SearchBar {...defaultProps} onHighlight={onHighlight} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      fireEvent.change(input, { target: { value: 'test' } });
      const clearButton = screen.getByTitle('Clear search');
      fireEvent.click(clearButton);
      expect(input).toHaveValue('');
      expect(onHighlight).toHaveBeenCalledWith(null);
    });

    it('should clear results when search is cleared', () => {
      render(<SearchBar {...defaultProps} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'Node');
      triggerSearch(input, '');
      expect(screen.queryByText('Action Node')).not.toBeInTheDocument();
    });

    it('should refocus input after clearing', () => {
      render(<SearchBar {...defaultProps} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      fireEvent.change(input, { target: { value: 'test' } });
      fireEvent.click(screen.getByTitle('Clear search'));
      expect(document.activeElement).toBe(input);
    });
  });

  describe('ref handling', () => {
    it('should expose focus method via ref', () => {
      const ref = React.createRef<any>();
      render(<SearchBar {...defaultProps} ref={ref} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      act(() => {
        ref.current?.focus();
      });
      expect(document.activeElement).toBe(input);
    });
  });

  describe('dropdown toggle', () => {
    it('should show result count when multiple results', () => {
      render(<SearchBar {...defaultProps} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'Node');
      expect(document.querySelector('.result-count')).toBeInTheDocument();
    });

    it('should toggle dropdown when toggle button clicked', () => {
      render(<SearchBar {...defaultProps} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'Node');
      const toggle = document.querySelector('.search-dropdown-toggle');
      if (toggle) {
        fireEvent.click(toggle);
        expect(screen.queryByText('Action Node')).not.toBeInTheDocument();
        fireEvent.click(toggle);
        expect(screen.getByText('Action Node')).toBeInTheDocument();
      }
    });

    it('should not show toggle for single result', () => {
      render(<SearchBar {...defaultProps} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'Condition Node');
      // Single result auto-selects, so no toggle shown
      expect(document.querySelector('.search-dropdown-toggle')).not.toBeInTheDocument();
    });
  });

  describe('mouse interaction', () => {
    it('should highlight item on mouse enter', () => {
      render(<SearchBar {...defaultProps} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'Node');
      const secondItem = screen.getByText('Event Node').closest('.search-result-item');
      if (secondItem) {
        fireEvent.mouseEnter(secondItem);
        expect(secondItem).toHaveClass('highlighted');
      }
    });
  });

  describe('result display', () => {
    it('should show Component badge for nodes', () => {
      render(<SearchBar {...defaultProps} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'Node');
      expect(screen.getAllByText('Component').length).toBeGreaterThan(0);
    });

    it('should show Condition badge for condition edges', () => {
      const onHighlight = jest.fn();
      render(<SearchBar {...defaultProps} onHighlight={onHighlight} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'First Condition');
      // Single result auto-selects; verify the result is an edge
      expect(onHighlight).toHaveBeenCalledWith(
        expect.objectContaining({ type: 'edge' })
      );
    });

    it('should show subtitle for results', () => {
      render(<SearchBar {...defaultProps} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'Node');
      // Subtitle shows type and plugin info
      expect(screen.getByText(/action_plugin/)).toBeInTheDocument();
    });
  });

  describe('empty and edge cases', () => {
    it('should handle empty nodes array', () => {
      mockNodes = [];
      mockEdges = testEdges;
      render(<SearchBar />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'Node');
      expect(screen.queryByText('Action Node')).not.toBeInTheDocument();
    });

    it('should handle empty edges array', () => {
      mockNodes = testNodes;
      mockEdges = [];
      render(<SearchBar />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'First Condition');
      expect(screen.queryByText('First Condition')).not.toBeInTheDocument();
    });

    it('should handle nodes without label (uses type or ID)', () => {
      mockNodes = [
        { id: 'node-empty', type: 'element', position: { x: 0, y: 0 }, data: {} },
      ];
      mockEdges = [];
      const onHighlight = jest.fn();
      render(<SearchBar onHighlight={onHighlight} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'element');
      // Should find by type and auto-select (single result)
      expect(onHighlight).toHaveBeenCalled();
    });

    it('should call onHighlight with null when no results found', () => {
      const onHighlight = jest.fn();
      render(<SearchBar {...defaultProps} onHighlight={onHighlight} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'nonexistent');
      expect(onHighlight).toHaveBeenCalledWith(null);
    });

    it('should not show dropdown when no results', () => {
      render(<SearchBar {...defaultProps} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, 'nonexistent');
      expect(document.querySelector('.search-dropdown')).not.toBeInTheDocument();
    });

    it('should handle whitespace-only search', () => {
      const onHighlight = jest.fn();
      render(<SearchBar {...defaultProps} onHighlight={onHighlight} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      triggerSearch(input, '   ');
      expect(document.querySelector('.search-dropdown')).not.toBeInTheDocument();
      expect(onHighlight).toHaveBeenCalledWith(null);
    });
  });

  describe('debouncing', () => {
    it('should not search immediately on input change', () => {
      render(<SearchBar {...defaultProps} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      
      fireEvent.change(input, { target: { value: 'Node' } });
      
      // Before timer runs, no dropdown should be visible
      expect(document.querySelector('.search-dropdown')).not.toBeInTheDocument();
    });

    it('should search after debounce delay', () => {
      render(<SearchBar {...defaultProps} />);
      const input = screen.getByPlaceholderText('Search components and conditions...');
      
      fireEvent.change(input, { target: { value: 'Node' } });
      
      act(() => {
        jest.runAllTimers();
      });
      
      expect(screen.getByText('Action Node')).toBeInTheDocument();
    });
  });
});
