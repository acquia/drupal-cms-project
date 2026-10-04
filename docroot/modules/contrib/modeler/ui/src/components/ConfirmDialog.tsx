import React, { useRef } from 'react';
import { FiAlertTriangle } from 'react-icons/fi';
import { t } from '../utils/translation';
import { useFocusTrap } from '../hooks/useFocusTrap';

interface ConfirmDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSaveAndClose: () => void;
  onCloseWithoutSave?: () => void;
  title?: string;
  message?: string;
  primaryButtonLabel?: string;
  /** Pass `false` to hide the secondary button entirely. */
  secondaryButtonLabel?: string | false;
  cancelButtonLabel?: string;
  primaryButtonVariant?: 'primary' | 'danger';
}

const ConfirmDialog: React.FC<ConfirmDialogProps> = ({
  isOpen,
  onClose,
  onSaveAndClose,
  onCloseWithoutSave,
  title = t("Unsaved Changes"),
  message = t("You have unsaved changes. What would you like to do?"),
  primaryButtonLabel = t('Save and Close'),
  secondaryButtonLabel = t('Close Without Saving'),
  cancelButtonLabel = t('Cancel'),
  primaryButtonVariant = 'primary'
}) => {
  const dialogRef = useRef<HTMLDivElement>(null);

  // Focus trap: keeps Tab inside the dialog, Escape closes, restores focus on close
  useFocusTrap({
    isActive: isOpen,
    onClose,
    containerRef: dialogRef,
  });

  if (!isOpen) return null;

  const handleOverlayClick = (e: React.MouseEvent<HTMLDivElement>) => {
    if (e.target === e.currentTarget) {
      onClose();
    }
  };

  return (
    <div className="confirm-dialog-overlay" onClick={handleOverlayClick}>
      <div className="confirm-dialog" ref={dialogRef} role="alertdialog" aria-modal="true" aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-message">
        <div className="confirm-dialog-header">
          <div className="confirm-dialog-icon">
            <FiAlertTriangle />
          </div>
          <h2 id="confirm-dialog-title">{title}</h2>
        </div>
        
        <div className="confirm-dialog-body">
          <p id="confirm-dialog-message">{message}</p>
        </div>
        
        <div className="confirm-dialog-footer">
          <button type="button" className={`btn btn-${primaryButtonVariant}`} onClick={onSaveAndClose}>
            {primaryButtonLabel}
          </button>
          {secondaryButtonLabel !== false && onCloseWithoutSave && (
            <button type="button" className="btn btn-danger" onClick={onCloseWithoutSave}>
              {secondaryButtonLabel}
            </button>
          )}
          <button type="button" className="btn btn-secondary" onClick={onClose}>
            {cancelButtonLabel}
          </button>
        </div>
      </div>
    </div>
  );
};

export default ConfirmDialog;