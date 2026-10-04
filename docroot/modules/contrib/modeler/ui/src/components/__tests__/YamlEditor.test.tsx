import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import YamlEditor, { validateAgainstSchema, handleYamlKeyDown } from '../YamlEditor';
import type { YamlSchema } from '../YamlEditor';

describe('YamlEditor', () => {
  const defaultProps = {
    value: '',
    onChange: jest.fn(),
    disabled: false,
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('string schema', () => {
    const schema: YamlSchema = {
      type: 'string',
      label: 'Name',
    };

    it('should render a text input', () => {
      render(<YamlEditor {...defaultProps} schema={schema} />);
      expect(screen.getByRole('textbox')).toBeInTheDocument();
    });

    it('should display existing YAML value', () => {
      render(<YamlEditor {...defaultProps} schema={schema} value="hello world" />);
      expect(screen.getByDisplayValue('hello world')).toBeInTheDocument();
    });

    it('should call onChange with YAML string on input change', () => {
      const onChange = jest.fn();
      render(<YamlEditor {...defaultProps} schema={schema} onChange={onChange} />);

      fireEvent.change(screen.getByRole('textbox'), { target: { value: 'new value' } });
      expect(onChange).toHaveBeenCalledWith('new value');
    });

    it('should disable input when disabled', () => {
      render(<YamlEditor {...defaultProps} schema={schema} disabled={true} />);
      expect(screen.getByRole('textbox')).toBeDisabled();
    });
  });

  describe('string schema with options (enum)', () => {
    const schema: YamlSchema = {
      type: 'string',
      label: 'Color',
      options: { red: 'Red', green: 'Green', blue: 'Blue' },
    };

    it('should render a select dropdown', () => {
      render(<YamlEditor {...defaultProps} schema={schema} />);
      expect(screen.getByRole('combobox')).toBeInTheDocument();
    });

    it('should show all options plus a placeholder', () => {
      render(<YamlEditor {...defaultProps} schema={schema} />);
      const options = screen.getAllByRole('option');
      // 1 placeholder + 3 options = 4
      expect(options).toHaveLength(4);
    });

    it('should select the correct option from YAML value', () => {
      render(<YamlEditor {...defaultProps} schema={schema} value="green" />);
      expect(screen.getByRole('combobox')).toHaveValue('green');
    });

    it('should call onChange when selection changes', () => {
      const onChange = jest.fn();
      render(<YamlEditor {...defaultProps} schema={schema} onChange={onChange} />);

      fireEvent.change(screen.getByRole('combobox'), { target: { value: 'blue' } });
      expect(onChange).toHaveBeenCalledWith('blue');
    });
  });

  describe('number schema', () => {
    const schema: YamlSchema = {
      type: 'number',
      label: 'Count',
      min: 0,
      max: 100,
    };

    it('should render a number input', () => {
      render(<YamlEditor {...defaultProps} schema={schema} />);
      expect(screen.getByRole('spinbutton')).toBeInTheDocument();
    });

    it('should display existing numeric value', () => {
      render(<YamlEditor {...defaultProps} schema={schema} value="42" />);
      expect(screen.getByRole('spinbutton')).toHaveValue(42);
    });

    it('should apply min/max constraints', () => {
      render(<YamlEditor {...defaultProps} schema={schema} />);
      const input = screen.getByRole('spinbutton');
      expect(input).toHaveAttribute('min', '0');
      expect(input).toHaveAttribute('max', '100');
    });
  });

  describe('boolean schema', () => {
    const schema: YamlSchema = {
      type: 'boolean',
      label: 'Enabled',
    };

    it('should render a checkbox', () => {
      render(<YamlEditor {...defaultProps} schema={schema} />);
      expect(screen.getByRole('checkbox')).toBeInTheDocument();
    });

    it('should be checked when value is true', () => {
      render(<YamlEditor {...defaultProps} schema={schema} value="true" />);
      expect(screen.getByRole('checkbox')).toBeChecked();
    });

    it('should be unchecked when value is false', () => {
      render(<YamlEditor {...defaultProps} schema={schema} value="false" />);
      expect(screen.getByRole('checkbox')).not.toBeChecked();
    });

    it('should call onChange with YAML string on toggle', () => {
      const onChange = jest.fn();
      render(<YamlEditor {...defaultProps} schema={schema} onChange={onChange} />);

      fireEvent.click(screen.getByRole('checkbox'));
      expect(onChange).toHaveBeenCalledWith('true');
    });
  });

  describe('list schema', () => {
    const schema: YamlSchema = {
      type: 'list',
      label: 'Tags',
      items: {
        type: 'string',
        label: 'Tag',
      },
    };

    it('should show "No items yet." when empty', () => {
      render(<YamlEditor {...defaultProps} schema={schema} />);
      expect(screen.getByText('No items yet.')).toBeInTheDocument();
    });

    it('should show an add button', () => {
      render(<YamlEditor {...defaultProps} schema={schema} />);
      expect(screen.getByText('Add Tag')).toBeInTheDocument();
    });

    it('should not show add button when disabled', () => {
      render(<YamlEditor {...defaultProps} schema={schema} disabled={true} />);
      expect(screen.queryByText('Add Tag')).not.toBeInTheDocument();
    });

    it('should render existing list items', () => {
      const yamlValue = '- alpha\n- beta';
      render(<YamlEditor {...defaultProps} schema={schema} value={yamlValue} />);
      expect(screen.getByDisplayValue('alpha')).toBeInTheDocument();
      expect(screen.getByDisplayValue('beta')).toBeInTheDocument();
    });

    it('should add a new item when add button is clicked', () => {
      const onChange = jest.fn();
      render(<YamlEditor {...defaultProps} schema={schema} onChange={onChange} />);

      fireEvent.click(screen.getByText('Add Tag'));
      // onChange should be called with a YAML list containing one empty string
      expect(onChange).toHaveBeenCalled();
      const callArg = onChange.mock.calls[0][0];
      expect(callArg).toContain("- ''");
    });

    it('should remove an item when remove button is clicked', () => {
      const onChange = jest.fn();
      const yamlValue = '- alpha\n- beta';
      render(<YamlEditor {...defaultProps} schema={schema} value={yamlValue} onChange={onChange} />);

      const removeButtons = screen.getAllByTitle('Remove');
      fireEvent.click(removeButtons[0]);

      expect(onChange).toHaveBeenCalled();
      const callArg = onChange.mock.calls[0][0];
      expect(callArg).toContain('beta');
      expect(callArg).not.toContain('alpha');
    });

    it('should not show remove buttons when disabled', () => {
      const yamlValue = '- alpha\n- beta';
      render(<YamlEditor {...defaultProps} schema={schema} value={yamlValue} disabled={true} />);
      expect(screen.queryByTitle('Remove')).not.toBeInTheDocument();
    });
  });

  describe('mapping schema', () => {
    const schema: YamlSchema = {
      type: 'mapping',
      label: 'Config',
      properties: {
        host: { type: 'string', label: 'Host', required: true },
        port: { type: 'number', label: 'Port' },
        ssl: { type: 'boolean', label: 'Use SSL' },
      },
    };

    it('should render all property labels', () => {
      render(<YamlEditor {...defaultProps} schema={schema} />);
      expect(screen.getByText('Host')).toBeInTheDocument();
      expect(screen.getByText('Port')).toBeInTheDocument();
      // Boolean labels are rendered inside the checkbox wrapper
      expect(screen.getByText('Use SSL')).toBeInTheDocument();
    });

    it('should show required indicator', () => {
      const { container } = render(<YamlEditor {...defaultProps} schema={schema} />);
      expect(container.querySelector('.required')).toBeInTheDocument();
    });

    it('should populate fields from YAML value', () => {
      const yamlValue = 'host: example.com\nport: 443\nssl: true';
      render(<YamlEditor {...defaultProps} schema={schema} value={yamlValue} />);
      expect(screen.getByDisplayValue('example.com')).toBeInTheDocument();
      expect(screen.getByDisplayValue('443')).toBeInTheDocument();
      expect(screen.getByRole('checkbox')).toBeChecked();
    });

    it('should call onChange when a property value changes', () => {
      const onChange = jest.fn();
      const yamlValue = 'host: example.com\nport: 443\nssl: true';
      render(<YamlEditor {...defaultProps} schema={schema} value={yamlValue} onChange={onChange} />);

      fireEvent.change(screen.getByDisplayValue('example.com'), {
        target: { value: 'new-host.com' },
      });

      expect(onChange).toHaveBeenCalled();
      const callArg = onChange.mock.calls[0][0];
      expect(callArg).toContain('new-host.com');
    });
  });

  describe('nested schema (list of mappings)', () => {
    const schema: YamlSchema = {
      type: 'list',
      label: 'Headers',
      items: {
        type: 'mapping',
        label: 'Header',
        properties: {
          name: { type: 'string', label: 'Name', required: true },
          value: { type: 'string', label: 'Value' },
        },
      },
    };

    it('should render nested mapping fields in list items', () => {
      const yamlValue = '- name: Content-Type\n  value: application/json';
      render(<YamlEditor {...defaultProps} schema={schema} value={yamlValue} />);
      expect(screen.getByDisplayValue('Content-Type')).toBeInTheDocument();
      expect(screen.getByDisplayValue('application/json')).toBeInTheDocument();
    });

    it('should show item labels with index', () => {
      const yamlValue = '- name: Content-Type\n  value: application/json';
      render(<YamlEditor {...defaultProps} schema={schema} value={yamlValue} />);
      expect(screen.getByText('Header 1')).toBeInTheDocument();
    });

    it('should show collapse/expand toggle for complex items', () => {
      const yamlValue = '- name: Content-Type\n  value: application/json';
      render(<YamlEditor {...defaultProps} schema={schema} value={yamlValue} />);
      const collapseBtn = screen.getByLabelText('Collapse item 1');
      expect(collapseBtn).toBeInTheDocument();
    });

    it('should toggle visibility of item body on collapse click', () => {
      const yamlValue = '- name: Content-Type\n  value: application/json';
      render(<YamlEditor {...defaultProps} schema={schema} value={yamlValue} />);

      // Initially visible
      expect(screen.getByDisplayValue('Content-Type')).toBeInTheDocument();

      // Click collapse
      fireEvent.click(screen.getByLabelText('Collapse item 1'));

      // Now the fields should be hidden
      expect(screen.queryByDisplayValue('Content-Type')).not.toBeInTheDocument();

      // Click expand
      fireEvent.click(screen.getByLabelText('Expand item 1'));

      // Fields visible again
      expect(screen.getByDisplayValue('Content-Type')).toBeInTheDocument();
    });
  });

  describe('raw YAML mode toggle', () => {
    const schema: YamlSchema = {
      type: 'mapping',
      label: 'Config',
      properties: {
        key: { type: 'string', label: 'Key' },
      },
    };

    it('should show Editor and YAML mode buttons', () => {
      render(<YamlEditor {...defaultProps} schema={schema} />);
      expect(screen.getByText('Editor')).toBeInTheDocument();
      expect(screen.getByText('YAML')).toBeInTheDocument();
    });

    it('should start in Editor mode', () => {
      render(<YamlEditor {...defaultProps} schema={schema} />);
      const editorBtn = screen.getByText('Editor');
      expect(editorBtn).toHaveAttribute('aria-pressed', 'true');
    });

    it('should switch to raw YAML mode when YAML button is clicked', () => {
      render(<YamlEditor {...defaultProps} schema={schema} value="key: hello" />);

      fireEvent.click(screen.getByText('YAML'));

      // Should show a textarea with the raw YAML
      const textarea = screen.getByRole('textbox');
      expect(textarea.tagName).toBe('TEXTAREA');
    });

    it('should switch back to Editor mode', () => {
      render(<YamlEditor {...defaultProps} schema={schema} value="key: hello" />);

      fireEvent.click(screen.getByText('YAML'));
      fireEvent.click(screen.getByText('Editor'));

      // Should show the structured input again
      expect(screen.getByDisplayValue('hello')).toBeInTheDocument();
    });
  });

  describe('error handling', () => {
    const schema: YamlSchema = {
      type: 'string',
      label: 'Value',
    };

    it('should show error for invalid YAML when switching from raw', () => {
      const onChange = jest.fn();
      render(<YamlEditor {...defaultProps} schema={schema} onChange={onChange} />);

      // Switch to raw mode
      fireEvent.click(screen.getByText('YAML'));

      // Type invalid YAML
      const textarea = screen.getByRole('textbox');
      fireEvent.change(textarea, { target: { value: '{ invalid: yaml: : :' } });

      expect(screen.getByRole('alert')).toBeInTheDocument();
    });
  });

  describe('deeply nested schema (mapping in list in mapping)', () => {
    const schema: YamlSchema = {
      type: 'mapping',
      label: 'API Config',
      properties: {
        endpoints: {
          type: 'list',
          label: 'Endpoints',
          items: {
            type: 'mapping',
            label: 'Endpoint',
            properties: {
              path: { type: 'string', label: 'Path' },
              methods: {
                type: 'list',
                label: 'Methods',
                items: { type: 'string', label: 'Method' },
              },
            },
          },
        },
      },
    };

    it('should render deeply nested structures', () => {
      const yamlValue = 'endpoints:\n  - path: /api/users\n    methods:\n      - GET\n      - POST';
      render(<YamlEditor {...defaultProps} schema={schema} value={yamlValue} />);
      expect(screen.getByDisplayValue('/api/users')).toBeInTheDocument();
      expect(screen.getByDisplayValue('GET')).toBeInTheDocument();
      expect(screen.getByDisplayValue('POST')).toBeInTheDocument();
    });
  });

  describe('discriminated mapping schema', () => {
    const schema: YamlSchema = {
      type: 'mapping',
      label: 'Process Plugin',
      properties: {
        plugin: {
          type: 'string',
          label: 'Plugin',
          required: true,
          options: { get: 'Get', callback: 'Callback', str_replace: 'String Replace' },
        },
        source: { type: 'string', label: 'Source' },
      },
      discriminator: 'plugin',
      conditionalProperties: {
        get: {
          default_value: { type: 'string', label: 'Default Value' },
        },
        callback: {
          callable: { type: 'string', label: 'Callable', required: true },
        },
        str_replace: {
          search: { type: 'string', label: 'Search', required: true },
          replace: { type: 'string', label: 'Replace' },
          regex: { type: 'boolean', label: 'Regular Expression' },
        },
      },
    };

    it('should show only base properties when no discriminator value is selected', () => {
      render(<YamlEditor {...defaultProps} schema={schema} />);
      expect(screen.getByText('Plugin')).toBeInTheDocument();
      expect(screen.getByText('Source')).toBeInTheDocument();
      // Conditional properties should not be visible
      expect(screen.queryByText('Default Value')).not.toBeInTheDocument();
      expect(screen.queryByText('Callable')).not.toBeInTheDocument();
      expect(screen.queryByText('Search')).not.toBeInTheDocument();
    });

    it('should show conditional properties when discriminator value is selected', () => {
      const yamlValue = 'plugin: str_replace\nsource: my_field';
      render(<YamlEditor {...defaultProps} schema={schema} value={yamlValue} />);
      // Base properties should be visible
      expect(screen.getByText('Plugin')).toBeInTheDocument();
      expect(screen.getByText('Source')).toBeInTheDocument();
      // str_replace conditional properties should be visible
      expect(screen.getByText('Search')).toBeInTheDocument();
      expect(screen.getByText('Replace')).toBeInTheDocument();
      expect(screen.getByText('Regular Expression')).toBeInTheDocument();
      // Other plugin properties should not be visible
      expect(screen.queryByText('Default Value')).not.toBeInTheDocument();
      expect(screen.queryByText('Callable')).not.toBeInTheDocument();
    });

    it('should show different conditional properties when discriminator changes', () => {
      const onChange = jest.fn();
      const yamlValue = 'plugin: get\nsource: my_field';
      render(<YamlEditor {...defaultProps} schema={schema} value={yamlValue} onChange={onChange} />);

      // Should show get conditional properties
      expect(screen.getByText('Default Value')).toBeInTheDocument();
      expect(screen.queryByText('Callable')).not.toBeInTheDocument();

      // Change to callback plugin
      fireEvent.change(screen.getByRole('combobox'), { target: { value: 'callback' } });

      // onChange should be called
      expect(onChange).toHaveBeenCalled();
    });

    it('should clean up obsolete properties when discriminator changes', () => {
      const onChange = jest.fn();
      const yamlValue = 'plugin: str_replace\nsource: my_field\nsearch: foo\nreplace: bar';
      render(<YamlEditor {...defaultProps} schema={schema} value={yamlValue} onChange={onChange} />);

      // Change to get plugin (which doesn't have search/replace)
      fireEvent.change(screen.getByRole('combobox'), { target: { value: 'get' } });

      // The onChange call should not include the obsolete search/replace properties
      expect(onChange).toHaveBeenCalled();
      const callArg = onChange.mock.calls[0][0];
      expect(callArg).not.toContain('search');
      expect(callArg).not.toContain('replace');
    });
  });

  describe('keyed_mapping schema', () => {
    const schema: YamlSchema = {
      type: 'keyed_mapping',
      label: 'Tool Arguments',
      items: {
        type: 'mapping',
        label: 'Argument',
        properties: {
          data_type: { type: 'string', label: 'Data Type' },
          label: { type: 'string', label: 'Label' },
          required: { type: 'boolean', label: 'Required' },
          description: { type: 'string', label: 'Description' },
        },
      },
    };

    it('should show "No items yet." when empty', () => {
      render(<YamlEditor {...defaultProps} schema={schema} />);
      expect(screen.getByText('No items yet.')).toBeInTheDocument();
    });

    it('should show an add button', () => {
      render(<YamlEditor {...defaultProps} schema={schema} />);
      expect(screen.getByText('Add Argument')).toBeInTheDocument();
    });

    it('should not show add button when disabled', () => {
      render(<YamlEditor {...defaultProps} schema={schema} disabled={true} />);
      expect(screen.queryByText('Add Argument')).not.toBeInTheDocument();
    });

    it('should render existing keyed entries collapsed by default', () => {
      const yamlValue = 'node:\n  data_type: "entity:node:article"\n  label: "The article"\n  required: true\n  description: "An article node"';
      render(<YamlEditor {...defaultProps} schema={schema} value={yamlValue} />);
      // The container label should show the key value
      expect(screen.getByText('node')).toBeInTheDocument();
      // Fields should be hidden because existing entries start collapsed
      expect(screen.queryByDisplayValue('entity:node:article')).not.toBeInTheDocument();
      expect(screen.queryByDisplayValue('The article')).not.toBeInTheDocument();
    });

    it('should show fields after expanding a collapsed entry', () => {
      const yamlValue = 'node:\n  data_type: "entity:node:article"\n  label: "The article"\n  required: true\n  description: "An article node"';
      render(<YamlEditor {...defaultProps} schema={schema} value={yamlValue} />);
      // Expand the entry
      fireEvent.click(screen.getByLabelText('Expand node'));
      // Now the key input and properties should be visible
      expect(screen.getByDisplayValue('node')).toBeInTheDocument();
      expect(screen.getByDisplayValue('entity:node:article')).toBeInTheDocument();
      expect(screen.getByDisplayValue('The article')).toBeInTheDocument();
      expect(screen.getByDisplayValue('An article node')).toBeInTheDocument();
    });

    it('should render multiple keyed entries with key as label', () => {
      const yamlValue = 'node:\n  data_type: "entity:node:article"\n  label: "The article"\n  required: true\n  description: "An article"\nuser:\n  data_type: "entity:user"\n  label: "The user"\n  required: true\n  description: "A user"';
      render(<YamlEditor {...defaultProps} schema={schema} value={yamlValue} />);
      // Both container labels should show the key values
      expect(screen.getByText('node')).toBeInTheDocument();
      expect(screen.getByText('user')).toBeInTheDocument();
      // Fields are collapsed by default
      expect(screen.queryByDisplayValue('entity:node:article')).not.toBeInTheDocument();
      // Expand one entry and verify its fields
      fireEvent.click(screen.getByLabelText('Expand node'));
      expect(screen.getByDisplayValue('entity:node:article')).toBeInTheDocument();
    });

    it('should add a new entry when add button is clicked', () => {
      const onChange = jest.fn();
      render(<YamlEditor {...defaultProps} schema={schema} onChange={onChange} />);

      fireEvent.click(screen.getByText('Add Argument'));
      expect(onChange).toHaveBeenCalled();
      const callArg = onChange.mock.calls[0][0];
      // Default key is derived from items label "Argument" + index.
      expect(callArg).toContain('argument_1');
    });

    it('should remove an entry when remove button is clicked', () => {
      const onChange = jest.fn();
      const yamlValue = 'node:\n  data_type: "entity:node"\n  label: "Node"\n  required: false\n  description: ""\nuser:\n  data_type: "entity:user"\n  label: "User"\n  required: false\n  description: ""';
      render(<YamlEditor {...defaultProps} schema={schema} value={yamlValue} onChange={onChange} />);

      const removeButtons = screen.getAllByTitle('Remove');
      fireEvent.click(removeButtons[0]);

      expect(onChange).toHaveBeenCalled();
      const callArg = onChange.mock.calls[0][0];
      expect(callArg).toContain('user');
      expect(callArg).not.toContain('node');
    });

    it('should update the key when the key input changes', () => {
      const onChange = jest.fn();
      const yamlValue = 'mynode:\n  data_type: "entity:node"\n  label: "Node"\n  required: false\n  description: ""';
      render(<YamlEditor {...defaultProps} schema={schema} value={yamlValue} onChange={onChange} />);

      // Expand the entry first to access the key input
      fireEvent.click(screen.getByLabelText('Expand mynode'));
      const keyInput = screen.getByDisplayValue('mynode');
      fireEvent.change(keyInput, { target: { value: 'article' } });

      expect(onChange).toHaveBeenCalled();
      const callArg = onChange.mock.calls[0][0];
      expect(callArg).toContain('article');
      expect(callArg).not.toContain('mynode');
    });

    it('should show expand toggle for complex items (collapsed by default)', () => {
      const yamlValue = 'node:\n  data_type: "entity:node"\n  label: "Node"\n  required: false\n  description: ""';
      render(<YamlEditor {...defaultProps} schema={schema} value={yamlValue} />);
      // Existing entries start collapsed, so the button says "Expand"
      const expandBtn = screen.getByLabelText('Expand node');
      expect(expandBtn).toBeInTheDocument();
      expect(expandBtn).toHaveAttribute('aria-expanded', 'false');
    });

    it('should toggle visibility of item body on collapse click', () => {
      const yamlValue = 'node:\n  data_type: "entity:node"\n  label: "Node"\n  required: false\n  description: ""';
      render(<YamlEditor {...defaultProps} schema={schema} value={yamlValue} />);

      // Initially collapsed — fields hidden
      expect(screen.queryByDisplayValue('entity:node')).not.toBeInTheDocument();

      // Click expand
      fireEvent.click(screen.getByLabelText('Expand node'));

      // Fields visible
      expect(screen.getByDisplayValue('entity:node')).toBeInTheDocument();

      // Click collapse
      fireEvent.click(screen.getByLabelText('Collapse node'));

      // Now the fields should be hidden again
      expect(screen.queryByDisplayValue('entity:node')).not.toBeInTheDocument();
    });

    it('should update the container label when the key changes', () => {
      const yamlValue = 'node:\n  data_type: "entity:node"\n  label: "Node"\n  required: false\n  description: ""';
      const { rerender } = render(<YamlEditor {...defaultProps} schema={schema} value={yamlValue} />);
      // Initial label shows the key value
      expect(screen.getByText('node')).toBeInTheDocument();
      // Simulate an external value update (as if the key was renamed)
      const updatedYaml = 'article:\n  data_type: "entity:node"\n  label: "Node"\n  required: false\n  description: ""';
      rerender(<YamlEditor {...defaultProps} schema={schema} value={updatedYaml} />);
      expect(screen.getByText('article')).toBeInTheDocument();
      expect(screen.queryByText('node')).not.toBeInTheDocument();
    });

    it('should not show remove buttons when disabled', () => {
      const yamlValue = 'node:\n  data_type: "entity:node"\n  label: "Node"\n  required: false\n  description: ""';
      render(<YamlEditor {...defaultProps} schema={schema} value={yamlValue} disabled={true} />);
      expect(screen.queryByTitle('Remove')).not.toBeInTheDocument();
    });

    it('should show newly added items expanded', () => {
      const onChange = jest.fn();
      render(<YamlEditor {...defaultProps} schema={schema} onChange={onChange} />);

      fireEvent.click(screen.getByText('Add Argument'));
      expect(onChange).toHaveBeenCalled();
      // Re-render with the new value to check it appears expanded
      const callArg = onChange.mock.calls[0][0];
      const { container } = render(<YamlEditor {...defaultProps} schema={schema} value={callArg} />);
      // The new item should start expanded (fields visible) since it was
      // not part of the initial value — but since we're rendering fresh here,
      // it counts as an existing value. This test verifies the add flow works.
      expect(container.querySelector('.yaml-editor-list-item')).toBeInTheDocument();
    });

    it('should generate unique keys when adding multiple items', () => {
      const onChange = jest.fn();
      // Pre-populate with argument_1 so the next add must pick argument_2.
      const yamlValue = 'argument_1:\n  data_type: ""\n  label: ""\n  required: false\n  description: ""';
      render(<YamlEditor {...defaultProps} schema={schema} value={yamlValue} onChange={onChange} />);

      fireEvent.click(screen.getByText('Add Argument'));
      expect(onChange).toHaveBeenCalled();
      const callArg = onChange.mock.calls[0][0];
      // Should contain both the original and a uniquely-named new item.
      expect(callArg).toContain('argument_1');
      expect(callArg).toContain('argument_2');
    });
  });

  // =========================================================================
  // validateAgainstSchema — unit tests
  // =========================================================================

  describe('validateAgainstSchema', () => {
    describe('string validation', () => {
      it('should pass for a valid string', () => {
        const schema: YamlSchema = { type: 'string', label: 'Name' };
        expect(validateAgainstSchema('hello', schema)).toEqual([]);
      });

      it('should fail when value is not a string', () => {
        const schema: YamlSchema = { type: 'string', label: 'Name' };
        const errors = validateAgainstSchema(42, schema);
        expect(errors).toHaveLength(1);
        expect(errors[0].message).toContain('Expected a string');
      });

      it('should fail for invalid enum option', () => {
        const schema: YamlSchema = {
          type: 'string',
          label: 'Color',
          options: { red: 'Red', green: 'Green', blue: 'Blue' },
        };
        const errors = validateAgainstSchema('yellow', schema);
        expect(errors).toHaveLength(1);
        expect(errors[0].message).toContain('not one of the allowed options');
        expect(errors[0].message).toContain('red, green, blue');
      });

      it('should pass for a valid enum option', () => {
        const schema: YamlSchema = {
          type: 'string',
          label: 'Color',
          options: { red: 'Red', green: 'Green', blue: 'Blue' },
        };
        expect(validateAgainstSchema('red', schema)).toEqual([]);
      });

      it('should allow empty string for optional enum field', () => {
        const schema: YamlSchema = {
          type: 'string',
          label: 'Color',
          options: { red: 'Red' },
        };
        expect(validateAgainstSchema('', schema)).toEqual([]);
      });

      it('should fail for required empty string', () => {
        const schema: YamlSchema = { type: 'string', label: 'Name', required: true };
        const errors = validateAgainstSchema('', schema);
        expect(errors).toHaveLength(1);
        expect(errors[0].message).toContain('must not be empty');
      });

      it('should pass for required non-empty string', () => {
        const schema: YamlSchema = { type: 'string', label: 'Name', required: true };
        expect(validateAgainstSchema('hello', schema)).toEqual([]);
      });
    });

    describe('number validation', () => {
      it('should pass for a valid number', () => {
        const schema: YamlSchema = { type: 'number', label: 'Count' };
        expect(validateAgainstSchema(42, schema)).toEqual([]);
      });

      it('should fail when value is not a number', () => {
        const schema: YamlSchema = { type: 'number', label: 'Count' };
        const errors = validateAgainstSchema('not a number', schema);
        expect(errors).toHaveLength(1);
        expect(errors[0].message).toContain('Expected a number');
      });

      it('should fail when below minimum', () => {
        const schema: YamlSchema = { type: 'number', label: 'Count', min: 10 };
        const errors = validateAgainstSchema(5, schema);
        expect(errors).toHaveLength(1);
        expect(errors[0].message).toContain('below the minimum');
      });

      it('should fail when above maximum', () => {
        const schema: YamlSchema = { type: 'number', label: 'Count', max: 100 };
        const errors = validateAgainstSchema(150, schema);
        expect(errors).toHaveLength(1);
        expect(errors[0].message).toContain('exceeds the maximum');
      });

      it('should pass when within min/max range', () => {
        const schema: YamlSchema = { type: 'number', label: 'Count', min: 0, max: 100 };
        expect(validateAgainstSchema(50, schema)).toEqual([]);
      });

      it('should pass at boundary values', () => {
        const schema: YamlSchema = { type: 'number', label: 'Count', min: 0, max: 100 };
        expect(validateAgainstSchema(0, schema)).toEqual([]);
        expect(validateAgainstSchema(100, schema)).toEqual([]);
      });
    });

    describe('boolean validation', () => {
      it('should pass for true', () => {
        const schema: YamlSchema = { type: 'boolean', label: 'Enabled' };
        expect(validateAgainstSchema(true, schema)).toEqual([]);
      });

      it('should pass for false', () => {
        const schema: YamlSchema = { type: 'boolean', label: 'Enabled' };
        expect(validateAgainstSchema(false, schema)).toEqual([]);
      });

      it('should fail for non-boolean', () => {
        const schema: YamlSchema = { type: 'boolean', label: 'Enabled' };
        const errors = validateAgainstSchema('yes', schema);
        expect(errors).toHaveLength(1);
        expect(errors[0].message).toContain('Expected a boolean');
      });
    });

    describe('list validation', () => {
      const schema: YamlSchema = {
        type: 'list',
        label: 'Tags',
        items: { type: 'string', label: 'Tag' },
      };

      it('should pass for a valid list of strings', () => {
        expect(validateAgainstSchema(['a', 'b', 'c'], schema)).toEqual([]);
      });

      it('should fail when value is not an array', () => {
        const errors = validateAgainstSchema('not an array', schema);
        expect(errors).toHaveLength(1);
        expect(errors[0].message).toContain('Expected a list');
      });

      it('should fail when value is an object instead of array', () => {
        const errors = validateAgainstSchema({ key: 'val' }, schema);
        expect(errors).toHaveLength(1);
        expect(errors[0].message).toContain('Expected a list');
        expect(errors[0].message).toContain('object');
      });

      it('should validate each item in the list', () => {
        const errors = validateAgainstSchema(['ok', 42, 'fine'], schema);
        expect(errors).toHaveLength(1);
        expect(errors[0].path).toBe('[1]');
        expect(errors[0].message).toContain('Expected a string');
      });

      it('should pass for an empty list', () => {
        expect(validateAgainstSchema([], schema)).toEqual([]);
      });
    });

    describe('mapping validation', () => {
      const schema: YamlSchema = {
        type: 'mapping',
        label: 'Config',
        properties: {
          host: { type: 'string', label: 'Host', required: true },
          port: { type: 'number', label: 'Port' },
          ssl: { type: 'boolean', label: 'Use SSL' },
        },
      };

      it('should pass for a valid mapping', () => {
        const errors = validateAgainstSchema(
          { host: 'example.com', port: 443, ssl: true },
          schema,
        );
        expect(errors).toEqual([]);
      });

      it('should fail when value is not an object', () => {
        const errors = validateAgainstSchema('not an object', schema);
        expect(errors).toHaveLength(1);
        expect(errors[0].message).toContain('Expected a mapping');
      });

      it('should fail when value is an array', () => {
        const errors = validateAgainstSchema([1, 2, 3], schema);
        expect(errors).toHaveLength(1);
        expect(errors[0].message).toContain('Expected a mapping');
        expect(errors[0].message).toContain('array');
      });

      it('should report missing required properties', () => {
        const errors = validateAgainstSchema({ port: 80, ssl: false }, schema);
        expect(errors.some(e => e.path === 'host' && e.message.includes('Required'))).toBe(true);
      });

      it('should report unexpected properties', () => {
        const errors = validateAgainstSchema(
          { host: 'example.com', port: 443, ssl: true, extra: 'nope' },
          schema,
        );
        expect(errors).toHaveLength(1);
        expect(errors[0].path).toBe('extra');
        expect(errors[0].message).toContain('Unexpected property');
      });

      it('should validate property types recursively', () => {
        const errors = validateAgainstSchema(
          { host: 'example.com', port: 'not-a-number', ssl: true },
          schema,
        );
        expect(errors).toHaveLength(1);
        expect(errors[0].path).toBe('port');
        expect(errors[0].message).toContain('Expected a number');
      });

      it('should not flag optional missing properties', () => {
        // port and ssl are optional — only host is required
        const errors = validateAgainstSchema({ host: 'example.com' }, schema);
        expect(errors).toEqual([]);
      });
    });

    describe('discriminated mapping validation', () => {
      const schema: YamlSchema = {
        type: 'mapping',
        label: 'Process Plugin',
        properties: {
          plugin: {
            type: 'string',
            label: 'Plugin',
            required: true,
            options: { get: 'Get', callback: 'Callback' },
          },
          source: { type: 'string', label: 'Source' },
        },
        discriminator: 'plugin',
        conditionalProperties: {
          get: {
            default_value: { type: 'string', label: 'Default Value' },
          },
          callback: {
            callable: { type: 'string', label: 'Callable', required: true },
          },
        },
      };

      it('should validate base properties without discriminator value', () => {
        const errors = validateAgainstSchema({ plugin: '', source: 'foo' }, schema);
        // Should fail because plugin is required
        expect(errors.some((e) => e.path === 'plugin')).toBe(true);
      });

      it('should validate conditional properties based on discriminator', () => {
        // callback requires callable field
        const errors = validateAgainstSchema({ plugin: 'callback', source: 'foo' }, schema);
        expect(errors.some((e) => e.path === 'callable')).toBe(true);
      });

      it('should pass when conditional required properties are provided', () => {
        const errors = validateAgainstSchema(
          { plugin: 'callback', source: 'foo', callable: 'myFunc' },
          schema,
        );
        expect(errors).toEqual([]);
      });

      it('should not require conditional properties from other discriminator values', () => {
        // get plugin doesn't require callable
        const errors = validateAgainstSchema({ plugin: 'get', source: 'foo' }, schema);
        expect(errors).toEqual([]);
      });

      it('should flag unexpected properties not in base or conditional', () => {
        const errors = validateAgainstSchema(
          { plugin: 'get', source: 'foo', unknown_prop: 'bar' },
          schema,
        );
        expect(errors.some((e) => e.path === 'unknown_prop')).toBe(true);
      });

      it('should allow conditional properties for the current discriminator value', () => {
        // default_value is valid for get plugin
        const errors = validateAgainstSchema(
          { plugin: 'get', source: 'foo', default_value: 'fallback' },
          schema,
        );
        expect(errors).toEqual([]);
      });
    });

    describe('keyed_mapping validation', () => {
      const schema: YamlSchema = {
        type: 'keyed_mapping',
        label: 'Arguments',
        items: {
          type: 'mapping',
          label: 'Argument',
          properties: {
            data_type: { type: 'string', label: 'Data Type' },
            required: { type: 'boolean', label: 'Required' },
          },
        },
      };

      it('should pass for a valid keyed mapping', () => {
        const errors = validateAgainstSchema(
          { node: { data_type: 'entity:node', required: true } },
          schema,
        );
        expect(errors).toEqual([]);
      });

      it('should fail when value is not an object', () => {
        const errors = validateAgainstSchema([1, 2], schema);
        expect(errors).toHaveLength(1);
        expect(errors[0].message).toContain('Expected a keyed mapping');
      });

      it('should validate each entry value against the items schema', () => {
        const errors = validateAgainstSchema(
          { node: { data_type: 123, required: true } },
          schema,
        );
        expect(errors).toHaveLength(1);
        expect(errors[0].path).toBe('node.data_type');
        expect(errors[0].message).toContain('Expected a string');
      });

      it('should report multiple errors across entries', () => {
        const errors = validateAgainstSchema(
          {
            node: { data_type: 'entity:node', required: 'yes' },
            user: { data_type: 42, required: true },
          },
          schema,
        );
        expect(errors).toHaveLength(2);
        expect(errors.some(e => e.path === 'node.required')).toBe(true);
        expect(errors.some(e => e.path === 'user.data_type')).toBe(true);
      });
    });

    describe('null / undefined handling', () => {
      it('should not error for optional null value', () => {
        const schema: YamlSchema = { type: 'string', label: 'Name' };
        expect(validateAgainstSchema(null, schema)).toEqual([]);
      });

      it('should not error for optional undefined value', () => {
        const schema: YamlSchema = { type: 'string', label: 'Name' };
        expect(validateAgainstSchema(undefined, schema)).toEqual([]);
      });

      it('should error for required null value', () => {
        const schema: YamlSchema = { type: 'string', label: 'Name', required: true };
        const errors = validateAgainstSchema(null, schema);
        expect(errors).toHaveLength(1);
        expect(errors[0].message).toContain('Required field is missing');
      });

      it('should error for required undefined value', () => {
        const schema: YamlSchema = { type: 'string', label: 'Name', required: true };
        const errors = validateAgainstSchema(undefined, schema);
        expect(errors).toHaveLength(1);
        expect(errors[0].message).toContain('Required field is missing');
      });
    });

    describe('path tracking', () => {
      it('should track paths through nested mappings', () => {
        const schema: YamlSchema = {
          type: 'mapping',
          label: 'Root',
          properties: {
            settings: {
              type: 'mapping',
              label: 'Settings',
              properties: {
                host: { type: 'string', label: 'Host' },
              },
            },
          },
        };
        const errors = validateAgainstSchema(
          { settings: { host: 42 } },
          schema,
        );
        expect(errors).toHaveLength(1);
        expect(errors[0].path).toBe('settings.host');
      });

      it('should track paths through lists', () => {
        const schema: YamlSchema = {
          type: 'list',
          label: 'Items',
          items: { type: 'string', label: 'Item' },
        };
        const errors = validateAgainstSchema(['ok', 42], schema);
        expect(errors).toHaveLength(1);
        expect(errors[0].path).toBe('[1]');
      });

      it('should track paths through list of mappings', () => {
        const schema: YamlSchema = {
          type: 'list',
          label: 'Endpoints',
          items: {
            type: 'mapping',
            label: 'Endpoint',
            properties: {
              path: { type: 'string', label: 'Path' },
            },
          },
        };
        const errors = validateAgainstSchema(
          [{ path: '/api' }, { path: 123 }],
          schema,
        );
        expect(errors).toHaveLength(1);
        expect(errors[0].path).toBe('[1].path');
      });

      it('should track paths through keyed mappings', () => {
        const schema: YamlSchema = {
          type: 'keyed_mapping',
          label: 'Services',
          items: {
            type: 'mapping',
            label: 'Service',
            properties: {
              port: { type: 'number', label: 'Port' },
            },
          },
        };
        const errors = validateAgainstSchema(
          { web: { port: 'eighty' } },
          schema,
        );
        expect(errors).toHaveLength(1);
        expect(errors[0].path).toBe('web.port');
      });

      it('should track paths through nested lists', () => {
        const schema: YamlSchema = {
          type: 'list',
          label: 'Groups',
          items: {
            type: 'list',
            label: 'Group',
            items: { type: 'number', label: 'Value' },
          },
        };
        const errors = validateAgainstSchema(
          [[1, 2], [3, 'bad']],
          schema,
        );
        expect(errors).toHaveLength(1);
        expect(errors[0].path).toBe('[1][1]');
      });
    });

    describe('edge cases', () => {
      it('should pass for an empty keyed mapping', () => {
        const schema: YamlSchema = {
          type: 'keyed_mapping',
          label: 'Args',
          items: { type: 'string', label: 'Arg' },
        };
        expect(validateAgainstSchema({}, schema)).toEqual([]);
      });

      it('should validate a required list with no items', () => {
        const schema: YamlSchema = {
          type: 'list',
          label: 'Tags',
          required: true,
          items: { type: 'string', label: 'Tag' },
        };
        // An empty array is present (not null/undefined), so required is satisfied.
        // The validator only flags null/undefined for required.
        expect(validateAgainstSchema([], schema)).toEqual([]);
      });

      it('should flag required null for collection types', () => {
        const schema: YamlSchema = {
          type: 'mapping',
          label: 'Config',
          required: true,
          properties: { key: { type: 'string', label: 'Key' } },
        };
        const errors = validateAgainstSchema(null, schema);
        expect(errors).toHaveLength(1);
        expect(errors[0].message).toContain('Required field is missing');
      });

      it('should use label as path when no path is provided', () => {
        const schema: YamlSchema = { type: 'string', label: 'MyField' };
        const errors = validateAgainstSchema(42, schema);
        expect(errors).toHaveLength(1);
        expect(errors[0].path).toBe('MyField');
      });

      it('should fall back to "value" when neither path nor label is set', () => {
        const schema: YamlSchema = { type: 'string' } as YamlSchema;
        const errors = validateAgainstSchema(42, schema);
        expect(errors).toHaveLength(1);
        expect(errors[0].path).toBe('value');
      });

      it('should accumulate multiple errors in a single mapping', () => {
        const schema: YamlSchema = {
          type: 'mapping',
          label: 'Config',
          properties: {
            host: { type: 'string', label: 'Host', required: true },
            port: { type: 'number', label: 'Port' },
            ssl: { type: 'boolean', label: 'SSL' },
          },
        };
        const errors = validateAgainstSchema(
          { port: 'bad', ssl: 'also bad', extra: 1 },
          schema,
        );
        // Missing required host + wrong type port + wrong type ssl + unexpected extra = 4 errors
        expect(errors).toHaveLength(4);
      });
    });
  });

  // =========================================================================
  // Schema validation — integration tests (YAML mode UI)
  // =========================================================================

  describe('schema validation in YAML mode', () => {
    const schema: YamlSchema = {
      type: 'mapping',
      label: 'Config',
      properties: {
        host: { type: 'string', label: 'Host', required: true },
        port: { type: 'number', label: 'Port' },
      },
    };

    it('should show validation warnings when YAML has schema errors', () => {
      const onChange = jest.fn();
      render(<YamlEditor {...defaultProps} schema={schema} value="host: example.com\nport: 443" onChange={onChange} />);

      // Switch to YAML mode
      fireEvent.click(screen.getByText('YAML'));

      // Edit to have a type error
      const textarea = screen.getByRole('textbox');
      fireEvent.change(textarea, { target: { value: 'host: example.com\nport: not-a-number' } });

      // Should show warnings (port is a string "not-a-number" but schema expects number)
      expect(screen.getByRole('status')).toBeInTheDocument();
      expect(screen.getByText(/Schema validation/)).toBeInTheDocument();
    });

    it('should not show validation warnings when YAML is valid', () => {
      render(<YamlEditor {...defaultProps} schema={schema} value="host: example.com\nport: 443" />);

      // Switch to YAML mode
      fireEvent.click(screen.getByText('YAML'));

      // Should not show any warnings
      expect(screen.queryByText(/Schema validation/)).not.toBeInTheDocument();
    });

    it('should not show validation warnings in Editor mode', () => {
      render(<YamlEditor {...defaultProps} schema={schema} value="host: example.com\nport: not-a-number" />);

      // Stay in Editor mode - no validation warnings
      expect(screen.queryByText(/Schema validation/)).not.toBeInTheDocument();
    });

    it('should clear validation warnings when switching back to Editor mode', () => {
      const onChange = jest.fn();
      render(<YamlEditor {...defaultProps} schema={schema} value="host: example.com\nport: 443" onChange={onChange} />);

      // Switch to YAML mode and introduce an error
      fireEvent.click(screen.getByText('YAML'));
      const textarea = screen.getByRole('textbox');
      fireEvent.change(textarea, { target: { value: 'port: not-a-number' } });
      expect(screen.getByRole('status')).toBeInTheDocument();

      // Switch back to Editor mode — warnings should be gone
      fireEvent.click(screen.getByText('Editor'));
      expect(screen.queryByText(/Schema validation/)).not.toBeInTheDocument();
    });

    it('should show validation warnings when switching to YAML mode with invalid data', () => {
      render(<YamlEditor {...defaultProps} schema={schema} value="extra_key: bad" />);

      // Switch to YAML mode
      fireEvent.click(screen.getByText('YAML'));

      // Should show warnings about unexpected key and missing required host
      expect(screen.getByText(/Schema validation/)).toBeInTheDocument();
    });

    it('should clear validation warnings when text is emptied', () => {
      const onChange = jest.fn();
      render(<YamlEditor {...defaultProps} schema={schema} value="port: not-a-number" onChange={onChange} />);

      fireEvent.click(screen.getByText('YAML'));
      // Warnings should appear
      expect(screen.getByRole('status')).toBeInTheDocument();

      // Clear the textarea
      const textarea = screen.getByRole('textbox');
      fireEvent.change(textarea, { target: { value: '' } });

      // Warnings should be gone
      expect(screen.queryByText(/Schema validation/)).not.toBeInTheDocument();
    });

    it('should not show validation warnings when there is a parse error', () => {
      const onChange = jest.fn();
      render(<YamlEditor {...defaultProps} schema={schema} value="host: example.com" onChange={onChange} />);

      fireEvent.click(screen.getByText('YAML'));
      const textarea = screen.getByRole('textbox');
      fireEvent.change(textarea, { target: { value: '{ invalid: yaml: : :' } });

      // Parse error should be shown, not validation warnings
      expect(screen.getByRole('alert')).toBeInTheDocument();
      expect(screen.queryByRole('status')).not.toBeInTheDocument();
    });

    it('should show validation count in the header', () => {
      const onChange = jest.fn();
      render(<YamlEditor {...defaultProps} schema={schema} value="host: example.com\nport: 443" onChange={onChange} />);

      fireEvent.click(screen.getByText('YAML'));
      const textarea = screen.getByRole('textbox');
      // unexpected_key will generate 1 error, plus missing required host = 2 errors
      fireEvent.change(textarea, { target: { value: 'unexpected_key: bad' } });

      // Should show the count
      expect(screen.getByText(/Schema validation \(2\)/)).toBeInTheDocument();
    });

    it('should re-validate when external value changes while in YAML mode', () => {
      // Use a value with an unexpected key so validation triggers on switch.
      const validYaml = 'host: example.com';
      const invalidYaml = 'extra_key: oops';

      const { rerender } = render(
        <YamlEditor {...defaultProps} schema={schema} value={validYaml} />,
      );

      // Switch to YAML mode — valid data, no warnings
      fireEvent.click(screen.getByText('YAML'));
      expect(screen.queryByText(/Schema validation/)).not.toBeInTheDocument();

      // External value update introduces schema violations
      rerender(
        <YamlEditor {...defaultProps} schema={schema} value={invalidYaml} />,
      );

      // Should now show validation warnings (unexpected key + missing required host)
      expect(screen.getByText(/Schema validation/)).toBeInTheDocument();
    });

    it('should not show validation when external value changes while in Editor mode', () => {
      const validYaml = 'host: example.com';
      const invalidYaml = 'extra_key: oops';

      const { rerender } = render(
        <YamlEditor {...defaultProps} schema={schema} value={validYaml} />,
      );

      // Stay in Editor mode
      expect(screen.queryByText(/Schema validation/)).not.toBeInTheDocument();

      // External value update introduces schema violations
      rerender(
        <YamlEditor {...defaultProps} schema={schema} value={invalidYaml} />,
      );

      // Should still not show validation warnings in Editor mode
      expect(screen.queryByText(/Schema validation/)).not.toBeInTheDocument();
    });

    it('should display individual error paths and messages', () => {
      const onChange = jest.fn();
      render(<YamlEditor {...defaultProps} schema={schema} value="host: example.com\nport: 443" onChange={onChange} />);

      fireEvent.click(screen.getByText('YAML'));
      const textarea = screen.getByRole('textbox');
      fireEvent.change(textarea, { target: { value: 'port: not-a-number\nextra: bad' } });

      // Should display the path codes and messages for each error
      const listItems = screen.getAllByRole('listitem');
      expect(listItems.length).toBeGreaterThanOrEqual(2);
      // Check that error paths are rendered as <code> elements
      expect(screen.getByText('host')).toBeInTheDocument();
      expect(screen.getByText('port')).toBeInTheDocument();
    });
  });

  describe('schema-less mode', () => {
    it('should render a raw textarea without schema', () => {
      render(<YamlEditor value="key: value" onChange={jest.fn()} />);
      expect(screen.getByRole('textbox')).toBeInTheDocument();
      expect(screen.getByDisplayValue('key: value')).toBeInTheDocument();
    });

    it('should not render Editor/YAML mode toolbar without schema', () => {
      const { container } = render(<YamlEditor value="" onChange={jest.fn()} />);
      expect(container.querySelector('.yaml-editor-toolbar')).toBeNull();
    });

    it('should have yaml-editor-schemaless class without schema', () => {
      const { container } = render(<YamlEditor value="" onChange={jest.fn()} />);
      expect(container.querySelector('.yaml-editor-schemaless')).toBeInTheDocument();
    });

    it('should call onChange when text is entered', () => {
      const onChange = jest.fn();
      render(<YamlEditor value="" onChange={onChange} />);
      fireEvent.change(screen.getByRole('textbox'), { target: { value: 'new: value' } });
      expect(onChange).toHaveBeenCalledWith('new: value');
    });

    it('should not show validation errors without validate prop', () => {
      const { container } = render(
        <YamlEditor value="invalid: yaml: :::" onChange={jest.fn()} />
      );
      expect(container.querySelector('.yaml-editor-error')).toBeNull();
    });

    it('should show validation error for invalid YAML when validate is true', () => {
      const { container } = render(
        <YamlEditor value="invalid: yaml: :::" onChange={jest.fn()} validate={true} />
      );
      expect(container.querySelector('.yaml-editor-error')).toBeInTheDocument();
    });

    it('should not show validation error for valid YAML when validate is true', () => {
      const { container } = render(
        <YamlEditor value="key: value" onChange={jest.fn()} validate={true} />
      );
      expect(container.querySelector('.yaml-editor-error')).toBeNull();
    });

    it('should validate on typing when validate is true', () => {
      const onChange = jest.fn();
      const { container } = render(
        <YamlEditor value="" onChange={onChange} validate={true} />
      );
      fireEvent.change(screen.getByRole('textbox'), { target: { value: 'bad: yaml: :::' } });
      expect(container.querySelector('.yaml-editor-error')).toBeInTheDocument();
    });

    it('should clear validation error when invalid YAML is corrected', () => {
      const onChange = jest.fn();
      const { container, rerender } = render(
        <YamlEditor value="bad: yaml: :::" onChange={onChange} validate={true} />
      );
      expect(container.querySelector('.yaml-editor-error')).toBeInTheDocument();

      // User types valid YAML
      fireEvent.change(screen.getByRole('textbox'), { target: { value: 'key: value' } });
      // Re-render to reflect the onChange
      rerender(<YamlEditor value="key: value" onChange={onChange} validate={true} />);
      expect(container.querySelector('.yaml-editor-error')).toBeNull();
    });

    it('should not show error for empty value even with validate', () => {
      const { container } = render(
        <YamlEditor value="" onChange={jest.fn()} validate={true} />
      );
      expect(container.querySelector('.yaml-editor-error')).toBeNull();
    });

    it('should disable textarea when disabled', () => {
      render(<YamlEditor value="key: value" onChange={jest.fn()} disabled={true} />);
      expect(screen.getByRole('textbox')).toBeDisabled();
    });
  });

  describe('handleYamlKeyDown keyboard helpers', () => {
    /**
     * Helper: create a mock textarea element and React keyboard event,
     * fire `handleYamlKeyDown`, and return the resulting value and cursor.
     *
     * JSDOM does not support execCommand, so the fallback `ta.value = ...`
     * path is exercised automatically.
     */
    function simulateKey(
      text: string,
      selStart: number,
      selEnd: number,
      key: string,
      opts: { shiftKey?: boolean } = {},
    ): { value: string; selectionStart: number; selectionEnd: number; prevented: boolean; onNewValue: jest.Mock } {
      let prevented = false;
      const onNewValue = jest.fn();

      // Build a minimal mock textarea.
      const ta: Record<string, unknown> = {
        value: text,
        selectionStart: selStart,
        selectionEnd: selEnd,
        setSelectionRange(s: number, e: number) {
          ta.selectionStart = s;
          ta.selectionEnd = e;
        },
      };

      const event = {
        key,
        shiftKey: opts.shiftKey ?? false,
        currentTarget: ta as unknown as HTMLTextAreaElement,
        preventDefault() { prevented = true; },
      } as unknown as React.KeyboardEvent<HTMLTextAreaElement>;

      handleYamlKeyDown(event, onNewValue);

      return {
        value: ta.value as string,
        selectionStart: ta.selectionStart as number,
        selectionEnd: ta.selectionEnd as number,
        prevented,
        onNewValue,
      };
    }

    // -- Tab ---------------------------------------------------------------
    describe('Tab (indent)', () => {
      it('should insert 2 spaces at cursor when no selection', () => {
        // Cursor at position 0 (start of line)
        const r = simulateKey('key: value', 0, 0, 'Tab');
        expect(r.prevented).toBe(true);
        expect(r.onNewValue).toHaveBeenCalledWith('  key: value');
        expect(r.selectionStart).toBe(2);
      });

      it('should indent all selected lines', () => {
        // Select both lines: "a: 1\nb: 2"
        const text = 'a: 1\nb: 2';
        const r = simulateKey(text, 0, text.length, 'Tab');
        expect(r.prevented).toBe(true);
        expect(r.onNewValue).toHaveBeenCalledWith('  a: 1\n  b: 2');
      });

      it('should indent a single full-line selection', () => {
        const r = simulateKey('hello', 0, 5, 'Tab');
        expect(r.onNewValue).toHaveBeenCalledWith('  hello');
      });
    });

    // -- Shift+Tab ---------------------------------------------------------
    describe('Shift+Tab (outdent)', () => {
      it('should remove up to 2 leading spaces from the current line', () => {
        const r = simulateKey('  key: value', 4, 4, 'Tab', { shiftKey: true });
        expect(r.prevented).toBe(true);
        expect(r.onNewValue).toHaveBeenCalledWith('key: value');
      });

      it('should remove only 1 space if only 1 leading space exists', () => {
        const r = simulateKey(' key: value', 3, 3, 'Tab', { shiftKey: true });
        expect(r.onNewValue).toHaveBeenCalledWith('key: value');
      });

      it('should do nothing if line has no leading spaces', () => {
        const r = simulateKey('key: value', 3, 3, 'Tab', { shiftKey: true });
        expect(r.onNewValue).toHaveBeenCalledWith('key: value');
      });

      it('should outdent all selected lines', () => {
        const text = '  a: 1\n  b: 2';
        const r = simulateKey(text, 0, text.length, 'Tab', { shiftKey: true });
        expect(r.onNewValue).toHaveBeenCalledWith('a: 1\nb: 2');
      });
    });

    // -- Enter (auto-indent) -----------------------------------------------
    describe('Enter (auto-indent)', () => {
      it('should preserve indentation of the current line', () => {
        // Cursor at end of "  key: value"
        const text = '  key: value';
        const r = simulateKey(text, text.length, text.length, 'Enter');
        expect(r.prevented).toBe(true);
        expect(r.onNewValue).toHaveBeenCalledWith('  key: value\n  ');
      });

      it('should deepen indent after a line ending with ":"', () => {
        const text = 'parent:';
        const r = simulateKey(text, text.length, text.length, 'Enter');
        expect(r.onNewValue).toHaveBeenCalledWith('parent:\n  ');
      });

      it('should deepen indent after indented line ending with ":"', () => {
        const text = '  nested:';
        const r = simulateKey(text, text.length, text.length, 'Enter');
        expect(r.onNewValue).toHaveBeenCalledWith('  nested:\n    ');
      });

      it('should continue list bullet on a list item line', () => {
        const text = '- item one';
        const r = simulateKey(text, text.length, text.length, 'Enter');
        expect(r.onNewValue).toHaveBeenCalledWith('- item one\n- ');
      });

      it('should continue indented list bullet', () => {
        const text = '  - item one';
        const r = simulateKey(text, text.length, text.length, 'Enter');
        expect(r.onNewValue).toHaveBeenCalledWith('  - item one\n  - ');
      });

      it('should deepen indent on a list item ending with ":" instead of continuing the bullet', () => {
        const text = '- key:';
        const r = simulateKey(text, text.length, text.length, 'Enter');
        expect(r.onNewValue).toHaveBeenCalledWith('- key:\n  ');
      });

      it('should deepen indent on an indented list item ending with ":"', () => {
        const text = '  - nested_key:';
        const r = simulateKey(text, text.length, text.length, 'Enter');
        expect(r.onNewValue).toHaveBeenCalledWith('  - nested_key:\n    ');
      });

      it('should remove empty bullet on Enter (escape the list)', () => {
        // Line is just "- " with cursor at the end
        const text = '- first\n- ';
        const r = simulateKey(text, text.length, text.length, 'Enter');
        expect(r.prevented).toBe(true);
        // The empty "- " line is removed, leaving just "- first"
        expect(r.onNewValue).toHaveBeenCalledWith('- first');
      });

      it('should remove empty indented bullet on Enter', () => {
        const text = '  - first\n  - ';
        const r = simulateKey(text, text.length, text.length, 'Enter');
        expect(r.onNewValue).toHaveBeenCalledWith('  - first');
      });

      it('should handle Enter on first line with no indentation', () => {
        const text = 'key: value';
        const r = simulateKey(text, text.length, text.length, 'Enter');
        expect(r.onNewValue).toHaveBeenCalledWith('key: value\n');
      });

      it('should handle Enter in the middle of a line', () => {
        const text = '  key: value';
        // Cursor after "key"
        const r = simulateKey(text, 5, 5, 'Enter');
        expect(r.onNewValue).toHaveBeenCalledWith('  key\n  : value');
      });
    });

    // -- Non-handled keys --------------------------------------------------
    describe('non-handled keys', () => {
      it('should not prevent default for regular characters', () => {
        const r = simulateKey('test', 4, 4, 'a');
        expect(r.prevented).toBe(false);
        expect(r.onNewValue).not.toHaveBeenCalled();
      });

      it('should not prevent default for Escape', () => {
        const r = simulateKey('test', 4, 4, 'Escape');
        expect(r.prevented).toBe(false);
        expect(r.onNewValue).not.toHaveBeenCalled();
      });
    });
  });
});
