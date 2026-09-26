import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import MultiSelectionPanel from '../MultiSelectionPanel';

// Mock react-icons
jest.mock('react-icons/fi', () => ({
  FiActivity: () => <span data-testid="fi-activity" />,
  FiZap: () => <span data-testid="fi-zap" />,
  FiGitBranch: () => <span data-testid="fi-git-branch" />,
  FiBox: () => <span data-testid="fi-box" />,
  FiTrash2: () => <span data-testid="fi-trash2" />,
}));

describe('MultiSelectionPanel', () => {
  const mockOnConfigurationChange = jest.fn();
  const mockOnEdgeConfigurationChange = jest.fn();

  const defaultProps = {
    selectedNodes: [],
    selectedEdges: [],
    onConfigurationChange: mockOnConfigurationChange,
    onEdgeConfigurationChange: mockOnEdgeConfigurationChange,
    isLocked: false,
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('node list', () => {
    it('should render selected nodes with labels', () => {
      const nodes = [
        { id: 'node-1', type: 'start', data: { label: 'Event A' }, position: { x: 0, y: 0 } },
        { id: 'node-2', type: 'element', data: { label: 'Action B' }, position: { x: 100, y: 0 } },
      ];

      render(<MultiSelectionPanel {...defaultProps} selectedNodes={nodes as any} />);
      
      expect(screen.getByText('Event A')).toBeTruthy();
      expect(screen.getByText('Action B')).toBeTruthy();
    });

    it('should show node count in header', () => {
      const nodes = [
        { id: 'node-1', type: 'start', data: { label: 'A' }, position: { x: 0, y: 0 } },
        { id: 'node-2', type: 'start', data: { label: 'B' }, position: { x: 0, y: 0 } },
      ];

      render(<MultiSelectionPanel {...defaultProps} selectedNodes={nodes as any} />);
      expect(screen.getByText('Components (2)')).toBeTruthy();
    });

    it('should show "Unnamed" for nodes without labels', () => {
      const nodes = [
        { id: 'node-1', type: 'element', data: {}, position: { x: 0, y: 0 } },
      ];

      render(<MultiSelectionPanel {...defaultProps} selectedNodes={nodes as any} />);
      expect(screen.getByText('Unnamed')).toBeTruthy();
    });
  });

  describe('edge list', () => {
    it('should render selected edges', () => {
      const edges = [
        { id: 'edge-1', source: 'a', target: 'b', data: { conditionLabel: 'Condition X' } },
      ];

      render(<MultiSelectionPanel {...defaultProps} selectedEdges={edges as any} />);
      expect(screen.getByText('Condition X')).toBeTruthy();
    });

    it('should show edge count in header', () => {
      const edges = [
        { id: 'edge-1', source: 'a', target: 'b', data: {} },
        { id: 'edge-2', source: 'b', target: 'c', data: {} },
      ];

      render(<MultiSelectionPanel {...defaultProps} selectedEdges={edges as any} />);
      expect(screen.getByText('Connections (2)')).toBeTruthy();
    });

    it('should show "No condition" for edges without labels', () => {
      const edges = [
        { id: 'edge-1', source: 'a', target: 'b', data: {} },
      ];

      render(<MultiSelectionPanel {...defaultProps} selectedEdges={edges as any} />);
      expect(screen.getByText('No condition')).toBeTruthy();
    });
  });

  describe('edge label display', () => {
    it('should show edge label from edge.label if available', () => {
      const edges = [
        { id: 'edge-1', source: 'a', target: 'b', label: 'Custom Label', data: {} },
      ];

      render(<MultiSelectionPanel {...defaultProps} selectedEdges={edges as any} />);
      expect(screen.getByText('Custom Label')).toBeTruthy();
    });
  });

  describe('summary note', () => {
    it('should show note about single selection', () => {
      render(<MultiSelectionPanel {...defaultProps} />);
      expect(screen.getByText('Select a single component or connection to view its configuration.')).toBeTruthy();
    });
  });

  describe('delete all action', () => {
    it('should render Delete All button', () => {
      const nodes = [
        { id: 'node-1', type: 'element', data: { label: 'A' }, position: { x: 0, y: 0 } },
      ];

      render(<MultiSelectionPanel {...defaultProps} selectedNodes={nodes as any} />);

      expect(screen.getByTitle('Delete all selected items')).toBeTruthy();
      expect(screen.getByText('Delete All')).toBeTruthy();
    });

    it('should call onDeleteSelected when Delete All is clicked', () => {
      const mockOnDeleteSelected = jest.fn();
      const nodes = [
        { id: 'node-1', type: 'element', data: { label: 'A' }, position: { x: 0, y: 0 } },
      ];

      render(
        <MultiSelectionPanel
          {...defaultProps}
          selectedNodes={nodes as any}
          onDeleteSelected={mockOnDeleteSelected}
        />
      );

      const deleteAllBtn = screen.getByTitle('Delete all selected items');
      fireEvent.click(deleteAllBtn);

      expect(mockOnDeleteSelected).toHaveBeenCalled();
    });

    it('should disable Delete All button when globally locked', () => {
      const nodes = [
        { id: 'node-1', type: 'element', data: { label: 'A' }, position: { x: 0, y: 0 } },
      ];

      render(<MultiSelectionPanel {...defaultProps} selectedNodes={nodes as any} isLocked={true} />);

      const deleteAllBtn = screen.getByTitle('Delete all selected items');
      expect(deleteAllBtn).toBeDisabled();
    });

    it('should not call onDeleteSelected when locked', () => {
      const mockOnDeleteSelected = jest.fn();
      const nodes = [
        { id: 'node-1', type: 'element', data: { label: 'A' }, position: { x: 0, y: 0 } },
      ];

      render(
        <MultiSelectionPanel
          {...defaultProps}
          selectedNodes={nodes as any}
          onDeleteSelected={mockOnDeleteSelected}
          isLocked={true}
        />
      );

      const deleteAllBtn = screen.getByTitle('Delete all selected items');
      expect(deleteAllBtn).toBeDisabled();
      fireEvent.click(deleteAllBtn);

      expect(mockOnDeleteSelected).not.toHaveBeenCalled();
    });

    it('should not throw when onDeleteSelected is not provided', () => {
      const nodes = [
        { id: 'node-1', type: 'element', data: { label: 'A' }, position: { x: 0, y: 0 } },
      ];

      render(<MultiSelectionPanel {...defaultProps} selectedNodes={nodes as any} />);

      const deleteAllBtn = screen.getByTitle('Delete all selected items');
      expect(() => fireEvent.click(deleteAllBtn)).not.toThrow();
    });
  });
});
