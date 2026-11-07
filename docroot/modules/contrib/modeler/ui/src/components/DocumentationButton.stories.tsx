import type { Meta, StoryObj } from '@storybook/react';
import DocumentationButton from './DocumentationButton';

const meta: Meta<typeof DocumentationButton> = {
  title: 'Components/DocumentationButton',
  component: DocumentationButton,
  parameters: {
    layout: 'centered',
  },
  args: {
    url: 'https://ecaguide.org/actions/send_mail',
    title: 'Send Email Documentation',
    className: '',
    size: 14,
  },
  argTypes: {
    url: {
      control: 'text',
      description: 'Documentation URL (renders nothing if null/undefined)',
    },
    title: {
      control: 'text',
      description: 'Title shown in the documentation popup',
    },
    size: {
      control: { type: 'number', min: 10, max: 24 },
      description: 'Icon size in pixels',
    },
  },
};

export default meta;
type Story = StoryObj<typeof DocumentationButton>;

/**
 * Default documentation button with valid URL
 */
export const Default: Story = {};

/**
 * No URL (button not rendered)
 */
export const NoUrl: Story = {
  args: {
    url: null,
  },
};

/**
 * Larger icon size
 */
export const LargeIcon: Story = {
  args: {
    size: 20,
    title: 'Workflow Documentation',
  },
};

/**
 * With custom CSS class
 */
export const WithClassName: Story = {
  args: {
    className: 'custom-doc-button',
  },
};
