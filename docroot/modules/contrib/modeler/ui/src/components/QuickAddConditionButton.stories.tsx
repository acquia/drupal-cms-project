import type { Meta, StoryObj } from '@storybook/react';
import { fn, within, userEvent } from '@storybook/test';
import QuickAddConditionButton from './QuickAddConditionButton';
import { withStore } from '../../.storybook/decorators';

// QuickAddConditionButton filters to Conditions and Decisions.  Without
// matching components in the store, hasConditions is false and the button
// renders null.
//
// IMPORTANT: We provide >= 16 condition components so that
// SEARCH_VISIBILITY_MIN_COMPONENTS (15) is exceeded and the search input
// renders inside the popup.  This is critical for a11y audits: axe-core can
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
  { plugin: 'condition:entity_is_of_type', label: 'Entity is of Type', type: 'link', componentType: 5 },
  { plugin: 'condition:current_theme', label: 'Current Theme', type: 'link', componentType: 5 },
  { plugin: 'condition:request_path', label: 'Request Path', type: 'link', componentType: 5 },
  { plugin: 'condition:user_has_permission', label: 'User Has Permission', type: 'link', componentType: 5 },
  { plugin: 'condition:entity_is_published', label: 'Entity is Published', type: 'link', componentType: 5 },
  { plugin: 'condition:field_value_changed', label: 'Field Value Changed', type: 'link', componentType: 5 },
];

const meta: Meta<typeof QuickAddConditionButton> = {
  title: 'Components/QuickAddConditionButton',
  component: QuickAddConditionButton,
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
    disabled: false,
  },
  argTypes: {
    disabled: {
      control: 'boolean',
      description: 'Whether the button is disabled',
    },
    edgeId: {
      control: 'text',
      description: 'ID of the edge to attach the condition to',
    },
  },
};

export default meta;
type Story = StoryObj<typeof QuickAddConditionButton>;

/**
 * Default condition add button (appears on edge hover)
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
    const button = canvas.getByRole('button', { name: /add link/i });
    await userEvent.click(button);
  },
};
