import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import EdgeDeleteButton from './EdgeDeleteButton';

// EdgeDeleteButton is a small presentational trash button rendered at the
// edge midpoint next to the quick-add plus button. It is neutral by default
// and turns danger-red on hover/focus/active. Deletion is immediate (undo
// already exists), so there is no confirmation modal.
const meta: Meta<typeof EdgeDeleteButton> = {
  title: 'Components/Edges/EdgeDeleteButton',
  component: EdgeDeleteButton,
  parameters: {
    layout: 'centered',
  },
  args: {
    edgeId: 'edge_1',
    onDelete: fn(),
    disabled: false,
  },
  argTypes: {
    disabled: {
      control: 'boolean',
      description: 'When true, the button does not render',
    },
    edgeId: {
      control: 'text',
      description: 'ID of the edge to delete',
    },
  },
};

export default meta;
type Story = StoryObj<typeof EdgeDeleteButton>;

/**
 * Default trash button (revealed on edge hover or selection).
 */
export const Default: Story = {};

/**
 * Disabled state — the button renders nothing.
 */
export const Disabled: Story = {
  args: {
    disabled: true,
  },
};
