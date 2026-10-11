import React from 'react';
import type { Meta, StoryObj } from '@storybook/react';
import PanelErrorBoundary from './PanelErrorBoundary';

/**
 * Component that throws an error on render for testing error boundaries.
 */
const ErrorThrowingComponent: React.FC = () => {
  throw new Error('Something went wrong in this panel!');
};

/**
 * Component that renders normally.
 */
const NormalComponent: React.FC = () => (
  <div style={{ padding: 20, textAlign: 'center', color: 'var(--modeler-color-text-secondary)' }}>
    <p>This panel content is rendering correctly.</p>
    <p>No errors here!</p>
  </div>
);

const meta: Meta<typeof PanelErrorBoundary> = {
  title: 'Components/PanelErrorBoundary',
  component: PanelErrorBoundary,
  parameters: {
    layout: 'centered',
  },
  decorators: [
    (Story: React.ComponentType) => (
      <div style={{ width: 350, height: 300, border: '1px solid var(--modeler-color-border-default)', borderRadius: 8, overflow: 'hidden', background: 'var(--modeler-color-bg-primary)' }}>
        <Story />
      </div>
    ),
  ],
  args: {
    panelName: 'Property Panel',
    className: '',
  },
  argTypes: {
    panelName: {
      control: 'text',
      description: 'Name displayed in the error fallback UI',
    },
    className: {
      control: 'text',
      description: 'CSS class applied to the fallback container',
    },
  },
};

export default meta;
type Story = StoryObj<typeof PanelErrorBoundary>;

/**
 * Normal rendering (no error)
 */
export const Normal: Story = {
  render: (args) => (
    <PanelErrorBoundary {...args}>
      <NormalComponent />
    </PanelErrorBoundary>
  ),
};

/**
 * Error state with auto-retry and manual retry button.
 * The boundary first attempts automatic recovery with exponential backoff,
 * then offers a manual "Try Again" button.
 */
export const WithError: Story = {
  tags: ['!test'],
  render: (args) => (
    <PanelErrorBoundary {...args}>
      <ErrorThrowingComponent />
    </PanelErrorBoundary>
  ),
};

/**
 * Error in FlowCanvas panel
 */
export const FlowCanvasError: Story = {
  tags: ['!test'],
  args: {
    panelName: 'Flow Canvas',
  },
  render: (args) => (
    <PanelErrorBoundary {...args}>
      <ErrorThrowingComponent />
    </PanelErrorBoundary>
  ),
};

/**
 * Error in Replay Panel
 */
export const ReplayPanelError: Story = {
  tags: ['!test'],
  args: {
    panelName: 'Replay Panel',
  },
  render: (args) => (
    <PanelErrorBoundary {...args}>
      <ErrorThrowingComponent />
    </PanelErrorBoundary>
  ),
};

/**
 * Error in Toolbar
 */
export const ToolbarError: Story = {
  tags: ['!test'],
  args: {
    panelName: 'Toolbar',
    className: 'toolbar-error',
  },
  render: (args) => (
    <PanelErrorBoundary {...args}>
      <ErrorThrowingComponent />
    </PanelErrorBoundary>
  ),
};


