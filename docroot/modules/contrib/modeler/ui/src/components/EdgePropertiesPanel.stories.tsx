import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import EdgePropertiesPanel from './EdgePropertiesPanel';

const sampleEdge = {
  id: 'edge_1',
  source: 'node_1',
  target: 'node_2',
  type: 'default',
  data: {
    locked: false,
    annotation: 'Routes to the approval step.',
    isAnnotationVisible: false,
  },
};

const mockAnnotationField = {
  value: 'Routes to the approval step.',
  onChange: fn(),
  flush: fn(),
};

const meta: Meta<typeof EdgePropertiesPanel> = {
  title: 'Components/EdgePropertiesPanel',
  component: EdgePropertiesPanel,
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
    edge: sampleEdge,
    onEdgeUpdate: fn(),
    isLocked: false,
    edgeAnnotationField: mockAnnotationField as any,
  },
  argTypes: {
    isLocked: {
      control: 'boolean',
      description: 'Whether the annotation field is locked',
    },
  },
};

export default meta;
type Story = StoryObj<typeof EdgePropertiesPanel>;

/**
 * Plain connection with an annotation. Conditions are authored as condition
 * nodes and edited through the generic node panel, so this panel only edits
 * a connection's annotation.
 */
export const Annotation: Story = {};

/**
 * Locked panel (annotation field disabled)
 */
export const Locked: Story = {
  args: {
    isLocked: true,
  },
};

/**
 * Connection without an annotation yet (empty field)
 */
export const EmptyAnnotation: Story = {
  args: {
    edge: {
      id: 'edge_2',
      source: 'node_1',
      target: 'node_2',
      type: 'default',
      data: { locked: false },
    },
    edgeAnnotationField: {
      value: '',
      onChange: fn(),
      flush: fn(),
    } as any,
  },
};
