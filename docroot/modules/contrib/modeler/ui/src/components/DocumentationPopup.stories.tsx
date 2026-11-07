import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import DocumentationPopup from './DocumentationPopup';

const meta: Meta<typeof DocumentationPopup> = {
  title: 'Components/DocumentationPopup',
  component: DocumentationPopup,
  parameters: {
    layout: 'fullscreen',
  },
  args: {
    url: 'https://ecaguide.org/actions/send_mail',
    title: 'Send Email Documentation',
    isOpen: true,
    onClose: fn(),
  },
  argTypes: {
    isOpen: {
      control: 'boolean',
      description: 'Whether the popup is visible',
    },
    url: {
      control: 'text',
      description: 'URL to fetch documentation from',
    },
    title: {
      control: 'text',
      description: 'Title displayed in the popup header',
    },
  },
};

export default meta;
type Story = StoryObj<typeof DocumentationPopup>;

/**
 * Open documentation popup (fetches content from URL).
 * Excluded from a11y test-runner: the popup renders remote HTML via
 * dangerouslySetInnerHTML whose accessibility cannot be guaranteed.
 */
export const Default: Story = {
  tags: ['!test'],
};

/**
 * Closed popup
 */
export const Closed: Story = {
  args: {
    isOpen: false,
  },
};

/**
 * Long title.
 * Excluded from a11y test-runner: same reason as Default.
 */
export const LongTitle: Story = {
  tags: ['!test'],
  args: {
    title: 'Content Entity Insert Event - Complete Documentation and Configuration Guide',
  },
};
