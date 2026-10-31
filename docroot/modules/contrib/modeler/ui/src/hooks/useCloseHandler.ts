/**
 * Custom hook for handling modeler close functionality
 * Manages close and save-and-close actions.
 */

import { useCallback, useRef } from 'react';
import type { Settings } from '../types/settings';

interface UseCloseHandlerProps {
  settings: Settings;
  hasUnsavedChanges: boolean;
  showConfirmationDialog: (
    title: string,
    message: string,
    type: 'danger' | 'warning' | 'info',
    onSaveAndCloseCallback?: () => void,
    onCloseWithoutSaveCallback?: () => void
  ) => void;
}

export function useCloseHandler({
  settings,
  hasUnsavedChanges,
  showConfirmationDialog,
}: UseCloseHandlerProps) {
  const pendingCloseAfterSaveRef = useRef(false);
  const saveButtonRef = useRef<HTMLButtonElement>(null);

  // Perform the actual close action
  const performClose = useCallback(() => {
    if (settings.modeler?.stayInContextOnClose) {
      // Close the overlay by hiding the modeler wrapper
      const wrapper = document.getElementById('workflow-modeler-wrapper');
      if (wrapper) {
        wrapper.style.display = 'none';
      }
    } else {
      // Navigate to collection URL
      const collectionUrl = settings.modeler_api?.collection_url;
      if (collectionUrl) {
        window.location.href = collectionUrl;
      }
    }
  }, [settings]);

  // Handle save and close - triggers save then closes on completion
  const handleSaveAndClose = useCallback(() => {
    pendingCloseAfterSaveRef.current = true;
    // Trigger save by clicking the save button
    if (saveButtonRef.current) {
      saveButtonRef.current.click();
    }
  }, []);

  // Handle close with unsaved changes check
  const handleClose = useCallback(() => {
    if (hasUnsavedChanges) {
      showConfirmationDialog(
        'Unsaved Changes',
        'You have unsaved changes. What would you like to do?',
        'warning',
        handleSaveAndClose,
        performClose
      );
    } else {
      performClose();
    }
  }, [hasUnsavedChanges, showConfirmationDialog, handleSaveAndClose, performClose]);

  // Callback for when save completes - checks if we should close
  const handleSaveComplete = useCallback((setHasUnsavedChanges: (value: boolean) => void) => {
    setHasUnsavedChanges(false);
    if (pendingCloseAfterSaveRef.current) {
      pendingCloseAfterSaveRef.current = false;
      performClose();
    }
  }, [performClose]);

  return {
    handleClose,
    handleSaveComplete,
    saveButtonRef,
  };
}
