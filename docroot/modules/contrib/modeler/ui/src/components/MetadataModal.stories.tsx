import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import MetadataModal from './MetadataModal';
import { metadataForm, newModelMetadataForm } from './__fixtures__/metadataForm';

const meta: Meta<typeof MetadataModal> = {
  title: 'Components/MetadataModal',
  component: MetadataModal,
  parameters: {
    layout: 'fullscreen',
  },
  args: {
    isOpen: true,
    onClose: fn(),
    onSave: fn(),
    isNew: false,
    modelId: 'email_notification_workflow',
    form: metadataForm,
    metadata: {
      label: 'Email Notification Workflow',
      version: '1.0.0',
      executable: true,
      template: false,
      storage: '',
      documentation: 'https://example.com/docs/email-workflow',
      tags: ['email', 'notification', 'automated'],
      changelog: 'Initial release with email notification support.',
      summary: 'Sends a notification email whenever an article is published.',
      recipes: ['core/recipes/article_tags'],
      export_config: ['system.site'],
      modules: ['node'],
      config_actions: [
        { config: 'system.site', actions: { simple_config_update: { slogan: 'Hello' } } },
      ],
    },
  },
  argTypes: {
    isOpen: {
      control: 'boolean',
      description: 'Whether the modal is visible',
    },
    isNew: {
      control: 'boolean',
      description: 'Whether this is a new model (shows ID generation)',
    },
  },
};

export default meta;
type Story = StoryObj<typeof MetadataModal>;

/**
 * Editing existing model metadata
 */
export const Default: Story = {};

/**
 * Creating a new model (machine name derived from the label)
 */
export const NewModel: Story = {
  args: {
    isNew: true,
    modelId: undefined,
    form: newModelMetadataForm,
    metadata: {
      label: '',
      version: '1.0.0',
      executable: true,
      template: false,
      storage: '',
      documentation: '',
      tags: [],
    },
  },
};

/**
 * Modal with minimal metadata
 */
export const MinimalMetadata: Story = {
  args: {
    metadata: {
      label: 'Simple Workflow',
      version: '1.0.0',
    },
  },
};

/**
 * Modal with many tags
 */
export const ManyTags: Story = {
  args: {
    metadata: {
      label: 'Complex Workflow',
      version: '2.3.1',
      executable: true,
      template: true,
      tags: ['email', 'notification', 'cron', 'content', 'user', 'commerce', 'automation', 'integration'],
      changelog: 'Version 2.3.1: Added commerce integration and improved error handling.',
    },
  },
};

/**
 * Read-only for a user without the edit permission
 */
export const ReadOnly: Story = {
  args: {
    canEditMetadata: false,
  },
};

/**
 * No metadata form delivered by the backend
 */
export const WithoutForm: Story = {
  args: {
    form: undefined,
  },
};

/**
 * Closed modal
 */
export const Closed: Story = {
  args: {
    isOpen: false,
  },
};
