import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import CustomNode from './CustomNode';
import { withNodeContext } from '../../../.storybook/decorators';

const meta: Meta<typeof CustomNode> = {
  title: 'Components/Nodes/CustomNode',
  component: CustomNode,
  decorators: [withNodeContext],
  parameters: {
    layout: 'centered',
  },
  args: {
    id: 'node_1',
    type: 'custom',
    selected: false,
    dragging: false,
    isConnectable: true,
    xPos: 0,
    yPos: 0,
    zIndex: 0,
    data: {
      label: 'Send Email Notification',
      annotation: '',
      isLocked: false,
      onDelete: fn(),
    },
  },
  argTypes: {
    selected: {
      control: 'boolean',
      description: 'Whether the node is selected',
    },
    'data.label': {
      control: 'text',
      description: 'Node label text',
    },
    'data.annotation': {
      control: 'text',
      description: 'Annotation text for the node',
    },
  },
};

export default meta;
type Story = StoryObj<typeof CustomNode>;

/**
 * Default action node
 */
export const Default: Story = {};

/**
 * Selected node state
 */
export const Selected: Story = {
  args: {
    selected: true,
  },
};

/**
 * Read-only node (canvas locked, cannot be edited or deleted)
 */
export const ReadOnly: Story = {
  args: {
    data: {
      label: 'Protected Action',
      isLocked: true,
      onDelete: fn(),
    },
  },
};

/**
 * Node with annotation text. A small document icon appears in the node
 * footer indicating that an annotation is present (visible on hover via
 * the browser tooltip).
 */
export const WithAnnotation: Story = {
  args: {
    data: {
      label: 'Process Payment',
      annotation: 'This action processes the payment via the configured gateway. Make sure to test with sandbox credentials first.',
      onDelete: fn(),
    },
  },
};

/**
 * Long label text
 */
export const LongLabel: Story = {
  args: {
    data: {
      label: 'Send Personalized Email Notification to All Subscribed Users Based on Their Preferences',
      onDelete: fn(),
    },
  },
};

/**
 * Node with quick-add button visible (editable node with onQuickAdd handler)
 */
export const WithQuickAdd: Story = {
  args: {
    data: {
      label: 'Send Email Notification',
      isLocked: false,
      onDelete: fn(),
      onQuickAdd: fn(),
    },
  },
};
