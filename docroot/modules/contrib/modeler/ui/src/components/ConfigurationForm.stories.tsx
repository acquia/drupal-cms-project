import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import ConfigurationForm from './ConfigurationForm';

const textFieldsForm = [
  { key: 'recipient', type: 'textfield', title: 'Recipient', description: 'Email address of the recipient', required: true, default_value: '' },
  { key: 'subject', type: 'textfield', title: 'Subject', description: 'Email subject line', required: true, default_value: '' },
  { key: 'body', type: 'textarea', title: 'Body', description: 'Email body content. Supports tokens.', required: false, default_value: '' },
];

const mixedFieldsForm = [
  { key: 'name', type: 'textfield', title: 'Name', required: true, default_value: '' },
  { key: 'email', type: 'email', title: 'Email', description: 'Contact email address', required: true, default_value: '' },
  { key: 'url', type: 'url', title: 'Website', description: 'Optional website URL', required: false, default_value: '' },
  { key: 'priority', type: 'number', title: 'Priority', description: 'Execution priority (1-100)', min: 1, max: 100, step: 1, default_value: 50 },
  { key: 'enabled', type: 'checkbox', title: 'Enabled', description: 'Enable this action', default_value: true },
  { key: 'format', type: 'select', title: 'Format', description: 'Output format', options: { plain: 'Plain Text', html: 'HTML', json: 'JSON' }, default_value: 'plain' },
  { key: 'role', type: 'radios', title: 'Target Role', options: { admin: 'Administrator', editor: 'Editor', viewer: 'Viewer' }, default_value: 'editor' },
  { key: 'tags', type: 'checkboxes', title: 'Tags', options: { important: 'Important', urgent: 'Urgent', review: 'Needs Review' }, default_value: [] },
];

const markupForm = [
  { key: 'info', type: 'markup', markup: '<p><strong>Note:</strong> This action requires the Mail module to be enabled.</p>' },
  { key: 'recipient', type: 'textfield', title: 'Recipient', required: true, default_value: '' },
];

const meta: Meta<typeof ConfigurationForm> = {
  title: 'Components/ConfigurationForm',
  component: ConfigurationForm,
  parameters: {
    layout: 'centered',
  },
  decorators: [
    (Story: React.ComponentType) => (
      <div style={{ width: 400, padding: 20, border: '1px solid #e0e0e0', borderRadius: 8 }}>
        <Story />
      </div>
    ),
  ],
  args: {
    form: textFieldsForm,
    configuration: {},
    onChange: fn(),
    disabled: false,
  },
  argTypes: {
    disabled: {
      control: 'boolean',
      description: 'Whether the form fields are disabled',
    },
  },
};

export default meta;
type Story = StoryObj<typeof ConfigurationForm>;

/**
 * Simple text field form (email configuration)
 */
export const TextFields: Story = {};

/**
 * Mixed field types showing all supported inputs
 */
export const MixedFields: Story = {
  args: {
    form: mixedFieldsForm,
    configuration: {
      name: 'My Action',
      email: 'admin@example.com',
      priority: 75,
      enabled: true,
      format: 'html',
      role: 'editor',
    },
  },
};

/**
 * Form with markup/info field
 */
export const WithMarkup: Story = {
  args: {
    form: markupForm,
  },
};

/**
 * Disabled form (read-only mode)
 */
export const Disabled: Story = {
  args: {
    disabled: true,
    configuration: {
      recipient: 'admin@example.com',
      subject: 'Notification',
      body: 'Content has been updated.',
    },
  },
};

/**
 * Empty form (no fields)
 */
export const EmptyForm: Story = {
  args: {
    form: [],
  },
};

/**
 * Null form (loading state)
 */
export const NullForm: Story = {
  args: {
    form: null,
  },
};

/**
 * Pre-filled configuration values
 */
export const PreFilled: Story = {
  args: {
    form: textFieldsForm,
    configuration: {
      recipient: 'admin@example.com',
      subject: 'New content published: [node:title]',
      body: 'A new article has been published by [current-user:display-name].',
    },
  },
};

const yamlSchemaForm = [
  { key: 'label', type: 'textfield', title: 'Label', description: 'A human-readable label for this event', required: true, default_value: '' },
  {
    key: 'arguments',
    type: 'textarea',
    title: 'Arguments',
    description: 'Structured arguments for the event handler. Edited via the YAML editor.',
    required: false,
    default_value: '',
    yaml_schema: {
      type: 'mapping' as const,
      label: 'Event Arguments',
      properties: {
        method: {
          type: 'string' as const,
          label: 'HTTP Method',
          options: { GET: 'GET', POST: 'POST', PUT: 'PUT', DELETE: 'DELETE' },
          required: true,
        },
        path: { type: 'string' as const, label: 'Path', required: true },
        timeout: { type: 'number' as const, label: 'Timeout (seconds)', min: 1, max: 300, step: 1 },
        verify_ssl: { type: 'boolean' as const, label: 'Verify SSL' },
        headers: {
          type: 'list' as const,
          label: 'Headers',
          items: {
            type: 'mapping' as const,
            properties: {
              name: { type: 'string' as const, label: 'Header Name', required: true },
              value: { type: 'string' as const, label: 'Header Value', required: true },
            },
          },
        },
      },
    },
  },
  { key: 'replace_tokens', type: 'checkbox', title: 'Replace tokens', description: 'Enable token replacement in field values', default_value: false },
];

/**
 * Textarea with inline YAML schema — renders the structured YAML editor widget
 */
export const WithYamlSchema: Story = {
  args: {
    form: yamlSchemaForm,
    configuration: {
      label: 'HTTP Request Handler',
      arguments: 'method: POST\npath: /api/webhook\ntimeout: 30\nverify_ssl: true\nheaders:\n  - name: Content-Type\n    value: application/json\n',
    },
  },
};
