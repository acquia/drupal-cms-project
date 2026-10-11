import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import PlaceholderNode from '../PlaceholderNode';
import { Position } from 'reactflow';

// Mock reactflow Handle component
jest.mock('reactflow', () => ({
  Handle: ({ type, position, id }: any) => (
    <div data-testid={`handle-${type}-${id}`} data-position={position}>
      Handle
    </div>
  ),
  Position: {
    Top: 'top',
    Bottom: 'bottom',
    Left: 'left',
    Right: 'right',
  },
}));

// Mock the DocumentationButton component
jest.mock('../../DocumentationButton', () => {
  return function MockDocumentationButton({ title }: { url: string; title: string }) {
    return <span data-testid="doc-button" title={`Docs: ${title}`}>Doc</span>;
  };
});

// Mock components: actions, gateways, and types that should be excluded
const mockComponents = [
  { plugin: 'action:save', label: 'Save Entity', type: 'element', componentType: 4 },
  { plugin: 'action:delete', label: 'Delete Entity', type: 'element', componentType: 4 },
  { plugin: 'gateway:exclusive', label: 'Exclusive Gateway', type: 'gateway', componentType: 6 },
  { plugin: 'event:insert', label: 'Entity Insert', type: 'start', componentType: 1 },
  { plugin: 'condition:is_new', label: 'Entity is New', type: 'link', componentType: 5 },
];

jest.mock('../../../store/useComponentStore', () => ({
  useComponentStore: jest.fn((selector) => {
    const state = {
      components: mockComponents,
      favoriteComponents: {},
    };
    return selector(state);
  }),
}));

jest.mock('../../../store/useContextStore', () => ({
  useContextStore: jest.fn((selector) => {
    const state = {
      selectedContextId: null,
      contexts: [],
      dependencies: [],
    };
    return selector(state);
  }),
}));

jest.mock('../../../store/useGraphStore', () => ({
  useGraphStore: jest.fn((selector) => {
    const state = {
      nodes: [],
      edges: [],
    };
    return selector(state);
  }),
}));

jest.mock('../../../utils/profiling', () => ({
  onRenderCallback: jest.fn(),
}));

// Mock createPortal to render in place (QuickAddPopup uses portals)
jest.mock('react-dom', () => ({
  ...jest.requireActual('react-dom'),
  createPortal: (node: React.ReactNode) => node,
}));

describe('PlaceholderNode', () => {
  const defaultNodeData = {
    label: 'Placeholder Action',
    annotation: '',
    isAnnotationVisible: false,
    onDelete: jest.fn(),
    onToggleAnnotation: jest.fn(),
  };

  const defaultProps = {
    id: 'placeholder-1',
    type: 'placeholder',
    data: defaultNodeData,
    selected: false,
    xPos: 0,
    yPos: 0,
    dragging: false,
    isConnectable: true,
    zIndex: 0,
    positionAbsoluteX: 0,
    positionAbsoluteY: 0,
    sourcePosition: Position.Bottom,
    targetPosition: Position.Top,
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('rendering', () => {
    it('should render the node with placeholder styling', () => {
      render(<PlaceholderNode {...defaultProps} />);

      expect(document.querySelector('.node-header')).toBeInTheDocument();
      expect(document.querySelector('.node-body')).toBeInTheDocument();
      expect(document.querySelector('.placeholder-node')).toBeInTheDocument();
    });
  });

  describe('select action button', () => {
    it('should show "Select action..." button when onReplacePlaceholder is provided and node is not locked', () => {
      const onReplacePlaceholder = jest.fn();
      const data = { ...defaultNodeData, onReplacePlaceholder };
      render(<PlaceholderNode {...defaultProps} data={data} />);

      expect(screen.getByText('Select action...')).toBeInTheDocument();
    });

    it('should NOT show the button when isLocked is true', () => {
      const onReplacePlaceholder = jest.fn();
      const data = { ...defaultNodeData, onReplacePlaceholder, isLocked: true };
      render(<PlaceholderNode {...defaultProps} data={data} />);

      expect(screen.queryByText('Select action...')).not.toBeInTheDocument();
      expect(screen.getByText('Placeholder Action')).toBeInTheDocument();
    });

    it('should NOT show the button when onReplacePlaceholder is undefined', () => {
      const data = { ...defaultNodeData };
      render(<PlaceholderNode {...defaultProps} data={data} />);

      expect(screen.queryByText('Select action...')).not.toBeInTheDocument();
      expect(screen.getByText('Placeholder Action')).toBeInTheDocument();
    });
  });

  describe('popup interaction', () => {
    it('should open popup when "Select action..." button is clicked', () => {
      const onReplacePlaceholder = jest.fn();
      const data = { ...defaultNodeData, onReplacePlaceholder };
      render(<PlaceholderNode {...defaultProps} data={data} />);

      fireEvent.click(screen.getByText('Select action...'));

      expect(screen.getByText('Select Action')).toBeInTheDocument();
    });

    it('should call onReplacePlaceholder with the selected component when a component is picked', () => {
      const onReplacePlaceholder = jest.fn();
      const data = { ...defaultNodeData, onReplacePlaceholder };
      render(<PlaceholderNode {...defaultProps} data={data} />);

      fireEvent.click(screen.getByText('Select action...'));
      fireEvent.click(screen.getByText('Save Entity'));

      expect(onReplacePlaceholder).toHaveBeenCalledWith(
        expect.objectContaining({
          plugin: 'action:save',
          label: 'Save Entity',
        })
      );
    });

    it('should only show actions and gateways in the popup (not events or conditions)', () => {
      const onReplacePlaceholder = jest.fn();
      const data = { ...defaultNodeData, onReplacePlaceholder };
      render(<PlaceholderNode {...defaultProps} data={data} />);

      fireEvent.click(screen.getByText('Select action...'));

      // Actions should be visible
      expect(screen.getByText('Save Entity')).toBeInTheDocument();
      expect(screen.getByText('Delete Entity')).toBeInTheDocument();

      // Gateways should be visible
      expect(screen.getByText('Exclusive Gateway')).toBeInTheDocument();

      // Events (type: 'start') should NOT be visible
      expect(screen.queryByText('Entity Insert')).not.toBeInTheDocument();

      // Conditions (type: 'link') should NOT be visible
      expect(screen.queryByText('Entity is New')).not.toBeInTheDocument();
    });
  });

  describe('handles', () => {
    it('should show target and source handles', () => {
      render(<PlaceholderNode {...defaultProps} />);

      expect(screen.getByTestId('handle-target-input')).toBeInTheDocument();
      expect(screen.getByTestId('handle-source-output')).toBeInTheDocument();
    });
  });

  describe('displayName', () => {
    it('should have correct displayName', () => {
      expect(PlaceholderNode.displayName).toBe('PlaceholderNode');
    });
  });
});
