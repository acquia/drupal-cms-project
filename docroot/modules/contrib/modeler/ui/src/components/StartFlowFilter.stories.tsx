import type { Meta, StoryObj } from '@storybook/react';
import StartFlowFilter from './StartFlowFilter';
import { withStore } from '../../.storybook/decorators';
import type { StoreNode as Node } from '../types/settings';

// ─── Sample data ─────────────────────────────────────────────────────────────

/** Two start nodes — minimum required for the filter to render. */
const twoStartNodes: Node[] = [
  {
    id: 'start_1',
    type: 'start',
    position: { x: 0, y: 0 },
    data: { label: 'Content Created', plugin: 'content:entity_insert' },
  },
  {
    id: 'start_2',
    type: 'start',
    position: { x: 0, y: 300 },
    data: { label: 'User Registered', plugin: 'user:user_insert' },
  },
];

/** Four start nodes for a busier workflow. */
const manyStartNodes: Node[] = [
  ...twoStartNodes,
  {
    id: 'start_3',
    type: 'start',
    position: { x: 0, y: 600 },
    data: { label: 'Order Completed', plugin: 'commerce:order_complete' },
  },
  {
    id: 'start_4',
    type: 'start',
    position: { x: 0, y: 900 },
    data: { label: 'Cron Triggered', plugin: 'base:cron' },
  },
];

/** Mix of start and non-start nodes (realistic workflow). */
const mixedNodes: Node[] = [
  ...twoStartNodes,
  {
    id: 'action_1',
    type: 'element',
    position: { x: 200, y: 0 },
    data: { label: 'Send Email', plugin: 'mail:send_mail' },
  },
  {
    id: 'gateway_1',
    type: 'gateway',
    position: { x: 400, y: 0 },
    data: { label: 'Check Role' },
  },
];

/** Single start node — filter should NOT render. */
const singleStartNode: Node[] = [
  {
    id: 'start_1',
    type: 'start',
    position: { x: 0, y: 0 },
    data: { label: 'Content Created', plugin: 'content:entity_insert' },
  },
];

/** Start nodes with long labels. */
const longLabelNodes: Node[] = [
  {
    id: 'start_1',
    type: 'start',
    position: { x: 0, y: 0 },
    data: { label: 'Entity Insert: Content Type Article (Published)', plugin: 'content:entity_insert' },
  },
  {
    id: 'start_2',
    type: 'start',
    position: { x: 0, y: 300 },
    data: { label: 'Commerce Order State Transition: Fulfillment to Completed', plugin: 'commerce:order_transition' },
  },
  {
    id: 'start_3',
    type: 'start',
    position: { x: 0, y: 600 },
    data: { label: 'Scheduled Task: Daily Content Audit and Cleanup Process', plugin: 'base:scheduled' },
  },
];

// ─── Meta ────────────────────────────────────────────────────────────────────

const meta: Meta<typeof StartFlowFilter> = {
  title: 'Components/StartFlowFilter',
  component: StartFlowFilter,
  parameters: {
    layout: 'centered',
  },
  decorators: [
    withStore({ initialState: { nodes: mixedNodes, visibleStartNodeIds: null } }),
    (Story: React.ComponentType) => (
      <div style={{ width: '320px', padding: '20px', position: 'relative' }}>
        <Story />
      </div>
    ),
  ],
};

export default meta;
type Story = StoryObj<typeof StartFlowFilter>;

// ─── Stories ─────────────────────────────────────────────────────────────────

/**
 * Default state — "All Flows" selected with two start nodes and additional
 * action/gateway nodes.  The filter button displays "All Flows".
 */
export const Default: Story = {};

/**
 * With exactly two start nodes (minimum for the filter to appear).
 * Demonstrates the simplest case where filtering is available.
 */
export const TwoStartNodes: Story = {
  decorators: [
    withStore({ initialState: { nodes: twoStartNodes, visibleStartNodeIds: null } }),
  ],
};

/**
 * With four start nodes — a more complex workflow with many entry points.
 */
export const ManyStartNodes: Story = {
  decorators: [
    withStore({ initialState: { nodes: manyStartNodes, visibleStartNodeIds: null } }),
  ],
};

/**
 * With a single start node selected.  The button label displays the
 * selected node's name instead of a count.
 */
export const SingleNodeSelected: Story = {
  decorators: [
    withStore({ initialState: { nodes: manyStartNodes, visibleStartNodeIds: ['start_1'] } }),
  ],
};

/**
 * With two of four start nodes selected.  The button label shows "2 Flows".
 */
export const MultipleNodesSelected: Story = {
  decorators: [
    withStore({ initialState: { nodes: manyStartNodes, visibleStartNodeIds: ['start_1', 'start_3'] } }),
  ],
};

/**
 * Only one start node in the workflow — the filter does NOT render.
 * This story intentionally renders nothing to verify the component
 * correctly hides itself when filtering is not applicable.
 */
export const SingleStartNodeHidden: Story = {
  decorators: [
    withStore({ initialState: { nodes: singleStartNode, visibleStartNodeIds: null } }),
  ],
};

/**
 * No start nodes at all — the filter does NOT render.
 */
export const NoStartNodes: Story = {
  decorators: [
    withStore({ initialState: { nodes: [], visibleStartNodeIds: null } }),
  ],
};

/**
 * Start nodes with long labels — tests text overflow and truncation
 * behavior in the dropdown and button.
 */
export const LongLabels: Story = {
  decorators: [
    withStore({ initialState: { nodes: longLabelNodes, visibleStartNodeIds: null } }),
  ],
};

/**
 * Long label with a single node selected — the button label shows the
 * full (potentially truncated) node name.
 */
export const LongLabelSelected: Story = {
  decorators: [
    withStore({ initialState: { nodes: longLabelNodes, visibleStartNodeIds: ['start_2'] } }),
  ],
};
