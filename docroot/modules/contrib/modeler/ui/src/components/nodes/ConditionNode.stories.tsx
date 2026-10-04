import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import ConditionNode from './ConditionNode';
import { withNodeContext } from '../../../.storybook/decorators';

const meta: Meta<typeof ConditionNode> = {
  title: 'Components/Nodes/ConditionNode',
  component: ConditionNode,
  decorators: [withNodeContext],
  parameters: {
    layout: 'centered',
  },
  args: {
    id: 'condition_1',
    type: 'condition',
    selected: false,
    dragging: false,
    isConnectable: true,
    xPos: 0,
    yPos: 0,
    zIndex: 0,
    data: {
      label: 'Is New Entity',
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
type Story = StoryObj<typeof ConditionNode>;

/**
 * Default condition node with uniform card layout
 */
export const Default: Story = {};

/**
 * Selected condition node
 */
export const Selected: Story = {
  args: {
    selected: true,
  },
};

/**
 * Read-only condition node (canvas locked)
 */
export const ReadOnly: Story = {
  args: {
    data: {
      label: 'Has Permission',
      isLocked: true,
      onDelete: fn(),
    },
  },
};

/**
 * Condition with visible annotation
 */
export const WithAnnotation: Story = {
  args: {
    data: {
      label: 'Field Is Empty',
      annotation: 'Checks whether the target field has no value before proceeding.',
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
      label: 'New?',
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
      label: 'Entity Bundle Is Article And Field Tags Contains Featured',
      onDelete: fn(),
    },
  },
};

/**
 * Condition node with quick-add button visible (editable node with onQuickAdd handler)
 */
export const WithQuickAdd: Story = {
  args: {
    data: {
      label: 'Is New Entity',
      isLocked: false,
      onDelete: fn(),
      onQuickAdd: fn(),
    },
  },
};
