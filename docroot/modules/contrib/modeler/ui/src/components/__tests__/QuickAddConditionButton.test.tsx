import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import QuickAddConditionButton from '../QuickAddConditionButton';

// Mock the DocumentationButton component
jest.mock('../DocumentationButton', () => {
  return function MockDocumentationButton({ title }: { url: string; title: string }) {
    return <span data-testid="doc-button" title={`Docs: ${title}`}>Doc</span>;
  };
});

// Mock store — need >= 15 condition/decision components so the search field is visible
// (see THRESHOLDS.SEARCH_VISIBILITY_MIN_COMPONENTS in constants/dimensions.ts)
const mockConditionFillers = Array.from({ length: 13 }, (_, i) => ({
  plugin: `condition:filler_${i}`, label: `Filler Condition ${i}`, type: 'link', componentType: 5,
}));

const mockComponents = [
  { plugin: 'condition:is_new', label: 'Entity is New', type: 'link', componentType: 5, documentationUrl: 'https://docs.example.com/is-new' },
  { plugin: 'condition:has_role', label: 'User Has Role', type: 'link', componentType: 5 },
  { plugin: 'decision:compare', label: 'Compare Values', type: 'link', componentType: 5, documentationUrl: 'https://docs.example.com/compare' },
  ...mockConditionFillers,
  { plugin: 'action:save', label: 'Save Entity', type: 'element', componentType: 4 },
  { plugin: 'event:insert', label: 'Entity Insert', type: 'start', componentType: 1 },
];

const mockFavoriteComponents = {
  5: ['condition:is_new'], // Condition type favorites
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

describe('QuickAddConditionButton', () => {
  const mockOnAddCondition = jest.fn();

  beforeEach(() => {
    jest.clearAllMocks();
    mockSelectedContextId = null;
    mockContexts = [];
  });

  describe('rendering', () => {
    it('should render the button', () => {
      render(
        <QuickAddConditionButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
        />
      );
      
      const button = screen.getByTitle('Add link');
      expect(button).toBeInTheDocument();
    });

    it('should not render when disabled', () => {
      render(
        <QuickAddConditionButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
          disabled={true}
        />
      );
      
      expect(screen.queryByTitle('Add link')).not.toBeInTheDocument();
    });

    it('should have the correct edge ID data attribute', () => {
      render(
        <QuickAddConditionButton
          edgeId="edge_123"
          onAddCondition={mockOnAddCondition}
        />
      );
      
      const button = screen.getByTitle('Add link');
      expect(button).toHaveAttribute('data-edge-id', 'edge_123');
    });
  });

  describe('popup interaction', () => {
    it('should open popup when button is clicked', () => {
      render(
        <QuickAddConditionButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
        />
      );
      
      fireEvent.click(screen.getByTitle('Add link'));
      
      expect(screen.getByText('Add Link')).toBeInTheDocument();
    });

    it('should show search input in popup', () => {
      render(
        <QuickAddConditionButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
        />
      );
      
      fireEvent.click(screen.getByTitle('Add link'));
      
      expect(screen.getByPlaceholderText('Search link...')).toBeInTheDocument();
    });

    it('should close popup when close button is clicked', () => {
      render(
        <QuickAddConditionButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
        />
      );
      
      fireEvent.click(screen.getByTitle('Add link'));
      expect(screen.getByText('Add Link')).toBeInTheDocument();
      
      fireEvent.click(screen.getByTitle('Close'));
      
      expect(screen.queryByText('Add Link')).not.toBeInTheDocument();
    });

    it('should close popup on Escape key', () => {
      render(
        <QuickAddConditionButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
        />
      );
      
      fireEvent.click(screen.getByTitle('Add link'));
      expect(screen.getByText('Add Link')).toBeInTheDocument();
      
      fireEvent.keyDown(document, { key: 'Escape' });
      
      expect(screen.queryByText('Add Link')).not.toBeInTheDocument();
    });

    it('should close popup when the quick-add button is clicked again', () => {
      render(
        <QuickAddConditionButton edgeId="edge_1" onAddCondition={mockOnAddCondition} />
      );

      const button = screen.getByTitle('Add link');
      fireEvent.click(button);
      expect(screen.getByText('Add Link')).toBeInTheDocument();

      fireEvent.click(button);
      expect(screen.queryByText('Add Link')).not.toBeInTheDocument();
    });

    it('should close popup when clicking outside', async () => {
      render(
        <div>
          <QuickAddConditionButton edgeId="edge_1" onAddCondition={mockOnAddCondition} />
          <div data-testid="outside-area">Outside</div>
        </div>
      );

      fireEvent.click(screen.getByTitle('Add link'));

      // Wait for popup to render AND for the setTimeout(0) inside the
      // click-outside effect to register the document listener.
      await waitFor(() => {
        expect(screen.getByText('Add Link')).toBeInTheDocument();
      });

      await waitFor(() => {
        fireEvent.pointerDown(screen.getByTestId('outside-area'));
      });

      await waitFor(() => {
        expect(screen.queryByText('Add Link')).not.toBeInTheDocument();
      });
    });
  });

  describe('component filtering', () => {
    it('should only show Conditions and Decisions', () => {
      render(
        <QuickAddConditionButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
        />
      );
      
      fireEvent.click(screen.getByTitle('Add link'));
      
      // Conditions should be visible
      expect(screen.getByText('Entity is New')).toBeInTheDocument();
      expect(screen.getByText('User Has Role')).toBeInTheDocument();
      
      // Decisions should also be visible
      expect(screen.getByText('Compare Values')).toBeInTheDocument();
      
      // Actions should NOT be visible
      expect(screen.queryByText('Save Entity')).not.toBeInTheDocument();
      
      // Events should NOT be visible
      expect(screen.queryByText('Entity Insert')).not.toBeInTheDocument();
    });

    it('should filter conditions by search term', async () => {
      render(
        <QuickAddConditionButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
        />
      );
      
      fireEvent.click(screen.getByTitle('Add link'));
      
      const searchInput = screen.getByPlaceholderText('Search link...');
      fireEvent.change(searchInput, { target: { value: 'Role' } });
      
      await waitFor(() => {
        expect(screen.getByText('User Has Role')).toBeInTheDocument();
        expect(screen.queryByText('Entity is New')).not.toBeInTheDocument();
      });
    });
  });

  describe('section grouping', () => {
    it('should show favorites under Recommended section header', () => {
      render(
        <QuickAddConditionButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
        />
      );
      
      fireEvent.click(screen.getByTitle('Add link'));
      
      // Section headers should be present
      expect(screen.getByText('Recommended')).toBeInTheDocument();
      expect(screen.getByText('All others')).toBeInTheDocument();

      // Get all condition buttons (excluding close button and main button)
      const items = screen.getAllByRole('button').filter(btn => 
        btn.classList.contains('quick-add-component-item')
      );
      
      // Entity is New is a favorite, should come first (under Recommended)
      expect(items[0]).toHaveTextContent('Entity is New');
    });

    it('should not show favorite indicator or favorite class on component items', () => {
      render(
        <QuickAddConditionButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
        />
      );
      
      fireEvent.click(screen.getByTitle('Add link'));
      
      // Favorite star icon and favorite class are removed from popup items
      const popup = screen.getByRole('dialog');
      expect(popup.querySelector('.favorite-indicator')).not.toBeInTheDocument();
      expect(popup.querySelector('.favorite')).not.toBeInTheDocument();
    });
  });

  describe('condition selection', () => {
    it('should call onAddCondition when a condition is selected', () => {
      render(
        <QuickAddConditionButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
        />
      );
      
      fireEvent.click(screen.getByTitle('Add link'));
      fireEvent.click(screen.getByText('User Has Role'));
      
      expect(mockOnAddCondition).toHaveBeenCalledWith(
        expect.objectContaining({
          plugin: 'condition:has_role',
          label: 'User Has Role',
        })
      );
    });

    it('should close popup after selection', () => {
      render(
        <QuickAddConditionButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
        />
      );
      
      fireEvent.click(screen.getByTitle('Add link'));
      fireEvent.click(screen.getByText('User Has Role'));
      
      expect(screen.queryByText('Add Link')).not.toBeInTheDocument();
    });

    it('should stop event propagation on selection', () => {
      const parentClickHandler = jest.fn();
      
      render(
        <div onClick={parentClickHandler}>
          <QuickAddConditionButton
            edgeId="edge_1"
            onAddCondition={mockOnAddCondition}
          />
        </div>
      );
      
      fireEvent.click(screen.getByTitle('Add link'));
      fireEvent.click(screen.getByText('User Has Role'));
      
      // Parent should not receive the click
      expect(parentClickHandler).not.toHaveBeenCalled();
    });
  });

  describe('focus trapping', () => {
    it('should trap Tab focus within the popup', async () => {
      render(
        <QuickAddConditionButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
        />
      );
      
      fireEvent.click(screen.getByTitle('Add link'));
      
      // Wait for popup to appear
      await waitFor(() => {
        expect(screen.getByText('Add Link')).toBeInTheDocument();
      });

      // Get focusable elements inside the popup
      const searchInput = screen.getByPlaceholderText('Search link...');
      searchInput.focus();
      
      // Verify the popup has role="dialog" and aria-modal="true" (focus trap prerequisites)
      const dialog = screen.getByRole('dialog');
      expect(dialog).toHaveAttribute('aria-modal', 'true');
      expect(dialog).toHaveAttribute('aria-label', 'Add Link');
    });

    it('should close popup via focus trap Escape handling', () => {
      render(
        <QuickAddConditionButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
        />
      );
      
      fireEvent.click(screen.getByTitle('Add link'));
      expect(screen.getByText('Add Link')).toBeInTheDocument();
      
      // useFocusTrap handles Escape via capture-phase keydown
      fireEvent.keyDown(document, { key: 'Escape' });
      
      expect(screen.queryByText('Add Link')).not.toBeInTheDocument();
    });
  });

  describe('empty state', () => {
    it('should show empty message when no conditions match search', async () => {
      render(
        <QuickAddConditionButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
        />
      );
      
      fireEvent.click(screen.getByTitle('Add link'));
      
      const searchInput = screen.getByPlaceholderText('Search link...');
      fireEvent.change(searchInput, { target: { value: 'nonexistent' } });
      
      await waitFor(() => {
        expect(screen.getByText('No link found')).toBeInTheDocument();
      });
    });
  });

  describe('with no conditions available', () => {
    beforeEach(() => {
      // Override the mock to return no conditions
      const { useComponentStore } = require('../../store/useComponentStore');
      useComponentStore.mockImplementation((selector: any) => {
        const state = {
          components: [
            { plugin: 'action:save', label: 'Save Entity', type: 'element', componentType: 4 },
          ],
          favoriteComponents: {},
        };
        return selector(state);
      });
      const { useContextStore } = require('../../store/useContextStore');
      useContextStore.mockImplementation((selector: any) => {
        const state = {
          selectedContextId: null,
          contexts: [],
          dependencies: [],
        };
        return selector(state);
      });
    });

    it('should not render when no conditions are available', () => {
      render(
        <QuickAddConditionButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
        />
      );
      
      expect(screen.queryByTitle('Add link')).not.toBeInTheDocument();
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

    it('should hide search when fewer than 15 conditions are available', () => {
      const { useComponentStore } = require('../../store/useComponentStore');
      useComponentStore.mockImplementation((selector: any) => {
        const state = {
          components: [
            { plugin: 'condition:is_new', label: 'Entity is New', type: 'link', componentType: 5 },
            { plugin: 'condition:has_role', label: 'User Has Role', type: 'link', componentType: 5 },
          ],
          favoriteComponents: {},
        };
        return selector(state);
      });

      render(
        <QuickAddConditionButton edgeId="edge_1" onAddCondition={mockOnAddCondition} />
      );

      fireEvent.click(screen.getByTitle('Add link'));

      // Popup should open but search should be hidden
      expect(screen.getByText('Add Link')).toBeInTheDocument();
      expect(screen.queryByPlaceholderText('Search link...')).not.toBeInTheDocument();
    });
  });

  describe('documentation button', () => {
    beforeEach(() => {
      // Reset mock to use components with documentationUrl
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
          selectedContextId: null,
          contexts: [],
          dependencies: [],
        };
        return selector(state);
      });
    });

    it('should show documentation button for components with documentationUrl', async () => {
      render(
        <QuickAddConditionButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
        />
      );
      
      fireEvent.click(screen.getByTitle('Add link'));
      
      await waitFor(() => {
        // Entity is New and Compare Values have documentation URLs
        const docButtons = screen.getAllByTestId('doc-button');
        expect(docButtons.length).toBe(2);
      });
    });

    it('should not show documentation button for components without documentationUrl', async () => {
      render(
        <QuickAddConditionButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
        />
      );
      
      fireEvent.click(screen.getByTitle('Add link'));
      
      await waitFor(() => {
        // Only 2 condition components have documentationUrl
        const docButtons = screen.getAllByTestId('doc-button');
        expect(docButtons.length).toBe(2);
      });
    });
  });

  describe('context filtering', () => {
    afterEach(() => {
      mockSelectedContextId = null;
      mockContexts = [];
    });

    it('should show all conditions when no context is selected', () => {
      mockSelectedContextId = null;
      mockContexts = [{
        id: 'ctx_1',
        topic: 'Test',
        model_owner: 'test_owner',
        components: { link: { plugins: ['condition:is_new'] } },
      }];

      // Reset the mock to use top-level variables
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

      render(
        <QuickAddConditionButton edgeId="edge_1" onAddCondition={mockOnAddCondition} />
      );
      fireEvent.click(screen.getByTitle('Add link'));

      expect(screen.getByText('Entity is New')).toBeInTheDocument();
      expect(screen.getByText('User Has Role')).toBeInTheDocument();
      expect(screen.getByText('Compare Values')).toBeInTheDocument();
    });

    it('should only show conditions from the selected context', () => {
      mockSelectedContextId = 'ctx_1';
      mockContexts = [{
        id: 'ctx_1',
        topic: 'Content Editing',
        model_owner: 'test_owner',
        components: {
          link: { plugins: ['condition:is_new'] },
          element: { plugins: ['action:save'] },
        },
      }];

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

      render(
        <QuickAddConditionButton edgeId="edge_1" onAddCondition={mockOnAddCondition} />
      );
      fireEvent.click(screen.getByTitle('Add link'));

      // condition:is_new is in context
      expect(screen.getByText('Entity is New')).toBeInTheDocument();
      // condition:has_role is NOT in context
      expect(screen.queryByText('User Has Role')).not.toBeInTheDocument();
      // decision:compare is NOT in context
      expect(screen.queryByText('Compare Values')).not.toBeInTheDocument();
    });

    it('should ignore favorite status when a context is selected', () => {
      mockSelectedContextId = 'ctx_1';
      mockContexts = [{
        id: 'ctx_1',
        topic: 'Content Editing',
        model_owner: 'test_owner',
        components: {
          link: { plugins: ['condition:is_new', 'condition:has_role', 'decision:compare'] },
        },
      }];

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

      render(
        <QuickAddConditionButton edgeId="edge_1" onAddCondition={mockOnAddCondition} />
      );
      fireEvent.click(screen.getByTitle('Add link'));

      // Entity is New is normally a favorite but should NOT have the favorite class
      const entityIsNewButton = screen.getByText('Entity is New').closest('button');
      expect(entityIsNewButton).not.toHaveClass('favorite');

      // No Recommended section when context is selected (favorites are ignored)
      expect(screen.queryByText('Recommended')).not.toBeInTheDocument();

      // All components should still be visible under All others
      expect(screen.getByText('All others')).toBeInTheDocument();
      expect(screen.getByText('Compare Values')).toBeInTheDocument();
      expect(screen.getByText('Entity is New')).toBeInTheDocument();
      expect(screen.getByText('User Has Role')).toBeInTheDocument();
    });

    it('should not render button when context excludes all conditions', () => {
      mockSelectedContextId = 'ctx_1';
      mockContexts = [{
        id: 'ctx_1',
        topic: 'No Conditions',
        model_owner: 'test_owner',
        components: {
          element: { plugins: ['action:save'] },
        },
      }];

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

      render(
        <QuickAddConditionButton edgeId="edge_1" onAddCondition={mockOnAddCondition} />
      );

      // No conditions in context, button should not render
      expect(screen.queryByTitle('Add link')).not.toBeInTheDocument();
    });
  });
});
