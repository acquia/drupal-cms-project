/**
 * EdgeDeleteButton - A small trash button that appears at the edge midpoint
 * (alongside the quick-add plus button) to delete a connection.
 *
 * Mirrors the node delete affordance (NodeWrapper's `.node-footer-delete`):
 * neutral by default, turning danger-red on hover/focus/active.  Deletion is
 * immediate (no modal) because undo already exists.  The keyboard
 * Delete/Backspace path is handled separately and is unaffected by this
 * component.
 */

import React, { useCallback } from 'react';
import { FiTrash2 } from 'react-icons/fi';
import { t } from '../../utils/translation';

interface EdgeDeleteButtonProps {
  /** ID of the edge this button deletes. */
  edgeId: string;
  /** Callback invoked with the edge ID when the trash button is clicked. */
  onDelete: (edgeId: string) => void;
  /** When true, the button does not render. */
  disabled?: boolean;
}

const EdgeDeleteButton: React.FC<EdgeDeleteButtonProps> = ({
  edgeId,
  onDelete,
  disabled = false,
}) => {
  const handleClick = useCallback(
    (e: React.MouseEvent) => {
      e.stopPropagation();
      e.preventDefault();
      onDelete(edgeId);
    },
    [onDelete, edgeId],
  );

  if (disabled) {
    return null;
  }

  return (
    <button
      type="button"
      className="edge-delete-button nodrag nopan"
      onClick={handleClick}
      title={t('Delete connection')}
      aria-label={t('Delete connection')}
      data-edge-id={edgeId}
    >
      <FiTrash2 size={14} />
    </button>
  );
};

export default EdgeDeleteButton;
