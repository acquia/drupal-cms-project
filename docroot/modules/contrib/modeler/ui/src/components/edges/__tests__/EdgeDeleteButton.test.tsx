import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import EdgeDeleteButton from '../EdgeDeleteButton';

describe('EdgeDeleteButton', () => {
  const mockOnDelete = jest.fn();

  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('rendering', () => {
    it('should render a trash button with the accessible name "Delete connection"', () => {
      render(<EdgeDeleteButton edgeId="edge_1" onDelete={mockOnDelete} />);

      const button = screen.getByRole('button', { name: 'Delete connection' });
      expect(button).toBeInTheDocument();
      expect(button).toHaveAttribute('title', 'Delete connection');
      expect(button).toHaveAttribute('data-edge-id', 'edge_1');
    });

    it('should not render when disabled', () => {
      render(
        <EdgeDeleteButton edgeId="edge_1" onDelete={mockOnDelete} disabled={true} />,
      );

      expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });
  });

  describe('interaction', () => {
    it('should call onDelete with the edge id when clicked', () => {
      render(<EdgeDeleteButton edgeId="edge_42" onDelete={mockOnDelete} />);

      fireEvent.click(screen.getByRole('button', { name: 'Delete connection' }));

      expect(mockOnDelete).toHaveBeenCalledTimes(1);
      expect(mockOnDelete).toHaveBeenCalledWith('edge_42');
    });

    it('should stop event propagation so the click does not select the edge', () => {
      const onParentClick = jest.fn();
      render(
        <div onClick={onParentClick}>
          <EdgeDeleteButton edgeId="edge_1" onDelete={mockOnDelete} />
        </div>,
      );

      fireEvent.click(screen.getByRole('button', { name: 'Delete connection' }));

      expect(mockOnDelete).toHaveBeenCalledWith('edge_1');
      expect(onParentClick).not.toHaveBeenCalled();
    });
  });
});
