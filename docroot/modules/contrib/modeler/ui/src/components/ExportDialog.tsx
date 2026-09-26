/**
 * ExportDialog - Modal dialog for selecting export format and options
 *
 * Presents the user with available export formats (Recipe, Archive, JSON, SVG)
 * and format-specific options (e.g., include replay data for JSON export).
 */

import React, { useCallback, useRef, useState } from 'react';
import { FiPackage, FiArchive, FiFileText, FiImage } from 'react-icons/fi';
import { t } from '../utils/translation';
import { useFocusTrap } from '../hooks/useFocusTrap';
import type { ExportFormat } from '../hooks/useExport';

interface ExportDialogProps {
  /** Whether the dialog is open */
  isOpen: boolean;
  /** Callback to close the dialog */
  onClose: () => void;
  /** Available export formats */
  availableFormats: ExportFormat[];
  /** Whether replay data is available for inclusion in JSON export */
  hasReplayData: boolean;
  /** List of required modules (derived from model providers) */
  requiredModules: string[];
  /** Callback when the user confirms an export */
  onExport: (format: ExportFormat, includeReplayData?: boolean) => void;
  /** Whether an export is currently in progress */
  isExporting?: boolean;
}

/** Map of format to display info */
const FORMAT_INFO: Record<ExportFormat, {
  icon: React.ReactNode;
  label: string;
  description: string;
}> = {
  recipe: {
    icon: <FiPackage />,
    label: t('Recipe'),
    description: t('Export as a Drupal recipe for reuse across sites'),
  },
  archive: {
    icon: <FiArchive />,
    label: t('Archive'),
    description: t('Export as a .tar.gz archive with configuration files'),
  },
  json: {
    icon: <FiFileText />,
    label: t('JSON'),
    description: t('Export the model data as a JSON file'),
  },
  svg: {
    icon: <FiImage />,
    label: t('SVG'),
    description: t('Export the visual canvas as an SVG image'),
  },
};

const ExportDialog: React.FC<ExportDialogProps> = ({
  isOpen,
  onClose,
  availableFormats,
  hasReplayData,
  requiredModules,
  onExport,
  isExporting = false,
}) => {
  const dialogRef = useRef<HTMLDivElement>(null);
  const [selectedFormat, setSelectedFormat] = useState<ExportFormat | null>(null);
  const [includeReplayData, setIncludeReplayData] = useState(false);

  // Focus trap: keeps Tab inside the dialog, Escape closes
  useFocusTrap({
    isActive: isOpen,
    onClose,
    containerRef: dialogRef,
  });

  const handleExport = useCallback(() => {
    if (!selectedFormat) return;
    onExport(selectedFormat, selectedFormat === 'json' ? includeReplayData : undefined);
  }, [selectedFormat, includeReplayData, onExport]);

  const handleOverlayClick = useCallback((e: React.MouseEvent<HTMLDivElement>) => {
    if (e.target === e.currentTarget) {
      onClose();
    }
  }, [onClose]);

  const handleFormatSelect = useCallback((format: ExportFormat) => {
    setSelectedFormat(format);
  }, []);

  if (!isOpen) return null;

  return (
    <div className="export-dialog-overlay" onClick={handleOverlayClick}>
      <div
        className="export-dialog"
        ref={dialogRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby="export-dialog-title"
      >
        <div className="export-dialog-header">
          <h2 id="export-dialog-title">{t('Export Model')}</h2>
        </div>

        <div className="export-dialog-body">
          <p className="export-dialog-instruction">
            {t('Select an export format:')}
          </p>

          <div className="export-format-list" role="radiogroup" aria-label={t('Export format')}>
            {availableFormats.map((format) => {
              const info = FORMAT_INFO[format];
              return (
                <button
                  key={format}
                  type="button"
                  className={`export-format-option${selectedFormat === format ? ' selected' : ''}`}
                  role="radio"
                  aria-checked={selectedFormat === format}
                  onClick={() => handleFormatSelect(format)}
                >
                  <span className="export-format-icon">{info.icon}</span>
                  <span className="export-format-details">
                    <span className="export-format-label">{info.label}</span>
                    <span className="export-format-description">{info.description}</span>
                  </span>
                </button>
              );
            })}
          </div>

          {/* JSON-specific options */}
          {selectedFormat === 'json' && (
            <div className="export-options">
              {hasReplayData && (
                <label className="export-option-checkbox">
                  <input
                    type="checkbox"
                    checked={includeReplayData}
                    onChange={(e) => setIncludeReplayData(e.target.checked)}
                  />
                  <span>{t('Include replay data')}</span>
                </label>
              )}
              {requiredModules.length > 0 && (
                <div className="export-required-modules">
                  <span className="export-modules-label">{t('Required modules:')}</span>
                  <span className="export-modules-list">{requiredModules.join(', ')}</span>
                </div>
              )}
            </div>
          )}
        </div>

        <div className="export-dialog-footer">
          <button
            type="button"
            className="btn btn-primary"
            onClick={handleExport}
            disabled={!selectedFormat || isExporting}
          >
            {isExporting ? t('Exporting...') : t('Export')}
          </button>
          <button
            type="button"
            className="btn btn-secondary"
            onClick={onClose}
            disabled={isExporting}
          >
            {t('Cancel')}
          </button>
        </div>
      </div>
    </div>
  );
};

export default ExportDialog;
