import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import QuickAddEventButton from '../QuickAddEventButton';

// Mock the DocumentationButton component
jest.mock('../DocumentationButton', () => {
  return function MockDocumentationButton({ title }: { url: string; title: string }) {
    return <span data-testid="doc-button" title={`Docs: ${title}`}>Doc</span>;
  };
});

// Mock the store — need >= 15 event/trigger components so the search field is visible
// (see THRESHOLDS.SEARCH_VISIBILITY_MIN_COMPONENTS in constants/dimensions.ts)
const mockEventFillers = Array.from({ length: 12 }, (_, i) => ({
  plugin: `event:filler_${i}`, label: `Filler Event ${i}`, type: 'start', componentType: 1,
}));

const mockEventComponents = [
  { plugin: 'content_entity:insert', label: 'Content Insert', type: 'start', componentType: 1 },
  { plugin: 'cron', label: 'Cron Run', type: 'start', componentType: 1, documentationUrl: 'https://docs.example.com/cron' },
  { plugin: 'user:login', label: 'User Login', type: 'start', componentType: 1 },
  { plugin: 'trigger:manual', label: 'Manual Trigger', type: 'start', componentType: 1, documentationUrl: 'https://docs.example.com/trigger' },
  ...mockEventFillers,
];

const mockActionComponents = [
  { plugin: 'entity:save', label: 'Save Entity', type: 'element', componentType: 4 },
];

const mockAllComponents = [...mockEventComponents, ...mockActionComponents];

let mockSelectedContextId: string | null = null;
let mockContexts: any[] = [];

jest.mock('../../store/useComponentStore', () => ({
  useComponentStore: jest.fn((selector) => {
    const state = {
      components: mockAllComponents,
      favoriteComponents: { 1: ['cron'] }, // Cron Run is a favorite
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

describe('QuickAddEventButton', () => {
  const mockOnAddEvent = jest.fn();

  beforeEach(() => {
    jest.clearAllMocks();
    mockSelectedContextId = null;
    mockContexts = [];
  });

  describe('Rendering', () => {
    it('should render the button with correct text', () => {
      render(<QuickAddEventButton onAddEvent={mockOnAddEvent} />);
      
      expect(screen.getByRole('button', { name: /new start/i })).toBeInTheDocument();
    });

    it('should render the button with an icon', () => {
      render(<QuickAddEventButton onAddEvent={mockOnAddEvent} />);
      
      const button = screen.getByRole('button', { name: /new start/i });
      expect(button.querySelector('svg')).toBeInTheDocument();
    });

    it('should not render when disabled', () => {
      const { container } = render(<QuickAddEventButton onAddEvent={mockOnAddEvent} disabled />);
      
      expect(container.firstChild).toBeNull();
    });
  });

  describe('Popup Behavior', () => {
    it('should open popup when button is clicked', async () => {
      render(<QuickAddEventButton onAddEvent={mockOnAddEvent} />);
      
      const button = screen.getByRole('button', { name: /new start/i });
      fireEvent.click(button);
      
      await waitFor(() => {
        expect(screen.getByPlaceholderText(/search start/i)).toBeInTheDocument();
      });
    });

    it('should show search input in popup', async () => {
      render(<QuickAddEventButton onAddEvent={mockOnAddEvent} />);
      
      fireEvent.click(screen.getByRole('button', { name: /new start/i }));
      
      await waitFor(() => {
        expect(screen.getByPlaceholderText(/search start/i)).toBeInTheDocument();
      });
    });

    it('should close popup when close button is clicked', async () => {
      render(<QuickAddEventButton onAddEvent={mockOnAddEvent} />);
      
      fireEvent.click(screen.getByRole('button', { name: /new start/i }));
      
      await waitFor(() => {
        expect(screen.getByPlaceholderText(/search start/i)).toBeInTheDocument();
      });
      
      const closeButton = screen.getByTitle('Close');
      fireEvent.click(closeButton);
      
      await waitFor(() => {
        expect(screen.queryByPlaceholderText(/search start/i)).not.toBeInTheDocument();
      });
    });

    it('should close popup on Escape key', async () => {
      render(<QuickAddEventButton onAddEvent={mockOnAddEvent} />);
      
      fireEvent.click(screen.getByRole('button', { name: /new start/i }));
      
      await waitFor(() => {
        expect(screen.getByPlaceholderText(/search start/i)).toBeInTheDocument();
      });
      
      fireEvent.keyDown(document, { key: 'Escape' });
      
      await waitFor(() => {
        expect(screen.queryByPlaceholderText(/search start/i)).not.toBeInTheDocument();
      });
    });

    it('should close popup when the quick-add button is clicked again', async () => {
      render(<QuickAddEventButton onAddEvent={mockOnAddEvent} />);

      const button = screen.getByRole('button', { name: /new start/i });
      fireEvent.click(button);

      await waitFor(() => {
        expect(screen.getByPlaceholderText(/search start/i)).toBeInTheDocument();
      });

      fireEvent.click(button);

      await waitFor(() => {
        expect(screen.queryByPlaceholderText(/search start/i)).not.toBeInTheDocument();
      });
    });

    it('should close popup when clicking outside', async () => {
      render(
        <div>
          <QuickAddEventButton onAddEvent={mockOnAddEvent} />
          <div data-testid="outside-area">Outside</div>
        </div>
      );

      fireEvent.click(screen.getByRole('button', { name: /new start/i }));

      await waitFor(() => {
        expect(screen.getByPlaceholderText(/search start/i)).toBeInTheDocument();
      });

      await waitFor(() => {
        fireEvent.pointerDown(screen.getByTestId('outside-area'));
      });

      await waitFor(() => {
        expect(screen.queryByPlaceholderText(/search start/i)).not.toBeInTheDocument();
      });
    });
  });

  describe('Component Filtering', () => {
    it('should only show event and trigger components', async () => {
      render(<QuickAddEventButton onAddEvent={mockOnAddEvent} />);
      
      fireEvent.click(screen.getByRole('button', { name: /new start/i }));
      
      await waitFor(() => {
        // Should show events
        expect(screen.getByText('Content Insert')).toBeInTheDocument();
        expect(screen.getByText('Cron Run')).toBeInTheDocument();
        expect(screen.getByText('User Login')).toBeInTheDocument();
        // Should show triggers
        expect(screen.getByText('Manual Trigger')).toBeInTheDocument();
      });
      
      // Should NOT show actions
      expect(screen.queryByText('Save Entity')).not.toBeInTheDocument();
    });

    it('should filter components by search term', async () => {
      render(<QuickAddEventButton onAddEvent={mockOnAddEvent} />);
      
      fireEvent.click(screen.getByRole('button', { name: /new start/i }));
      
      await waitFor(() => {
        expect(screen.getByPlaceholderText(/search start/i)).toBeInTheDocument();
      });
      
      const searchInput = screen.getByPlaceholderText(/search start/i);
      await userEvent.type(searchInput, 'user');
      
      await waitFor(() => {
        expect(screen.getByText('User Login')).toBeInTheDocument();
        expect(screen.queryByText('Cron Run')).not.toBeInTheDocument();
        expect(screen.queryByText('Content Insert')).not.toBeInTheDocument();
      });
    });

    it('should show favorites under Recommended section header', async () => {
      render(<QuickAddEventButton onAddEvent={mockOnAddEvent} />);
      
      fireEvent.click(screen.getByRole('button', { name: /new start/i }));
      
      await waitFor(() => {
        // Section headers should be present
        expect(screen.getByText('Recommended')).toBeInTheDocument();
        expect(screen.getByText('All others')).toBeInTheDocument();

        const items = screen.getAllByRole('button').filter(btn => 
          btn.classList.contains('quick-add-component-item')
        );
        // Cron Run (favorite) should be first
        expect(items[0]).toHaveTextContent('Cron Run');
      });
    });

    it('should show "No start found" when search has no results', async () => {
      render(<QuickAddEventButton onAddEvent={mockOnAddEvent} />);
      
      fireEvent.click(screen.getByRole('button', { name: /new start/i }));
      
      await waitFor(() => {
        expect(screen.getByPlaceholderText(/search start/i)).toBeInTheDocument();
      });
      
      const searchInput = screen.getByPlaceholderText(/search start/i);
      await userEvent.type(searchInput, 'nonexistent');
      
      await waitFor(() => {
        expect(screen.getByText('No start found')).toBeInTheDocument();
      });
    });
  });

  describe('Component Selection', () => {
    it('should call onAddEvent when a component is clicked', async () => {
      render(<QuickAddEventButton onAddEvent={mockOnAddEvent} />);
      
      fireEvent.click(screen.getByRole('button', { name: /new start/i }));
      
      await waitFor(() => {
        expect(screen.getByText('Content Insert')).toBeInTheDocument();
      });
      
      fireEvent.click(screen.getByText('Content Insert'));
      
      expect(mockOnAddEvent).toHaveBeenCalledTimes(1);
      expect(mockOnAddEvent).toHaveBeenCalledWith(
        expect.objectContaining({
          plugin: 'content_entity:insert',
          label: 'Content Insert',
          type: 'start',
        })
      );
    });

    it('should close popup after selecting a component', async () => {
      render(<QuickAddEventButton onAddEvent={mockOnAddEvent} />);
      
      fireEvent.click(screen.getByRole('button', { name: /new start/i }));
      
      await waitFor(() => {
        expect(screen.getByText('Content Insert')).toBeInTheDocument();
      });
      
      fireEvent.click(screen.getByText('Content Insert'));
      
      await waitFor(() => {
        expect(screen.queryByPlaceholderText(/search start/i)).not.toBeInTheDocument();
      });
    });

    it('should clear search term after closing popup', async () => {
      render(<QuickAddEventButton onAddEvent={mockOnAddEvent} />);
      
      fireEvent.click(screen.getByRole('button', { name: /new start/i }));
      
      await waitFor(() => {
        expect(screen.getByPlaceholderText(/search start/i)).toBeInTheDocument();
      });
      
      const searchInput = screen.getByPlaceholderText(/search start/i);
      await userEvent.type(searchInput, 'cron');
      
      // Click on a visible result
      await waitFor(() => {
        expect(screen.getByText('Cron Run')).toBeInTheDocument();
      });
      
      fireEvent.click(screen.getByText('Cron Run'));
      
      // Reopen popup
      fireEvent.click(screen.getByRole('button', { name: /new start/i }));
      
      await waitFor(() => {
        const newSearchInput = screen.getByPlaceholderText(/search start/i);
        expect(newSearchInput).toHaveValue('');
      });
    });
  });

  describe('Focus Trapping', () => {
    it('should trap Tab focus within the popup', async () => {
      render(<QuickAddEventButton onAddEvent={mockOnAddEvent} />);
      
      fireEvent.click(screen.getByRole('button', { name: /new start/i }));
      
      // Wait for popup to appear
      await waitFor(() => {
        expect(screen.getByPlaceholderText(/search start/i)).toBeInTheDocument();
      });

      // Get focusable elements inside the popup
      const searchInput = screen.getByPlaceholderText(/search start/i);
      searchInput.focus();
      
      // Verify the popup has role="dialog" and aria-modal="true" (focus trap prerequisites)
      const dialog = screen.getByRole('dialog');
      expect(dialog).toHaveAttribute('aria-modal', 'true');
      expect(dialog).toHaveAttribute('aria-label', 'Add Start');
    });

    it('should close popup via focus trap Escape handling', async () => {
      render(<QuickAddEventButton onAddEvent={mockOnAddEvent} />);
      
      fireEvent.click(screen.getByRole('button', { name: /new start/i }));
      
      await waitFor(() => {
        expect(screen.getByPlaceholderText(/search start/i)).toBeInTheDocument();
      });
      
      // useFocusTrap handles Escape via capture-phase keydown
      fireEvent.keyDown(document, { key: 'Escape' });
      
      await waitFor(() => {
        expect(screen.queryByPlaceholderText(/search start/i)).not.toBeInTheDocument();
      });
    });
  });

  describe('Search Visibility Threshold', () => {
    afterEach(() => {
      // Restore default mock so subsequent tests are not affected
      const { useComponentStore } = require('../../store/useComponentStore');
      useComponentStore.mockImplementation((selector: any) => {
        const state = {
          components: mockAllComponents,
          favoriteComponents: { 1: ['cron'] },
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

    it('should hide search when fewer than 15 event components are available', async () => {
      const { useComponentStore } = require('../../store/useComponentStore');
      useComponentStore.mockImplementation((selector: any) => {
        const state = {
          components: [
            { plugin: 'content_entity:insert', label: 'Content Insert', type: 'start', componentType: 1 },
            { plugin: 'cron', label: 'Cron Run', type: 'start', componentType: 1 },
          ],
          favoriteComponents: {},
        };
        return selector(state);
      });

      render(<QuickAddEventButton onAddEvent={mockOnAddEvent} />);

      fireEvent.click(screen.getByRole('button', { name: /new start/i }));

      await waitFor(() => {
        // Popup should open but search should be hidden
        expect(screen.getByText('Content Insert')).toBeInTheDocument();
      });

      expect(screen.queryByPlaceholderText(/search start/i)).not.toBeInTheDocument();
    });
  });

  describe('Controlled Mode', () => {
    it('should open popup when isOpen prop is true', async () => {
      render(<QuickAddEventButton onAddEvent={mockOnAddEvent} isOpen={true} />);
      
      await waitFor(() => {
        expect(screen.getByPlaceholderText(/search start/i)).toBeInTheDocument();
      });
    });

    it('should not open popup when isOpen prop is false', () => {
      render(<QuickAddEventButton onAddEvent={mockOnAddEvent} isOpen={false} />);
      
      expect(screen.queryByPlaceholderText(/search start/i)).not.toBeInTheDocument();
    });

    it('should call onOpenChange when popup opens via button click', async () => {
      const mockOnOpenChange = jest.fn();
      render(
        <QuickAddEventButton 
          onAddEvent={mockOnAddEvent} 
          onOpenChange={mockOnOpenChange}
        />
      );
      
      fireEvent.click(screen.getByRole('button', { name: /new start/i }));
      
      await waitFor(() => {
        expect(mockOnOpenChange).toHaveBeenCalledWith(true);
      });
    });

    it('should call onOpenChange when popup closes', async () => {
      const mockOnOpenChange = jest.fn();
      render(
        <QuickAddEventButton 
          onAddEvent={mockOnAddEvent}
          isOpen={true}
          onOpenChange={mockOnOpenChange}
        />
      );
      
      await waitFor(() => {
        expect(screen.getByPlaceholderText(/search start/i)).toBeInTheDocument();
      });
      
      const closeButton = screen.getByTitle('Close');
      fireEvent.click(closeButton);
      
      await waitFor(() => {
        expect(mockOnOpenChange).toHaveBeenCalledWith(false);
      });
    });

    it('should call onOpenChange when component is selected', async () => {
      const mockOnOpenChange = jest.fn();
      render(
        <QuickAddEventButton 
          onAddEvent={mockOnAddEvent}
          isOpen={true}
          onOpenChange={mockOnOpenChange}
        />
      );
      
      await waitFor(() => {
        expect(screen.getByText('Content Insert')).toBeInTheDocument();
      });
      
      fireEvent.click(screen.getByText('Content Insert'));
      
      expect(mockOnOpenChange).toHaveBeenCalledWith(false);
    });

    it('should call onOpenChange when Escape key is pressed', async () => {
      const mockOnOpenChange = jest.fn();
      render(
        <QuickAddEventButton 
          onAddEvent={mockOnAddEvent}
          isOpen={true}
          onOpenChange={mockOnOpenChange}
        />
      );
      
      await waitFor(() => {
        expect(screen.getByPlaceholderText(/search start/i)).toBeInTheDocument();
      });
      
      fireEvent.keyDown(document, { key: 'Escape' });
      
      await waitFor(() => {
        expect(mockOnOpenChange).toHaveBeenCalledWith(false);
      });
    });
  });

  describe('Documentation Button', () => {
    it('should show documentation button for components with documentationUrl', async () => {
      render(<QuickAddEventButton onAddEvent={mockOnAddEvent} />);
      
      fireEvent.click(screen.getByRole('button', { name: /new start/i }));
      
      await waitFor(() => {
        // Cron Run and Manual Trigger have documentation URLs
        const docButtons = screen.getAllByTestId('doc-button');
        expect(docButtons.length).toBe(2);
      });
    });

    it('should not show documentation button for components without documentationUrl', async () => {
      render(<QuickAddEventButton onAddEvent={mockOnAddEvent} />);
      
      fireEvent.click(screen.getByRole('button', { name: /new start/i }));
      
      await waitFor(() => {
        // Content Insert and User Login do not have documentation URLs
        // Only 2 doc buttons should exist (for Cron Run and Manual Trigger)
        const docButtons = screen.getAllByTestId('doc-button');
        expect(docButtons.length).toBe(2);
      });
    });
  });

  describe('Context Filtering', () => {
    afterEach(() => {
      mockSelectedContextId = null;
      mockContexts = [];
    });

    it('should show all events when no context is selected', async () => {
      mockSelectedContextId = null;
      mockContexts = [{
        id: 'ctx_1',
        topic: 'Test',
        model_owner: 'test_owner',
        components: { start: { plugins: ['content_entity:insert'] } },
      }];

      render(<QuickAddEventButton onAddEvent={mockOnAddEvent} />);
      fireEvent.click(screen.getByRole('button', { name: /new start/i }));

      await waitFor(() => {
        expect(screen.getByText('Content Insert')).toBeInTheDocument();
        expect(screen.getByText('Cron Run')).toBeInTheDocument();
        expect(screen.getByText('User Login')).toBeInTheDocument();
        expect(screen.getByText('Manual Trigger')).toBeInTheDocument();
      });
    });

    it('should only show events from the selected context', async () => {
      mockSelectedContextId = 'ctx_1';
      mockContexts = [{
        id: 'ctx_1',
        topic: 'Content Editing',
        model_owner: 'test_owner',
        components: {
          start: { plugins: ['content_entity:insert', 'cron'] },
          element: { plugins: ['entity:save'] },
        },
      }];

      render(<QuickAddEventButton onAddEvent={mockOnAddEvent} />);
      fireEvent.click(screen.getByRole('button', { name: /new start/i }));

      await waitFor(() => {
        // content_entity:insert and cron are in context
        expect(screen.getByText('Content Insert')).toBeInTheDocument();
        expect(screen.getByText('Cron Run')).toBeInTheDocument();
        // user:login is NOT in context
        expect(screen.queryByText('User Login')).not.toBeInTheDocument();
        // trigger:manual is NOT in context
        expect(screen.queryByText('Manual Trigger')).not.toBeInTheDocument();
      });
    });

    it('should ignore favorite status when a context is selected', async () => {
      mockSelectedContextId = 'ctx_1';
      mockContexts = [{
        id: 'ctx_1',
        topic: 'Content Editing',
        model_owner: 'test_owner',
        components: {
          start: { plugins: ['content_entity:insert', 'cron', 'user:login', 'trigger:manual'] },
        },
      }];

      render(<QuickAddEventButton onAddEvent={mockOnAddEvent} />);
      fireEvent.click(screen.getByRole('button', { name: /new start/i }));

      await waitFor(() => {
        // Cron Run is normally a favorite but should NOT have the favorite class
        const cronButton = screen.getByText('Cron Run').closest('button');
        expect(cronButton).not.toHaveClass('favorite');

        // No Recommended section when context is selected (favorites are ignored)
        expect(screen.queryByText('Recommended')).not.toBeInTheDocument();

        // All components should still be visible under All others
        expect(screen.getByText('All others')).toBeInTheDocument();
        expect(screen.getByText('Content Insert')).toBeInTheDocument();
        expect(screen.getByText('Cron Run')).toBeInTheDocument();
        expect(screen.getByText('Manual Trigger')).toBeInTheDocument();
        expect(screen.getByText('User Login')).toBeInTheDocument();
      });
    });

    it('should not render button when context excludes all events', () => {
      mockSelectedContextId = 'ctx_1';
      mockContexts = [{
        id: 'ctx_1',
        topic: 'No Events',
        model_owner: 'test_owner',
        components: {
          element: { plugins: ['entity:save'] },
        },
      }];

      const { container } = render(<QuickAddEventButton onAddEvent={mockOnAddEvent} />);

      // No events in context, button should not render
      expect(container.firstChild).toBeNull();
    });
  });
});
