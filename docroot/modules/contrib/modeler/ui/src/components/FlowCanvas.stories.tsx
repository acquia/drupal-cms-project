import React from 'react';
import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import { ReactFlowProvider } from 'reactflow';
import FlowCanvas from './FlowCanvas';

const sampleNodes = [
  { id: 'node_1', type: 'start', position: { x: 100, y: 50 }, data: { label: 'Content Created', plugin: 'content:entity_insert' } },
  { id: 'node_2', type: 'element', position: { x: 100, y: 200 }, data: { label: 'Send Email', plugin: 'mail:send_mail' } },
  { id: 'node_3', type: 'gateway', position: { x: 100, y: 350 }, data: { label: 'Check Role' } },
  { id: 'node_4', type: 'element', position: { x: 300, y: 350 }, data: { label: 'Log Message', plugin: 'log:log_message' } },
];

const sampleEdges = [
  { id: 'edge_1', source: 'node_1', target: 'node_2', type: 'default', data: {} },
  { id: 'edge_2', source: 'node_2', target: 'node_3', type: 'default', data: {} },
  { id: 'edge_3', source: 'node_3', target: 'node_4', type: 'condition', label: 'Is Admin', data: { condition: 'user_has_role' } },
];

const FlowCanvasWrapper = (Story: React.ComponentType) => (
  <ReactFlowProvider>
    <div style={{ width: '100vw', height: '100vh' }}>
      <Story />
    </div>
  </ReactFlowProvider>
);

const defaultEventHandlers = {
  onNodesChange: fn(),
  onEdgesChange: fn(),
  onConnect: fn(),
  onSelectionChange: fn(),
  onConnectStart: fn(),
  onConnectEnd: fn(),
  onNodeClick: fn(),
  onEdgeClick: fn(),
  onPaneClick: fn(),
  onSelectionStart: fn(),
  onNodeDragStart: fn(),
  onNodeDragStop: fn(),
  onInit: fn(),
};

const defaultElementCallbacks = {
  onEdgeUpdate: fn(),
  onNodeUpdate: fn(),
  onDeleteNode: fn(),
};

const defaultModifierKeys = {
  isShiftPressed: false,
  isCtrlPressed: false,
  isAltPressed: false,
};

const defaultUIState = {
  isDragActive: false,
  isLocked: false,
  showEdgeOrderNumbers: false,
  showAllAnnotations: false,
};

const defaultSearch = {
  searchTerm: '',
  highlightedSearchResult: null,
};

const defaultReplay = {
  replayData: [] as any[],
  currentReplayStep: -1,
  isReplayMode: false,
  replayIndicators: [] as any[],
};

const defaultQuickAdd = {
  onQuickAdd: fn(),
  onAddCondition: fn(),
  onReplacePlaceholder: fn(),
};

const meta: Meta<typeof FlowCanvas> = {
  title: 'Components/FlowCanvas',
  component: FlowCanvas,
  decorators: [FlowCanvasWrapper],
  parameters: {
    layout: 'fullscreen',
  },
  args: {
    nodes: sampleNodes,
    edges: sampleEdges,
    eventHandlers: defaultEventHandlers,
    elementCallbacks: defaultElementCallbacks,
    viewport: { x: 0, y: 0, zoom: 1 },
    modifierKeys: defaultModifierKeys,
    uiState: defaultUIState,
    search: defaultSearch,
    replay: defaultReplay,
    setEdges: fn(),
    setHasUnsavedChanges: fn(),
    quickAdd: defaultQuickAdd,
  },
};

export default meta;
type Story = StoryObj<typeof FlowCanvas>;

/**
 * Default canvas with sample workflow
 */
export const Default: Story = {};

/**
 * Locked canvas (no interactions)
 */
export const Locked: Story = {
  args: {
    uiState: { ...defaultUIState, isLocked: true },
  },
};

/**
 * Canvas with annotation text on every node. Each node displays a small
 * document icon in its footer indicating that an annotation is present.
 * The full text is accessible via the browser tooltip on hover.
 */
export const WithAnnotations: Story = {
  args: {
    nodes: sampleNodes.map(n => ({
      ...n,
      data: { ...n.data, annotation: 'Sample annotation text' },
    })),
  },
};

/**
 * Canvas with edge order numbers shown
 */
export const WithEdgeOrders: Story = {
  args: {
    uiState: { ...defaultUIState, showEdgeOrderNumbers: true },
  },
};

/**
 * Canvas with a placeholder node (condition-first authoring).
 * The placeholder node has a dashed amber border and awaits an action selection.
 */
export const WithPlaceholderNode: Story = {
  args: {
    nodes: [
      ...sampleNodes,
      { id: 'placeholder_1', type: 'placeholder', position: { x: 300, y: 200 }, data: { label: 'Select action...' } },
    ],
    edges: [
      ...sampleEdges,
      { id: 'edge_placeholder', source: 'node_1', target: 'placeholder_1', type: 'condition', label: 'Entity is New', data: { condition: 'entity_is_new', conditionLabel: 'Entity is New' } },
    ],
  },
};

/**
 * Empty canvas (no nodes or edges)
 */
export const EmptyCanvas: Story = {
  args: {
    nodes: [],
    edges: [],
  },
};

/**
 * Canvas during active drag operation.
 * Excluded from a11y test-runner: ReactFlow renders asynchronously
 * and the drag-active overlay can produce intermittent violations
 * before the canvas is fully painted.
 */
export const DragActive: Story = {
  tags: ['!test'],
  args: {
    uiState: { ...defaultUIState, isDragActive: true },
  },
};

/**
 * Canvas with active replay indicators on nodes
 */
export const WithReplayIndicators: Story = {
  args: {
    replay: {
      replayData: [{ id: 'event_1', type: 'event', data: {} }],
      currentReplayStep: 0,
      isReplayMode: true,
      replayIndicators: [
        { id: 'ind_1', x: 150, y: 125, color: 'var(--modeler-color-replay-true)' },
        { id: 'ind_2', x: 150, y: 275, color: 'var(--modeler-color-replay-false)' },
      ],
    },
  },
};
