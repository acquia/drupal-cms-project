import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import QuickAddEdgeButton from '../QuickAddEdgeButton';

// Mock the DocumentationButton component
jest.mock('../DocumentationButton', () => {
  return function MockDocumentationButton({ title }: { url: string; title: string }) {
    return <span data-testid="doc-button" title={`Docs: ${title}`}>Doc</span>;
  };
});

// Mock store — need >= 16 total components so the search field is visible
// (see THRESHOLDS.SEARCH_VISIBILITY_MIN_COMPONENTS in constants/dimensions.ts)
const mockConditionFillers = Array.from({ length: 10 }, (_, i) => ({
  plugin: `condition:filler_${i}`, label: `Filler Condition ${i}`, type: 'link', componentType: 5,
}));

const mockComponents = [
  { plugin: 'condition:is_new', label: 'Entity is New', type: 'link', componentType: 5 },
  { plugin: 'condition:has_role', label: 'User Has Role', type: 'link', componentType: 5 },
  { plugin: 'decision:compare', label: 'Compare Values', type: 'link', componentType: 5 },
  ...mockConditionFillers,
  { plugin: 'action:save', label: 'Save Entity', type: 'element', componentType: 4 },
  { plugin: 'action:publish', label: 'Publish Content', type: 'element', componentType: 4 },
  { plugin: 'gateway:split', label: 'Split Flow', type: 'gateway', componentType: 6 },
  { plugin: 'event:insert', label: 'Entity Insert', type: 'start', componentType: 1 },
];

jest.mock('../../store/useComponentStore', () => ({
  useComponentStore: jest.fn((selector) => {
    return selector({
      components: mockComponents,
      favoriteComponents: {},
    });
  }),
}));

jest.mock('../../hooks/useContextFilter', () => ({
  useContextFilter: jest.fn((components) => components),
}));

jest.mock('../../store/useContextStore', () => ({
  useContextStore: jest.fn((selector) => {
    const state = {
      selectedContextId: null,
      contexts: [],
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

describe('QuickAddEdgeButton', () => {
  const mockOnAddCondition = jest.fn();
  const mockOnAddAction = jest.fn();

  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('rendering', () => {
    it('should render when components are available', () => {
      render(
        <QuickAddEdgeButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
          onAddAction={mockOnAddAction}
        />
      );

      const button = screen.getByRole('button');
      expect(button).toBeInTheDocument();
      expect(button).toHaveAttribute('data-edge-id', 'edge_1');
    });

    it('should not render when disabled', () => {
      render(
        <QuickAddEdgeButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
          onAddAction={mockOnAddAction}
          disabled={true}
        />
      );

      expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });

    it('should not render when no components are available', () => {
      const { useComponentStore } = require('../../store/useComponentStore');
      useComponentStore.mockImplementation((selector: any) => {
        return selector({
          components: [
            { plugin: 'event:insert', label: 'Entity Insert', type: 'start', componentType: 1 },
          ],
          favoriteComponents: {},
        });
      });
      const { useContextFilter } = require('../../hooks/useContextFilter');
      useContextFilter.mockImplementation((comps: any) => comps);

      render(
        <QuickAddEdgeButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
          onAddAction={mockOnAddAction}
        />
      );

      expect(screen.queryByRole('button')).not.toBeInTheDocument();

      // Restore default mock
      useComponentStore.mockImplementation((selector: any) => {
        return selector({
          components: mockComponents,
          favoriteComponents: {},
        });
      });
      useContextFilter.mockImplementation((comps: any) => comps);
    });

    it('should have correct title and aria-label', () => {
      render(
        <QuickAddEdgeButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
          onAddAction={mockOnAddAction}
        />
      );

      const button = screen.getByRole('button');
      expect(button).toHaveAttribute('title');
      expect(button).toHaveAttribute('aria-label');
    });
  });

  describe('popup interaction', () => {
    it('should open popup when button is clicked', () => {
      render(
        <QuickAddEdgeButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
          onAddAction={mockOnAddAction}
        />
      );

      fireEvent.click(screen.getByRole('button'));

      expect(screen.getByText('Insert on Edge')).toBeInTheDocument();
    });

    it('should show conditions AND actions/gateways in popup', () => {
      render(
        <QuickAddEdgeButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
          onAddAction={mockOnAddAction}
        />
      );

      fireEvent.click(screen.getByRole('button'));

      // Conditions should be visible
      expect(screen.getByText('Entity is New')).toBeInTheDocument();
      expect(screen.getByText('User Has Role')).toBeInTheDocument();

      // Actions should be visible
      expect(screen.getByText('Save Entity')).toBeInTheDocument();
      expect(screen.getByText('Publish Content')).toBeInTheDocument();

      // Gateways should be visible
      expect(screen.getByText('Split Flow')).toBeInTheDocument();

      // Events should NOT be visible (filtered out)
      expect(screen.queryByText('Entity Insert')).not.toBeInTheDocument();
    });

    it('should close popup after selection', () => {
      render(
        <QuickAddEdgeButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
          onAddAction={mockOnAddAction}
        />
      );

      fireEvent.click(screen.getByRole('button'));
      expect(screen.getByText('Insert on Edge')).toBeInTheDocument();

      fireEvent.click(screen.getByText('User Has Role'));

      expect(screen.queryByText('Insert on Edge')).not.toBeInTheDocument();
    });
  });

  describe('component selection', () => {
    it('should call onAddCondition when a condition is selected', () => {
      render(
        <QuickAddEdgeButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
          onAddAction={mockOnAddAction}
        />
      );

      fireEvent.click(screen.getByRole('button'));
      fireEvent.click(screen.getByText('User Has Role'));

      expect(mockOnAddCondition).toHaveBeenCalledWith(
        expect.objectContaining({
          plugin: 'condition:has_role',
          label: 'User Has Role',
          type: 'link',
        })
      );
      expect(mockOnAddAction).not.toHaveBeenCalled();
    });

    it('should call onAddAction when an action is selected', () => {
      render(
        <QuickAddEdgeButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
          onAddAction={mockOnAddAction}
        />
      );

      fireEvent.click(screen.getByRole('button'));
      fireEvent.click(screen.getByText('Save Entity'));

      expect(mockOnAddAction).toHaveBeenCalledWith(
        expect.objectContaining({
          plugin: 'action:save',
          label: 'Save Entity',
          type: 'element',
        })
      );
      expect(mockOnAddCondition).not.toHaveBeenCalled();
    });

    it('should call onAddAction when a gateway is selected', () => {
      render(
        <QuickAddEdgeButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
          onAddAction={mockOnAddAction}
        />
      );

      fireEvent.click(screen.getByRole('button'));
      fireEvent.click(screen.getByText('Split Flow'));

      expect(mockOnAddAction).toHaveBeenCalledWith(
        expect.objectContaining({
          plugin: 'gateway:split',
          label: 'Split Flow',
          type: 'gateway',
        })
      );
      expect(mockOnAddCondition).not.toHaveBeenCalled();
    });
  });

  describe('context filtering', () => {
    it('should use context filter for components', () => {
      const { useContextFilter } = require('../../hooks/useContextFilter');

      render(
        <QuickAddEdgeButton
          edgeId="edge_1"
          onAddCondition={mockOnAddCondition}
          onAddAction={mockOnAddAction}
        />
      );

      expect(useContextFilter).toHaveBeenCalled();
    });
  });
});
