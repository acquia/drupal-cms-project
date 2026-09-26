import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import ConditionNode from '../ConditionNode';

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

describe('ConditionNode', () => {
  const defaultProps = {
    id: 'condition-1',
    data: {
      label: 'Test Condition',
    },
    selected: false,
    type: 'condition',
    xPos: 0,
    yPos: 0,
    isConnectable: true,
    zIndex: 0,
    dragging: false,
    targetPosition: undefined,
    sourcePosition: undefined,
  };

  describe('rendering', () => {
    it('should render condition node with label', () => {
      render(<ConditionNode {...defaultProps} />);
      expect(screen.getByText('Test Condition')).toBeInTheDocument();
    });

    it('should render default label when no label provided', () => {
      render(<ConditionNode {...defaultProps} data={{}} />);
      expect(screen.getByText('Link', { selector: '.node-label' })).toBeInTheDocument();
    });

    it('should render type indicator as Link (componentType 5)', () => {
      render(<ConditionNode {...defaultProps} />);
      expect(screen.getByText('Link', { selector: '.node-type' })).toBeInTheDocument();
    });

    it('should render input handle at top', () => {
      render(<ConditionNode {...defaultProps} />);
      const handle = screen.getByTestId('handle-target-input');
      expect(handle).toBeInTheDocument();
      expect(handle).toHaveAttribute('data-position', 'top');
    });

    it('should render output handle at bottom', () => {
      render(<ConditionNode {...defaultProps} />);
      const handle = screen.getByTestId('handle-source-output');
      expect(handle).toBeInTheDocument();
      expect(handle).toHaveAttribute('data-position', 'bottom');
    });

    it('should have condition-node class', () => {
      const { container } = render(<ConditionNode {...defaultProps} />);
      expect(container.firstChild).toHaveClass('condition-node');
    });

    it('should render with header and body (compact card layout, no footer)', () => {
      const { container } = render(<ConditionNode {...defaultProps} />);
      expect(container.querySelector('.node-header')).toBeInTheDocument();
      expect(container.querySelector('.node-body')).toBeInTheDocument();
      expect(container.querySelector('.node-footer')).not.toBeInTheDocument();
    });

    it('should render header actions (annotation + delete) inside the header', () => {
      const { container } = render(
        <ConditionNode {...defaultProps} data={{ label: 'Test', annotation: 'Note' }} />
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
        <ConditionNode {...defaultProps} selected={true} />
      );
      expect(container.firstChild).toHaveClass('selected');
    });

    it('should not have selected class when not selected', () => {
      const { container } = render(
        <ConditionNode {...defaultProps} selected={false} />
      );
      expect(container.firstChild).not.toHaveClass('selected');
    });
  });

  describe('delete functionality', () => {
    it('should show delete button', () => {
      const { container } = render(
        <ConditionNode
          {...defaultProps}
          data={{ label: 'Test' }}
        />
      );
      expect(container.querySelector('.node-footer-delete')).toBeInTheDocument();
    });

    it('should call onDelete when delete button clicked', () => {
      const onDelete = jest.fn();
      const { container } = render(
        <ConditionNode
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
          <ConditionNode
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
        <ConditionNode
          {...defaultProps}
          data={{ label: 'Test' }}
        />
      );
      expect(() => {
        fireEvent.click(container.querySelector('.node-footer-delete')!);
      }).not.toThrow();
    });

    it('should hide delete button when locked', () => {
      const { container } = render(
        <ConditionNode
          {...defaultProps}
          data={{ label: 'Test', isLocked: true }}
        />
      );
      expect(container.querySelector('.node-footer-delete')).not.toBeInTheDocument();
    });
  });

  describe('source handle disabled state (issue #3589093)', () => {
    it('should render the source handle as connectable when not disabled', () => {
      render(<ConditionNode {...defaultProps} data={{ label: 'Test' }} />);
      const handle = screen.getByTestId('handle-source-output');
      // Component passes isConnectable={!data.sourceHandleDisabled} -> true.
      expect(handle).toHaveAttribute('data-connectable', 'true');
    });

    it('should not set a title on the source handle when enabled', () => {
      render(<ConditionNode {...defaultProps} data={{ label: 'Test' }} />);
      const handle = screen.getByTestId('handle-source-output');
      // No misleading tooltip on a working handle.
      expect(handle).not.toHaveAttribute('title');
    });

    it('should not add the node-handle--disabled modifier class when enabled', () => {
      render(<ConditionNode {...defaultProps} data={{ label: 'Test' }} />);
      const handle = screen.getByTestId('handle-source-output');
      // Base class only; the modifier (which re-enables hover for the tooltip)
      // must not be present on an enabled, connectable handle.
      expect(handle).toHaveClass('node-handle');
      expect(handle).not.toHaveClass('node-handle--disabled');
    });

    it('should render the source handle as non-connectable when disabled', () => {
      render(
        <ConditionNode
          {...defaultProps}
          data={{ label: 'Test', sourceHandleDisabled: true }}
        />
      );
      const handle = screen.getByTestId('handle-source-output');
      expect(handle).toHaveAttribute('data-connectable', 'false');
    });

    it('should add the node-handle--disabled modifier class when disabled', () => {
      render(
        <ConditionNode
          {...defaultProps}
          data={{ label: 'Test', sourceHandleDisabled: true }}
        />
      );
      const handle = screen.getByTestId('handle-source-output');
      // The modifier re-enables pointer-events so the native title tooltip
      // can appear on hover, while isConnectable stays false (no connection).
      expect(handle).toHaveClass('node-handle');
      expect(handle).toHaveClass('node-handle--disabled');
      expect(handle).toHaveAttribute('data-connectable', 'false');
    });

    it('should show the condition-specific title when disabled (default reason)', () => {
      render(
        <ConditionNode
          {...defaultProps}
          data={{ label: 'Test', sourceHandleDisabled: true }}
        />
      );
      const handle = screen.getByTestId('handle-source-output');
      expect(handle).toHaveAttribute(
        'title',
        'A condition can have only one outgoing connection.'
      );
    });

    it('should show the condition-specific title for reason "condition-single-out"', () => {
      render(
        <ConditionNode
          {...defaultProps}
          data={{
            label: 'Test',
            sourceHandleDisabled: true,
            sourceHandleDisabledReason: 'condition-single-out',
          }}
        />
      );
      const handle = screen.getByTestId('handle-source-output');
      expect(handle).toHaveAttribute(
        'title',
        'A condition can have only one outgoing connection.'
      );
    });

    it('should show the generic title for reason "max-successors"', () => {
      render(
        <ConditionNode
          {...defaultProps}
          data={{
            label: 'Test',
            sourceHandleDisabled: true,
            sourceHandleDisabledReason: 'max-successors',
          }}
        />
      );
      const handle = screen.getByTestId('handle-source-output');
      expect(handle).toHaveAttribute('title', 'Maximum number of connections reached.');
    });
  });

  describe('annotation', () => {
    it('should show annotation icon in header when annotation exists', () => {
      const { container } = render(
        <ConditionNode
          {...defaultProps}
          data={{ label: 'Test', annotation: 'Test annotation' }}
        />
      );
      expect(container.querySelector('.node-footer-annotation')).toBeInTheDocument();
    });

    it('should show annotation text as title attribute', () => {
      const { container } = render(
        <ConditionNode
          {...defaultProps}
          data={{ label: 'Test', annotation: 'My annotation text' }}
        />
      );
      const indicator = container.querySelector('.node-footer-annotation');
      expect(indicator).toHaveAttribute('title', 'My annotation text');
    });

    it('should have has-annotation class when annotation exists', () => {
      const { container } = render(
        <ConditionNode
          {...defaultProps}
          data={{ label: 'Test', annotation: 'Note' }}
        />
      );
      expect(container.firstChild).toHaveClass('node-has-annotation');
    });
  });
});
