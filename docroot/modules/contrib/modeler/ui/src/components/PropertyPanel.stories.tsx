import React from 'react';
import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import PropertyPanel from './PropertyPanel';
import { withStore } from '../../.storybook/decorators';
import { usePanelStore } from '../store/usePanelStore';

/** Sample tokens for the in-field "[" token picker (Drupal shape). */
const sampleGlobalTokens = {
  '[site:name]': { name: 'Site name', 'raw token': '[site:name]', token: 'name', value: 'My Site' },
  '[current-user]': {
    name: 'Current user',
    'raw token': '[current-user]',
    token: 'current-user',
    children: {
      'display-name': { name: 'User name', 'raw token': '[current-user:display-name]', token: 'display-name' },
    },
  },
} as Record<string, any>;

/** Settings for a SAVED model that has replay + test capability. */
const reviewCapableSettings = {
  modeler_api: {
    isNew: false,
    replay_url: '/api/modeler/replay',
    test_url: '/api/modeler/test',
    config_url: '/api/modeler/config',
    token_url: '/api/modeler/token',
    permissions: ['replay', 'test'],
  },
  modeler: { modelId: 'model_abc123' },
} as Record<string, any>;

/** Force the persisted panel mode for a story (real store is used in SB). */
const withPanelMode = (mode: 'event' | 'review') => (Story: React.ComponentType) => {
  usePanelStore.getState().setPanelMode(mode);
  return <Story />;
};

const sampleNode = {
  id: 'node_1',
  type: 'element',
  position: { x: 200, y: 100 },
  data: {
    label: 'Send Email',
    plugin: 'eca_mail:send_mail',
    locked: false,
    annotation: '',
    isAnnotationVisible: false,
  },
};

const sampleEventNode = {
  id: 'event_1',
  type: 'start',
  position: { x: 0, y: 0 },
  data: {
    label: 'Content Created',
    plugin: 'content_entity:insert',
    locked: false,
    annotation: 'Triggers when a new content entity is created',
    isAnnotationVisible: false,
  },
};

const sampleEdge = {
  id: 'edge_1',
  source: 'node_1',
  target: 'node_2',
  type: 'condition',
  label: 'Is Admin',
  data: {
    condition: 'user_has_role',
    conditionLabel: 'Is Admin',
    locked: false,
    annotation: '',
    isAnnotationVisible: false,
  },
};

const meta: Meta<typeof PropertyPanel> = {
  title: 'Components/PropertyPanel',
  component: PropertyPanel,
  decorators: [
    withStore({
      initialState: {
        nodes: [sampleNode],
        edges: [sampleEdge],
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
    node: sampleNode,
    edge: null,
    selectedNodes: [],
    selectedEdges: [],
    onConfigurationChange: fn(),
    onNodeUpdate: fn(),
    onEdgeUpdate: fn(),
    isLocked: false,
    settings: {},
    isReplayMode: false,
  },
  argTypes: {
    isLocked: {
      control: 'boolean',
      description: 'Whether editing is locked',
    },
    isReplayMode: {
      control: 'boolean',
      description: 'Whether replay mode is active',
    },
  },
};

export default meta;
type Story = StoryObj<typeof PropertyPanel>;

/**
 * Property panel with a selected node
 */
export const WithNode: Story = {};

/**
 * Property panel with a selected edge
 */
export const WithEdge: Story = {
  tags: ['!test'],
  args: {
    node: null,
    edge: sampleEdge,
  },
};

/**
 * Property panel with no selection
 */
export const NoSelection: Story = {
  args: {
    node: null,
    edge: null,
  },
};

/**
 * Property panel in locked mode
 */
export const Locked: Story = {
  tags: ['!test'],
  args: {
    isLocked: true,
  },
};

/**
 * Property panel in replay mode
 */
export const ReplayMode: Story = {
  tags: ['!test'],
  args: {
    isReplayMode: true,
  },
};

/**
 * Multi-selection mode
 */
export const MultiSelection: Story = {
  args: {
    node: null,
    edge: null,
    selectedNodes: [
      sampleNode,
      { id: 'node_2', type: 'element', position: { x: 400, y: 100 }, data: { label: 'Log Message' } },
    ],
    selectedEdges: [sampleEdge],
    onDeleteSelected: fn(),
  },
};

/**
 * Event node with replay URL configured — shows the replay load button in header
 */
export const EventNodeWithReplayUrl: Story = {
  decorators: [
    withStore({
      initialState: {
        nodes: [sampleEventNode],
        edges: [],
      },
    }),
    (Story: React.ComponentType) => (
      <div style={{ width: 350, height: 600, border: '1px solid #e0e0e0', borderRadius: 8 }}>
        <Story />
      </div>
    ),
  ],
  args: {
    node: sampleEventNode,
    edge: null,
    selectedNodes: [],
    selectedEdges: [],
    settings: {
      modeler_api: {
        replay_url: '/api/modeler/replay',
        config_url: '/api/modeler/config',
        token_url: '/api/modeler/token',
      },
      modeler: {
        modelId: 'model_abc123',
      },
    },
    onReplayEntriesLoaded: fn(),
  },
};

/**
 * Event node without replay URL — no replay button shown
 */
export const EventNodeWithoutReplayUrl: Story = {
  decorators: [
    withStore({
      initialState: {
        nodes: [sampleEventNode],
        edges: [],
      },
    }),
    (Story: React.ComponentType) => (
      <div style={{ width: 350, height: 600, border: '1px solid #e0e0e0', borderRadius: 8 }}>
        <Story />
      </div>
    ),
  ],
  args: {
    node: sampleEventNode,
    edge: null,
    selectedNodes: [],
    selectedEdges: [],
    settings: {
      modeler_api: {
        config_url: '/api/modeler/config',
        token_url: '/api/modeler/token',
      },
    },
  },
};

/**
 * Non-event node with replay URL — replay button should NOT appear (only for event nodes)
 */
export const ActionNodeWithReplayUrl: Story = {
  args: {
    settings: {
      modeler_api: {
        replay_url: '/api/modeler/replay',
        config_url: '/api/modeler/config',
        token_url: '/api/modeler/token',
      },
      modeler: {
        modelId: 'model_abc123',
      },
    },
  },
};

// ─── Review flow entry + coexisting views (header view-switch) ─────────────

/**
 * Event node selected, no session yet: the "Review flow" button appears in the
 * Properties header. Clicking it STARTS a session through the unsaved-changes
 * guard in Flow (onRequestReviewMode).
 */
export const EventNodeReviewButton: Story = {
  decorators: [withPanelMode('event')],
  args: {
    node: sampleEventNode,
    edge: null,
    settings: reviewCapableSettings,
    hasAnyReplayCapability: true,
    globalTokens: sampleGlobalTokens,
    onRequestReviewMode: fn(),
  },
};

/**
 * Replay view - the header is emptied down to a SINGLE control: a left-arrow
 * "Back" button in the label zone. Clicking it (or pressing Alt+Shift+R)
 * returns to the selected node's properties while the replay session stays
 * active. Requires an active session.
 */
export const ReviewModelView: Story = {
  tags: ['!test'],
  decorators: [withPanelMode('review')],
  args: {
    node: sampleEventNode,
    edge: null,
    settings: reviewCapableSettings,
    hasAnyReplayCapability: true,
    replaySessionActive: true,
    hasTestUrl: true,
    selectedStartNodeId: 'event_1',
    globalTokens: sampleGlobalTokens,
    onStartTest: fn(),
    onSelectReplayStep: fn(),
    onToggleReplay: fn(),
    onRequestReviewMode: fn(),
  },
};

/**
 * Active session, NON-event node that traces to a starting event: the "Review
 * flow" button appears and is ENABLED so the user can jump to the flow the
 * selected node belongs to (coexisting views). The owning event is supplied
 * structurally via `pickerOwningEventId`.
 */
export const ActiveSessionReturnFromAnyNode: Story = {
  tags: ['!test'],
  decorators: [withPanelMode('event')],
  args: {
    node: sampleNode,
    edge: null,
    settings: reviewCapableSettings,
    hasAnyReplayCapability: true,
    replaySessionActive: true,
    pickerOwningEventId: 'event_1',
    globalTokens: sampleGlobalTokens,
    onRequestReviewMode: fn(),
  },
};

/**
 * New (unsaved) model: there is no "Review flow" button — starting a session
 * requires a saved model with replay/test capability.
 */
export const ReviewButtonHiddenWhenUnsaved: Story = {
  decorators: [withPanelMode('event')],
  args: {
    node: sampleEventNode,
    edge: null,
    settings: { modeler_api: { isNew: true } },
    hasAnyReplayCapability: false,
  },
};

/**
 * ORPHANED non-event node (no owning event: no session AND no structural
 * `pickerOwningEventId`): the "Review flow" button IS shown (a single node is
 * selected) but stays DISABLED, since the node reaches no starting event to
 * review. This demonstrates the disabled state of the button.
 */
export const ReviewButtonDisabledForOrphanNode: Story = {
  tags: ['!test'],
  decorators: [withPanelMode('event')],
  args: {
    node: sampleNode,
    edge: null,
    settings: reviewCapableSettings,
    hasAnyReplayCapability: true,
    reviewableEventId: null,
    pickerOwningEventId: null,
    onRequestReviewMode: fn(),
  },
};

/**
 * NON-event node with a STRUCTURAL owning event but NO session yet: the "Review
 * flow" button appears and is ENABLED. Clicking it starts a review session for
 * the owning event resolved via `pickerOwningEventId`.
 */
export const ReviewButtonEnabledViaStructuralOwner: Story = {
  tags: ['!test'],
  decorators: [withPanelMode('event')],
  args: {
    node: sampleNode,
    edge: null,
    settings: reviewCapableSettings,
    hasAnyReplayCapability: true,
    reviewableEventId: null,
    pickerOwningEventId: 'event_1',
    onRequestReviewMode: fn(),
  },
};
