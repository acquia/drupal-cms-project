import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import StartNode from './StartNode';
import { withNodeContext } from '../../../.storybook/decorators';

const meta: Meta<typeof StartNode> = {
  title: 'Components/Nodes/StartNode',
  component: StartNode,
  decorators: [withNodeContext],
  parameters: {
    layout: 'centered',
  },
  args: {
    id: 'start_1',
    type: 'start',
    selected: false,
    dragging: false,
    isConnectable: true,
    xPos: 0,
    yPos: 0,
    zIndex: 0,
    data: {
      label: 'Content Created',
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
type Story = StoryObj<typeof StartNode>;

/**
 * Default start/event node
 */
export const Default: Story = {};

/**
 * Selected start node
 */
export const Selected: Story = {
  args: {
    selected: true,
  },
};

/**
 * Read-only start node (canvas locked)
 */
export const ReadOnly: Story = {
  args: {
    data: {
      label: 'User Login',
      isLocked: true,
      onDelete: fn(),
    },
  },
};

/**
 * Start node with visible annotation
 */
export const WithAnnotation: Story = {
  args: {
    data: {
      label: 'Cron Triggered',
      annotation: 'Runs every 15 minutes to check for pending tasks.',
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
      label: 'Content Entity Insert Event for Published Articles with Taxonomy Terms',
      onDelete: fn(),
    },
  },
};

/**
 * Start node with quick-add button visible (editable node with onQuickAdd handler)
 */
export const WithQuickAdd: Story = {
  args: {
    data: {
      label: 'Content Created',
      isLocked: false,
      onDelete: fn(),
      onQuickAdd: fn(),
    },
  },
};
