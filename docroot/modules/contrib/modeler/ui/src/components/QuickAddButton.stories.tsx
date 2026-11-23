import type { Meta, StoryObj } from '@storybook/react';
import { fn, within, userEvent } from '@storybook/test';
import QuickAddButton from './QuickAddButton';
import { withStore } from '../../.storybook/decorators';

// QuickAddButton shows Actions, Gateways, and Conditions (for condition-first
// authoring).  The store must contain matching components so the button, its
// popup, and the collapsible type-filter panel all have something to display.
//
// IMPORTANT: We provide >= 16 non-start components so that
// SEARCH_VISIBILITY_MIN_COMPONENTS (15) is exceeded and the search input
// renders inside the popup.  This is critical for a11y audits: axe-core can
// only check color-contrast on elements that exist in the DOM.
const mockComponents = [
  { plugin: 'action:save_entity', label: 'Save Entity', type: 'element', componentType: 4 },
  { plugin: 'action:send_email', label: 'Send Email', type: 'element', componentType: 4 },
  { plugin: 'action:set_field', label: 'Set Field Value', type: 'element', componentType: 4 },
  { plugin: 'action:publish', label: 'Publish Content', type: 'element', componentType: 4 },
  { plugin: 'action:unpublish', label: 'Unpublish Content', type: 'element', componentType: 4 },
  { plugin: 'action:delete_entity', label: 'Delete Entity', type: 'element', componentType: 4 },
  { plugin: 'action:clone_entity', label: 'Clone Entity', type: 'element', componentType: 4 },
  { plugin: 'action:set_status', label: 'Set Status', type: 'element', componentType: 4 },
  { plugin: 'action:send_notification', label: 'Send Notification', type: 'element', componentType: 4 },
  { plugin: 'action:redirect', label: 'Redirect', type: 'element', componentType: 4 },
  { plugin: 'action:log_message', label: 'Log Message', type: 'element', componentType: 4 },
  { plugin: 'gateway:exclusive', label: 'Exclusive Gateway', type: 'gateway', componentType: 6 },
  { plugin: 'gateway:parallel', label: 'Parallel Gateway', type: 'gateway', componentType: 6 },
  { plugin: 'condition:entity_is_new', label: 'Entity is New', type: 'link', componentType: 5 },
  { plugin: 'condition:user_has_role', label: 'User Has Role', type: 'link', componentType: 5 },
  { plugin: 'condition:entity_has_field', label: 'Entity Has Field', type: 'link', componentType: 5 },
];

const meta: Meta<typeof QuickAddButton> = {
  title: 'Components/QuickAddButton',
  component: QuickAddButton,
  decorators: [
    withStore({
      initialState: {
        nodes: [],
        edges: [],
        components: mockComponents,
      },
    }),
  ],
  parameters: {
    layout: 'centered',
  },
  args: {
    onAddNode: fn(),
    disabled: false,
  },
  argTypes: {
    disabled: {
      control: 'boolean',
      description: 'Whether the button is disabled',
    },
  },
};

export default meta;
type Story = StoryObj<typeof QuickAddButton>;

/**
 * Default quick-add button (appears on node hover)
 */
export const Default: Story = {};

/**
 * Disabled state (e.g., read-only mode)
 */
export const Disabled: Story = {
  args: {
    disabled: true,
  },
};

/**
 * Popup open with search input visible.
 *
 * The play function clicks the button to open the popup, ensuring the
 * search input (which only renders when >= 15 components are present) is
 * in the DOM when the Storybook test-runner runs axe-core in both light
 * and dark mode.
 */
export const WithPopupOpen: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const button = canvas.getByRole('button', { name: /add successor node/i });
    await userEvent.click(button);
  },
};
