import React from 'react';
import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import { ReactFlowProvider } from 'reactflow';
import Flow from './Flow';
import { withStore } from '../../.storybook/decorators';

const sampleNodes = [
  { id: 'node_1', type: 'start', position: { x: 100, y: 50 }, data: { label: 'Content Created', plugin: 'content:entity_insert' } },
  { id: 'node_2', type: 'element', position: { x: 100, y: 200 }, data: { label: 'Send Email', plugin: 'mail:send_mail' } },
  { id: 'node_3', type: 'gateway', position: { x: 100, y: 350 }, data: { label: 'Check Role' } },
];

const sampleEdges = [
  { id: 'edge_1', source: 'node_1', target: 'node_2', type: 'default' },
  { id: 'edge_2', source: 'node_2', target: 'node_3', type: 'default' },
];

const sampleComponents = {
  Events: [
    { label: 'Content Created', plugin: 'content:entity_insert', componentType: 1, type: 'start', provider: 'content', documentationUrl: null },
    { label: 'User Login', plugin: 'user:user_login', componentType: 1, type: 'start', provider: 'user', documentationUrl: null },
  ],
  Actions: [
    { label: 'Send Email', plugin: 'mail:send_mail', componentType: 4, type: 'element', provider: 'mail', documentationUrl: null },
    { label: 'Set Field Value', plugin: 'content:set_field_value', componentType: 4, type: 'element', provider: 'content', documentationUrl: null },
  ],
  Conditions: [
    { label: 'User Has Role', plugin: 'user:user_has_role', componentType: 5, type: 'link', provider: 'user', documentationUrl: null },
  ],
};

const mockDrupal = {
  ajax: fn(),
  t: (text: string) => text,
};

/**
 * Wrapper providing ReactFlowProvider context.
 */
const FlowWrapper = (Story: React.ComponentType) => (
  <ReactFlowProvider>
    <div style={{ width: '100vw', height: '100vh' }}>
      <Story />
    </div>
  </ReactFlowProvider>
);

const meta: Meta<typeof Flow> = {
  title: 'Components/Flow',
  component: Flow,
  decorators: [
    withStore({
      initialState: {
        nodes: sampleNodes,
        edges: sampleEdges,
      },
    }),
    FlowWrapper,
  ],
  parameters: {
    layout: 'fullscreen',
  },
  args: {
    settings: {
      modeler: {
        stayInContextOnClose: false,
        modelId: 'sample_workflow',
      },
      modeler_api: {
        isNew: false,
        token_url: '',
        save_url: '',
        config_url: '',
        collection_url: '',
        metadata: {
          label: 'Sample Workflow',
          version: '1.0.0',
          executable: true,
        },
      },
      ownerComponents: sampleComponents,
    },
    drupal: mockDrupal as any,
  },
};

export default meta;
type Story = StoryObj<typeof Flow>;

/**
 * Full modeler application with sample workflow
 */
export const Default: Story = {};

/**
 * New empty model
 */
export const NewModel: Story = {
  decorators: [
    withStore({
      initialState: {
        nodes: [],
        edges: [],
      },
    }),
    FlowWrapper,
  ],
  args: {
    settings: {
      modeler: {
        modelId: 'new_workflow',
      },
      modeler_api: {
        isNew: true,
        metadata: {
          label: '',
          version: '1.0.0',
        },
      },
      ownerComponents: sampleComponents,
    },
    drupal: mockDrupal as any,
  },
};

/**
 * Workflow containing a placeholder node (condition-first authoring).
 * The placeholder node blocks saving until an action or gateway is assigned.
 */
export const WithPlaceholderNode: Story = {
  decorators: [
    withStore({
      initialState: {
        nodes: [
          ...sampleNodes,
          { id: 'placeholder_1', type: 'placeholder', position: { x: 300, y: 200 }, data: { label: 'Select action...' } },
        ],
        edges: [
          ...sampleEdges,
          { id: 'edge_cond', source: 'node_1', target: 'placeholder_1', type: 'condition', label: 'User Has Role', data: { condition: 'user:user_has_role', conditionLabel: 'User Has Role' } },
        ],
      },
    }),
    FlowWrapper,
  ],
};

/**
 * Modeler with minimize button enabled
 */
export const WithMinimize: Story = {
  args: {
    settings: {
      modeler: {
        stayInContextOnClose: true,
        modelId: 'sample_workflow',
      },
      modeler_api: {
        isNew: false,
        metadata: {
          label: 'Minimizable Workflow',
          version: '1.0.0',
          executable: true,
        },
      },
      ownerComponents: sampleComponents,
    },
    drupal: mockDrupal as any,
  },
};

/**
 * Modeler with context selector dropdown.
 * Each context declares the plugins it allows. The dropdown appears in the
 * CanvasToolbar and filters available components in quick-add popups.
 */
export const WithContexts: Story = {
  args: {
    settings: {
      modeler: {
        stayInContextOnClose: false,
        modelId: 'sample_workflow',
      },
      modeler_api: {
        isNew: false,
        contexts: [
          {
            id: 'ctx_1',
            topic: 'Content Publishing',
            model_owner: 'example_owner',
            components: {
              start: { plugins: ['content:entity_insert'] },
              element: { plugins: ['mail:send_mail', 'content:set_field_value'] },
              link: { plugins: ['user:user_has_role'] },
            },
          },
          {
            id: 'ctx_2',
            topic: 'User Registration',
            model_owner: 'example_owner',
            components: {
              start: { plugins: ['user:user_login'] },
              element: { plugins: ['mail:send_mail'] },
            },
          },
        ],
        token_url: '',
        save_url: '',
        config_url: '',
        collection_url: '',
        metadata: {
          label: 'Workflow with Contexts',
          version: '1.0.0',
          executable: true,
        },
      },
      ownerComponents: sampleComponents,
    },
    drupal: mockDrupal as any,
  },
};
