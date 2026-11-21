import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import MetadataModal from '../MetadataModal';
import { metadataForm, newModelMetadataForm } from '../__fixtures__/metadataForm';

/**
 * The dialog renders the shared configuration form, where text fields are
 * contenteditable widgets rather than native inputs. Typing is therefore
 * "set the text, fire input, blur" - blur is what flushes the value
 * synchronously, exactly as it does for a user who tabs away.
 */
function type(element: HTMLElement, value: string): void {
  element.textContent = value;
  fireEvent.input(element);
  fireEvent.blur(element);
}

describe('MetadataModal', () => {
  const configActions = [
    { config: 'system.site', actions: { simple_config_update: { slogan: 'Hi' } } },
  ];

  const metadata = {
    label: 'Test Model',
    version: '2.0.0',
    executable: true,
    template: false,
    storage: 'separate',
    documentation: 'Some docs',
    tags: ['one', 'two'],
    changelog: 'Initial',
    summary: 'A one-line description.',
    recipes: ['core/recipes/article_tags', 'core/recipes/page_content_type'],
    export_config: ['system.site'],
    modules: ['node'],
    config_actions: configActions,
  };

  const defaultProps = {
    isOpen: true,
    onClose: jest.fn(),
    onSave: jest.fn(),
    form: metadataForm,
    metadata,
    modelId: 'test_model',
    isNew: false,
  };

  const newModelProps = {
    ...defaultProps,
    form: newModelMetadataForm,
    metadata: { label: 'New Workflow', version: '1.0.0', executable: true, tags: [] },
    modelId: undefined,
    isNew: true,
  };

  /** The dialog's Save always submits the whole payload; this is what it sent. */
  const saved = (onSave: jest.Mock) => onSave.mock.calls[0][0];

  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('rendering', () => {
    it('should render nothing when isOpen is false', () => {
      render(<MetadataModal {...defaultProps} isOpen={false} />);
      expect(screen.queryByText('Model Information')).not.toBeInTheDocument();
    });

    it('should render every field of the delivered form', () => {
      render(<MetadataModal {...defaultProps} />);

      expect(screen.getByText('Model Information')).toBeInTheDocument();
      expect(screen.getByLabelText('Label')).toBeInTheDocument();
      expect(screen.getByLabelText('Model ID')).toBeInTheDocument();
      expect(screen.getByRole('checkbox', { name: 'Enabled' })).toBeInTheDocument();
      expect(screen.getByRole('checkbox', { name: 'Template' })).toBeInTheDocument();
      expect(screen.getByLabelText('Documentation')).toBeInTheDocument();
      expect(screen.getByLabelText('Tags')).toBeInTheDocument();
      expect(screen.getByLabelText('Summary')).toBeInTheDocument();
      expect(screen.getByLabelText('Included recipes')).toBeInTheDocument();
      expect(screen.getByLabelText('Additional config to export')).toBeInTheDocument();
      expect(screen.getByLabelText('Additional required modules')).toBeInTheDocument();
      expect(screen.getByLabelText('Config actions')).toBeInTheDocument();
      expect(screen.getByLabelText('Version')).toBeInTheDocument();
      expect(screen.getByRole('combobox', { name: 'Storage of raw data' })).toBeInTheDocument();
      expect(screen.getByLabelText('Changelog')).toBeInTheDocument();
    });

    it('should render both groups collapsed', () => {
      const { container } = render(<MetadataModal {...defaultProps} />);

      const groups = Array.from(container.querySelectorAll('details'));
      expect(groups.map((group) => group.querySelector('summary')?.textContent))
        .toEqual(['Recipe export', 'Advanced']);
      expect(groups.every((group) => group.hasAttribute('open'))).toBe(false);
    });

    it('should show the storage description the backend delivered', () => {
      render(<MetadataModal {...defaultProps} />);
      expect(screen.getByText('Controls if and how the raw modeler data is stored.')).toBeInTheDocument();
    });

    it('should render the storage options', () => {
      render(<MetadataModal {...defaultProps} />);

      expect(screen.getByRole('option', { name: 'Default' })).toBeInTheDocument();
      expect(screen.getByRole('option', { name: 'Do not store raw model data' })).toBeInTheDocument();
      expect(screen.getByRole('option', { name: 'Store raw data in separate config entity' })).toBeInTheDocument();
      expect(screen.getByRole('option', { name: 'Store raw data with config as third-party setting' })).toBeInTheDocument();
    });

    it('should not render the changelog for a new model', () => {
      render(<MetadataModal {...newModelProps} />);
      expect(screen.queryByLabelText('Changelog')).not.toBeInTheDocument();
    });

    it('should render no label control when the backend withheld the label field', () => {
      const withoutLabel = metadataForm.filter((field) => field.key !== 'label');
      render(<MetadataModal {...defaultProps} form={withoutLabel} />);

      expect(screen.queryByLabelText('Label')).not.toBeInTheDocument();
      expect(screen.getByLabelText('Model ID')).toBeInTheDocument();
    });

    it('should fall back to the empty state when no form was delivered', () => {
      render(<MetadataModal {...defaultProps} form={undefined} />);

      expect(screen.getByText('No configuration available')).toBeInTheDocument();
      expect(screen.queryByLabelText('Label')).not.toBeInTheDocument();
    });
  });

  describe('initial values', () => {
    it('should populate the plain fields from metadata', () => {
      render(<MetadataModal {...defaultProps} />);

      expect(screen.getByLabelText('Label')).toHaveTextContent('Test Model');
      expect(screen.getByLabelText('Version')).toHaveTextContent('2.0.0');
      expect(screen.getByLabelText('Documentation')).toHaveTextContent('Some docs');
      expect(screen.getByLabelText('Summary')).toHaveTextContent('A one-line description.');
      expect(screen.getByRole('combobox', { name: 'Storage of raw data' })).toHaveValue('separate');
      expect(screen.getByRole('checkbox', { name: 'Enabled' })).toBeChecked();
      expect(screen.getByRole('checkbox', { name: 'Template' })).not.toBeChecked();
    });

    it('should show tags comma separated and lists one per line', () => {
      render(<MetadataModal {...defaultProps} />);

      expect(screen.getByLabelText('Tags')).toHaveTextContent('one, two');
      expect(screen.getByLabelText('Included recipes').textContent)
        .toBe('core/recipes/article_tags\ncore/recipes/page_content_type');
      expect(screen.getByLabelText('Additional config to export')).toHaveTextContent('system.site');
      expect(screen.getByLabelText('Additional required modules')).toHaveTextContent('node');
    });

    it('should show config actions as YAML', () => {
      render(<MetadataModal {...defaultProps} />);

      const editor = screen.getByLabelText('Config actions') as HTMLTextAreaElement;
      expect(editor.value).toContain('- config: system.site');
      expect(editor.value).toContain('slogan: Hi');
    });

    it('should show the new values when reopened on different metadata', () => {
      const { rerender } = render(<MetadataModal {...defaultProps} />);
      expect(screen.getByLabelText('Label')).toHaveTextContent('Test Model');

      rerender(<MetadataModal {...defaultProps} metadata={{ ...metadata, label: 'Other Model' }} />);

      expect(screen.getByLabelText('Label')).toHaveTextContent('Other Model');
    });
  });

  describe('focus on open', () => {
    it('should focus the label control, not the close button', () => {
      render(<MetadataModal {...defaultProps} />);

      expect(screen.getByLabelText('Label')).toHaveFocus();
    });

    it('should select the default label of a new model so it can be typed over', () => {
      render(<MetadataModal {...newModelProps} />);

      expect(screen.getByLabelText('Label')).toHaveFocus();
      expect(window.getSelection()?.toString()).toBe('New Workflow');
    });
  });

  describe('machine name', () => {
    it('should derive the machine name from the label of a new model', () => {
      render(<MetadataModal {...newModelProps} />);

      type(screen.getByLabelText('Label'), 'My Test Model');

      expect(screen.getByLabelText('Model ID')).toHaveValue('my_test_model');
    });

    it('should keep a typed machine name when the label changes', () => {
      render(<MetadataModal {...newModelProps} />);

      fireEvent.change(screen.getByLabelText('Model ID'), { target: { value: 'custom_id' } });
      type(screen.getByLabelText('Label'), 'My Test Model');

      expect(screen.getByLabelText('Model ID')).toHaveValue('custom_id');
    });

    it('should derive again after the machine name is cleared', () => {
      render(<MetadataModal {...newModelProps} />);

      fireEvent.change(screen.getByLabelText('Model ID'), { target: { value: 'custom_id' } });
      fireEvent.change(screen.getByLabelText('Model ID'), { target: { value: '' } });
      type(screen.getByLabelText('Label'), 'Second Label');

      expect(screen.getByLabelText('Model ID')).toHaveValue('second_label');
    });

    it('should show the fixed machine name of an existing model, disabled', () => {
      render(<MetadataModal {...defaultProps} />);

      const machineName = screen.getByLabelText('Model ID');
      expect(machineName).toHaveValue('test_model');
      expect(machineName).toBeDisabled();
    });

    it('should send the machine name as the id of a new model', () => {
      const onSave = jest.fn();
      render(<MetadataModal {...newModelProps} onSave={onSave} />);

      type(screen.getByLabelText('Label'), 'My Test Model');
      fireEvent.click(screen.getByText('Save'));

      expect(saved(onSave).id).toBe('my_test_model');
    });

    it('should send no id for an existing model', () => {
      const onSave = jest.fn();
      render(<MetadataModal {...defaultProps} onSave={onSave} />);

      fireEvent.click(screen.getByText('Save'));

      expect('id' in saved(onSave)).toBe(false);
    });
  });

  describe('save behavior', () => {
    it('should send the edited values with lists as arrays', () => {
      const onSave = jest.fn();
      render(<MetadataModal {...defaultProps} onSave={onSave} />);

      type(screen.getByLabelText('Label'), 'Edited Model');
      type(screen.getByLabelText('Tags'), '  three , four , ');
      type(screen.getByLabelText('Included recipes'), 'core/recipes/article_tags\n\n  core/recipes/tags  \n');
      type(screen.getByLabelText('Additional required modules'), 'node\nuser\n');
      fireEvent.click(screen.getByText('Save'));

      expect(saved(onSave)).toEqual(expect.objectContaining({
        label: 'Edited Model',
        tags: ['three', 'four'],
        recipes: ['core/recipes/article_tags', 'core/recipes/tags'],
        modules: ['node', 'user'],
        export_config: ['system.site'],
        config_actions: configActions,
      }));
    });

    it('should round-trip untouched metadata', () => {
      const onSave = jest.fn();
      render(<MetadataModal {...defaultProps} onSave={onSave} />);

      fireEvent.click(screen.getByText('Save'));

      expect(saved(onSave)).toEqual(metadata);
    });

    it('should send an explicit empty for cleared fields', () => {
      const onSave = jest.fn();
      render(<MetadataModal {...defaultProps} onSave={onSave} />);

      type(screen.getByLabelText('Summary'), '');
      type(screen.getByLabelText('Included recipes'), '');
      type(screen.getByLabelText('Additional config to export'), '');
      fireEvent.change(screen.getByLabelText('Config actions'), { target: { value: '' } });
      fireEvent.click(screen.getByText('Save'));

      expect(saved(onSave)).toEqual(expect.objectContaining({
        summary: '',
        recipes: [],
        export_config: [],
        config_actions: [],
      }));
    });

    it('should save config actions parsed from the YAML editor', () => {
      const onSave = jest.fn();
      render(<MetadataModal {...defaultProps} onSave={onSave} />);

      fireEvent.change(screen.getByLabelText('Config actions'), {
        target: { value: '- config: user.settings\n  actions:\n    simple_config_update:\n      notify: true\n' },
      });
      fireEvent.click(screen.getByText('Save'));

      expect(saved(onSave).config_actions).toEqual([
        { config: 'user.settings', actions: { simple_config_update: { notify: true } } },
      ]);
    });

    it('should keep the previous config actions while the YAML is invalid', () => {
      const onSave = jest.fn();
      render(<MetadataModal {...defaultProps} onSave={onSave} />);

      fireEvent.change(screen.getByLabelText('Config actions'), { target: { value: '- config: [unclosed' } });
      fireEvent.click(screen.getByText('Save'));

      expect(saved(onSave).config_actions).toEqual(configActions);
    });

    it('should toggle the checkboxes', () => {
      const onSave = jest.fn();
      render(<MetadataModal {...defaultProps} onSave={onSave} />);

      fireEvent.click(screen.getByRole('checkbox', { name: 'Enabled' }));
      fireEvent.click(screen.getByRole('checkbox', { name: 'Template' }));
      fireEvent.click(screen.getByText('Save'));

      expect(saved(onSave)).toEqual(expect.objectContaining({ executable: false, template: true }));
    });

    it('should close after save', () => {
      const onClose = jest.fn();
      render(<MetadataModal {...defaultProps} onClose={onClose} />);

      fireEvent.click(screen.getByText('Save'));

      expect(onClose).toHaveBeenCalled();
    });
  });

  describe('permissions', () => {
    it('should disable the template checkbox without the permission', () => {
      render(<MetadataModal {...defaultProps} canCreateTemplate={false} />);

      expect(screen.getByRole('checkbox', { name: 'Template' })).toBeDisabled();
      expect(screen.getByRole('checkbox', { name: 'Enabled' })).toBeEnabled();
    });

    it('should hide Save and lock every control in read-only mode', () => {
      render(<MetadataModal {...defaultProps} canEditMetadata={false} />);

      expect(screen.queryByText('Save')).not.toBeInTheDocument();
      expect(screen.getByText('Close')).toBeInTheDocument();
      expect(screen.getByRole('checkbox', { name: 'Enabled' })).toBeDisabled();
      expect(screen.getByRole('combobox', { name: 'Storage of raw data' })).toBeDisabled();
      expect(screen.getByLabelText('Label')).toHaveAttribute('contenteditable', 'false');
      expect(screen.getByLabelText('Config actions')).toBeDisabled();
    });

    it('should stay editable for a new model without the edit permission', () => {
      render(<MetadataModal {...newModelProps} canEditMetadata={false} />);

      expect(screen.getByText('Save')).toBeInTheDocument();
      expect(screen.getByLabelText('Label')).toHaveAttribute('contenteditable', 'true');
    });
  });

  describe('close behavior', () => {
    it('should call onClose when the close button is clicked', () => {
      const onClose = jest.fn();
      render(<MetadataModal {...defaultProps} onClose={onClose} />);

      fireEvent.click(document.querySelector('.close-btn')!);

      expect(onClose).toHaveBeenCalled();
    });

    it('should call onClose when Cancel is clicked', () => {
      const onClose = jest.fn();
      render(<MetadataModal {...defaultProps} onClose={onClose} />);

      fireEvent.click(screen.getByText('Cancel'));

      expect(onClose).toHaveBeenCalled();
    });

    it('should call onClose when Escape is pressed', () => {
      const onClose = jest.fn();
      render(<MetadataModal {...defaultProps} onClose={onClose} />);

      fireEvent.keyDown(document, { key: 'Escape' });

      expect(onClose).toHaveBeenCalled();
    });
  });
});
