import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import Toolbar from './Toolbar';
import { withReactFlow, withStore } from '../../.storybook/decorators';
import type { StoreNode as Node, StoreEdge as Edge } from '../types/settings';

// ─── Event components for QuickAddEventButton ────────────────────────────────
// The store needs event/trigger components so the "New event" button renders.
const eventComponents = [
  { plugin: 'content_entity:insert', label: 'Content Insert', type: 'start', componentType: 1 },
  { plugin: 'cron', label: 'Cron Run', type: 'start', componentType: 1 },
  { plugin: 'user:login', label: 'User Login', type: 'start', componentType: 1 },
  { plugin: 'trigger:manual', label: 'Manual Trigger', type: 'start', componentType: 1 },
];



const meta: Meta<typeof Toolbar> = {
  title: 'Components/Toolbar',
  component: Toolbar,
  parameters: {
    layout: 'fullscreen',
  },
  decorators: [
    withReactFlow,
    withStore({ initialState: { components: eventComponents } }),
    (Story: React.ComponentType) => (
      <div style={{ width: '100%', background: '#fff' }}>
        <Story />
      </div>
    ),
  ],
  args: {
    onSave: fn(),
    onOpenMetadata: fn(),
    onToggleMessages: fn(),
    onClearMessages: fn(),
    onClose: fn(),
    onSearchHighlight: fn(),
    onSearchFocus: fn(),
    onAddEvent: fn(),
    onEventPopupOpenChange: fn(),
    onExport: fn(),
    isEventPopupOpen: false,
    isLocked: false,
    isReadOnly: false,
    hasMessages: false,
    messagesVisible: false,
    canExport: true,
    modelName: 'My Workflow',
    hasUnsavedChanges: false,
    settings: {},
  },
  argTypes: {
    isLocked: {
      control: 'boolean',
      description: 'Whether the canvas is in read-only mode',
    },
    isReadOnly: {
      control: 'boolean',
      description: 'Whether all editing is disabled',
    },
    hasUnsavedChanges: {
      control: 'boolean',
      description: 'Shows unsaved indicator in title and enables save button',
    },
    modelName: {
      control: 'text',
      description: 'Name of the workflow model',
    },
  },
};

export default meta;
type Story = StoryObj<typeof Toolbar>;

/**
 * Default toolbar state with inline search bar, Docs link, Save button,
 * kebab menu, and Close button.
 */
export const Default: Story = {};

/**
 * Toolbar with unsaved changes indicator
 */
export const UnsavedChanges: Story = {
  args: {
    hasUnsavedChanges: true,
    modelName: 'Email Notification Workflow',
  },
};

/**
 * Toolbar with messages toggle visible (messages hidden, ready to show)
 */
export const WithMessagesToggleActive: Story = {
  args: {
    hasMessages: true,
    messagesVisible: false,
  },
};

/**
 * Toolbar with messages toggle visible (messages shown, can hide)
 */
export const WithMessagesToggleInactive: Story = {
  args: {
    hasMessages: true,
    messagesVisible: true,
  },
};

// ─── New Event Button stories ────────────────────────────────────────────────

/**
 * Toolbar with the "New event" button visible in the toolbar-left section.
 * The button now appears in light blue instead of alarming orange.
 */
export const WithNewEventButton: Story = {
  args: {
    onAddEvent: fn(),
    isEventPopupOpen: false,
  },
};

/**
 * Toolbar with the "New event" popup open (controlled mode).
 */
export const WithNewEventPopupOpen: Story = {
  args: {
    onAddEvent: fn(),
    isEventPopupOpen: true,
    onEventPopupOpenChange: fn(),
  },
};

/**
 * Toolbar in read-only mode hides the "New event" button.
 * Compare with WithNewEventButton to see the difference.
 */
export const ReadOnlyHidesNewEvent: Story = {
  args: {
    isLocked: true,
    onAddEvent: fn(),
  },
};

// ─── Start Flow Filter stories ───────────────────────────────────────────────

// Nodes with multiple start nodes so the StartFlowFilter renders
const multiStartNodes: Node[] = [
  { id: 'start_1', type: 'start', position: { x: 0, y: 0 }, data: { label: 'Content Created', plugin: 'content:entity_insert' } },
  { id: 'start_2', type: 'start', position: { x: 0, y: 300 }, data: { label: 'User Registered', plugin: 'user:user_insert' } },
  { id: 'start_3', type: 'start', position: { x: 0, y: 600 }, data: { label: 'Order Completed', plugin: 'commerce:order_complete' } },
  { id: 'action_1', type: 'element', position: { x: 200, y: 0 }, data: { label: 'Send Email', plugin: 'mail:send_mail' } },
  { id: 'gateway_1', type: 'gateway', position: { x: 400, y: 0 }, data: { label: 'Check Role' } },
];

const multiStartEdges: Edge[] = [
  { id: 'edge_1', source: 'start_1', target: 'action_1', type: 'default' },
  { id: 'edge_2', source: 'action_1', target: 'gateway_1', type: 'default' },
];

/**
 * Toolbar with Start Flow Filter visible (all flows selected).
 * The filter appears because there are multiple start nodes.
 */
export const WithFlowFilter: Story = {
  decorators: [
    withStore({
      initialState: {
        nodes: multiStartNodes,
        edges: multiStartEdges,
        visibleStartNodeIds: null,
        components: eventComponents,
      },
    }),
  ],
};

/**
 * Toolbar with a very long model name demonstrating title truncation
 * in the center region.
 */
export const LongModelName: Story = {
  args: {
    modelName: 'My Very Long Workflow Model Name That Should Get Truncated With Ellipsis When It Exceeds Available Space',
    hasUnsavedChanges: true,
  },
};

/**
 * Toolbar in read-only mode. Save button and new event button are hidden.
 * Docs, menu (settings/export/dark mode), and close remain visible.
 */
export const ReadOnlyMode: Story = {
  args: {
    isReadOnly: true,
  },
};
