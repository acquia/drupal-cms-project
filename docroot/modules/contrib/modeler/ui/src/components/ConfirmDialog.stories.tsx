import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import ConfirmDialog from './ConfirmDialog';

const meta: Meta<typeof ConfirmDialog> = {
  title: 'Components/ConfirmDialog',
  component: ConfirmDialog,
  parameters: {
    layout: 'fullscreen',
  },
  args: {
    isOpen: true,
    onClose: fn(),
    onSaveAndClose: fn(),
    onCloseWithoutSave: fn(),
  },
  argTypes: {
    isOpen: {
      control: 'boolean',
      description: 'Controls whether the dialog is visible',
    },
    title: {
      control: 'text',
      description: 'Dialog title',
    },
    message: {
      control: 'text',
      description: 'Dialog message body',
    },
    primaryButtonLabel: {
      control: 'text',
      description: 'Label for the primary action button',
    },
    primaryButtonVariant: {
      control: 'radio',
      options: ['primary', 'danger'],
      description: 'Visual variant for the primary button',
    },
    cancelButtonLabel: {
      control: 'text',
      description: 'Label for the cancel button',
    },
  },
};

export default meta;
type Story = StoryObj<typeof ConfirmDialog>;

/**
 * Default unsaved changes dialog
 */
export const Default: Story = {};

/**
 * Custom title and message
 */
export const CustomContent: Story = {
  args: {
    title: 'Delete Component?',
    message: 'This action cannot be undone. Are you sure you want to delete this component?',
  },
};

/**
 * Long message content
 */
export const LongMessage: Story = {
  args: {
    title: 'Confirm Action',
    message:
      'You are about to perform an action that will affect multiple components in your workflow. ' +
      'This includes all connected nodes and their associated conditions. ' +
      'Please review your changes carefully before proceeding.',
  },
};

/**
 * Delete confirmation dialog — 2-button danger variant used by the
 * "Delete All" flow (primary = Delete, secondary hidden, cancel = Cancel).
 */
export const DeleteConfirmation: Story = {
  args: {
    title: 'Delete Selected?',
    message: 'This will permanently delete the selected elements. This action cannot be undone.',
    primaryButtonLabel: 'Delete',
    primaryButtonVariant: 'danger',
    secondaryButtonLabel: false,
    cancelButtonLabel: 'Cancel',
    onCloseWithoutSave: undefined,
  },
};

/**
 * Dialog in closed state (not visible)
 */
export const Closed: Story = {
  args: {
    isOpen: false,
  },
};
