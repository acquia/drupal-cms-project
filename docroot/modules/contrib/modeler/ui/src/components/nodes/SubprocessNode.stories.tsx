import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import SubprocessNode from './SubprocessNode';
import { withNodeContext } from '../../../.storybook/decorators';

const meta: Meta<typeof SubprocessNode> = {
  title: 'Components/Nodes/SubprocessNode',
  component: SubprocessNode,
  decorators: [withNodeContext],
  parameters: {
    layout: 'centered',
  },
  args: {
    id: 'subprocess_1',
    type: 'subprocess',
    selected: false,
    dragging: false,
    isConnectable: true,
    xPos: 0,
    yPos: 0,
    zIndex: 0,
    data: {
      label: 'Process Order',
      annotation: '',
      isLocked: false,
      subflowCount: 3,
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
    'data.subflowCount': {
      control: { type: 'number', min: 0, max: 20 },
      description: 'Number of sub-flows in the subprocess',
    },
    'data.annotation': {
      control: 'text',
      description: 'Annotation text for the node',
    },
  
  },
};

export default meta;
type Story = StoryObj<typeof SubprocessNode>;

/**
 * Default subprocess node with sub-flow count
 */
export const Default: Story = {};

/**
 * Selected subprocess node
 */
export const Selected: Story = {
  args: {
    selected: true,
  },
};

/**
 * Read-only subprocess node (canvas locked)
 */
export const ReadOnly: Story = {
  args: {
    data: {
      label: 'Critical Workflow',
      isLocked: true,
      subflowCount: 5,
      onDelete: fn(),
    },
  },
};

/**
 * Subprocess with visible annotation
 */
export const WithAnnotation: Story = {
  args: {
    data: {
      label: 'Batch Import',
      subflowCount: 12,
      annotation: 'Handles importing records in batches of 100 to avoid timeouts.',
      onDelete: fn(),
    },
  },
};

/**
 * Subprocess with no sub-flows
 */
export const NoSubflows: Story = {
  args: {
    data: {
      label: 'Empty Subprocess',
      subflowCount: 0,
      onDelete: fn(),
    },
  },
};

/**
 * Subprocess node with quick-add button visible (editable node with onQuickAdd handler)
 */
export const WithQuickAdd: Story = {
  args: {
    data: {
      label: 'Process Order',
      isLocked: false,
      subflowCount: 3,
      onDelete: fn(),
      onQuickAdd: fn(),
    },
  },
};
