import React from 'react';
import type { Meta, StoryObj } from '@storybook/react';
import { fn, within, userEvent } from '@storybook/test';
import TokenPicker from './TokenPicker';
import { TokenSourceContext } from './TokenSourceContext';
import type { TokenSourceValue } from './TokenSourceContext';
import { LISTEN_ITEM_INDEX } from '../hooks/useReplayLoader';

/** Drupal-shaped global tokens (name + "raw token" → transformGlobalToken). */
const sampleGlobalTokens = {
  '[site:name]': { name: 'Site name', 'raw token': '[site:name]', token: 'name', value: 'My Site' },
  '[site:slogan]': { name: 'Site slogan', 'raw token': '[site:slogan]', token: 'slogan', value: 'Just do it' },
  '[current-user]': {
    name: 'Current user',
    'raw token': '[current-user]',
    token: 'current-user',
    children: {
      'account-name': { name: 'Account name', 'raw token': '[current-user:account-name]', token: 'account-name', value: 'admin' },
      'display-name': { name: 'User name', 'raw token': '[current-user:display-name]', token: 'display-name', value: 'Administrator' },
      mail: { name: 'Email', 'raw token': '[current-user:mail]', token: 'mail', value: 'admin@example.com' },
    },
  },
} as Record<string, any>;

const sampleTemplateTokens = {
  '[template:author]': { name: 'Author', 'raw token': '[template:author]', token: 'author', value: 'Jane Doe' },
  '[template:version]': { name: 'Version', 'raw token': '[template:version]', token: 'version', value: '1.0.0' },
} as Record<string, any>;

/** Expanded step data with sub-categories (Current user, Entity, ...). */
const sampleStepData = {
  'current-user': {
    label: 'Current user',
    data: {
      'account-name': { label: 'Account name', token: '[current-user:account-name]', value: 'admin' },
      'display-name': { label: 'User name', token: '[current-user:display-name]', value: 'Administrator' },
    },
  },
  entity: {
    label: 'Entity',
    data: {
      title: { label: 'Title', token: '[entity:title]', value: 'My Article' },
      status: { label: 'Status', token: '[entity:status]', value: '1' },
    },
  },
} as Record<string, unknown>;

/** Provide token sources via context, and frame the popup like a field. */
const withTokenSources = (value: TokenSourceValue) => (Story: React.ComponentType) => (
  <TokenSourceContext.Provider value={value}>
    <div
      style={{
        position: 'relative',
        width: 360,
        height: 420,
        padding: 16,
        border: '1px solid #e0e0e0',
        borderRadius: 8,
      }}
    >
      <Story />
    </div>
  </TokenSourceContext.Provider>
);

const meta: Meta<typeof TokenPicker> = {
  title: 'Components/TokenPicker',
  component: TokenPicker,
  parameters: { layout: 'centered' },
  args: {
    position: { x: 16, y: 16 },
    onSelect: fn(),
    onClose: fn(),
  },
};

export default meta;
type Story = StoryObj<typeof TokenPicker>;

/**
 * Top-level "Select token category" list — Step data, Global, Template.
 */
export const Categories: Story = {
  decorators: [
    withTokenSources({
      globalTokens: sampleGlobalTokens,
      templateTokens: sampleTemplateTokens,
      isTemplate: true,
      stepData: sampleStepData,
      hasStepData: true,
      reviewAvailable: true,
    }),
  ],
};

/**
 * Filtering via the picker's own search box (DECISION A) — a flat list of
 * matching tokens. The `play` step types into the search input.
 */
export const Filtered: Story = {
  decorators: [
    withTokenSources({
      globalTokens: sampleGlobalTokens,
      stepData: sampleStepData,
      hasStepData: true,
      reviewAvailable: true,
    }),
  ],
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.type(await canvas.findByLabelText('Search tokens'), 'name');
  },
};

/**
 * Filter with no matches — the search box typed a term that matches nothing.
 */
export const NoMatches: Story = {
  tags: ['!test'],
  decorators: [
    withTokenSources({
      globalTokens: sampleGlobalTokens,
      hasStepData: true,
      reviewAvailable: true,
    }),
  ],
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.type(await canvas.findByLabelText('Search tokens'), 'zzz-nothing');
  },
};

/**
 * Empty step data — Global/Template stay available and the picker nudges the
 * user to "Review the flow" for richer (step-data) tokens.
 */
export const EmptyStepDataHint: Story = {
  decorators: [
    withTokenSources({
      globalTokens: sampleGlobalTokens,
      hasStepData: false,
      reviewAvailable: true,
      onReviewModel: fn(),
    }),
  ],
};

/**
 * Global-tokens-only (no step data, review unavailable) — just the categories.
 */
export const GlobalOnly: Story = {
  tags: ['!test'],
  decorators: [
    withTokenSources({
      globalTokens: sampleGlobalTokens,
      hasStepData: false,
      reviewAvailable: false,
    }),
  ],
};

/** Sample replay datasets (executions) for the step-data dropdown. */
const sampleReplayEntries = [
  { model_id: 'm', component_id: 'event_1', history: [], timestamp: '2024-02-01T10:00:00Z', user: { name: 'alice', uid: 3 }, ip: '127.0.0.1', url: '/node/1' },
  { model_id: 'm', component_id: 'event_1', history: [], timestamp: '2024-01-15T09:30:00Z', user: { name: 'bob', uid: 7 }, ip: '127.0.0.1', url: '/node/2' },
] as TokenSourceValue['replayEntries'];

/**
 * Feature J: the Step-data category shows for a non-template node with a
 * resolvable owning event. Opening it reveals the dataset dropdown (newest
 * first, persistent "Listen…" at top) above the selected dataset's tokens.
 */
export const StepDataWithDatasets: Story = {
  decorators: [
    withTokenSources({
      globalTokens: sampleGlobalTokens,
      isTemplate: false,
      owningEventId: 'event_1',
      replayEntries: sampleReplayEntries,
      selectedEntryIndex: 0,
      stepData: sampleStepData,
      hasStepData: true,
      reviewAvailable: true,
      onSelectDataset: fn(),
      onStartListen: fn(),
      onLoadStepData: fn(),
    }),
  ],
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    // Open the step-data category, then expand the dataset dropdown.
    await userEvent.click(await canvas.findByText('Step data tokens'));
    await userEvent.click(await canvas.findByLabelText('Select step data dataset'));
  },
};

/**
 * Feature J: load-on-demand "Listening for event…" inline waiting state shown
 * when the persistent "Listen…" item is selected (single live listener armed).
 */
export const StepDataListening: Story = {
  tags: ['!test'],
  decorators: [
    withTokenSources({
      globalTokens: sampleGlobalTokens,
      isTemplate: false,
      owningEventId: 'event_1',
      replayEntries: sampleReplayEntries,
      selectedEntryIndex: LISTEN_ITEM_INDEX,
      isListening: true,
      reviewAvailable: true,
      onStartListen: fn(),
      onLoadStepData: fn(),
    }),
  ],
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.click(await canvas.findByText('Step data tokens'));
  },
};
