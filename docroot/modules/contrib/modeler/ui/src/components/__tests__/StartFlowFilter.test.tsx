import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import StartFlowFilter from '../StartFlowFilter';

// Mock store
const mockSetVisibleStartNodeIds = jest.fn();
let mockVisibleStartNodeIds: string[] | null = null;
let mockNodes: any[] = [];

jest.mock('../../store/useGraphStore', () => ({
  useGraphStore: jest.fn((selector) => {
    const state = {
      nodes: mockNodes,
    };
    return selector(state);
  }),
}));

jest.mock('../../store/useFilterStore', () => ({
  useFilterStore: jest.fn((selector) => {
    const state = {
      visibleStartNodeIds: mockVisibleStartNodeIds,
      setVisibleStartNodeIds: mockSetVisibleStartNodeIds,
    };
    return selector(state);
  }),
}));

// Mock useClickOutside
jest.mock('../../hooks/useClickOutside', () => ({
  useClickOutside: jest.fn(),
}));

// Mock translation
jest.mock('../../utils/translation', () => ({
  t: jest.fn((str: string, replacements?: Record<string, string>) => {
    if (replacements) {
      return Object.entries(replacements).reduce(
        (s, [k, v]) => s.replace(k, v),
        str,
      );
    }
    return str;
  }),
}));

const makeStartNode = (id: string, label?: string) => ({
  id,
  type: 'start' as const,
  position: { x: 0, y: 0 },
  data: { label: label || `Event ${id}` },
});

const makeElementNode = (id: string) => ({
  id,
  type: 'element' as const,
  position: { x: 0, y: 0 },
  data: { label: `Action ${id}` },
});

describe('StartFlowFilter', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockVisibleStartNodeIds = null;
    mockNodes = [];
  });

  describe('rendering', () => {
    it('should return null when there are no start nodes', () => {
      mockNodes = [makeElementNode('a1')];
      const { container } = render(<StartFlowFilter />);
      expect(container.innerHTML).toBe('');
    });

    it('should return null when there is only 1 start node', () => {
      mockNodes = [makeStartNode('s1'), makeElementNode('a1')];
      const { container } = render(
        <StartFlowFilter />,
      );
      expect(container.innerHTML).toBe('');
    });

    it('should render the filter button when 2+ start nodes exist', () => {
      mockNodes = [makeStartNode('s1'), makeStartNode('s2')];
      render(
        <StartFlowFilter />,
      );
      expect(screen.getByTitle('Filter visible flows')).toBeInTheDocument();
    });

    it('should show "All Flows" label when visibleStartNodeIds is null', () => {
      mockNodes = [makeStartNode('s1'), makeStartNode('s2')];
      render(
        <StartFlowFilter />,
      );
      expect(screen.getByText('All Flows')).toBeInTheDocument();
    });

    it('should show single flow name when 1 node is selected', () => {
      mockVisibleStartNodeIds = ['s1'];
      mockNodes = [makeStartNode('s1', 'My Event'), makeStartNode('s2')];
      render(
        <StartFlowFilter />,
      );
      expect(screen.getByText('My Event')).toBeInTheDocument();
    });

    it('should show count label when multiple nodes are selected', () => {
      mockVisibleStartNodeIds = ['s1', 's2'];
      mockNodes = [makeStartNode('s1'), makeStartNode('s2'), makeStartNode('s3')];
      render(
        <StartFlowFilter />,
      );
      expect(screen.getByText('2 Flows')).toBeInTheDocument();
    });

    it('should have active class on toggle button when filtering is applied', () => {
      mockVisibleStartNodeIds = ['s1'];
      mockNodes = [makeStartNode('s1'), makeStartNode('s2')];
      render(
        <StartFlowFilter />,
      );
      expect(screen.getByTitle('Filter visible flows')).toHaveClass('active');
    });

    it('should not have active class when showing all', () => {
      mockNodes = [makeStartNode('s1'), makeStartNode('s2')];
      render(
        <StartFlowFilter />,
      );
      expect(screen.getByTitle('Filter visible flows')).not.toHaveClass('active');
    });
  });

  describe('dropdown toggle', () => {
    it('should open dropdown when button is clicked', () => {
      mockNodes = [makeStartNode('s1'), makeStartNode('s2')];
      render(
        <StartFlowFilter />,
      );
      fireEvent.click(screen.getByTitle('Filter visible flows'));
      expect(screen.getByRole('listbox')).toBeInTheDocument();
    });

    it('should close dropdown when button is clicked again', () => {
      mockNodes = [makeStartNode('s1'), makeStartNode('s2')];
      render(
        <StartFlowFilter />,
      );
      const button = screen.getByTitle('Filter visible flows');
      fireEvent.click(button);
      expect(screen.getByRole('listbox')).toBeInTheDocument();
      fireEvent.click(button);
      expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    });

    it('should list "All" option and all start nodes in the dropdown', () => {
      mockNodes = [makeStartNode('s1', 'Alpha'), makeStartNode('s2', 'Beta')];
      render(
        <StartFlowFilter />,
      );
      fireEvent.click(screen.getByTitle('Filter visible flows'));
      expect(screen.getByText('All')).toBeInTheDocument();
      expect(screen.getByText('Alpha')).toBeInTheDocument();
      expect(screen.getByText('Beta')).toBeInTheDocument();
    });
  });

  describe('select all', () => {
    it('should call setVisibleStartNodeIds(null) when "All" is clicked', () => {
      mockVisibleStartNodeIds = ['s1'];
      mockNodes = [makeStartNode('s1'), makeStartNode('s2')];
      render(
        <StartFlowFilter />,
      );
      fireEvent.click(screen.getByTitle('Filter visible flows'));
      fireEvent.click(screen.getByText('All'));
      expect(mockSetVisibleStartNodeIds).toHaveBeenCalledWith(null);
    });
  });

  describe('toggle individual node', () => {
    it('should select only the clicked node when switching from "All"', () => {
      mockNodes = [makeStartNode('s1', 'Alpha'), makeStartNode('s2', 'Beta')];
      render(
        <StartFlowFilter />,
      );
      fireEvent.click(screen.getByTitle('Filter visible flows'));
      fireEvent.click(screen.getByText('Alpha'));
      expect(mockSetVisibleStartNodeIds).toHaveBeenCalledWith(['s1']);
    });

    it('should remove node from selection when unchecking a selected node', () => {
      mockVisibleStartNodeIds = ['s1', 's2'];
      mockNodes = [makeStartNode('s1', 'Alpha'), makeStartNode('s2', 'Beta'), makeStartNode('s3', 'Gamma')];
      render(
        <StartFlowFilter />,
      );
      fireEvent.click(screen.getByTitle('Filter visible flows'));
      fireEvent.click(screen.getByText('Alpha'));
      expect(mockSetVisibleStartNodeIds).toHaveBeenCalledWith(['s2']);
    });

    it('should add node to selection when checking an unselected node', () => {
      mockVisibleStartNodeIds = ['s1'];
      mockNodes = [makeStartNode('s1', 'Alpha'), makeStartNode('s2', 'Beta'), makeStartNode('s3', 'Gamma')];
      render(
        <StartFlowFilter />,
      );
      fireEvent.click(screen.getByTitle('Filter visible flows'));
      fireEvent.click(screen.getByText('Beta'));
      expect(mockSetVisibleStartNodeIds).toHaveBeenCalledWith(['s1', 's2']);
    });

    it('should revert to null when unchecking last selected node', () => {
      mockVisibleStartNodeIds = ['s1'];
      mockNodes = [makeStartNode('s1', 'Alpha'), makeStartNode('s2', 'Beta')];
      render(
        <StartFlowFilter />,
      );
      fireEvent.click(screen.getByTitle('Filter visible flows'));
      // "Alpha" appears in both the button label and the dropdown option.
      // Target the dropdown option specifically via its role.
      const options = screen.getAllByRole('option');
      const alphaOption = options.find(o => o.textContent?.includes('Alpha'))!;
      fireEvent.click(alphaOption);
      expect(mockSetVisibleStartNodeIds).toHaveBeenCalledWith(null);
    });

    it('should revert to null when all nodes become selected', () => {
      mockVisibleStartNodeIds = ['s1'];
      mockNodes = [makeStartNode('s1', 'Alpha'), makeStartNode('s2', 'Beta')];
      render(
        <StartFlowFilter />,
      );
      fireEvent.click(screen.getByTitle('Filter visible flows'));
      fireEvent.click(screen.getByText('Beta'));
      expect(mockSetVisibleStartNodeIds).toHaveBeenCalledWith(null);
    });
  });

  describe('keyboard interaction', () => {
    it('should handle Enter key on "All" option', () => {
      mockVisibleStartNodeIds = ['s1'];
      mockNodes = [makeStartNode('s1'), makeStartNode('s2')];
      render(
        <StartFlowFilter />,
      );
      fireEvent.click(screen.getByTitle('Filter visible flows'));
      fireEvent.keyDown(screen.getByText('All').closest('li')!, { key: 'Enter' });
      expect(mockSetVisibleStartNodeIds).toHaveBeenCalledWith(null);
    });

    it('should handle Space key on individual node option', () => {
      mockNodes = [makeStartNode('s1', 'Alpha'), makeStartNode('s2', 'Beta')];
      render(
        <StartFlowFilter />,
      );
      fireEvent.click(screen.getByTitle('Filter visible flows'));
      fireEvent.keyDown(screen.getByText('Alpha').closest('li')!, { key: ' ' });
      expect(mockSetVisibleStartNodeIds).toHaveBeenCalledWith(['s1']);
    });
  });

  describe('accessibility', () => {
    it('should have aria-haspopup and aria-expanded on the toggle button', () => {
      mockNodes = [makeStartNode('s1'), makeStartNode('s2')];
      render(
        <StartFlowFilter />,
      );
      const button = screen.getByTitle('Filter visible flows');
      expect(button).toHaveAttribute('aria-haspopup', 'listbox');
      expect(button).toHaveAttribute('aria-expanded', 'false');

      fireEvent.click(button);
      expect(button).toHaveAttribute('aria-expanded', 'true');
    });

    it('should have role="listbox" with aria-multiselectable on dropdown', () => {
      mockNodes = [makeStartNode('s1'), makeStartNode('s2')];
      render(
        <StartFlowFilter />,
      );
      fireEvent.click(screen.getByTitle('Filter visible flows'));
      const listbox = screen.getByRole('listbox');
      expect(listbox).toHaveAttribute('aria-multiselectable', 'true');
    });

    it('should have role="option" with correct aria-selected on each item', () => {
      mockVisibleStartNodeIds = ['s1'];
      mockNodes = [makeStartNode('s1', 'Alpha'), makeStartNode('s2', 'Beta')];
      render(
        <StartFlowFilter />,
      );
      fireEvent.click(screen.getByTitle('Filter visible flows'));

      const options = screen.getAllByRole('option');
      // "All" should not be selected
      expect(options[0]).toHaveAttribute('aria-selected', 'false');
      // "Alpha" (s1) should be selected
      expect(options[1]).toHaveAttribute('aria-selected', 'true');
      // "Beta" (s2) should not be selected
      expect(options[2]).toHaveAttribute('aria-selected', 'false');
    });

    it('should have role="separator" on divider', () => {
      mockNodes = [makeStartNode('s1'), makeStartNode('s2')];
      render(
        <StartFlowFilter />,
      );
      fireEvent.click(screen.getByTitle('Filter visible flows'));
      expect(screen.getByRole('separator')).toBeInTheDocument();
    });
  });

  describe('node label display', () => {
    it('should fall back to plugin name when label is missing', () => {
      const nodeWithPlugin = {
        id: 's1',
        type: 'start' as const,
        position: { x: 0, y: 0 },
        data: { plugin: 'my_plugin' },
      };
      mockVisibleStartNodeIds = ['s1'];
      mockNodes = [nodeWithPlugin, makeStartNode('s2')];
      render(
        <StartFlowFilter />,
      );
      expect(screen.getByText('my_plugin')).toBeInTheDocument();
    });

    it('should fall back to node ID when both label and plugin are missing', () => {
      const bareNode = {
        id: 's1',
        type: 'start' as const,
        position: { x: 0, y: 0 },
        data: {},
      };
      mockVisibleStartNodeIds = ['s1'];
      mockNodes = [bareNode, makeStartNode('s2')];
      render(
        <StartFlowFilter />,
      );
      expect(screen.getByText('s1')).toBeInTheDocument();
    });
  });
});
