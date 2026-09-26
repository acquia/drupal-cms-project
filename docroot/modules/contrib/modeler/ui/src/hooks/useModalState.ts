/**
 * Custom hook for modal state management
 * Handles metadata modal and confirmation dialog state
 */

import { useState, useCallback } from 'react';
import { useModelStore } from '../store/useModelStore';

/**
 * Metadata as the dialog submits it.
 *
 * Every member is optional: the metadata form comes from the model owner, and
 * a field the owner hides is neither rendered nor submitted, so its stored
 * value has to survive the merge below untouched.
 */
interface ModelMetadata {
  id?: string;
  label?: string;
  version?: string;
  executable?: boolean;
  template?: boolean;
  storage?: string;
  documentation?: string;
  tags?: string[];
  changelog?: string;
  summary?: string;
  recipes?: string[];
  config_actions?: Array<{ config: string; actions: unknown }>;
  export_config?: string[];
  modules?: string[];
}

interface UseModalStateProps {
  setHasUnsavedChanges: (value: boolean) => void;
}

export function useModalState({ setHasUnsavedChanges }: UseModalStateProps) {
  const setModelData = useModelStore(state => state.setModelData);

  // Modal state
  const [showMetadataModal, setShowMetadataModal] = useState(false);
  const [showConfirmDialog, setShowConfirmDialog] = useState(false);
  const [confirmDialogTitle, setConfirmDialogTitle] = useState('');
  const [confirmDialogMessage, setConfirmDialogMessage] = useState('');
  const [confirmDialogType, setConfirmDialogType] = useState<'danger' | 'warning' | 'info'>('info');
  const [confirmDialogLoading, setConfirmDialogLoading] = useState(false);
  const [confirmCallback, setConfirmCallback] = useState<(() => void) | null>(null);
  const [closeWithoutSaveCallback, setCloseWithoutSaveCallback] = useState<(() => void) | null>(null);
  const [primaryButtonLabel, setPrimaryButtonLabel] = useState<string | undefined>(undefined);
  const [secondaryButtonLabel, setSecondaryButtonLabel] = useState<string | false | undefined>(undefined);
  const [cancelButtonLabel, setCancelButtonLabel] = useState<string | undefined>(undefined);
  const [primaryButtonVariant, setPrimaryButtonVariant] = useState<'primary' | 'danger' | undefined>(undefined);

  // Handle metadata form submission
  const onMetadataSubmit = useCallback((metadata: ModelMetadata) => {
    const { id, ...metadataWithoutId } = metadata;
    setModelData(prev => ({
      ...(prev || {}),
      // Update model ID if provided (for new models)
      ...(id ? { id } : {}),
      metadata: {
        ...(prev?.metadata || {}),
        ...metadataWithoutId,
      },
    }));
    setShowMetadataModal(false);
    setHasUnsavedChanges(true);
  }, [setModelData, setHasUnsavedChanges]);

  // Show confirmation dialog with callbacks.
  // The first 5 positional args are the original API; the optional 6th arg
  // is an options bag for button customization so callers stay readable.
  const showConfirmationDialog = useCallback((
    title: string,
    message: string,
    type: 'danger' | 'warning' | 'info' = 'info',
    onSaveAndCloseCallback?: () => void,
    onCloseWithoutSaveCallback?: () => void,
    options?: {
      primaryLabel?: string;
      secondaryLabel?: string | false;
      cancelLabel?: string;
      primaryVariant?: 'primary' | 'danger';
    }
  ) => {
    setConfirmDialogTitle(title);
    setConfirmDialogMessage(message);
    setConfirmDialogType(type);
    setConfirmCallback(() => onSaveAndCloseCallback || null);
    setCloseWithoutSaveCallback(() => onCloseWithoutSaveCallback || null);
    setPrimaryButtonLabel(options?.primaryLabel);
    // `false` explicitly hides the secondary button; `undefined` keeps the default.
    setSecondaryButtonLabel(options?.secondaryLabel);
    setCancelButtonLabel(options?.cancelLabel);
    setPrimaryButtonVariant(options?.primaryVariant);
    setShowConfirmDialog(true);
    setConfirmDialogLoading(false);
  }, []);

  // Handle confirm dialog actions
  const handleConfirmDialog = useCallback(() => {
    if (confirmCallback) {
      setConfirmDialogLoading(true);
      confirmCallback();
    }
    setShowConfirmDialog(false);
    setConfirmDialogLoading(false);
  }, [confirmCallback]);

  const handleCancelDialog = useCallback(() => {
    setShowConfirmDialog(false);
    setConfirmDialogLoading(false);
  }, []);

  const handleCloseWithoutSave = useCallback(() => {
    if (closeWithoutSaveCallback) {
      closeWithoutSaveCallback();
    }
    setShowConfirmDialog(false);
    setConfirmDialogLoading(false);
  }, [closeWithoutSaveCallback]);

  // Open/close modal helpers
  const openMetadataModal = useCallback(() => {
    setShowMetadataModal(true);
  }, []);

  const closeMetadataModal = useCallback(() => {
    setShowMetadataModal(false);
  }, []);

  return {
    // Modal state
    showMetadataModal,
    showConfirmDialog,
    confirmDialogTitle,
    confirmDialogMessage,
    confirmDialogType,
    confirmDialogLoading,
    confirmDialogPrimaryLabel: primaryButtonLabel,
    confirmDialogSecondaryLabel: secondaryButtonLabel,
    confirmDialogCancelLabel: cancelButtonLabel,
    confirmDialogPrimaryVariant: primaryButtonVariant,

    // Modal actions
    onMetadataSubmit,
    showConfirmationDialog,
    handleConfirmDialog,
    handleCancelDialog,
    handleCloseWithoutSave,
    openMetadataModal,
    closeMetadataModal,
    setShowMetadataModal,
    setShowConfirmDialog,
    setConfirmDialogLoading
  };
}