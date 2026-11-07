import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import Modals from './Modals';
import { metadataForm, newModelMetadataForm } from './__fixtures__/metadataForm';

const meta: Meta<typeof Modals> = {
  title: 'Components/Modals',
  component: Modals,
  parameters: {
    layout: 'fullscreen',
  },
  args: {
    // Metadata Modal
    showMetadataModal: false,
    onCloseMetadataModal: fn(),
    onMetadataSubmit: fn(),
    modelMetadata: {
      label: 'Email Notification Workflow',
      version: '1.0.0',
      executable: true,
      template: false,
      tags: ['email', 'notification'],
    },
    metadataForm,
    modelId: 'email_notification_workflow',
    isNewModel: false,

    // Confirm Dialog
    showConfirmDialog: false,
    confirmDialogTitle: 'Unsaved Changes',
    confirmDialogMessage: 'You have unsaved changes. What would you like to do?',
    confirmDialogType: 'warning' as const,
    onConfirmDialog: fn(),
    onCancelDialog: fn(),
    onCloseWithoutSave: fn(),
    confirmDialogLoading: false,
  },
  argTypes: {
    showMetadataModal: {
      control: 'boolean',
      description: 'Show the metadata editing modal',
    },
    showConfirmDialog: {
      control: 'boolean',
      description: 'Show the confirmation dialog',
    },
    confirmDialogType: {
      control: 'radio',
      options: ['danger', 'warning', 'info'],
      description: 'Confirm dialog visual type',
    },
    confirmDialogLoading: {
      control: 'boolean',
      description: 'Whether the confirm action is loading',
    },
  },
};

export default meta;
type Story = StoryObj<typeof Modals>;

/**
 * No modals shown (default idle state)
 */
export const NoModals: Story = {};

/**
 * Metadata modal open
 */
export const MetadataModalOpen: Story = {
  args: {
    showMetadataModal: true,
  },
};

/**
 * Confirm dialog open (unsaved changes warning)
 */
export const ConfirmDialogOpen: Story = {
  args: {
    showConfirmDialog: true,
  },
};

/**
 * Confirm dialog with danger type
 */
export const DangerConfirmDialog: Story = {
  args: {
    showConfirmDialog: true,
    confirmDialogTitle: 'Delete Workflow?',
    confirmDialogMessage: 'This action cannot be undone. The workflow and all its data will be permanently deleted.',
    confirmDialogType: 'danger' as const,
  },
};

/**
 * Delete confirmation dialog — 2-button danger variant used by the
 * "Delete All" flow (primary = Delete, secondary hidden, cancel = Cancel).
 */
export const DeleteConfirmDialog: Story = {
  args: {
    showConfirmDialog: true,
    confirmDialogTitle: 'Delete Selected?',
    confirmDialogMessage: 'This will permanently delete the selected elements. This action cannot be undone.',
    confirmDialogType: 'danger' as const,
    confirmDialogPrimaryLabel: 'Delete',
    confirmDialogPrimaryVariant: 'danger',
    confirmDialogSecondaryLabel: false,
    confirmDialogCancelLabel: 'Cancel',
    onCloseWithoutSave: undefined,
  },
};

/**
 * Pre-save validation error -- placeholder nodes block saving.
 * Shown when the user tries to save, test, or close with placeholder
 * nodes still on the canvas.
 */
export const PlaceholderValidationError: Story = {
  args: {
    showConfirmDialog: true,
    confirmDialogTitle: 'Cannot Save',
    confirmDialogMessage: 'Cannot save: 1 placeholder node(s) still need an action or gateway assigned. Please replace all placeholder nodes before saving.',
    confirmDialogType: 'warning' as const,
    confirmDialogSecondaryLabel: false,
    confirmDialogCancelLabel: 'OK',
    onCloseWithoutSave: undefined,
  },
};

/**
 * New model metadata modal
 */
export const NewModelMetadata: Story = {
  args: {
    showMetadataModal: true,
    isNewModel: true,
    metadataForm: newModelMetadataForm,
    modelId: undefined,
    modelMetadata: {
      label: '',
      version: '1.0.0',
    },
  },
};
