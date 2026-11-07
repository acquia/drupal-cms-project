import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import ConfirmDialog from '../ConfirmDialog';

describe('ConfirmDialog', () => {
  const defaultProps = {
    isOpen: true,
    onClose: jest.fn(),
    onSaveAndClose: jest.fn(),
    onCloseWithoutSave: jest.fn(),
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('rendering', () => {
    it('should not render when isOpen is false', () => {
      render(<ConfirmDialog {...defaultProps} isOpen={false} />);

      expect(screen.queryByRole('heading')).not.toBeInTheDocument();
    });

    it('should render when isOpen is true', () => {
      render(<ConfirmDialog {...defaultProps} />);

      expect(screen.getByRole('heading')).toBeInTheDocument();
    });

    it('should display default title', () => {
      render(<ConfirmDialog {...defaultProps} />);

      expect(screen.getByRole('heading')).toHaveTextContent('Unsaved Changes');
    });

    it('should display custom title', () => {
      render(<ConfirmDialog {...defaultProps} title="Custom Title" />);

      expect(screen.getByRole('heading')).toHaveTextContent('Custom Title');
    });

    it('should display default message', () => {
      render(<ConfirmDialog {...defaultProps} />);

      expect(screen.getByText(/You have unsaved changes/)).toBeInTheDocument();
    });

    it('should display custom message', () => {
      render(<ConfirmDialog {...defaultProps} message="Custom message here" />);

      expect(screen.getByText('Custom message here')).toBeInTheDocument();
    });

    it('should render all three buttons', () => {
      render(<ConfirmDialog {...defaultProps} />);

      expect(screen.getByRole('button', { name: /Save and Close/i })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /Close Without Saving/i })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /Cancel/i })).toBeInTheDocument();
    });
  });

  describe('interactions', () => {
    it('should call onSaveAndClose when Save and Close button is clicked', () => {
      const onSaveAndClose = jest.fn();
      render(<ConfirmDialog {...defaultProps} onSaveAndClose={onSaveAndClose} />);

      fireEvent.click(screen.getByRole('button', { name: /Save and Close/i }));

      expect(onSaveAndClose).toHaveBeenCalledTimes(1);
    });

    it('should call onCloseWithoutSave when Close Without Saving button is clicked', () => {
      const onCloseWithoutSave = jest.fn();
      render(<ConfirmDialog {...defaultProps} onCloseWithoutSave={onCloseWithoutSave} />);

      fireEvent.click(screen.getByRole('button', { name: /Close Without Saving/i }));

      expect(onCloseWithoutSave).toHaveBeenCalledTimes(1);
    });

    it('should call onClose when Cancel button is clicked', () => {
      const onClose = jest.fn();
      render(<ConfirmDialog {...defaultProps} onClose={onClose} />);

      fireEvent.click(screen.getByRole('button', { name: /Cancel/i }));

      expect(onClose).toHaveBeenCalledTimes(1);
    });

    it('should call onClose when clicking on the overlay', () => {
      const onClose = jest.fn();
      render(<ConfirmDialog {...defaultProps} onClose={onClose} />);

      // Find the overlay element (the outer div with the class)
      const overlay = document.querySelector('.confirm-dialog-overlay');
      expect(overlay).toBeInTheDocument();

      // Click directly on the overlay, not on the dialog content
      fireEvent.click(overlay!);

      expect(onClose).toHaveBeenCalledTimes(1);
    });

    it('should not call onClose when clicking inside the dialog', () => {
      const onClose = jest.fn();
      render(<ConfirmDialog {...defaultProps} onClose={onClose} />);

      // Click on the dialog content, not the overlay
      const dialog = document.querySelector('.confirm-dialog');
      expect(dialog).toBeInTheDocument();

      fireEvent.click(dialog!);

      // onClose should NOT be called when clicking inside the dialog
      expect(onClose).not.toHaveBeenCalled();
    });
  });

  describe('accessibility', () => {
    it('should have proper button types', () => {
      render(<ConfirmDialog {...defaultProps} />);

      const buttons = screen.getAllByRole('button');
      buttons.forEach(button => {
        expect(button).toHaveAttribute('type', 'button');
      });
    });

    it('should have a heading element', () => {
      render(<ConfirmDialog {...defaultProps} />);

      const heading = screen.getByRole('heading', { level: 2 });
      expect(heading).toBeInTheDocument();
    });
  });

  describe('styling', () => {
    it('should have correct CSS classes', () => {
      render(<ConfirmDialog {...defaultProps} />);

      expect(document.querySelector('.confirm-dialog-overlay')).toBeInTheDocument();
      expect(document.querySelector('.confirm-dialog')).toBeInTheDocument();
      expect(document.querySelector('.confirm-dialog-header')).toBeInTheDocument();
      expect(document.querySelector('.confirm-dialog-body')).toBeInTheDocument();
      expect(document.querySelector('.confirm-dialog-footer')).toBeInTheDocument();
    });

    it('should have correct button classes', () => {
      render(<ConfirmDialog {...defaultProps} />);

      const saveButton = screen.getByRole('button', { name: /Save and Close/i });
      const closeButton = screen.getByRole('button', { name: /Close Without Saving/i });
      const cancelButton = screen.getByRole('button', { name: /Cancel/i });

      expect(saveButton).toHaveClass('btn', 'btn-primary');
      expect(closeButton).toHaveClass('btn', 'btn-danger');
      expect(cancelButton).toHaveClass('btn', 'btn-secondary');
    });
  });

  describe('custom button labels', () => {
    it('should display custom primary button label', () => {
      render(<ConfirmDialog {...defaultProps} primaryButtonLabel="Delete" />);

      expect(screen.getByRole('button', { name: 'Delete' })).toBeInTheDocument();
    });

    it('should display custom cancel button label', () => {
      render(<ConfirmDialog {...defaultProps} cancelButtonLabel="Dismiss" />);

      expect(screen.getByRole('button', { name: 'Dismiss' })).toBeInTheDocument();
    });

    it('should hide secondary button when secondaryButtonLabel is false', () => {
      render(<ConfirmDialog {...defaultProps} secondaryButtonLabel={false} />);

      const buttons = screen.getAllByRole('button');
      expect(buttons).toHaveLength(2);
      expect(screen.queryByRole('button', { name: /Close Without Saving/i })).not.toBeInTheDocument();
    });

    it('should hide secondary button when onCloseWithoutSave is not provided', () => {
      render(<ConfirmDialog {...defaultProps} onCloseWithoutSave={undefined} />);

      const buttons = screen.getAllByRole('button');
      expect(buttons).toHaveLength(2);
      expect(screen.queryByRole('button', { name: /Close Without Saving/i })).not.toBeInTheDocument();
    });

    it('should use danger variant for primary button', () => {
      render(<ConfirmDialog {...defaultProps} primaryButtonVariant="danger" primaryButtonLabel="Delete" />);

      const deleteButton = screen.getByRole('button', { name: 'Delete' });
      expect(deleteButton).toHaveClass('btn', 'btn-danger');
    });

    it('should render two-button delete confirmation dialog', () => {
      render(
        <ConfirmDialog
          isOpen={true}
          onClose={jest.fn()}
          onSaveAndClose={jest.fn()}
          title="Delete Selected Items"
          message="Are you sure?"
          primaryButtonLabel="Delete"
          primaryButtonVariant="danger"
          secondaryButtonLabel={false}
          cancelButtonLabel="Cancel"
        />
      );

      const buttons = screen.getAllByRole('button');
      expect(buttons).toHaveLength(2);

      const deleteButton = screen.getByRole('button', { name: 'Delete' });
      expect(deleteButton).toHaveClass('btn', 'btn-danger');

      const cancelButton = screen.getByRole('button', { name: 'Cancel' });
      expect(cancelButton).toHaveClass('btn', 'btn-secondary');
    });
  });
});
