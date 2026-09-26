import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import Modals from '../Modals';

// Mock child components
jest.mock('../MetadataModal', () => {
  return function MockMetadataModal({ isOpen, onClose, onSave, metadata, isNew }: any) {
    if (!isOpen) return null;
    return (
      <div data-testid="metadata-modal">
        <span data-testid="metadata-label">{metadata?.label || 'No label'}</span>
        <span data-testid="is-new">{isNew ? 'New' : 'Edit'}</span>
        <button onClick={onClose} data-testid="close-metadata">Close</button>
        <button onClick={() => onSave({ label: 'Test', tags: [] })} data-testid="save-metadata">Save</button>
      </div>
    );
  };
});

jest.mock('../ConfirmDialog', () => {
  return function MockConfirmDialog({ isOpen, onClose, onSaveAndClose, onCloseWithoutSave, secondaryButtonLabel, title, message }: any) {
    if (!isOpen) return null;
    return (
      <div data-testid="confirm-dialog">
        <h3 data-testid="confirm-title">{title}</h3>
        <p data-testid="confirm-message">{message}</p>
        <button onClick={onClose} data-testid="cancel-confirm">Cancel</button>
        <button onClick={onSaveAndClose} data-testid="save-and-close">Save & Close</button>
        {secondaryButtonLabel !== false && onCloseWithoutSave && (
          <button onClick={onCloseWithoutSave} data-testid="close-without-save">{secondaryButtonLabel || 'Close Without Saving'}</button>
        )}
      </div>
    );
  };
});

jest.mock('../ExportDialog', () => {
  return function MockExportDialog({ isOpen, onClose, onExport, availableFormats }: any) {
    if (!isOpen) return null;
    return (
      <div data-testid="export-dialog">
        <span data-testid="export-formats">{availableFormats?.join(', ')}</span>
        <button onClick={onClose} data-testid="cancel-export">Cancel</button>
        <button onClick={() => onExport('json')} data-testid="export-json">Export JSON</button>
      </div>
    );
  };
});

describe('Modals', () => {
  const defaultProps = {
    // Metadata Modal
    showMetadataModal: false,
    onCloseMetadataModal: jest.fn(),
    onMetadataSubmit: jest.fn(),
    modelMetadata: {
      label: 'Test Model',
      documentation: 'Test Description',
      tags: ['tag1'],
    },
    isNewModel: false,

    // Confirm Dialog
    showConfirmDialog: false,
    confirmDialogTitle: 'Confirm',
    confirmDialogMessage: 'Are you sure?',
    confirmDialogType: 'warning' as const,
    onConfirmDialog: jest.fn(),
    onCancelDialog: jest.fn(),
    confirmDialogLoading: false,
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('rendering', () => {
    it('should render nothing when all modals are closed', () => {
      const { container } = render(<Modals {...defaultProps} />);
      expect(container.firstChild).toBeNull();
    });

    it('should render metadata modal when showMetadataModal is true', () => {
      render(<Modals {...defaultProps} showMetadataModal={true} />);
      expect(screen.getByTestId('metadata-modal')).toBeInTheDocument();
    });

    it('should render confirm dialog when showConfirmDialog is true', () => {
      render(<Modals {...defaultProps} showConfirmDialog={true} />);
      expect(screen.getByTestId('confirm-dialog')).toBeInTheDocument();
    });

  });

  describe('metadata modal', () => {
    it('should pass metadata to modal', () => {
      render(
        <Modals
          {...defaultProps}
          showMetadataModal={true}
          modelMetadata={{ label: 'My Workflow', tags: [] }}
        />
      );
      expect(screen.getByTestId('metadata-label')).toHaveTextContent('My Workflow');
    });

    it('should pass isNew flag to modal', () => {
      render(
        <Modals
          {...defaultProps}
          showMetadataModal={true}
          isNewModel={true}
        />
      );
      expect(screen.getByTestId('is-new')).toHaveTextContent('New');
    });

    it('should call onCloseMetadataModal when close button clicked', () => {
      const onClose = jest.fn();
      render(
        <Modals
          {...defaultProps}
          showMetadataModal={true}
          onCloseMetadataModal={onClose}
        />
      );
      fireEvent.click(screen.getByTestId('close-metadata'));
      expect(onClose).toHaveBeenCalled();
    });

    it('should call onMetadataSubmit when save clicked', () => {
      const onSubmit = jest.fn();
      render(
        <Modals
          {...defaultProps}
          showMetadataModal={true}
          onMetadataSubmit={onSubmit}
        />
      );
      fireEvent.click(screen.getByTestId('save-metadata'));
      expect(onSubmit).toHaveBeenCalledWith({ label: 'Test', tags: [] });
    });
  });

  describe('confirm dialog', () => {
    it('should display title and message', () => {
      render(
        <Modals
          {...defaultProps}
          showConfirmDialog={true}
          confirmDialogTitle="Delete Item"
          confirmDialogMessage="This action cannot be undone"
        />
      );
      expect(screen.getByTestId('confirm-title')).toHaveTextContent('Delete Item');
      expect(screen.getByTestId('confirm-message')).toHaveTextContent('This action cannot be undone');
    });

    it('should call onCancelDialog when cancel clicked', () => {
      const onCancel = jest.fn();
      render(
        <Modals
          {...defaultProps}
          showConfirmDialog={true}
          onCancelDialog={onCancel}
        />
      );
      fireEvent.click(screen.getByTestId('cancel-confirm'));
      expect(onCancel).toHaveBeenCalled();
    });

    it('should call onConfirmDialog when save and close clicked', () => {
      const onConfirm = jest.fn();
      render(
        <Modals
          {...defaultProps}
          showConfirmDialog={true}
          onConfirmDialog={onConfirm}
        />
      );
      fireEvent.click(screen.getByTestId('save-and-close'));
      expect(onConfirm).toHaveBeenCalled();
    });

    it('should call onCloseWithoutSave when provided', () => {
      const onCloseWithoutSave = jest.fn();
      render(
        <Modals
          {...defaultProps}
          showConfirmDialog={true}
          onCloseWithoutSave={onCloseWithoutSave}
          confirmDialogSecondaryLabel="Close Without Saving"
        />
      );
      fireEvent.click(screen.getByTestId('close-without-save'));
      expect(onCloseWithoutSave).toHaveBeenCalled();
    });

    it('should not show secondary button when secondaryLabel is false', () => {
      render(
        <Modals
          {...defaultProps}
          showConfirmDialog={true}
          confirmDialogSecondaryLabel={false}
        />
      );
      expect(screen.queryByTestId('close-without-save')).not.toBeInTheDocument();
    });
  });

  describe('export dialog', () => {
    it('should render export dialog when showExportDialog is true', () => {
      render(
        <Modals
          {...defaultProps}
          showExportDialog={true}
          onCloseExportDialog={jest.fn()}
          onExport={jest.fn()}
          exportAvailableFormats={['json', 'svg']}
        />
      );
      expect(screen.getByTestId('export-dialog')).toBeInTheDocument();
    });

    it('should not render export dialog without required callbacks', () => {
      render(
        <Modals
          {...defaultProps}
          showExportDialog={true}
        />
      );
      expect(screen.queryByTestId('export-dialog')).not.toBeInTheDocument();
    });

    it('should pass available formats to export dialog', () => {
      render(
        <Modals
          {...defaultProps}
          showExportDialog={true}
          onCloseExportDialog={jest.fn()}
          onExport={jest.fn()}
          exportAvailableFormats={['recipe', 'archive', 'json', 'svg']}
        />
      );
      expect(screen.getByTestId('export-formats')).toHaveTextContent('recipe, archive, json, svg');
    });

    it('should call onCloseExportDialog when cancel clicked', () => {
      const onClose = jest.fn();
      render(
        <Modals
          {...defaultProps}
          showExportDialog={true}
          onCloseExportDialog={onClose}
          onExport={jest.fn()}
        />
      );
      fireEvent.click(screen.getByTestId('cancel-export'));
      expect(onClose).toHaveBeenCalled();
    });

    it('should call onExport when export button clicked', () => {
      const onExport = jest.fn();
      render(
        <Modals
          {...defaultProps}
          showExportDialog={true}
          onCloseExportDialog={jest.fn()}
          onExport={onExport}
        />
      );
      fireEvent.click(screen.getByTestId('export-json'));
      expect(onExport).toHaveBeenCalledWith('json');
    });
  });

  describe('multiple modals', () => {
    it('should render multiple modals when multiple are open', () => {
      render(
        <Modals
          {...defaultProps}
          showMetadataModal={true}
          showConfirmDialog={true}
        />
      );
      expect(screen.getByTestId('metadata-modal')).toBeInTheDocument();
      expect(screen.getByTestId('confirm-dialog')).toBeInTheDocument();
    });
  });
});
