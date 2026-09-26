import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import ReplayPanel from './ReplayPanel';
import { withStore } from '../../.storybook/decorators';
import type { ReplayEntry } from '../hooks/useReplayLoader';

const sampleReplayData = [
  { id: 'event_1', type: 'event', data: { label: 'Content Created' } },
  { id: 'action_1', type: 'action', data: { label: 'Send Email' }, successorId: 'node_2' },
  { id: 'condition_1', type: 'condition', data: { label: 'Check Role' }, conditionId: 'edge_1' },
  { id: 'action_2', type: 'action', data: { label: 'Log Message' }, successorId: 'node_3' },
  { id: 'action_3', type: 'action', data: { label: 'Update Status' }, successorId: 'node_4' },
];

const sampleReplayEntries: ReplayEntry[] = [
  {
    model_id: 'model_abc123',
    component_id: 'event_1',
    history: sampleReplayData,
    timestamp: '2026-02-09T10:30:00Z',
    user: { name: 'admin', uid: 1 },
    ip: '192.168.1.100',
    url: '/node/42/edit',
  },
  {
    model_id: 'model_abc123',
    component_id: 'event_1',
    history: [
      { id: 'event_1', type: 'event', data: { label: 'Content Created' } },
      { id: 'action_1', type: 'action', data: { label: 'Send Email' }, successorId: 'node_2' },
    ],
    timestamp: '2026-02-08T14:15:30Z',
    user: { name: 'editor', uid: 5 },
    ip: '10.0.0.42',
    url: '/admin/content',
  },
  {
    model_id: 'model_abc123',
    component_id: 'event_1',
    history: [
      { id: 'event_1', type: 'event', data: { label: 'Content Created' } },
    ],
    timestamp: '2026-02-07T09:00:00Z',
    user: 'anonymous',
    ip: '203.0.113.50',
    url: '/contact',
  },
];

const sampleNodes = [
  { id: 'node_1', type: 'start', position: { x: 0, y: 0 }, data: { label: 'Content Created' } },
  { id: 'node_2', type: 'element', position: { x: 200, y: 0 }, data: { label: 'Send Email' } },
  { id: 'node_3', type: 'element', position: { x: 400, y: 0 }, data: { label: 'Log Message' } },
];

const sampleEdges = [
  { id: 'edge_1', source: 'node_1', target: 'node_2', type: 'default' },
  { id: 'edge_2', source: 'node_2', target: 'node_3', type: 'condition', label: 'Is Admin' },
];

const meta: Meta<typeof ReplayPanel> = {
  title: 'Components/ReplayPanel',
  component: ReplayPanel,
  decorators: [
    withStore({
      initialState: {
        nodes: sampleNodes,
        edges: sampleEdges,
      },
    }),
    (Story: React.ComponentType) => (
      <div style={{ width: 350, height: 600, border: '1px solid #e0e0e0', borderRadius: 8 }}>
        <Story />
      </div>
    ),
  ],
  parameters: {
    layout: 'centered',
  },
  args: {
    replayData: sampleReplayData,
    isReplayMode: true,
    onToggleReplay: fn(),
    onSelectStep: fn(),
    currentStep: 0,
    stepData: { entity: { title: 'Test Article', type: 'node' } },
    stepInfo: { type: 'event', id: 'event_1' },
    edges: sampleEdges,
    nodes: sampleNodes,
    isVisible: true,
  },
  argTypes: {
    isReplayMode: {
      control: 'boolean',
      description: 'Whether replay mode is active',
    },
    currentStep: {
      control: { type: 'number', min: -1, max: 4 },
      description: 'Currently selected replay step index',
    },
    isVisible: {
      control: 'boolean',
      description: 'Whether the panel is visible',
    },
    hasReplayUrl: {
      control: 'boolean',
      description: 'Whether a replay_url endpoint is available',
    },
    hasTestUrl: {
      control: 'boolean',
      description: 'Whether a test_url endpoint is available',
    },
    isTestRunning: {
      control: 'boolean',
      description: 'Whether a test is currently running (polling for results)',
    },
    isTestInitiating: {
      control: 'boolean',
      description: 'Whether the initial test request is in flight',
    },
  },
};

export default meta;
type Story = StoryObj<typeof ReplayPanel>;

/**
 * Default replay panel with step list
 */
export const Default: Story = {};

/**
 * Panel with a specific step selected
 */
export const StepSelected: Story = {
  args: {
    currentStep: 2,
    stepInfo: { type: 'condition', id: 'condition_1', conditionId: 'edge_1' },
  },
};

/**
 * Panel not visible (collapsed)
 */
export const Hidden: Story = {
  args: {
    isVisible: false,
  },
};

/**
 * No replay data available
 */
export const NoData: Story = {
  args: {
    replayData: null,
    isReplayMode: false,
  },
};

/**
 * Step with exception data
 */
export const WithException: Story = {
  args: {
    currentStep: 1,
    stepInfo: {
      type: 'action',
      id: 'action_1',
      exception: { message: 'Mail sending failed: SMTP connection refused.' },
    },
  },
};

/**
 * Replay panel with loaded execution entries and the first entry selected
 */
export const WithReplayEntries: Story = {
  args: {
    replayEntries: sampleReplayEntries,
    selectedEntryIndex: 0,
    onSelectReplayEntry: fn(),
  },
};

/**
 * Replay panel with loaded entries but none selected yet
 */
export const WithReplayEntriesNoneSelected: Story = {
  args: {
    replayEntries: sampleReplayEntries,
    selectedEntryIndex: -1,
    onSelectReplayEntry: fn(),
    replayData: null,
    currentStep: -1,
    stepData: null,
    stepInfo: null,
  },
};

/**
 * Replay panel with a single loaded entry
 */
export const WithSingleReplayEntry: Story = {
  args: {
    replayEntries: [sampleReplayEntries[0]],
    selectedEntryIndex: 0,
    onSelectReplayEntry: fn(),
  },
};

/**
 * Empty state with replay URL available (shows "select an event" hint)
 */
export const EmptyWithReplayUrl: Story = {
  args: {
    replayData: null,
    isReplayMode: false,
    currentStep: -1,
    stepData: null,
    stepInfo: null,
    hasReplayUrl: true,
    hasTestUrl: false,
  },
};

/**
 * Test button visible (test URL available, event node selected)
 */
export const TestButtonVisible: Story = {
  args: {
    replayData: null,
    isReplayMode: false,
    currentStep: -1,
    stepData: null,
    stepInfo: null,
    selectedStartNodeId: 'node_1',
    hasTestUrl: true,
    hasReplayUrl: false,
    onStartTest: fn(),
  },
};

/**
 * Test button alongside existing replay data
 */
export const TestButtonWithReplayData: Story = {
  args: {
    selectedStartNodeId: 'node_1',
    hasTestUrl: true,
    hasReplayUrl: true,
    onStartTest: fn(),
  },
};

/**
 * Test is being initiated (spinner with "Starting test..." text)
 */
export const TestInitiating: Story = {
  args: {
    replayData: null,
    isReplayMode: false,
    currentStep: -1,
    stepData: null,
    stepInfo: null,
    isTestInitiating: true,
    isTestRunning: false,
    selectedStartNodeId: 'node_1',
    hasTestUrl: true,
  },
};

/**
 * Test is running (spinner with "Waiting for test execution..." and Cancel button)
 */
export const TestRunning: Story = {
  args: {
    replayData: null,
    isReplayMode: false,
    currentStep: -1,
    stepData: null,
    stepInfo: null,
    isTestRunning: true,
    isTestInitiating: false,
    selectedStartNodeId: 'node_1',
    hasTestUrl: true,
    onCancelTest: fn(),
  },
};

/**
 * Test failed with error message
 */
export const TestError: Story = {
  args: {
    replayData: null,
    isReplayMode: false,
    currentStep: -1,
    stepData: null,
    stepInfo: null,
    selectedStartNodeId: 'node_1',
    hasTestUrl: true,
    testError: 'Test execution timed out after 30 seconds.',
    onStartTest: fn(),
  },
};

/**
 * A selected step whose data renders INLINE underneath its row in the single
 * step list (issue project/modeler#3589103).
 */
export const StepSelectedWithInlineData: Story = {
  args: {
    replayEntries: sampleReplayEntries,
    selectedEntryIndex: 0,
    onSelectReplayEntry: fn(),
    currentStep: 1,
    stepInfo: { type: 'action', id: 'action_1' },
    stepData: {
      entity: {
        label: 'Entity',
        token: '[node]',
        data: {
          title: { label: 'Title', token: '[node:title]', value: 'Test Article' },
          type: { label: 'Type', token: '[node:type]', value: 'article' },
        },
      },
    },
  },
};

/**
 * A selected step that carries no token data: the inline block shows the
 * "no token data" notice instead of a token tree.
 */
export const StepSelectedWithoutData: Story = {
  args: {
    replayEntries: sampleReplayEntries,
    selectedEntryIndex: 0,
    onSelectReplayEntry: fn(),
    currentStep: 2,
    stepData: null,
    stepInfo: null,
  },
};
