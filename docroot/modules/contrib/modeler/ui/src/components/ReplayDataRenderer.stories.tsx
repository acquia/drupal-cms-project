import type { Meta, StoryObj } from '@storybook/react';
import { ReplayDataRenderer, StepDataContainer } from './ReplayDataRenderer';

const meta: Meta<typeof ReplayDataRenderer> = {
  title: 'Components/ReplayDataRenderer',
  component: ReplayDataRenderer,
  parameters: {
    layout: 'centered',
  },
  decorators: [
    (Story: React.ComponentType) => (
      <div style={{ width: 400, padding: 16, border: '1px solid #e0e0e0', borderRadius: 8, fontFamily: 'monospace', fontSize: 13 }}>
        <Story />
      </div>
    ),
  ],
  args: {
    data: {
      entity: {
        type: 'node',
        bundle: 'article',
        title: 'Test Article',
        status: true,
      },
      user: {
        uid: 1,
        name: 'admin',
        roles: ['authenticated', 'administrator'],
      },
    },
    basePath: '',
  },
};

export default meta;
type Story = StoryObj<typeof ReplayDataRenderer>;

/**
 * Default hierarchical data display
 */
export const Default: Story = {};

/**
 * Flat key-value data
 */
export const FlatData: Story = {
  args: {
    data: {
      name: 'John Doe',
      email: 'john@example.com',
      role: 'administrator',
      active: true,
      loginCount: 42,
    },
  },
};

/**
 * Deeply nested data structure
 */
export const DeeplyNested: Story = {
  args: {
    data: {
      level1: {
        level2: {
          level3: {
            level4: {
              level5: {
                value: 'deep value',
              },
            },
          },
        },
      },
    },
  },
};

/**
 * Array data
 */
export const ArrayData: Story = {
  args: {
    data: {
      items: ['First', 'Second', 'Third', 'Fourth', 'Fifth'],
      tags: ['drupal', 'workflow', 'automation'],
      nested: [
        { id: 1, name: 'Item One' },
        { id: 2, name: 'Item Two' },
      ],
    },
  },
};

/**
 * Empty data
 */
export const EmptyData: Story = {
  args: {
    data: {},
  },
};

/**
 * Primitive value
 */
export const PrimitiveValue: Story = {
  args: {
    data: 'Simple string value',
  },
};

/**
 * StepDataContainer with full step data
 */
export const StepContainer: StoryObj<typeof StepDataContainer> = {
  render: () => (
    <StepDataContainer
      stepData={{
        entity: {
          type: 'node',
          bundle: 'article',
          title: 'Published Article',
          uid: 1,
          status: true,
        },
        event: {
          machine_name: 'content:entity_insert',
          label: 'Content: After inserting a new entity',
        },
      }}
    />
  ),
};

/**
 * StepDataContainer rendering PREDICTED tokens (issue #3577207): each top-level
 * token carries a subtle "Predicted" badge + tooltip, indicating the data was
 * propagated from a replay-covered predecessor and not yet confirmed by a run.
 */
export const StepContainerPredicted: StoryObj<typeof StepDataContainer> = {
  render: () => (
    <StepDataContainer
      predicted
      stepData={{
        entity: {
          label: 'Entity',
          token: '[entity:title]',
          value: 'Published Article',
        },
        event: {
          label: 'Content: After inserting a new entity',
          token: '[event:machine-name]',
          value: 'content:entity_insert',
        },
      }}
    />
  ),
};

/**
 * Token data structure with label/token/value hierarchy
 */
export const TokenDataStructure: Story = {
  args: {
    data: {
      label: 'Entity',
      token: '[entity:title]',
      value: 'Test Article',
      data: {
        title: {
          label: 'Title',
          token: '[entity:title]',
          value: 'Test Article',
        },
        status: {
          label: 'Published',
          token: '[entity:status]',
          value: true,
        },
        author: {
          label: 'Author',
          token: '[entity:author:name]',
          value: 'admin',
          data: {
            name: {
              label: 'Name',
              token: '[entity:author:name]',
              value: 'admin',
            },
          },
        },
      },
    },
    basePath: 'token.',
  },
};

