import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import ContentEditableField from './ContentEditableField';

const meta: Meta<typeof ContentEditableField> = {
  title: 'Components/ContentEditableField',
  component: ContentEditableField,
  parameters: {
    layout: 'centered',
  },
  decorators: [
    (Story: React.ComponentType) => (
      <div style={{ width: 400, padding: 20 }}>
        <Story />
      </div>
    ),
  ],
  args: {
    value: '',
    onChange: fn(),
    placeholder: 'Enter value...',
    disabled: false,
    multiline: false,
    className: '',
  },
  argTypes: {
    value: {
      control: 'text',
      description: 'Current field value (may contain token syntax)',
    },
    placeholder: {
      control: 'text',
      description: 'Placeholder text when field is empty',
    },
    disabled: {
      control: 'boolean',
      description: 'Whether the field is disabled',
    },
    multiline: {
      control: 'boolean',
      description: 'Whether the field supports multiple lines',
    },
    acceptsTokens: {
      control: 'boolean',
      description: 'Whether this field accepts token drops',
    },
  },
};

export default meta;
type Story = StoryObj<typeof ContentEditableField>;

/**
 * Empty field with placeholder
 */
export const Default: Story = {};

/**
 * Field with plain text value
 */
export const WithValue: Story = {
  args: {
    value: 'Hello World',
  },
};

/**
 * Field with token syntax
 */
export const WithTokens: Story = {
  args: {
    value: 'Welcome [current-user:display-name], your article [node:title] has been published.',
  },
};

/**
 * Multiline field
 */
export const Multiline: Story = {
  args: {
    multiline: true,
    value: 'Line 1\nLine 2\nLine 3',
    placeholder: 'Enter multiple lines...',
  },
};

/**
 * Disabled field
 */
export const Disabled: Story = {
  args: {
    disabled: true,
    value: 'This field is read-only',
  },
};

/**
 * Field with long content
 */
export const LongContent: Story = {
  args: {
    value: 'Dear [current-user:display-name], we wanted to inform you that the content titled [node:title] has been updated on [site:name]. Please review the changes at your earliest convenience.',
    multiline: true,
  },
};

/**
 * Field with token acceptance disabled (no token pill rendering)
 */
export const NoTokensAccepted: Story = {
  args: {
    value: 'Plain text [not:a:token]',
    acceptsTokens: false,
  },
};
