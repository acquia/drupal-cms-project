import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import SubprocessNode from '../SubprocessNode';

// Mock ReactFlow components
jest.mock('reactflow', () => ({
  Handle: ({ type, position, id }: any) => (
    <div data-testid={`handle-${type}-${id}`} data-position={position} />
  ),
  Position: {
    Top: 'top',
    Bottom: 'bottom',
    Left: 'left',
    Right: 'right',
  },
}));

describe('SubprocessNode', () => {
  const defaultProps = {
    id: 'subprocess-1',
    data: {
      label: 'Test Subprocess',
    },
    selected: false,
    type: 'subprocess',
    xPos: 0,
    yPos: 0,
    isConnectable: true,
    zIndex: 0,
    dragging: false,
    targetPosition: undefined,
    sourcePosition: undefined,
  };

  describe('rendering', () => {
    it('should render subprocess node with label', () => {
      render(<SubprocessNode {...defaultProps} />);
      expect(screen.getByText('Test Subprocess')).toBeInTheDocument();
    });

    it('should render default label when no label provided', () => {
      const { container } = render(<SubprocessNode {...defaultProps} data={{}} />);
      // Both type indicator and label will show "Subprocess"
      const labels = container.querySelectorAll('.node-label');
      expect(labels.length).toBe(1);
      expect(labels[0]).toHaveTextContent('Subprocess');
    });

    it('should render type indicator', () => {
      render(<SubprocessNode {...defaultProps} />);
      expect(screen.getByText('Subprocess')).toBeInTheDocument();
    });

    it('should render handles for connections', () => {
      render(<SubprocessNode {...defaultProps} />);
      expect(screen.getByTestId('handle-target-input')).toBeInTheDocument();
      expect(screen.getByTestId('handle-source-output')).toBeInTheDocument();
    });

    it('should render subflow count when provided', () => {
      render(
        <SubprocessNode
          {...defaultProps}
          data={{ label: 'Test', subflowCount: 5 }}
        />
      );
      expect(screen.getByText('5 nodes')).toBeInTheDocument();
    });

    it('should not render subflow count when zero', () => {
      render(
        <SubprocessNode
          {...defaultProps}
          data={{ label: 'Test', subflowCount: 0 }}
        />
      );
      expect(screen.queryByText('0 nodes')).not.toBeInTheDocument();
    });
  });

  describe('selected state', () => {
    it('should have selected class when selected', () => {
      const { container } = render(
        <SubprocessNode {...defaultProps} selected={true} />
      );
      expect(container.firstChild).toHaveClass('selected');
    });

    it('should not have selected class when not selected', () => {
      const { container } = render(
        <SubprocessNode {...defaultProps} selected={false} />
      );
      expect(container.firstChild).not.toHaveClass('selected');
    });

  });

  describe('delete functionality', () => {
    it('should show delete button', () => {
      const { container } = render(
        <SubprocessNode
          {...defaultProps}
          data={{ label: 'Test' }}
        />
      );
      expect(container.querySelector('.node-footer-delete')).toBeInTheDocument();
    });

    it('should call onDelete when delete button clicked', () => {
      const onDelete = jest.fn();
      const { container } = render(
        <SubprocessNode
          {...defaultProps}
          data={{ label: 'Test', onDelete }}
        />
      );
      fireEvent.click(container.querySelector('.node-footer-delete')!);
      expect(onDelete).toHaveBeenCalled();
    });

    it('should stop propagation on delete click', () => {
      const onDelete = jest.fn();
      const onContainerClick = jest.fn();

      const { container } = render(
        <div onClick={onContainerClick}>
          <SubprocessNode
            {...defaultProps}
            data={{ label: 'Test', onDelete }}
          />
        </div>
      );

      fireEvent.click(container.querySelector('.node-footer-delete')!);
      expect(onDelete).toHaveBeenCalled();
      expect(onContainerClick).not.toHaveBeenCalled();
    });

    it('should not throw when onDelete is not provided', () => {
      const { container } = render(
        <SubprocessNode
          {...defaultProps}
          data={{ label: 'Test' }}
        />
      );
      expect(() => {
        fireEvent.click(container.querySelector('.node-footer-delete')!);
      }).not.toThrow();
    });
  });

  describe('annotation', () => {
    it('should show annotation icon in footer when annotation exists', () => {
      const { container } = render(
        <SubprocessNode
          {...defaultProps}
          data={{ label: 'Test', annotation: 'Test annotation' }}
        />
      );
      expect(container.querySelector('.node-footer-annotation')).toBeInTheDocument();
    });

    it('should render annotation indicator as passive (no active class)', () => {
      const { container } = render(
        <SubprocessNode
          {...defaultProps}
          data={{
            label: 'Test',
            annotation: 'Note',
          }}
        />
      );
      const indicator = container.querySelector('.node-footer-annotation');
      expect(indicator).toBeInTheDocument();
      expect(indicator).not.toHaveClass('active');
    });

    it('should show annotation text as title attribute', () => {
      const { container } = render(
        <SubprocessNode
          {...defaultProps}
          data={{ label: 'Test', annotation: 'My annotation text' }}
        />
      );
      const indicator = container.querySelector('.node-footer-annotation');
      expect(indicator).toHaveAttribute('title', 'My annotation text');
    });

    it('should have has-annotation class when annotation exists', () => {
      const { container } = render(
        <SubprocessNode
          {...defaultProps}
          data={{ label: 'Test', annotation: 'Note' }}
        />
      );
      expect(container.firstChild).toHaveClass('node-has-annotation');
    });
  });
});
