import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import GatewayNode from './GatewayNode';
import { withNodeContext } from '../../../.storybook/decorators';

const meta: Meta<typeof GatewayNode> = {
  title: 'Components/Nodes/GatewayNode',
  component: GatewayNode,
  decorators: [withNodeContext],
  parameters: {
    layout: 'centered',
  },
  args: {
    id: 'gateway_1',
    type: 'gateway',
    selected: false,
    dragging: false,
    isConnectable: true,
    xPos: 0,
    yPos: 0,
    zIndex: 0,
    data: {
      label: 'Check Role',
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
type Story = StoryObj<typeof GatewayNode>;

/**
 * Default gateway node with uniform card layout
 */
export const Default: Story = {};

/**
 * Selected gateway node
 */
export const Selected: Story = {
  args: {
    selected: true,
  },
};

/**
 * Read-only gateway node (canvas locked)
 */
export const ReadOnly: Story = {
  args: {
    data: {
      label: 'Is Admin?',
      isLocked: true,
      onDelete: fn(),
    },
  },
};

/**
 * Gateway with visible annotation
 */
export const WithAnnotation: Story = {
  args: {
    data: {
      label: 'Has Permission',
      annotation: 'Checks if the current user has the required permission to proceed.',
      onDelete: fn(),
    },
  },
};

/**
 * Short label
 */
export const ShortLabel: Story = {
  args: {
    data: {
      label: 'OK?',
      onDelete: fn(),
    },
  },
};

/**
 * Long label (truncated with ellipsis)
 */
export const LongLabel: Story = {
  args: {
    data: {
      label: 'User Has Administrator Role and Content Access Permission',
      onDelete: fn(),
    },
  },
};

/**
 * Gateway node with quick-add button visible (editable node with onQuickAdd handler)
 */
export const WithQuickAdd: Story = {
  args: {
    data: {
      label: 'Check Role',
      isLocked: false,
      onDelete: fn(),
      onQuickAdd: fn(),
    },
  },
};
