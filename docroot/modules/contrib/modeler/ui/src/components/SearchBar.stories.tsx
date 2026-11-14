import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import SearchBar from './SearchBar';
import { withStore } from '../../.storybook/decorators';
import type { StoreNode as Node, StoreEdge as Edge } from '../types/settings';

// Sample nodes for stories
const sampleNodes: Node[] = [
  {
    id: 'node_1',
    type: 'start',
    position: { x: 0, y: 0 },
    data: { label: 'Content Created', plugin: 'eca_content:entity_insert' },
  },
  {
    id: 'node_2',
    type: 'element',
    position: { x: 200, y: 0 },
    data: { label: 'Send Email Notification', plugin: 'eca_mail:send_mail' },
  },
  {
    id: 'node_3',
    type: 'gateway',
    position: { x: 400, y: 0 },
    data: { label: 'Check User Role', plugin: 'eca_user:user_has_role' },
  },
  {
    id: 'node_4',
    type: 'element',
    position: { x: 600, y: -100 },
    data: { label: 'Log Admin Action', plugin: 'eca_log:log_message' },
  },
  {
    id: 'node_5',
    type: 'element',
    position: { x: 600, y: 100 },
    data: { label: 'Update Status Field', plugin: 'eca_content:set_field_value' },
  },
];

// Sample edges for stories
const sampleEdges: Edge[] = [
  { id: 'edge_1', source: 'node_1', target: 'node_2', type: 'default' },
  { id: 'edge_2', source: 'node_2', target: 'node_3', type: 'default' },
  {
    id: 'edge_3',
    source: 'node_3',
    target: 'node_4',
    type: 'condition',
    label: 'Is Admin',
    data: { condition: 'user_has_role', conditionLabel: 'Is Admin' },
  },
  {
    id: 'edge_4',
    source: 'node_3',
    target: 'node_5',
    type: 'condition',
    label: 'Is Editor',
    data: { condition: 'user_has_role', conditionLabel: 'Is Editor' },
  },
];

const meta: Meta<typeof SearchBar> = {
  title: 'Components/SearchBar',
  component: SearchBar,
  parameters: {
    layout: 'centered',
  },
  args: {
    onHighlight: fn(),
    onFocus: fn(),
  },
  decorators: [
    withStore({ initialState: { nodes: sampleNodes, edges: sampleEdges } }),
    (Story: React.ComponentType) => (
      <div style={{ width: '400px', padding: '20px' }}>
        <Story />
      </div>
    ),
  ],
};

export default meta;
type Story = StoryObj<typeof SearchBar>;

/**
 * Default search bar with sample workflow data
 */
export const Default: Story = {};

/**
 * Search bar with no data (empty workflow)
 */
export const EmptyWorkflow: Story = {
  decorators: [
    withStore({ initialState: { nodes: [], edges: [] } }),
  ],
};

/**
 * Search bar with only nodes (no edges)
 */
export const NodesOnly: Story = {
  decorators: [
    withStore({ initialState: { nodes: sampleNodes, edges: [] } }),
  ],
};

/**
 * Search bar with many items for testing scroll behavior
 */
export const ManyItems: Story = {
  decorators: [
    withStore({
      initialState: {
        nodes: [
          ...sampleNodes,
          { id: 'node_6', type: 'element', position: { x: 0, y: 200 }, data: { label: 'Send SMS', plugin: 'eca_sms:send' } },
          { id: 'node_7', type: 'element', position: { x: 0, y: 300 }, data: { label: 'Send Push', plugin: 'eca_push:send' } },
          { id: 'node_8', type: 'gateway', position: { x: 0, y: 400 }, data: { label: 'Check Status', plugin: 'eca_check' } },
          { id: 'node_9', type: 'element', position: { x: 0, y: 500 }, data: { label: 'Archive Content', plugin: 'eca_archive' } },
          { id: 'node_10', type: 'element', position: { x: 0, y: 600 }, data: { label: 'Delete Draft', plugin: 'eca_delete' } },
        ],
        edges: sampleEdges,
      },
    }),
  ],
};
