import React, { useState, useMemo, useCallback, useEffect, useRef } from 'react';
import { FiX } from 'react-icons/fi';
import { t } from '../utils/translation';
import { useFocusTrap } from '../hooks/useFocusTrap';
import { labelToSnakeCase } from '../utils/modelUtils';
import { encodeMetadata, decodeMetadata, MODEL_ID_KEY } from '../utils/metadataCodec';
import type { MetadataFormData } from '../utils/metadataCodec';
import type { FormField } from '../types/forms';
import type { ModelData } from '../types/settings';
import ConfigurationForm from './ConfigurationForm';

interface MetadataModalProps {
  isOpen: boolean;
  onClose: () => void;
  /**
   * The model metadata form, converted from the shared Drupal form array the
   * model owner defines. The dialog renders whatever the backend sends; it
   * never knows a field list of its own.
   */
  form?: FormField[];
  metadata?: ModelData['metadata'];
  onSave: (data: MetadataFormData) => void;
  isNew?: boolean;
  modelId?: string;
  /** When false and not a new model, all fields are read-only. */
  canEditMetadata?: boolean;
  /** When false, the Template checkbox is disabled. */
  canCreateTemplate?: boolean;
}

const MetadataModal: React.FC<MetadataModalProps> = ({ isOpen, onClose, form, metadata, onSave, isNew = false, modelId, canEditMetadata = true, canCreateTemplate = true }) => {
  const dialogRef = useRef<HTMLDivElement>(null);
  const formRef = useRef<HTMLFormElement>(null);

  // Focus trap: keeps Tab inside the modal, Escape closes, restores focus on close.
  // Auto-focus is off because the trap would land on the header close button;
  // the effect below focuses the first actual field instead.
  useFocusTrap({
    isActive: isOpen,
    onClose,
    containerRef: dialogRef,
    autoFocus: false,
  });

  // Metadata fields are read-only when the permission is denied AND the model is not new.
  const fieldsReadOnly = !isNew && !canEditMetadata;
  const initialValues = useMemo(
    () => encodeMetadata(metadata, isNew, modelId),
    [metadata, isNew, modelId],
  );

  // ConfigurationForm seeds its values from `configuration` on mount only, so
  // opening the dialog on different metadata has to remount it. `generation`
  // is that remount key; `values` mirrors what the form currently holds.
  const [edit, setEdit] = useState({ seed: initialValues, values: initialValues, generation: 0 });
  if (edit.seed !== initialValues) {
    setEdit({ seed: initialValues, values: initialValues, generation: edit.generation + 1 });
  }

  const handleChange = useCallback((next: Record<string, unknown>) => {
    setEdit((prev) => ({ ...prev, values: next }));
  }, []);

  // Open on the first editable field, the way the dialog did before it was
  // generated from the backend form. Re-runs when the form is re-seeded
  // (a reopen on different metadata remounts it, so the old node is gone).
  useEffect(() => {
    if (!isOpen) {
      return;
    }
    const control = formRef.current?.querySelector<HTMLElement>(
      'input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [contenteditable="true"]',
    );
    if (!control) {
      return;
    }
    control.focus();
    // A new model starts on a default label, so preselect it: the user can
    // type their own straight over it.
    if (!isNew) {
      return;
    }
    // The attribute, not `isContentEditable`: the latter is a rendering
    // property that only a real layout engine computes.
    if (control.getAttribute('contenteditable') === 'true') {
      const range = document.createRange();
      range.selectNodeContents(control);
      const selection = window.getSelection();
      selection?.removeAllRanges();
      selection?.addRange(range);
      return;
    }
    if (control instanceof HTMLInputElement || control instanceof HTMLTextAreaElement) {
      control.select();
    }
  }, [isOpen, isNew, edit.generation]);

  // The two adjustments the dialog owns: a template may only be created with
  // the matching permission, and a model that does not exist yet has nothing
  // to write a changelog about.
  const preparedForm = useMemo(() => {
    if (!form) {
      return null;
    }
    const prepare = (fields: FormField[]): FormField[] => fields
      .filter((field) => !(isNew && field.key === 'changelog'))
      .map((field) => {
        if (field.key === 'template' && !canCreateTemplate) {
          return { ...field, disabled: true };
        }
        if (field.children) {
          return { ...field, children: prepare(field.children) };
        }
        return field;
      });
    return prepare(form);
  }, [form, isNew, canCreateTemplate]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const data = decodeMetadata(edit.values, metadata);
    // Only a new model carries an id: for an existing one the machine name is
    // fixed, and sending it would invite a rename the backend cannot do.
    if (isNew) {
      const typedId = edit.values[MODEL_ID_KEY];
      data.id = (typeof typedId === 'string' && typedId)
        ? typedId
        : labelToSnakeCase(typeof edit.values.label === 'string' ? edit.values.label : '');
    }
    onSave(data);
    onClose();
  };

  if (!isOpen) return null;

  return (
    <div className="metadata-modal-overlay">
      <div className="metadata-modal" ref={dialogRef} role="dialog" aria-modal="true" aria-labelledby="metadata-modal-title">
        <div className="metadata-modal-header">
          <h2 id="metadata-modal-title">{t('Model Information')}</h2>
          <button type="button" className="close-btn" onClick={onClose} aria-label={t('Close')}>
            <FiX />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="metadata-form" ref={formRef}>
          <ConfigurationForm
            key={edit.generation}
            form={preparedForm}
            configuration={edit.seed}
            onChange={handleChange}
            disabled={fieldsReadOnly}
          />

          <div className="form-actions">
            <button type="button" onClick={onClose} className="btn btn-secondary">
              {fieldsReadOnly ? t('Close') : t('Cancel')}
            </button>
            {!fieldsReadOnly && (
              <button type="submit" className="btn btn-primary">
                {t('Save')}
              </button>
            )}
          </div>
        </form>
      </div>
    </div>
  );
};

export default MetadataModal;
