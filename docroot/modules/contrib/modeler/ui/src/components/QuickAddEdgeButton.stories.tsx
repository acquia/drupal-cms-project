import type { Meta, StoryObj } from '@storybook/react';
import { fn, within, userEvent } from '@storybook/test';
import QuickAddEdgeButton from './QuickAddEdgeButton';
import { withStore } from '../../.storybook/decorators';

// QuickAddEdgeButton shows conditions, actions, and gateways. Without
// matching components in the store, hasComponents is false and the button
// renders null.
//
// IMPORTANT: We provide >= 16 total components so that
// SEARCH_VISIBILITY_MIN_COMPONENTS (15) is exceeded and the search input
// renders inside the popup. This is critical for a11y audits: axe-core can
// only check color-contrast on elements that exist in the DOM.
const mockComponents = [
  { plugin: 'condition:entity_is_new', label: 'Entity is New', type: 'link', componentType: 5 },
  { plugin: 'condition:entity_field_value', label: 'Entity Field Value', type: 'link', componentType: 5 },
  { plugin: 'condition:user_role', label: 'User Has Role', type: 'link', componentType: 5 },
  { plugin: 'condition:content_is_published', label: 'Content is Published', type: 'link', componentType: 5 },
  { plugin: 'condition:entity_type', label: 'Entity Type', type: 'link', componentType: 5 },
  { plugin: 'condition:entity_bundle', label: 'Entity Bundle', type: 'link', componentType: 5 },
  { plugin: 'condition:user_is_logged_in', label: 'User is Logged In', type: 'link', componentType: 5 },
  { plugin: 'condition:node_is_promoted', label: 'Node is Promoted', type: 'link', componentType: 5 },
  { plugin: 'condition:node_is_sticky', label: 'Node is Sticky', type: 'link', componentType: 5 },
  { plugin: 'condition:entity_has_field', label: 'Entity Has Field', type: 'link', componentType: 5 },
  { plugin: 'action:save_entity', label: 'Save Entity', type: 'element', componentType: 4 },
  { plugin: 'action:publish_content', label: 'Publish Content', type: 'element', componentType: 4 },
  { plugin: 'action:send_email', label: 'Send Email', type: 'element', componentType: 4 },
  { plugin: 'action:set_field_value', label: 'Set Field Value', type: 'element', componentType: 4 },
  { plugin: 'action:redirect', label: 'Redirect', type: 'element', componentType: 4 },
  { plugin: 'gateway:parallel_split', label: 'Parallel Split', type: 'gateway', componentType: 6 },
  { plugin: 'gateway:exclusive_split', label: 'Exclusive Split', type: 'gateway', componentType: 6 },
];

const meta: Meta<typeof QuickAddEdgeButton> = {
  title: 'Components/QuickAddEdgeButton',
  component: QuickAddEdgeButton,
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
    edgeId: 'edge_1',
    onAddCondition: fn(),
    onAddAction: fn(),
    disabled: false,
  },
  argTypes: {
    disabled: {
      control: 'boolean',
      description: 'Whether the button is disabled',
    },
    edgeId: {
      control: 'text',
      description: 'ID of the edge to add components to',
    },
  },
};

export default meta;
type Story = StoryObj<typeof QuickAddEdgeButton>;

/**
 * Default edge add button (appears on edge hover)
 */
export const Default: Story = {};

/**
 * Disabled state
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
    const button = canvas.getByRole('button', { name: /add link or insert node/i });
    await userEvent.click(button);
  },
};
