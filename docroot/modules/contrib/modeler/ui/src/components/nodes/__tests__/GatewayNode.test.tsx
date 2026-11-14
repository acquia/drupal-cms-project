import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import GatewayNode from '../GatewayNode';

// Mock ReactFlow components. The Handle mock forwards `isConnectable`,
// `title`, and `className` so tests can assert the disabled-source-handle UX
// (issue #3589093), including the `node-handle--disabled` modifier class.
jest.mock('reactflow', () => ({
  Handle: ({ type, position, id, isConnectable, title, className }: any) => (
    <div
      data-testid={`handle-${type}-${id}`}
      data-position={position}
      data-connectable={isConnectable === undefined ? 'true' : String(isConnectable)}
      title={title}
      className={className}
    />
  ),
  Position: {
    Top: 'top',
    Bottom: 'bottom',
    Left: 'left',
    Right: 'right',
  },
}));

describe('GatewayNode', () => {
  const defaultProps = {
    id: 'gateway-1',
    data: {
      label: 'Test Gateway',
    },
    selected: false,
    type: 'gateway',
    xPos: 0,
    yPos: 0,
    isConnectable: true,
    zIndex: 0,
    dragging: false,
    targetPosition: undefined,
    sourcePosition: undefined,
  };

  describe('rendering', () => {
    it('should render gateway node with label', () => {
      render(<GatewayNode {...defaultProps} />);
      expect(screen.getByText('Test Gateway')).toBeInTheDocument();
    });

    it('should render default label when no label provided', () => {
      render(<GatewayNode {...defaultProps} data={{}} />);
      expect(screen.getByText('Gateway', { selector: '.node-label' })).toBeInTheDocument();
    });

    it('should render type indicator as Gateway', () => {
      render(<GatewayNode {...defaultProps} />);
      expect(screen.getByText('Gateway', { selector: '.node-type' })).toBeInTheDocument();
    });

    it('should render input handle at top', () => {
      render(<GatewayNode {...defaultProps} />);
      const handle = screen.getByTestId('handle-target-input');
      expect(handle).toBeInTheDocument();
      expect(handle).toHaveAttribute('data-position', 'top');
    });

    it('should render output handle at bottom', () => {
      render(<GatewayNode {...defaultProps} />);
      const handle = screen.getByTestId('handle-source-output');
      expect(handle).toBeInTheDocument();
      expect(handle).toHaveAttribute('data-position', 'bottom');
    });

    it('should have gateway-node class', () => {
      const { container } = render(<GatewayNode {...defaultProps} />);
      expect(container.firstChild).toHaveClass('gateway-node');
    });

    it('should render the source handle as connectable when not disabled', () => {
      render(<GatewayNode {...defaultProps} data={{ label: 'Test' }} />);
      const handle = screen.getByTestId('handle-source-output');
      expect(handle).toHaveAttribute('data-connectable', 'true');
      expect(handle).not.toHaveClass('node-handle--disabled');
    });

    it('should add the node-handle--disabled modifier class and title when disabled (issue #3589093)', () => {
      render(
        <GatewayNode
          {...defaultProps}
          data={{ label: 'Test', sourceHandleDisabled: true }}
        />
      );
      const handle = screen.getByTestId('handle-source-output');
      // Re-enables hover so the tooltip shows, while connection stays disabled.
      expect(handle).toHaveClass('node-handle--disabled');
      expect(handle).toHaveAttribute('data-connectable', 'false');
      expect(handle).toHaveAttribute('title', 'Maximum number of connections reached.');
    });

    it('should render with header and body (compact card layout, no footer)', () => {
      const { container } = render(<GatewayNode {...defaultProps} />);
      expect(container.querySelector('.node-header')).toBeInTheDocument();
      expect(container.querySelector('.node-body')).toBeInTheDocument();
      expect(container.querySelector('.node-footer')).not.toBeInTheDocument();
    });

    it('should render header actions (annotation + delete) inside the header', () => {
      const { container } = render(
        <GatewayNode {...defaultProps} data={{ label: 'Test', annotation: 'Note' }} />
      );
      const headerActions = container.querySelector('.node-header-actions');
      expect(headerActions).toBeInTheDocument();
      expect(headerActions!.querySelector('.node-footer-delete')).toBeInTheDocument();
      expect(headerActions!.querySelector('.node-footer-annotation')).toBeInTheDocument();
    });
  });

  describe('selected state', () => {
    it('should have selected class when selected', () => {
      const { container } = render(
        <GatewayNode {...defaultProps} selected={true} />
      );
      expect(container.firstChild).toHaveClass('selected');
    });

    it('should not have selected class when not selected', () => {
      const { container } = render(
        <GatewayNode {...defaultProps} selected={false} />
      );
      expect(container.firstChild).not.toHaveClass('selected');
    });

  });

  describe('delete functionality', () => {
    it('should show delete button', () => {
      const { container } = render(
        <GatewayNode
          {...defaultProps}
          data={{ label: 'Test' }}
        />
      );
      expect(container.querySelector('.node-footer-delete')).toBeInTheDocument();
    });

    it('should call onDelete when delete button clicked', () => {
      const onDelete = jest.fn();
      const { container } = render(
        <GatewayNode
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
          <GatewayNode
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
        <GatewayNode
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
    it('should show annotation icon in header when annotation exists', () => {
      const { container } = render(
        <GatewayNode
          {...defaultProps}
          data={{ label: 'Test', annotation: 'Test annotation' }}
        />
      );
      expect(container.querySelector('.node-footer-annotation')).toBeInTheDocument();
    });

    it('should render annotation indicator as passive (no active class)', () => {
      const { container } = render(
        <GatewayNode
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
        <GatewayNode
          {...defaultProps}
          data={{ label: 'Test', annotation: 'My annotation text' }}
        />
      );
      const indicator = container.querySelector('.node-footer-annotation');
      expect(indicator).toHaveAttribute('title', 'My annotation text');
    });

    it('should have has-annotation class when annotation exists', () => {
      const { container } = render(
        <GatewayNode
          {...defaultProps}
          data={{ label: 'Test', annotation: 'Note' }}
        />
      );
      expect(container.firstChild).toHaveClass('node-has-annotation');
    });
  });
});
