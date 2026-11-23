import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import PlaceholderNode from './PlaceholderNode';
import { withNodeContext, withStore } from '../../../.storybook/decorators';

const mockComponents = [
  { plugin: 'action:save_entity', label: 'Save Entity', type: 'element', componentType: 4 },
  { plugin: 'action:send_email', label: 'Send Email', type: 'element', componentType: 4 },
  { plugin: 'gateway:exclusive', label: 'Exclusive Gateway', type: 'gateway', componentType: 6 },
];

const meta: Meta<typeof PlaceholderNode> = {
  title: 'Components/Nodes/PlaceholderNode',
  component: PlaceholderNode,
  decorators: [
    withStore({
      initialState: {
        nodes: [],
        edges: [],
        components: mockComponents,
      },
    }),
    withNodeContext,
  ],
  parameters: {
    layout: 'centered',
  },
  args: {
    id: 'placeholder_1',
    type: 'placeholder',
    selected: false,
    dragging: false,
    isConnectable: true,
    xPos: 0,
    yPos: 0,
    zIndex: 0,
    data: {
      label: 'Select action...',
      isLocked: false,
      onDelete: fn(),
      onReplacePlaceholder: fn(),
    },
  },
  argTypes: {
    selected: {
      control: 'boolean',
      description: 'Whether the node is selected',
    },
  },
};

export default meta;
type Story = StoryObj<typeof PlaceholderNode>;

/**
 * Default placeholder node -- awaiting an action or gateway selection.
 * Shows a pulsing "Select action..." button.
 */
export const Default: Story = {};

/**
 * Selected placeholder node
 */
export const Selected: Story = {
  args: {
    selected: true,
  },
};

/**
 * Read-only placeholder (canvas locked -- button hidden, label shown instead)
 */
export const ReadOnly: Story = {
  args: {
    data: {
      label: 'Select action...',
      isLocked: true,
      onDelete: fn(),
    },
  },
};

/**
 * Placeholder without the replacement callback (fallback label display)
 */
export const WithoutCallback: Story = {
  args: {
    data: {
      label: 'Select action...',
      isLocked: false,
      onDelete: fn(),
    },
  },
};
