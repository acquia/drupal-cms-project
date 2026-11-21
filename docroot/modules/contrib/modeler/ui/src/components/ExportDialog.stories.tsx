import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import ExportDialog from './ExportDialog';

const meta: Meta<typeof ExportDialog> = {
  title: 'Components/ExportDialog',
  component: ExportDialog,
  parameters: {
    layout: 'fullscreen',
  },
  args: {
    isOpen: true,
    onClose: fn(),
    onExport: fn(),
    availableFormats: ['recipe', 'archive', 'json', 'svg'],
    hasReplayData: false,
    requiredModules: [],
    isExporting: false,
  },
  argTypes: {
    isOpen: {
      control: 'boolean',
      description: 'Controls whether the dialog is visible',
    },
    availableFormats: {
      control: 'check',
      options: ['recipe', 'archive', 'json', 'svg'],
      description: 'Available export formats',
    },
    hasReplayData: {
      control: 'boolean',
      description: 'Whether replay data is available for JSON export',
    },
    requiredModules: {
      control: 'object',
      description: 'List of required Drupal modules',
    },
    isExporting: {
      control: 'boolean',
      description: 'Whether an export is currently in progress',
    },
  },
};

export default meta;
type Story = StoryObj<typeof ExportDialog>;

/**
 * Default export dialog with all four formats available.
 */
export const Default: Story = {};

/**
 * JSON format selected with replay data available and required modules
 * listed.  Demonstrates the format-specific options panel.
 */
export const JsonWithOptions: Story = {
  args: {
    hasReplayData: true,
    requiredModules: ['workflow_base', 'workflow_content', 'workflow_user'],
  },
};

/**
 * Only client-side formats available (no backend URLs configured).
 * Typical for a new, unsaved model.
 */
export const ClientSideOnly: Story = {
  args: {
    availableFormats: ['json', 'svg'],
  },
};

/**
 * Export in progress — buttons are disabled and the primary button shows
 * "Exporting..." to indicate the operation is running.
 */
export const Exporting: Story = {
  args: {
    isExporting: true,
  },
};

/**
 * Dialog in closed state (not visible).
 */
export const Closed: Story = {
  args: {
    isOpen: false,
  },
};
