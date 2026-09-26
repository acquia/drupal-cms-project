import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import EdgeOrderBadge from './EdgeOrderBadge';

const meta: Meta<typeof EdgeOrderBadge> = {
  title: 'Components/Edges/EdgeOrderBadge',
  component: EdgeOrderBadge,
  parameters: {
    layout: 'centered',
  },
  args: {
    edgeId: 'edge_1',
    isLocked: false,
    onReorderEdge: fn(),
    edgeOrderInfo: {
      pathX: 0,
      pathY: 0,
      order: 1,
      totalEdges: 3,
      sourceNodeId: 'node_1',
    },
  },
  argTypes: {
    isLocked: {
      control: 'boolean',
      description: 'Whether the canvas is in read-only mode (disables drag reordering)',
    },
    'edgeOrderInfo.order': {
      control: { type: 'number', min: 1, max: 10 },
      description: 'Current order position of this edge',
    },
    'edgeOrderInfo.totalEdges': {
      control: { type: 'number', min: 1, max: 10 },
      description: 'Total number of edges from the source node',
    },
  },
};

export default meta;
type Story = StoryObj<typeof EdgeOrderBadge>;

/**
 * Default edge order badge showing position 1 of 3
 */
export const Default: Story = {};

/**
 * Badge showing last position
 */
export const LastPosition: Story = {
  args: {
    edgeOrderInfo: {
      pathX: 0,
      pathY: 0,
      order: 3,
      totalEdges: 3,
      sourceNodeId: 'node_1',
    },
  },
};

/**
 * Locked badge (not draggable)
 */
export const Locked: Story = {
  args: {
    isLocked: true,
  },
};

/**
 * Single edge (no reorder needed)
 */
export const SingleEdge: Story = {
  args: {
    edgeOrderInfo: {
      pathX: 0,
      pathY: 0,
      order: 1,
      totalEdges: 1,
      sourceNodeId: 'node_1',
    },
  },
};

/**
 * Many edges from one source
 */
export const ManyEdges: Story = {
  args: {
    edgeOrderInfo: {
      pathX: 0,
      pathY: 0,
      order: 5,
      totalEdges: 8,
      sourceNodeId: 'node_1',
    },
  },
};
