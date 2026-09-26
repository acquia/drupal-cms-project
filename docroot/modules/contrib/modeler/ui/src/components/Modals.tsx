import React from 'react';
import MetadataModal from './MetadataModal';
import ConfirmDialog from './ConfirmDialog';
import ExportDialog from './ExportDialog';
import type { ExportFormat } from '../hooks/useExport';
import type { MetadataFormData } from '../utils/metadataCodec';
import type { FormField } from '../types/forms';
import type { ModelData } from '../types/settings';

interface ModalsProps {
  // Metadata Modal
  showMetadataModal: boolean;
  onCloseMetadataModal: () => void;
  onMetadataSubmit: (metadata: MetadataFormData) => void;
  modelMetadata: ModelData['metadata'];
  /** The converted model metadata form, as delivered by the backend. */
  metadataForm?: FormField[];
  modelId?: string;
  isNewModel?: boolean;
  /** Whether the user may edit metadata fields. */
  canEditMetadata?: boolean;
  /** Whether the user may mark a model as template. */
  canCreateTemplate?: boolean;
  
  // Confirm Dialog
  showConfirmDialog: boolean;
  confirmDialogTitle: string;
  confirmDialogMessage: string;
  confirmDialogType: 'danger' | 'warning' | 'info';
  onConfirmDialog: () => void;
  onCancelDialog: () => void;
  onCloseWithoutSave?: () => void;
  confirmDialogLoading: boolean;
  confirmDialogPrimaryLabel?: string;
  /** Pass `false` to hide the secondary button entirely. */
  confirmDialogSecondaryLabel?: string | false;
  confirmDialogCancelLabel?: string;
  confirmDialogPrimaryVariant?: 'primary' | 'danger';
  
  // Export Dialog
  showExportDialog?: boolean;
  onCloseExportDialog?: () => void;
  exportAvailableFormats?: ExportFormat[];
  exportHasReplayData?: boolean;
  exportRequiredModules?: string[];
  onExport?: (format: ExportFormat, includeReplayData?: boolean) => void;
  isExporting?: boolean;
}

const Modals: React.FC<ModalsProps> = ({
  // Metadata Modal
  showMetadataModal,
  onCloseMetadataModal,
  onMetadataSubmit,
  modelMetadata,
  metadataForm,
  modelId,
  isNewModel = false,
  canEditMetadata = true,
  canCreateTemplate = true,
  
  // Confirm Dialog
  showConfirmDialog,
  confirmDialogTitle,
  confirmDialogMessage,
  confirmDialogType: _confirmDialogType,
  onConfirmDialog,
  onCancelDialog,
  onCloseWithoutSave,
  confirmDialogLoading: _confirmDialogLoading,
  confirmDialogPrimaryLabel,
  confirmDialogSecondaryLabel,
  confirmDialogCancelLabel,
  confirmDialogPrimaryVariant,
  
  // Export Dialog
  showExportDialog,
  onCloseExportDialog,
  exportAvailableFormats = [],
  exportHasReplayData = false,
  exportRequiredModules = [],
  onExport,
  isExporting,

}) => {
  return (
    <>
      {/* Metadata Modal */}
      {showMetadataModal && (
        <MetadataModal
          isOpen={showMetadataModal}
          onClose={onCloseMetadataModal}
          onSave={onMetadataSubmit}
          form={metadataForm}
          metadata={modelMetadata}
          modelId={modelId}
          isNew={isNewModel}
          canEditMetadata={canEditMetadata}
          canCreateTemplate={canCreateTemplate}
        />
      )}

      {/* Confirm Dialog */}
      {showConfirmDialog && (
        <ConfirmDialog
          isOpen={showConfirmDialog}
          onClose={onCancelDialog}
          onSaveAndClose={onConfirmDialog}
          onCloseWithoutSave={onCloseWithoutSave}
          title={confirmDialogTitle}
          message={confirmDialogMessage}
          primaryButtonLabel={confirmDialogPrimaryLabel}
          secondaryButtonLabel={confirmDialogSecondaryLabel}
          cancelButtonLabel={confirmDialogCancelLabel}
          primaryButtonVariant={confirmDialogPrimaryVariant}
        />
      )}

      {/* Export Dialog */}
      {showExportDialog && onCloseExportDialog && onExport && (
        <ExportDialog
          isOpen={showExportDialog}
          onClose={onCloseExportDialog}
          availableFormats={exportAvailableFormats}
          hasReplayData={exportHasReplayData}
          requiredModules={exportRequiredModules}
          onExport={onExport}
          isExporting={isExporting}
        />
      )}

    </>
  );
};

export default Modals;