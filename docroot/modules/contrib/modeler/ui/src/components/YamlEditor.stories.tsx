import React, { useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react';
import YamlEditor from './YamlEditor';
import type { YamlSchema } from './YamlEditor';

// ---------------------------------------------------------------------------
// Schemas used across stories
// ---------------------------------------------------------------------------

const httpHeadersSchema: YamlSchema = {
  type: 'list',
  label: 'HTTP Headers',
  items: {
    type: 'mapping',
    label: 'Header',
    properties: {
      name: { type: 'string', label: 'Header Name', required: true, placeholder: 'e.g. Content-Type' },
      value: { type: 'string', label: 'Header Value', required: true, placeholder: 'e.g. application/json' },
      enabled: { type: 'boolean', label: 'Enabled', default: true },
    },
  },
};

const connectionSchema: YamlSchema = {
  type: 'mapping',
  label: 'Connection Settings',
  properties: {
    host: { type: 'string', label: 'Host', required: true, placeholder: 'example.com' },
    port: { type: 'number', label: 'Port', min: 1, max: 65535 },
    protocol: {
      type: 'string',
      label: 'Protocol',
      options: { http: 'HTTP', https: 'HTTPS', ftp: 'FTP', ssh: 'SSH' },
    },
    ssl: { type: 'boolean', label: 'Use SSL' },
    tags: {
      type: 'list',
      label: 'Tags',
      items: { type: 'string', label: 'Tag', placeholder: 'Enter a tag' },
    },
  },
};

const deeplyNestedSchema: YamlSchema = {
  type: 'mapping',
  label: 'API Configuration',
  properties: {
    base_url: { type: 'string', label: 'Base URL', required: true },
    endpoints: {
      type: 'list',
      label: 'Endpoints',
      items: {
        type: 'mapping',
        label: 'Endpoint',
        properties: {
          path: { type: 'string', label: 'Path', required: true },
          method: {
            type: 'string',
            label: 'Method',
            options: { GET: 'GET', POST: 'POST', PUT: 'PUT', DELETE: 'DELETE' },
          },
          headers: {
            type: 'list',
            label: 'Headers',
            items: {
              type: 'mapping',
              label: 'Header',
              properties: {
                key: { type: 'string', label: 'Key' },
                value: { type: 'string', label: 'Value' },
              },
            },
          },
        },
      },
    },
  },
};

const simpleStringSchema: YamlSchema = {
  type: 'string',
  label: 'Name',
  placeholder: 'Enter a name',
};

const simpleListSchema: YamlSchema = {
  type: 'list',
  label: 'Items',
  items: {
    type: 'string',
    label: 'Item',
    placeholder: 'Enter item text',
  },
};

// ---------------------------------------------------------------------------
// Interactive wrapper so stories can show the current YAML output
// ---------------------------------------------------------------------------

const YamlEditorWithState: React.FC<{
  schema: YamlSchema;
  initialValue?: string;
  disabled?: boolean;
}> = ({ schema, initialValue = '', disabled = false }) => {
  const [value, setValue] = useState(initialValue);
  return (
    <div>
      <YamlEditor
        schema={schema}
        value={value}
        onChange={setValue}
        disabled={disabled}
      />
      <details className="yaml-editor-debug">
        <summary>Current YAML output</summary>
        <pre>{value || '(empty)'}</pre>
      </details>
    </div>
  );
};

// ---------------------------------------------------------------------------
// Meta
// ---------------------------------------------------------------------------

const meta: Meta<typeof YamlEditor> = {
  title: 'Components/YamlEditor',
  component: YamlEditor,
  parameters: {
    layout: 'centered',
  },
  decorators: [
    (Story: React.ComponentType) => (
      <div style={{ width: 420, padding: 20, border: '1px solid var(--modeler-color-border-default)', borderRadius: 8 }}>
        <Story />
      </div>
    ),
  ],
};

export default meta;
type Story = StoryObj<typeof YamlEditor>;

// ---------------------------------------------------------------------------
// Stories
// ---------------------------------------------------------------------------

/**
 * List of HTTP headers — the primary use case.
 * Each item is a mapping with name, value, and enabled toggle.
 */
export const HttpHeaders: Story = {
  render: () => (
    <YamlEditorWithState
      schema={httpHeadersSchema}
      initialValue={
        '- name: Content-Type\n  value: application/json\n  enabled: true\n- name: Authorization\n  value: Bearer xxx\n  enabled: true'
      }
    />
  ),
};

/**
 * Connection settings — mapping with mixed field types.
 */
export const ConnectionSettings: Story = {
  render: () => (
    <YamlEditorWithState
      schema={connectionSchema}
      initialValue="host: db.example.com\nport: 5432\nprotocol: https\nssl: true\ntags:\n  - production\n  - primary"
    />
  ),
};

/**
 * Deeply nested structure: mapping > list > mapping > list > mapping.
 */
export const DeeplyNested: Story = {
  render: () => (
    <YamlEditorWithState
      schema={deeplyNestedSchema}
      initialValue={
        'base_url: https://api.example.com\nendpoints:\n  - path: /users\n    method: GET\n    headers:\n      - key: Accept\n        value: application/json'
      }
    />
  ),
};

/**
 * Simple string value — the simplest possible schema.
 */
export const SimpleString: Story = {
  render: () => (
    <YamlEditorWithState schema={simpleStringSchema} initialValue="Hello World" />
  ),
};

/**
 * Simple list of strings.
 */
export const SimpleList: Story = {
  render: () => (
    <YamlEditorWithState
      schema={simpleListSchema}
      initialValue="- apple\n- banana\n- cherry"
    />
  ),
};

/**
 * Empty state — no initial data.
 */
export const Empty: Story = {
  render: () => (
    <YamlEditorWithState schema={httpHeadersSchema} />
  ),
};

/**
 * Disabled state — all fields read-only.
 */
export const Disabled: Story = {
  render: () => (
    <YamlEditorWithState
      schema={connectionSchema}
      initialValue="host: db.example.com\nport: 5432\nprotocol: https\nssl: true"
      disabled={true}
    />
  ),
};
