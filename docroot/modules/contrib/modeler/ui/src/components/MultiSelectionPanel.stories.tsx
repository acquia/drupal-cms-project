import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import MultiSelectionPanel from './MultiSelectionPanel';

const sampleNodes = [
  {
    id: 'node_1',
    type: 'element',
    position: { x: 100, y: 100 },
    data: { label: 'Send Email', plugin: 'eca_mail:send_mail', locked: false },
  },
  {
    id: 'node_2',
    type: 'gateway',
    position: { x: 300, y: 100 },
    data: { label: 'Check Role', locked: false },
  },
  {
    id: 'node_3',
    type: 'start',
    position: { x: 0, y: 0 },
    data: { label: 'Content Created', plugin: 'eca_content:entity_insert', locked: true },
  },
];

const sampleEdges = [
  {
    id: 'edge_1',
    source: 'node_1',
    target: 'node_2',
    type: 'default',
    data: { locked: false },
  },
  {
    id: 'edge_2',
    source: 'node_2',
    target: 'node_3',
    type: 'condition',
    label: 'Is Admin',
    data: { condition: 'user_has_role', locked: false },
  },
];

const meta: Meta<typeof MultiSelectionPanel> = {
  title: 'Components/MultiSelectionPanel',
  component: MultiSelectionPanel,
  parameters: {
    layout: 'centered',
  },
  decorators: [
    (Story: React.ComponentType) => (
      <div style={{ width: 320, padding: 16, border: '1px solid #e0e0e0', borderRadius: 8 }}>
        <Story />
      </div>
    ),
  ],
  args: {
    selectedNodes: sampleNodes,
    selectedEdges: sampleEdges,
    onDeleteSelected: fn(),
    isLocked: false,
  },
  argTypes: {
    isLocked: {
      control: 'boolean',
      description: 'Whether the selection is locked',
    },
  },
};

export default meta;
type Story = StoryObj<typeof MultiSelectionPanel>;

/**
 * Multiple nodes and edges selected
 */
export const Default: Story = {};

/**
 * Only nodes selected
 */
export const NodesOnly: Story = {
  args: {
    selectedEdges: [],
  },
};

/**
 * Only edges selected
 */
export const EdgesOnly: Story = {
  args: {
    selectedNodes: [],
  },
};

/**
 * Selection in locked mode
 */
export const Locked: Story = {
  args: {
    isLocked: true,
  },
};

/**
 * Single node and single edge
 */
export const MinimalSelection: Story = {
  args: {
    selectedNodes: [sampleNodes[0]],
    selectedEdges: [sampleEdges[0]],
  },
};
