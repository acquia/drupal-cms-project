import React from 'react';
import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import ToolbarMenu from './ToolbarMenu';

const meta: Meta<typeof ToolbarMenu> = {
  title: 'Components/ToolbarMenu',
  component: ToolbarMenu,
  parameters: {
    layout: 'centered',
  },
  decorators: [
    (Story: React.ComponentType) => (
      <div style={{ padding: '80px', position: 'relative' }}>
        <Story />
      </div>
    ),
  ],
  args: {
    onOpenMetadata: fn(),
    onExport: fn(),
    canExport: true,
  },
  argTypes: {
    canExport: {
      control: 'boolean',
      description: 'Whether the Export option is shown in the menu',
    },
  },
};

export default meta;
type Story = StoryObj<typeof ToolbarMenu>;

/**
 * Default kebab menu with all items: Model Settings, Export Model,
 * and Dark/Light Mode toggle.
 */
export const Default: Story = {};

/**
 * Kebab menu without the Export option (canExport=false).
 * Only Model Settings and Dark/Light Mode toggle are shown.
 */
export const WithoutExport: Story = {
  args: {
    canExport: false,
  },
};
