import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import QuickAddButton from '../QuickAddButton';

// Mock the DocumentationButton component
jest.mock('../DocumentationButton', () => {
  return function MockDocumentationButton({ title }: { url: string; title: string }) {
    return <span data-testid="doc-button" title={`Docs: ${title}`}>Doc</span>;
  };
});

// Mock store — need >= 15 successor (non-Event, non-Trigger, non-Condition) components
// so the search field is visible
// (see THRESHOLDS.SEARCH_VISIBILITY_MIN_COMPONENTS in constants/dimensions.ts)
const mockSuccessorFillers = Array.from({ length: 13 }, (_, i) => ({
  plugin: `action:filler_${i}`, label: `Filler Action ${i}`, type: 'element', componentType: 4,
}));

const mockComponents = [
  { plugin: 'action:save', label: 'Save Entity', type: 'element', componentType: 4, documentationUrl: 'https://docs.example.com/save' },
  { plugin: 'action:delete', label: 'Delete Entity', type: 'element', componentType: 4 },
  { plugin: 'gateway:exclusive', label: 'Exclusive Gateway', type: 'gateway', componentType: 6, documentationUrl: 'https://docs.example.com/gateway' },
  ...mockSuccessorFillers,
  { plugin: 'event:insert', label: 'Entity Insert', type: 'start', componentType: 1 },
  { plugin: 'condition:is_new', label: 'Entity is New', type: 'link', componentType: 5 },
];

const mockFavoriteComponents = {
  4: ['action:save'], // Action type favorites
};

let mockSelectedContextId: string | null = null;
let mockContexts: any[] = [];

jest.mock('../../store/useComponentStore', () => ({
  useComponentStore: jest.fn((selector) => {
    const state = {
      components: mockComponents,
      favoriteComponents: mockFavoriteComponents,
    };
    return selector(state);
  }),
}));

jest.mock('../../store/useContextStore', () => ({
  useContextStore: jest.fn((selector) => {
    const state = {
      selectedContextId: mockSelectedContextId,
      contexts: mockContexts,
      dependencies: [],
    };
    return selector(state);
  }),
}));

jest.mock('../../store/useGraphStore', () => ({
  useGraphStore: jest.fn((selector) => {
    const state = {
      nodes: [],
      edges: [],
    };
    return selector(state);
  }),
}));

// Mock createPortal to render in place
jest.mock('react-dom', () => ({
  ...jest.requireActual('react-dom'),
  createPortal: (node: React.ReactNode) => node,
}));

describe('QuickAddButton', () => {
  const mockOnAddNode = jest.fn();

  beforeEach(() => {
    jest.clearAllMocks();
    mockSelectedContextId = null;
    mockContexts = [];
  });

  describe('rendering', () => {
    it('should render the button', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);
      
      const button = screen.getByTitle('Add successor node');
      expect(button).toBeInTheDocument();
    });

    it('should not render when disabled', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} disabled={true} />);
      
      expect(screen.queryByTitle('Add successor node')).not.toBeInTheDocument();
    });
  });

  describe('popup interaction', () => {
    it('should open popup when button is clicked', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);
      
      const button = screen.getByTitle('Add successor node');
      fireEvent.click(button);
      
      expect(screen.getByText('Add Successor')).toBeInTheDocument();
    });

    it('should show search input in popup', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);
      
      fireEvent.click(screen.getByTitle('Add successor node'));
      
      expect(screen.getByPlaceholderText('Search components...')).toBeInTheDocument();
    });

    it('should close popup when close button is clicked', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);
      
      fireEvent.click(screen.getByTitle('Add successor node'));
      expect(screen.getByText('Add Successor')).toBeInTheDocument();
      
      fireEvent.click(screen.getByTitle('Close'));
      
      expect(screen.queryByText('Add Successor')).not.toBeInTheDocument();
    });

    it('should close popup on Escape key', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);
      
      fireEvent.click(screen.getByTitle('Add successor node'));
      expect(screen.getByText('Add Successor')).toBeInTheDocument();
      
      fireEvent.keyDown(document, { key: 'Escape' });
      
      expect(screen.queryByText('Add Successor')).not.toBeInTheDocument();
    });

    it('should close popup when the quick-add button is clicked again', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);

      const button = screen.getByTitle('Add successor node');
      fireEvent.click(button);
      expect(screen.getByText('Add Successor')).toBeInTheDocument();

      // Clicking the same button again should close the popup
      fireEvent.click(button);
      expect(screen.queryByText('Add Successor')).not.toBeInTheDocument();
    });

    it('should close popup when clicking outside', async () => {
      render(
        <div>
          <QuickAddButton onAddNode={mockOnAddNode} />
          <div data-testid="outside-area">Outside</div>
        </div>
      );

      fireEvent.click(screen.getByTitle('Add successor node'));

      // Wait for popup to render AND for the setTimeout(0) inside the
      // click-outside effect to register the document listener.
      await waitFor(() => {
        expect(screen.getByText('Add Successor')).toBeInTheDocument();
      });

      await waitFor(() => {
        fireEvent.pointerDown(screen.getByTestId('outside-area'));
      });

      await waitFor(() => {
        expect(screen.queryByText('Add Successor')).not.toBeInTheDocument();
      });
    });
  });

  describe('component filtering', () => {
    it('should exclude Events and Triggers from the list', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);
      
      fireEvent.click(screen.getByTitle('Add successor node'));
      
      // Actions should be visible
      expect(screen.getByText('Save Entity')).toBeInTheDocument();
      expect(screen.getByText('Delete Entity')).toBeInTheDocument();
      
      // Gateways should be visible
      expect(screen.getByText('Exclusive Gateway')).toBeInTheDocument();
      
      // Events should NOT be visible
      expect(screen.queryByText('Entity Insert')).not.toBeInTheDocument();
    });

    it('should include Conditions in the list for condition-first authoring', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);
      
      fireEvent.click(screen.getByTitle('Add successor node'));
      
      // Conditions should be visible (condition-first authoring creates a placeholder node)
      expect(screen.queryByText('Entity is New')).toBeInTheDocument();
    });

    it('should filter components by search term', async () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);
      
      fireEvent.click(screen.getByTitle('Add successor node'));
      
      const searchInput = screen.getByPlaceholderText('Search components...');
      fireEvent.change(searchInput, { target: { value: 'Delete' } });
      
      await waitFor(() => {
        expect(screen.getByText('Delete Entity')).toBeInTheDocument();
        expect(screen.queryByText('Save Entity')).not.toBeInTheDocument();
      });
    });

    it('should show section headers instead of type filter tabs', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);
      
      fireEvent.click(screen.getByTitle('Add successor node'));
      
      // Old category tabs should not be present
      expect(screen.queryByText('All')).not.toBeInTheDocument();
      expect(screen.queryByText('Elements')).not.toBeInTheDocument();

      // Section headers should be present
      expect(screen.getByText('Recommended')).toBeInTheDocument();
      expect(screen.getByText('Special')).toBeInTheDocument();
      expect(screen.getByText('All others')).toBeInTheDocument();
      // Condition section for condition-first authoring
      expect(screen.getByText('Links')).toBeInTheDocument();
    });

    it('should group gateways under the Special section', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);
      
      fireEvent.click(screen.getByTitle('Add successor node'));
      
      // Gateways should be visible
      expect(screen.getByText('Exclusive Gateway')).toBeInTheDocument();
      // Elements should also be visible
      expect(screen.getByText('Save Entity')).toBeInTheDocument();
    });
  });

  describe('section grouping', () => {
    it('should show favorites under Recommended section', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);
      
      fireEvent.click(screen.getByTitle('Add successor node'));
      
      // Save Entity is a favorite; it should appear in the popup
      expect(screen.getByText('Save Entity')).toBeInTheDocument();
      // The Recommended section header should be present
      expect(screen.getByText('Recommended')).toBeInTheDocument();
    });

    it('should not show favorite star icon on component items', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);
      
      fireEvent.click(screen.getByTitle('Add successor node'));
      
      // No item should have the .favorite class or .favorite-indicator
      const popup = screen.getByRole('dialog');
      expect(popup.querySelector('.favorite-indicator')).not.toBeInTheDocument();
      expect(popup.querySelector('.favorite')).not.toBeInTheDocument();
    });
  });

  describe('component selection', () => {
    it('should call onAddNode when a component is selected', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);
      
      fireEvent.click(screen.getByTitle('Add successor node'));
      fireEvent.click(screen.getByText('Delete Entity'));
      
      expect(mockOnAddNode).toHaveBeenCalledWith(
        expect.objectContaining({
          plugin: 'action:delete',
          label: 'Delete Entity',
        })
      );
    });

    it('should close popup after selection', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);
      
      fireEvent.click(screen.getByTitle('Add successor node'));
      fireEvent.click(screen.getByText('Delete Entity'));
      
      expect(screen.queryByText('Add Successor')).not.toBeInTheDocument();
    });

    it('should stop event propagation on selection', () => {
      const parentClickHandler = jest.fn();
      
      render(
        <div onClick={parentClickHandler}>
          <QuickAddButton onAddNode={mockOnAddNode} />
        </div>
      );
      
      fireEvent.click(screen.getByTitle('Add successor node'));
      fireEvent.click(screen.getByText('Delete Entity'));
      
      // Parent should not receive the click
      expect(parentClickHandler).not.toHaveBeenCalled();
    });

    it('should call onAddNode with condition component data when a condition is selected', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);

      fireEvent.click(screen.getByTitle('Add successor node'));
      fireEvent.click(screen.getByText('Entity is New'));

      expect(mockOnAddNode).toHaveBeenCalledWith(
        expect.objectContaining({
          plugin: 'condition:is_new',
          label: 'Entity is New',
          type: 'link',
        })
      );
    });
  });

  describe('focus trapping', () => {
    it('should trap Tab focus within the popup', async () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);
      
      fireEvent.click(screen.getByTitle('Add successor node'));
      
      // Wait for popup to appear and focus to be set
      await waitFor(() => {
        expect(screen.getByText('Add Successor')).toBeInTheDocument();
      });

      // Get focusable elements inside the popup
      const searchInput = screen.getByPlaceholderText('Search components...');
      
      // Tab from last focusable element should wrap to first
      // The focus trap hook handles this via keydown on document (capture phase)
      searchInput.focus();
      
      // Verify the popup has role="dialog" and aria-modal="true" (focus trap prerequisites)
      const dialog = screen.getByRole('dialog');
      expect(dialog).toHaveAttribute('aria-modal', 'true');
      expect(dialog).toHaveAttribute('aria-label', 'Add Successor');
    });

    it('should close popup via focus trap Escape handling', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);
      
      fireEvent.click(screen.getByTitle('Add successor node'));
      expect(screen.getByText('Add Successor')).toBeInTheDocument();
      
      // useFocusTrap handles Escape via capture-phase keydown
      fireEvent.keyDown(document, { key: 'Escape' });
      
      expect(screen.queryByText('Add Successor')).not.toBeInTheDocument();
    });
  });

  describe('search visibility threshold', () => {
    afterEach(() => {
      // Restore default mock so subsequent tests are not affected
      const { useComponentStore } = require('../../store/useComponentStore');
      useComponentStore.mockImplementation((selector: any) => {
        const state = {
          components: mockComponents,
          favoriteComponents: mockFavoriteComponents,
        };
        return selector(state);
      });
      const { useContextStore } = require('../../store/useContextStore');
      useContextStore.mockImplementation((selector: any) => {
        const state = {
          selectedContextId: mockSelectedContextId,
          contexts: mockContexts,
          dependencies: [],
        };
        return selector(state);
      });
      const { useGraphStore } = require('../../store/useGraphStore');
      useGraphStore.mockImplementation((selector: any) => {
        const state = {
          nodes: [],
          edges: [],
        };
        return selector(state);
      });
    });

    it('should hide search when fewer than 15 successor components are available', () => {
      const { useComponentStore } = require('../../store/useComponentStore');
      useComponentStore.mockImplementation((selector: any) => {
        const state = {
          components: [
            { plugin: 'action:save', label: 'Save Entity', type: 'element', componentType: 4 },
            { plugin: 'action:delete', label: 'Delete Entity', type: 'element', componentType: 4 },
          ],
          favoriteComponents: {},
        };
        return selector(state);
      });

      render(<QuickAddButton onAddNode={mockOnAddNode} />);

      fireEvent.click(screen.getByTitle('Add successor node'));

      // Popup should open but search should be hidden
      expect(screen.getByText('Add Successor')).toBeInTheDocument();
      expect(screen.queryByPlaceholderText('Search components...')).not.toBeInTheDocument();
    });
  });

  describe('empty state', () => {
    it('should show empty message when no components match search', async () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);
      
      fireEvent.click(screen.getByTitle('Add successor node'));
      
      const searchInput = screen.getByPlaceholderText('Search components...');
      fireEvent.change(searchInput, { target: { value: 'nonexistent' } });
      
      await waitFor(() => {
        expect(screen.getByText('No components found')).toBeInTheDocument();
      });
    });
  });

  describe('documentation button', () => {
    it('should show documentation button for components with documentationUrl', async () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);
      
      fireEvent.click(screen.getByTitle('Add successor node'));
      
      await waitFor(() => {
        // Save Entity and Exclusive Gateway have documentation URLs
        const docButtons = screen.getAllByTestId('doc-button');
        expect(docButtons.length).toBe(2);
      });
    });

    it('should not show documentation button for components without documentationUrl', async () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);
      
      fireEvent.click(screen.getByTitle('Add successor node'));
      
      await waitFor(() => {
        // Only 2 components have documentationUrl
        const docButtons = screen.getAllByTestId('doc-button');
        expect(docButtons.length).toBe(2);
      });
    });
  });

  describe('type filter panel', () => {
    it('should show the filter toggle button when popup is open', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);

      fireEvent.click(screen.getByTitle('Add successor node'));

      const filterToggle = document.querySelector('.quick-add-filter-toggle');
      expect(filterToggle).toBeInTheDocument();
      expect(screen.getByTitle('Filter by type')).toBeInTheDocument();
    });

    it('should expand filter options when toggle is clicked', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);

      fireEvent.click(screen.getByTitle('Add successor node'));

      // Filter options should not be visible initially
      expect(document.querySelector('.quick-add-filter-options')).not.toBeInTheDocument();

      // Click the filter toggle
      fireEvent.click(screen.getByTitle('Filter by type'));

      // Filter options should now be visible
      expect(document.querySelector('.quick-add-filter-options')).toBeInTheDocument();
    });

    it('should show All, Elements, Links, Gateways filter options', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);

      fireEvent.click(screen.getByTitle('Add successor node'));
      fireEvent.click(screen.getByTitle('Filter by type'));

      const filterOptions = document.querySelectorAll('.quick-add-filter-option');
      expect(filterOptions).toHaveLength(4);

      // Check labels match the plural labels from the component store defaults
      const labels = Array.from(filterOptions).map(opt => opt.textContent);
      expect(labels).toEqual(['All', 'Elements', 'Links', 'Gateways']);
    });

    it('should filter to only condition components when "Links" filter is selected', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);

      fireEvent.click(screen.getByTitle('Add successor node'));

      // Initially both actions and conditions are visible
      expect(screen.getByText('Save Entity')).toBeInTheDocument();
      expect(screen.getByText('Entity is New')).toBeInTheDocument();

      // Expand filter panel and select Links
      fireEvent.click(screen.getByTitle('Filter by type'));
      const filterOptions = document.querySelectorAll('.quick-add-filter-option');
      const linksOption = Array.from(filterOptions).find(opt => opt.textContent === 'Links')!;
      fireEvent.click(linksOption);

      // Only condition components (type 'link') should be visible
      expect(screen.getByText('Entity is New')).toBeInTheDocument();

      // Action components should be hidden
      expect(screen.queryByText('Save Entity')).not.toBeInTheDocument();
      expect(screen.queryByText('Delete Entity')).not.toBeInTheDocument();

      // Gateway components should be hidden
      expect(screen.queryByText('Exclusive Gateway')).not.toBeInTheDocument();
    });

    it('should filter to only action components when "Elements" filter is selected', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);

      fireEvent.click(screen.getByTitle('Add successor node'));

      // Expand filter panel and select Elements
      fireEvent.click(screen.getByTitle('Filter by type'));
      const filterOptions = document.querySelectorAll('.quick-add-filter-option');
      const elementsOption = Array.from(filterOptions).find(opt => opt.textContent === 'Elements')!;
      fireEvent.click(elementsOption);

      // Action components (type 'element') should be visible
      expect(screen.getByText('Save Entity')).toBeInTheDocument();
      expect(screen.getByText('Delete Entity')).toBeInTheDocument();

      // Condition components should be hidden
      expect(screen.queryByText('Entity is New')).not.toBeInTheDocument();

      // Gateway components should be hidden
      expect(screen.queryByText('Exclusive Gateway')).not.toBeInTheDocument();
    });

    it('should show all components when "All" filter is selected after using another filter', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);

      fireEvent.click(screen.getByTitle('Add successor node'));

      // First apply a filter to narrow results
      fireEvent.click(screen.getByTitle('Filter by type'));
      const filterOptions = document.querySelectorAll('.quick-add-filter-option');
      const linksOption = Array.from(filterOptions).find(opt => opt.textContent === 'Links')!;
      fireEvent.click(linksOption);

      // Verify filter is active — only conditions visible
      expect(screen.queryByText('Save Entity')).not.toBeInTheDocument();
      expect(screen.getByText('Entity is New')).toBeInTheDocument();

      // Now select "All"
      const allOption = Array.from(filterOptions).find(opt => opt.textContent === 'All')!;
      fireEvent.click(allOption);

      // All component types should be visible again
      expect(screen.getByText('Save Entity')).toBeInTheDocument();
      expect(screen.getByText('Delete Entity')).toBeInTheDocument();
      expect(screen.getByText('Exclusive Gateway')).toBeInTheDocument();
      expect(screen.getByText('Entity is New')).toBeInTheDocument();
    });

    it('should show a filter badge when a non-"All" filter is active', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);

      fireEvent.click(screen.getByTitle('Add successor node'));

      // No badge initially (All is active by default)
      expect(document.querySelector('.quick-add-filter-badge')).not.toBeInTheDocument();

      // Expand and select a non-All filter
      fireEvent.click(screen.getByTitle('Filter by type'));
      const filterOptions = document.querySelectorAll('.quick-add-filter-option');
      const gatewaysOption = Array.from(filterOptions).find(opt => opt.textContent === 'Gateways')!;
      fireEvent.click(gatewaysOption);

      // Badge should now be visible
      const badge = document.querySelector('.quick-add-filter-badge');
      expect(badge).toBeInTheDocument();
      expect(badge!.textContent).toBe('1');
    });

    it('should reset filter when popup is closed and reopened', () => {
      render(<QuickAddButton onAddNode={mockOnAddNode} />);

      // Open popup and apply a filter
      fireEvent.click(screen.getByTitle('Add successor node'));
      fireEvent.click(screen.getByTitle('Filter by type'));
      const filterOptions = document.querySelectorAll('.quick-add-filter-option');
      const linksOption = Array.from(filterOptions).find(opt => opt.textContent === 'Links')!;
      fireEvent.click(linksOption);

      // Verify filter is active
      expect(screen.queryByText('Save Entity')).not.toBeInTheDocument();
      expect(screen.getByText('Entity is New')).toBeInTheDocument();

      // Close popup
      fireEvent.click(screen.getByTitle('Close'));
      expect(screen.queryByText('Add Successor')).not.toBeInTheDocument();

      // Reopen popup
      fireEvent.click(screen.getByTitle('Add successor node'));

      // Filter should be reset — all components visible
      expect(screen.getByText('Save Entity')).toBeInTheDocument();
      expect(screen.getByText('Delete Entity')).toBeInTheDocument();
      expect(screen.getByText('Exclusive Gateway')).toBeInTheDocument();
      expect(screen.getByText('Entity is New')).toBeInTheDocument();

      // Filter panel should be collapsed
      expect(document.querySelector('.quick-add-filter-options')).not.toBeInTheDocument();

      // No badge should be visible
      expect(document.querySelector('.quick-add-filter-badge')).not.toBeInTheDocument();
    });
  });

  describe('context filtering', () => {
    afterEach(() => {
      mockSelectedContextId = null;
      mockContexts = [];
    });

    it('should show all successor components when no context is selected', () => {
      mockSelectedContextId = null;
      mockContexts = [{
        id: 'ctx_1',
        topic: 'Test',
        model_owner: 'test_owner',
        components: { element: { plugins: ['action:save'] } },
      }];

      render(<QuickAddButton onAddNode={mockOnAddNode} />);
      fireEvent.click(screen.getByTitle('Add successor node'));

      expect(screen.getByText('Save Entity')).toBeInTheDocument();
      expect(screen.getByText('Delete Entity')).toBeInTheDocument();
      expect(screen.getByText('Exclusive Gateway')).toBeInTheDocument();
    });

    it('should only show plugins from the selected context', () => {
      mockSelectedContextId = 'ctx_1';
      mockContexts = [{
        id: 'ctx_1',
        topic: 'Content Editing',
        model_owner: 'test_owner',
        components: {
          element: { plugins: ['action:save'] },
        },
      }];

      render(<QuickAddButton onAddNode={mockOnAddNode} />);
      fireEvent.click(screen.getByTitle('Add successor node'));

      // action:save is in context
      expect(screen.getByText('Save Entity')).toBeInTheDocument();
      // action:delete is NOT in context
      expect(screen.queryByText('Delete Entity')).not.toBeInTheDocument();
      // gateway:exclusive is always available (merged via mergeOthersCategory)
      expect(screen.getByText('Exclusive Gateway')).toBeInTheDocument();
    });

    it('should ignore favorite status when a context is selected', () => {
      mockSelectedContextId = 'ctx_1';
      mockContexts = [{
        id: 'ctx_1',
        topic: 'Content Editing',
        model_owner: 'test_owner',
        components: {
          element: { plugins: ['action:save', 'action:delete', 'gateway:exclusive'] },
        },
      }];

      render(<QuickAddButton onAddNode={mockOnAddNode} />);
      fireEvent.click(screen.getByTitle('Add successor node'));

      // Save Entity is normally a favorite but should NOT have the favorite class
      const saveEntityButton = screen.getByText('Save Entity').closest('button');
      expect(saveEntityButton).not.toHaveClass('favorite');

      // No Recommended section should appear when a context is selected (favorites are ignored)
      expect(screen.queryByText('Recommended')).not.toBeInTheDocument();

      // Gateways should still be under Special, elements under All others
      expect(screen.getByText('Special')).toBeInTheDocument();
      expect(screen.getByText('All others')).toBeInTheDocument();

      // All components should still be visible
      expect(screen.getByText('Delete Entity')).toBeInTheDocument();
      expect(screen.getByText('Exclusive Gateway')).toBeInTheDocument();
      expect(screen.getByText('Save Entity')).toBeInTheDocument();
    });

    it('should still exclude Events even when they are in context', () => {
      mockSelectedContextId = 'ctx_1';
      mockContexts = [{
        id: 'ctx_1',
        topic: 'All Plugins',
        model_owner: 'test_owner',
        components: {
          start: { plugins: ['event:insert'] },
          element: { plugins: ['action:save'] },
        },
      }];

      render(<QuickAddButton onAddNode={mockOnAddNode} />);
      fireEvent.click(screen.getByTitle('Add successor node'));

      // Events are still excluded by QuickAddButton's type filter
      expect(screen.queryByText('Entity Insert')).not.toBeInTheDocument();
      // But actions pass through
      expect(screen.getByText('Save Entity')).toBeInTheDocument();
    });
  });
});
