import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import NodePropertiesPanel from './NodePropertiesPanel';

const sampleNode = {
  id: 'node_1',
  type: 'element',
  position: { x: 200, y: 100 },
  data: {
    label: 'Send Email',
    plugin: 'eca_mail:send_mail',
    locked: false,
    annotation: 'Sends notification to admins.',
    isAnnotationVisible: false,
  },
};

const sampleConfigForm = [
  { key: 'recipient', type: 'textfield', title: 'Recipient', description: 'Email address of the recipient', required: true, default_value: '' },
  { key: 'subject', type: 'textfield', title: 'Subject', description: 'Email subject line', required: true, default_value: '' },
  { key: 'body', type: 'textarea', title: 'Body', description: 'Email body content', required: false, default_value: '' },
  { key: 'format', type: 'select', title: 'Format', description: 'Email format', options: { plain: 'Plain Text', html: 'HTML' }, default_value: 'plain' },
];

const mockDebouncedField = {
  value: 'Send Email',
  onChange: fn(),
  flush: fn(),
};

const mockAnnotationField = {
  value: 'Sends notification to admins.',
  onChange: fn(),
  flush: fn(),
};

const meta: Meta<typeof NodePropertiesPanel> = {
  title: 'Components/NodePropertiesPanel',
  component: NodePropertiesPanel,
  parameters: {
    layout: 'centered',
  },
  decorators: [
    (Story: React.ComponentType) => (
      <div style={{ width: 320, padding: 16, border: '1px solid #e0e0e0', borderRadius: 8 }}>
        <Story />
      </div>
    ),
  ],
  args: {
    node: sampleNode,
    configurationForm: sampleConfigForm,
    onConfigurationChange: fn(),
    onNodeUpdate: fn(),
    isLocked: false,
    nodeLabelField: mockDebouncedField as any,
    nodeAnnotationField: mockAnnotationField as any,
  },
  argTypes: {
    isLocked: {
      control: 'boolean',
      description: 'Whether the panel fields are locked',
    },
  },
};

export default meta;
type Story = StoryObj<typeof NodePropertiesPanel>;

/**
 * Default node properties panel with configuration form
 */
export const Default: Story = {};

/**
 * Locked panel (fields disabled)
 */
export const Locked: Story = {
  args: {
    isLocked: true,
  },
};

/**
 * Panel without configuration form
 */
export const NoConfigForm: Story = {
  args: {
    configurationForm: null,
  },
};

/**
 * Node without annotation
 */
export const NoAnnotation: Story = {
  args: {
    nodeAnnotationField: {
      value: '',
      onChange: fn(),
      flush: fn(),
    } as any,
  },
};
