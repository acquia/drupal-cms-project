import type { Meta, StoryObj } from '@storybook/react';
import React from 'react';
import { fn, within, userEvent } from '@storybook/test';
import QuickAddEventButton from './QuickAddEventButton';
import { withStore } from '../../.storybook/decorators';

// QuickAddEventButton filters to Events and Triggers.  Without matching
// components in the store, hasEvents is false and the button renders null.
//
// IMPORTANT: We provide >= 16 start components so that
// SEARCH_VISIBILITY_MIN_COMPONENTS (15) is exceeded and the search input
// renders inside the popup.  This is critical for a11y audits: axe-core can
// only check color-contrast on elements that exist in the DOM.
const mockComponents = [
  { plugin: 'content_entity:insert', label: 'Content Insert', type: 'start', componentType: 1 },
  { plugin: 'content_entity:update', label: 'Content Update', type: 'start', componentType: 1 },
  { plugin: 'user:login', label: 'User Login', type: 'start', componentType: 1 },
  { plugin: 'cron', label: 'Cron Run', type: 'start', componentType: 1 },
  { plugin: 'content_entity:delete', label: 'Content Delete', type: 'start', componentType: 1 },
  { plugin: 'content_entity:presave', label: 'Content Presave', type: 'start', componentType: 1 },
  { plugin: 'user:logout', label: 'User Logout', type: 'start', componentType: 1 },
  { plugin: 'user:register', label: 'User Register', type: 'start', componentType: 1 },
  { plugin: 'taxonomy_term:insert', label: 'Term Insert', type: 'start', componentType: 1 },
  { plugin: 'taxonomy_term:update', label: 'Term Update', type: 'start', componentType: 1 },
  { plugin: 'comment:insert', label: 'Comment Insert', type: 'start', componentType: 1 },
  { plugin: 'comment:update', label: 'Comment Update', type: 'start', componentType: 1 },
  { plugin: 'media:insert', label: 'Media Insert', type: 'start', componentType: 1 },
  { plugin: 'media:update', label: 'Media Update', type: 'start', componentType: 1 },
  { plugin: 'system:startup', label: 'System Startup', type: 'start', componentType: 1 },
  { plugin: 'custom:webhook', label: 'Webhook Received', type: 'start', componentType: 1 },
];

const meta: Meta<typeof QuickAddEventButton> = {
  title: 'Components/QuickAddEventButton',
  component: QuickAddEventButton,
  decorators: [
    withStore({
      initialState: {
        nodes: [],
        edges: [],
        components: mockComponents,
      },
    }),
    // Simulate the toolbar layout context; use CSS variables so both light
    // and dark mode render correctly during a11y audits.
    (Story: React.ComponentType) => (
      <div style={{
        display: 'flex',
        alignItems: 'center',
        gap: '8px',
        padding: '8px',
        background: 'var(--modeler-color-bg-primary)',
        borderBottom: '1px solid var(--modeler-color-border-light)',
      }}>
        <Story />
      </div>
    ),
  ],
  parameters: {
    layout: 'padded',
  },
  args: {
    onAddEvent: fn(),
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
type Story = StoryObj<typeof QuickAddEventButton>;

/**
 * Default "New event" toolbar button for adding event/start nodes.
 * Rendered in the toolbar-left section as the first element.
 */
export const Default: Story = {};

/**
 * Disabled state - button is not rendered when disabled
 */
export const Disabled: Story = {
  args: {
    disabled: true,
  },
};

/**
 * Controlled open state with popup visible.
 * The popup includes the search input since >= 15 components are provided.
 */
export const ControlledOpen: Story = {
  args: {
    isOpen: true,
    onOpenChange: fn(),
  },
};

/**
 * Popup open with search input visible (via user click).
 *
 * The play function clicks the button to open the popup, ensuring the
 * search input (which only renders when >= 15 components are present) is
 * in the DOM when the Storybook test-runner runs axe-core in both light
 * and dark mode.
 */
export const WithPopupOpen: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const button = canvas.getByRole('button', { name: /new start/i });
    await userEvent.click(button);
  },
};
