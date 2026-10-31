import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import ConfigurationForm from '../ConfigurationForm';

// Shorten the debounce for faster tests but keep every other dimension real:
// the form reaches the layout constants through modelUtils.
jest.mock('../../constants/dimensions', () => ({
  ...jest.requireActual('../../constants/dimensions'),
  TIMING: {
    DEBOUNCE_DELAY: 10, // Short delay for testing
  },
}));

// Mock the sanitize functions
jest.mock('../../utils/sanitize', () => ({
  sanitizeHtml: (html: string) => html,
  sanitizeTokenHtml: (html: string) => html,
  escapeHtml: (text: string) => text,
}));

describe('ConfigurationForm', () => {
  const defaultProps = {
    form: null,
    configuration: null,
    onChange: jest.fn(),
    disabled: false,
  };

  beforeEach(() => {
    jest.clearAllMocks();
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  describe('rendering with no form', () => {
    it('should show no configuration message when form is null', () => {
      render(<ConfigurationForm {...defaultProps} />);
      expect(screen.getByText('No configuration available')).toBeInTheDocument();
    });

    it('should show no configuration message when form is undefined', () => {
      render(<ConfigurationForm {...defaultProps} form={undefined} />);
      expect(screen.getByText('No configuration available')).toBeInTheDocument();
    });

    it('should show no configuration message when form is not an array', () => {
      render(<ConfigurationForm {...defaultProps} form={'not-array' as any} />);
      expect(screen.getByText('No configuration available')).toBeInTheDocument();
    });
  });

  describe('rendering form fields', () => {
    it('should render textfield type', () => {
      const form = [{ key: 'name', type: 'textfield', title: 'Name' }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByText('Name')).toBeInTheDocument();
    });

    it('should render email type', () => {
      const form = [{ key: 'email', type: 'email', title: 'Email' }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByText('Email')).toBeInTheDocument();
    });

    it('should render url type', () => {
      const form = [{ key: 'website', type: 'url', title: 'Website' }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByText('Website')).toBeInTheDocument();
    });

    it('should render textarea type', () => {
      const form = [{ key: 'description', type: 'textarea', title: 'Description' }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByText('Description')).toBeInTheDocument();
    });

    it('should render number type', () => {
      const form = [{ key: 'count', type: 'number', title: 'Count' }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByText('Count')).toBeInTheDocument();
      expect(screen.getByRole('spinbutton')).toBeInTheDocument();
    });

    it('should render checkbox type', () => {
      const form = [{ key: 'enabled', type: 'checkbox', title: 'Enabled' }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByText('Enabled')).toBeInTheDocument();
      expect(screen.getByRole('checkbox')).toBeInTheDocument();
    });

    it('should render select type', () => {
      const form = [{
        key: 'choice',
        type: 'select',
        title: 'Choice',
        options: { a: 'Option A', b: 'Option B' },
      }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByText('Choice')).toBeInTheDocument();
      expect(screen.getByRole('combobox')).toBeInTheDocument();
      expect(screen.getByRole('option', { name: 'Option A' })).toBeInTheDocument();
      expect(screen.getByRole('option', { name: 'Option B' })).toBeInTheDocument();
    });

    it('should render radios type', () => {
      const form = [{
        key: 'radio_choice',
        type: 'radios',
        title: 'Radio Choice',
        options: { x: 'Option X', y: 'Option Y' },
      }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByText('Radio Choice')).toBeInTheDocument();
      expect(screen.getAllByRole('radio')).toHaveLength(2);
    });

    it('should render checkboxes type', () => {
      const form = [{
        key: 'multi',
        type: 'checkboxes',
        title: 'Multi Select',
        options: { one: 'One', two: 'Two' },
      }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByText('Multi Select')).toBeInTheDocument();
      expect(screen.getAllByRole('checkbox')).toHaveLength(2);
    });

    it('should render markup type', () => {
      const form = [{
        key: 'info',
        type: 'markup',
        title: 'Information',
        markup: '<p>Some info</p>',
      }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByText('Information')).toBeInTheDocument();
      expect(screen.getByText('Some info')).toBeInTheDocument();
    });

    it('should render default type as text input', () => {
      const form = [{ key: 'unknown', type: 'unknown-type', title: 'Unknown' }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByRole('textbox')).toBeInTheDocument();
    });
  });

  describe('field labels and descriptions', () => {
    it('should render field title as label', () => {
      const form = [{ key: 'field', type: 'textfield', title: 'Field Title' }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByText('Field Title')).toBeInTheDocument();
    });

    it('should render required indicator', () => {
      const form = [{ key: 'field', type: 'textfield', title: 'Required Field', required: true }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(document.querySelector('.required')).toBeInTheDocument();
    });

    it('should render field description', () => {
      const form = [{
        key: 'field',
        type: 'textfield',
        title: 'Field',
        description: 'This is a description',
      }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByText('This is a description')).toBeInTheDocument();
    });
  });

  describe('initial values', () => {
    it('should use configuration values', () => {
      const form = [{ key: 'name', type: 'number', title: 'Name' }];
      const configuration = { name: 42 };
      render(<ConfigurationForm {...defaultProps} form={form} configuration={configuration} />);
      expect(screen.getByRole('spinbutton')).toHaveValue(42);
    });

    it('should use default value when no configuration', () => {
      const form = [{ key: 'count', type: 'number', title: 'Count', default_value: 10 }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByRole('spinbutton')).toHaveValue(10);
    });

    it('should prefer configuration over default value', () => {
      const form = [{ key: 'count', type: 'number', title: 'Count', default_value: 10 }];
      const configuration = { count: 20 };
      render(<ConfigurationForm {...defaultProps} form={form} configuration={configuration} />);
      expect(screen.getByRole('spinbutton')).toHaveValue(20);
    });
  });

  describe('number field constraints', () => {
    it('should apply min constraint', () => {
      const form = [{ key: 'count', type: 'number', title: 'Count', min: 0 }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByRole('spinbutton')).toHaveAttribute('min', '0');
    });

    it('should apply max constraint', () => {
      const form = [{ key: 'count', type: 'number', title: 'Count', max: 100 }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByRole('spinbutton')).toHaveAttribute('max', '100');
    });

    it('should apply step constraint', () => {
      const form = [{ key: 'count', type: 'number', title: 'Count', step: 5 }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByRole('spinbutton')).toHaveAttribute('step', '5');
    });
  });

  describe('number field with token support', () => {
    it('should render ContentEditableField when token_support is enabled', () => {
      const form = [{ key: 'value', type: 'number', title: 'Value', token_support: true }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      // Should NOT render a native number input (spinbutton)
      expect(screen.queryByRole('spinbutton')).toBeNull();
      // Should render a ContentEditableField (contenteditable div)
      expect(document.querySelector('[contenteditable]')).toBeInTheDocument();
    });

    it('should render ContentEditableField when replace_tokens is checked', () => {
      const form = [
        { key: 'replace_tokens', type: 'checkbox', title: 'Replace tokens' },
        { key: 'value', type: 'number', title: 'Value' },
      ];
      const configuration = { replace_tokens: true };
      render(<ConfigurationForm {...defaultProps} form={form} configuration={configuration} />);
      // Number field should use ContentEditableField
      expect(screen.queryByRole('spinbutton')).toBeNull();
      expect(document.querySelector('[contenteditable]')).toBeInTheDocument();
    });

    it('should render ContentEditableField when value contains a token pattern', () => {
      const form = [{ key: 'value', type: 'number', title: 'Value' }];
      const configuration = { value: '[node:field_length:value]' };
      render(<ConfigurationForm {...defaultProps} form={form} configuration={configuration} />);
      // Should switch to ContentEditableField to display the token
      expect(screen.queryByRole('spinbutton')).toBeNull();
      expect(document.querySelector('[contenteditable]')).toBeInTheDocument();
    });

    it('should render native number input when no token support and numeric value', () => {
      const form = [{ key: 'count', type: 'number', title: 'Count' }];
      const configuration = { count: 42 };
      render(<ConfigurationForm {...defaultProps} form={form} configuration={configuration} />);
      // Should render native number input
      expect(screen.getByRole('spinbutton')).toBeInTheDocument();
      expect(screen.getByRole('spinbutton')).toHaveValue(42);
    });

  });

  describe('onChange behavior', () => {
    it('should call onChange for number field changes', () => {
      const onChange = jest.fn();
      const form = [{ key: 'count', type: 'number', title: 'Count' }];
      render(<ConfigurationForm {...defaultProps} form={form} onChange={onChange} />);

      fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '42' } });

      expect(onChange).toHaveBeenCalledWith({ count: '42' });
    });

    it('should call onChange for checkbox toggle', () => {
      const onChange = jest.fn();
      const form = [{ key: 'enabled', type: 'checkbox', title: 'Enabled' }];
      render(<ConfigurationForm {...defaultProps} form={form} onChange={onChange} />);

      fireEvent.click(screen.getByRole('checkbox'));

      expect(onChange).toHaveBeenCalledWith({ enabled: true });
    });

    it('should call onChange for select change', () => {
      const onChange = jest.fn();
      const form = [{
        key: 'choice',
        type: 'select',
        title: 'Choice',
        options: { a: 'A', b: 'B' },
      }];
      render(<ConfigurationForm {...defaultProps} form={form} onChange={onChange} />);

      fireEvent.change(screen.getByRole('combobox'), { target: { value: 'b' } });

      expect(onChange).toHaveBeenCalledWith({ choice: 'b' });
    });

    it('should call onChange for radio change', () => {
      const onChange = jest.fn();
      const form = [{
        key: 'radio',
        type: 'radios',
        title: 'Radio',
        options: { x: 'X', y: 'Y' },
      }];
      render(<ConfigurationForm {...defaultProps} form={form} onChange={onChange} />);

      const radios = screen.getAllByRole('radio');
      fireEvent.click(radios[1]);

      expect(onChange).toHaveBeenCalledWith({ radio: 'y' });
    });

    it('should call onChange for checkboxes change (add)', () => {
      const onChange = jest.fn();
      const form = [{
        key: 'multi',
        type: 'checkboxes',
        title: 'Multi',
        options: { a: 'A', b: 'B' },
      }];
      render(<ConfigurationForm {...defaultProps} form={form} onChange={onChange} />);

      const checkboxes = screen.getAllByRole('checkbox');
      fireEvent.click(checkboxes[0]);

      expect(onChange).toHaveBeenCalledWith({ multi: ['a'] });
    });

    it('should call onChange for checkboxes change (remove)', () => {
      const onChange = jest.fn();
      const form = [{
        key: 'multi',
        type: 'checkboxes',
        title: 'Multi',
        options: { a: 'A', b: 'B' },
      }];
      const configuration = { multi: ['a', 'b'] };
      render(<ConfigurationForm {...defaultProps} form={form} configuration={configuration} onChange={onChange} />);

      const checkboxes = screen.getAllByRole('checkbox');
      fireEvent.click(checkboxes[0]); // Uncheck 'a'

      expect(onChange).toHaveBeenCalledWith({ multi: ['b'] });
    });
  });

  describe('disabled state', () => {
    it('should disable number input when disabled', () => {
      const form = [{ key: 'count', type: 'number', title: 'Count' }];
      render(<ConfigurationForm {...defaultProps} form={form} disabled={true} />);
      expect(screen.getByRole('spinbutton')).toBeDisabled();
    });

    it('should disable checkbox when disabled', () => {
      const form = [{ key: 'enabled', type: 'checkbox', title: 'Enabled' }];
      render(<ConfigurationForm {...defaultProps} form={form} disabled={true} />);
      expect(screen.getByRole('checkbox')).toBeDisabled();
    });

    it('should disable select when disabled', () => {
      const form = [{
        key: 'choice',
        type: 'select',
        title: 'Choice',
        options: { a: 'A' },
      }];
      render(<ConfigurationForm {...defaultProps} form={form} disabled={true} />);
      expect(screen.getByRole('combobox')).toBeDisabled();
    });

    it('should disable radio buttons when disabled', () => {
      const form = [{
        key: 'radio',
        type: 'radios',
        title: 'Radio',
        options: { a: 'A', b: 'B' },
      }];
      render(<ConfigurationForm {...defaultProps} form={form} disabled={true} />);
      const radios = screen.getAllByRole('radio');
      radios.forEach(radio => expect(radio).toBeDisabled());
    });

    it('should disable checkboxes when disabled', () => {
      const form = [{
        key: 'multi',
        type: 'checkboxes',
        title: 'Multi',
        options: { a: 'A', b: 'B' },
      }];
      render(<ConfigurationForm {...defaultProps} form={form} disabled={true} />);
      const checkboxes = screen.getAllByRole('checkbox');
      checkboxes.forEach(checkbox => expect(checkbox).toBeDisabled());
    });
  });

  describe('select default option', () => {
    it('should include the server-supplied placeholder option', () => {
      const form = [{
        key: 'choice',
        type: 'select',
        title: 'Choice',
        empty_option: { value: '', label: '- Select -' },
        options: { a: 'A' },
      }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByRole('option', { name: '- Select -' })).toBeInTheDocument();
    });
  });

  describe('multiple fields', () => {
    it('should render multiple fields', () => {
      const form = [
        { key: 'name', type: 'textfield', title: 'Name' },
        { key: 'count', type: 'number', title: 'Count' },
        { key: 'enabled', type: 'checkbox', title: 'Enabled' },
      ];
      render(<ConfigurationForm {...defaultProps} form={form} />);

      expect(screen.getByText('Name')).toBeInTheDocument();
      expect(screen.getByText('Count')).toBeInTheDocument();
      expect(screen.getByText('Enabled')).toBeInTheDocument();
    });

    it('should preserve values across field changes', () => {
      const onChange = jest.fn();
      const form = [
        { key: 'name', type: 'number', title: 'Name' },
        { key: 'count', type: 'number', title: 'Count' },
      ];
      const configuration = { name: 1, count: 2 };
      render(<ConfigurationForm {...defaultProps} form={form} configuration={configuration} onChange={onChange} />);

      const inputs = screen.getAllByRole('spinbutton');
      fireEvent.change(inputs[0], { target: { value: '10' } });

      expect(onChange).toHaveBeenCalledWith({ name: '10', count: 2 });
    });
  });


  describe('use_yaml / validate_yaml integration', () => {
    const yamlForm = [
      { key: 'config_value', type: 'textarea', title: 'Config value' },
      { key: 'use_yaml', type: 'checkbox', title: 'Use YAML', yaml_field: 'config_value' },
      { key: 'validate_yaml', type: 'checkbox', title: 'Validate YAML', yaml_field: 'config_value' },
    ];

    it('should render a plain textarea when use_yaml is unchecked', () => {
      const { container } = render(
        <ConfigurationForm {...defaultProps} form={yamlForm} configuration={{ use_yaml: false }} />
      );
      // No yaml-editor class on the textarea wrapper
      expect(container.querySelector('.yaml-editor')).toBeNull();
    });

    it('should render the YAML editor when use_yaml is checked', () => {
      const { container } = render(
        <ConfigurationForm {...defaultProps} form={yamlForm} configuration={{ use_yaml: true }} />
      );
      expect(container.querySelector('.yaml-editor')).toBeInTheDocument();
    });

    it('should switch from plain textarea to YAML editor when use_yaml is toggled on', () => {
      const onChange = jest.fn();
      const { container } = render(
        <ConfigurationForm {...defaultProps} form={yamlForm} onChange={onChange} />
      );
      // Initially no YAML editor
      expect(container.querySelector('.yaml-editor')).toBeNull();

      // Check the use_yaml checkbox (second checkbox in the form)
      const checkboxes = screen.getAllByRole('checkbox');
      // use_yaml is the first checkbox rendered
      fireEvent.click(checkboxes[0]);

      // YAML editor should now appear
      expect(container.querySelector('.yaml-editor')).toBeInTheDocument();
    });

    it('should hide validate_yaml checkbox when use_yaml is unchecked', () => {
      const { container } = render(
        <ConfigurationForm {...defaultProps} form={yamlForm} configuration={{ use_yaml: false }} />
      );
      // The validate_yaml field should have display: none
      const fields = container.querySelectorAll('.form-field');
      // Find the validate_yaml field (third field)
      const validateField = fields[2];
      expect(validateField).toHaveStyle('display: none');
    });

    it('should show validate_yaml checkbox when use_yaml is checked', () => {
      const { container } = render(
        <ConfigurationForm {...defaultProps} form={yamlForm} configuration={{ use_yaml: true }} />
      );
      const fields = container.querySelectorAll('.form-field');
      const validateField = fields[2];
      expect(validateField).not.toHaveStyle('display: none');
    });

    it('should render schema-less YAML editor without Editor/YAML mode toolbar', () => {
      const { container } = render(
        <ConfigurationForm
          {...defaultProps}
          form={yamlForm}
          configuration={{ use_yaml: true, config_value: 'key: value' }}
        />
      );
      // The schema-less editor has the yaml-editor-schemaless class
      expect(container.querySelector('.yaml-editor-schemaless')).toBeInTheDocument();
      // No toolbar buttons (Editor / YAML tabs)
      expect(container.querySelector('.yaml-editor-toolbar')).toBeNull();
    });

    it('should not show YAML validation errors when validate_yaml is unchecked', () => {
      const { container } = render(
        <ConfigurationForm
          {...defaultProps}
          form={yamlForm}
          configuration={{ use_yaml: true, validate_yaml: false, config_value: 'invalid: yaml: :::' }}
        />
      );
      expect(container.querySelector('.yaml-editor-error')).toBeNull();
    });

    it('should show YAML validation errors when validate_yaml is checked and YAML is invalid', () => {
      const { container } = render(
        <ConfigurationForm
          {...defaultProps}
          form={yamlForm}
          configuration={{ use_yaml: true, validate_yaml: true, config_value: 'invalid: yaml: :::' }}
        />
      );
      expect(container.querySelector('.yaml-editor-error')).toBeInTheDocument();
    });

    it('should not show validation errors when validate_yaml is checked and YAML is valid', () => {
      const { container } = render(
        <ConfigurationForm
          {...defaultProps}
          form={yamlForm}
          configuration={{ use_yaml: true, validate_yaml: true, config_value: 'key: value' }}
        />
      );
      expect(container.querySelector('.yaml-editor-error')).toBeNull();
    });
  });

  describe('select empty option (server-driven)', () => {
    it('should render the server-supplied empty_option as the first option', () => {
      const form = [{
        key: 'choice',
        type: 'select',
        title: 'Choice',
        empty_option: { value: '', label: '- Select -' },
        options: { a: 'Option A', b: 'Option B' },
      }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      // The empty option is present and is the first option in the list.
      expect(screen.getByRole('option', { name: '- Select -' })).toBeInTheDocument();
      const options = screen.getAllByRole('option');
      expect(options[0]).toHaveTextContent('- Select -');
      expect((options[0] as HTMLOptionElement).value).toBe('');
      // Exactly one option with an empty value.
      const emptyOptions = options.filter((opt) => (opt as HTMLOptionElement).value === '');
      expect(emptyOptions).toHaveLength(1);
    });

    it('should render NO empty option when empty_option is absent', () => {
      const form = [{
        key: 'choice',
        type: 'select',
        title: 'Choice',
        options: { a: 'Option A', b: 'Option B' },
      }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      // No option carries an empty value beyond the real choices.
      const emptyOptions = screen
        .getAllByRole('option')
        .filter((opt) => (opt as HTMLOptionElement).value === '');
      expect(emptyOptions).toHaveLength(0);
      expect(screen.getByRole('option', { name: 'Option A' })).toBeInTheDocument();
      expect(screen.getByRole('option', { name: 'Option B' })).toBeInTheDocument();
    });

    it('should render the server-supplied "- None -" label verbatim', () => {
      const form = [{
        key: 'choice',
        type: 'select',
        title: 'Choice',
        empty_option: { value: '', label: '- None -' },
        options: { a: 'Option A' },
      }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByRole('option', { name: '- None -' })).toBeInTheDocument();
      expect(screen.queryByRole('option', { name: '- Select -' })).toBeNull();
    });
  });

  describe('form #states support', () => {
    it('should hide a visible-state field until the controlling checkbox is checked', () => {
      const form = [
        { key: 'toggle', type: 'checkbox', title: 'Toggle' },
        {
          key: 'dependent',
          type: 'textfield',
          title: 'Dependent',
          states: { visible: [[{ field: 'toggle', checked: true }]] },
        },
      ];

      // Initially hidden (toggle unchecked).
      const { container } = render(
        <ConfigurationForm {...defaultProps} form={form} configuration={{ toggle: false }} />
      );
      expect(container.querySelectorAll('.form-field')[1]).toHaveStyle('display: none');

      // After toggling the controlling checkbox value, the dependent shows.
      fireEvent.click(screen.getByRole('checkbox'));
      expect(container.querySelectorAll('.form-field')[1]).not.toHaveStyle('display: none');
    });

    it('should hide an invisible-state field when its conditions hold', () => {
      const form = [
        {
          key: 'advanced',
          type: 'textfield',
          title: 'Advanced',
          states: { invisible: [[{ field: 'mode', value: 'simple' }]] },
        },
      ];

      // mode === 'simple' => advanced is hidden.
      const { container } = render(
        <ConfigurationForm {...defaultProps} form={form} configuration={{ mode: 'simple' }} />
      );
      expect(container.querySelectorAll('.form-field')[0]).toHaveStyle('display: none');

      // mode !== 'simple' => advanced is shown.
      const { container: container2 } = render(
        <ConfigurationForm {...defaultProps} form={form} configuration={{ mode: 'expert' }} />
      );
      expect(container2.querySelectorAll('.form-field')[0]).not.toHaveStyle('display: none');
    });

    it('should toggle the required marker based on a required-state condition', () => {
      const form = [
        { key: 'enabled', type: 'checkbox', title: 'Enabled' },
        {
          key: 'name',
          type: 'textfield',
          title: 'Name',
          states: { required: [[{ field: 'enabled', checked: true }]] },
        },
      ];

      // enabled unchecked => not required (no marker).
      const { container } = render(
        <ConfigurationForm {...defaultProps} form={form} configuration={{ enabled: false }} />
      );
      expect(container.querySelector('.required')).toBeNull();

      // Checking the controlling checkbox makes the dependent required.
      fireEvent.click(screen.getByRole('checkbox'));
      expect(container.querySelector('.required')).toBeInTheDocument();
    });

    it('should show a field visible when ANY of two OR groups matches', () => {
      // Mirrors the ECA LoadEntity 'properties' textarea, which is visible when
      // (from == 'properties') OR (from == '_eca_token'). The normalized shape
      // is two OR groups, each a single-condition AND group.
      const form = [
        {
          key: 'properties',
          type: 'textarea',
          title: 'Properties',
          states: {
            visible: [
              [{ field: 'from', value: 'properties' }],
              [{ field: 'from', value: '_eca_token' }],
            ],
          },
        },
      ];

      // from === 'id' => neither group matches => hidden.
      const { container } = render(
        <ConfigurationForm {...defaultProps} form={form} configuration={{ from: 'id' }} />
      );
      expect(container.querySelectorAll('.form-field')[0]).toHaveStyle('display: none');

      // from === 'properties' => first group matches => shown.
      const { container: container2 } = render(
        <ConfigurationForm {...defaultProps} form={form} configuration={{ from: 'properties' }} />
      );
      expect(container2.querySelectorAll('.form-field')[0]).not.toHaveStyle('display: none');

      // from === '_eca_token' => second group matches => shown.
      const { container: container3 } = render(
        <ConfigurationForm {...defaultProps} form={form} configuration={{ from: '_eca_token' }} />
      );
      expect(container3.querySelectorAll('.form-field')[0]).not.toHaveStyle('display: none');
    });

    it('should require all conditions within a single AND group', () => {
      const form = [
        {
          key: 'field',
          type: 'textfield',
          title: 'Field',
          states: {
            visible: [[
              { field: 'mode', value: 'advanced' },
              { field: 'enabled', checked: true },
            ]],
          },
        },
      ];

      // Only one condition of the AND group holds => hidden.
      const { container } = render(
        <ConfigurationForm {...defaultProps} form={form} configuration={{ mode: 'advanced', enabled: false }} />
      );
      expect(container.querySelectorAll('.form-field')[0]).toHaveStyle('display: none');

      // Both conditions hold => shown.
      const { container: container2 } = render(
        <ConfigurationForm {...defaultProps} form={form} configuration={{ mode: 'advanced', enabled: true }} />
      );
      expect(container2.querySelectorAll('.form-field')[0]).not.toHaveStyle('display: none');
    });

    it('should treat an array condition value as match-any', () => {
      const form = [
        {
          key: 'field',
          type: 'textfield',
          title: 'Field',
          states: { visible: [[{ field: 'mode', value: ['a', 'b'] }]] },
        },
      ];

      // mode not in ['a', 'b'] => hidden.
      const { container } = render(
        <ConfigurationForm {...defaultProps} form={form} configuration={{ mode: 'c' }} />
      );
      expect(container.querySelectorAll('.form-field')[0]).toHaveStyle('display: none');

      // mode === 'b' => shown.
      const { container: container2 } = render(
        <ConfigurationForm {...defaultProps} form={form} configuration={{ mode: 'b' }} />
      );
      expect(container2.querySelectorAll('.form-field')[0]).not.toHaveStyle('display: none');
    });
  });

  describe('field groups / nested fields', () => {
    const groupForm = [
      {
        key: 'settings',
        type: 'group',
        title: 'Settings',
        children: [
          { key: 'first', type: 'textfield', title: 'First Field' },
          { key: 'second', type: 'number', title: 'Second Field' },
        ],
      },
    ];

    it('should render a group title and both child inputs', () => {
      render(<ConfigurationForm {...defaultProps} form={groupForm} />);
      expect(screen.getByText('Settings')).toBeInTheDocument();
      expect(screen.getByText('First Field')).toBeInTheDocument();
      expect(screen.getByText('Second Field')).toBeInTheDocument();
      expect(screen.getByRole('spinbutton')).toBeInTheDocument();
    });

    it('should render a details group as a collapsible disclosure', () => {
      const detailsForm = [
        {
          key: 'advanced',
          type: 'group',
          title: 'Advanced',
          open: false,
          children: [{ key: 'opt', type: 'textfield', title: 'Option' }],
        },
      ];
      const { container } = render(<ConfigurationForm {...defaultProps} form={detailsForm} />);
      const details = container.querySelector('details.form-group-details');
      expect(details).toBeInTheDocument();
      // open=false => the details element is collapsed.
      expect(details).not.toHaveAttribute('open');
      expect(container.querySelector('summary.form-group-title')).toHaveTextContent('Advanced');
    });

    it('should call onChange with the flat key when a nested child changes', () => {
      const onChange = jest.fn();
      render(<ConfigurationForm {...defaultProps} form={groupForm} onChange={onChange} />);

      fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '7' } });

      // Nested child values flow through the SAME flat values object.
      expect(onChange).toHaveBeenCalledWith({ second: '7' });
    });
  });
});
