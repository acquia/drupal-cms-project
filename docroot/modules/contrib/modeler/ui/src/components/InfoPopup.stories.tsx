import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import InfoPopup from './InfoPopup';

const meta: Meta<typeof InfoPopup> = {
  title: 'Components/InfoPopup',
  component: InfoPopup,
  parameters: {
    layout: 'centered',
  },
  decorators: [
    (Story: React.ComponentType) => (
      <div style={{ width: 360, height: 200, position: 'relative', border: '1px solid var(--modeler-color-border-default)', borderRadius: 8, padding: 16, background: 'var(--modeler-color-bg-primary)' }}>
        <div style={{ fontSize: 13, color: 'var(--modeler-color-text-secondary)', marginBottom: 8 }}>Panel header area</div>
        <Story />
      </div>
    ),
  ],
  args: {
    items: [
      { label: 'ID', value: 'node_send_email_abc12', show: true },
      { label: 'Type', value: 'element', show: true },
      { label: 'Plugin ID', value: 'eca_mail:send_mail', show: true },
    ],
    onClose: fn(),
  },
};

export default meta;
type Story = StoryObj<typeof InfoPopup>;

/**
 * Node metadata popup showing ID, Type, and Plugin ID
 */
export const NodeMetadata: Story = {};

/**
 * Edge metadata popup showing connection details
 */
export const EdgeMetadata: Story = {
  args: {
    items: [
      { label: 'Connection Type', value: 'Edge', show: true },
      { label: 'Condition Plugin', value: 'user_has_role', show: true },
      { label: 'Edge ID', value: 'edge_is_admin_f4e21', show: true },
      { label: 'Source', value: 'node_content_created', show: true },
      { label: 'Target', value: 'node_send_email', show: true },
    ],
  },
};

/**
 * Replay step metadata with error
 */
export const ReplayStepWithError: Story = {
  args: {
    items: [
      { label: 'Type', value: 'action', show: true },
      { label: 'Component ID', value: 'action_1', show: true },
      { label: 'Successor ID', value: 'node_3', show: true },
      { label: 'Error', value: 'Mail sending failed: SMTP connection refused.', show: true, isError: true },
    ],
  },
};

/**
 * Replay step metadata without optional fields
 */
export const ReplayStepMinimal: Story = {
  args: {
    items: [
      { label: 'Type', value: 'event', show: true },
      { label: 'Component ID', value: 'event_1', show: true },
      { label: 'Successor ID', value: '', show: false },
      { label: 'Condition ID', value: '', show: false },
    ],
  },
};

/**
 * Edge metadata without condition plugin
 */
export const EdgeWithoutCondition: Story = {
  args: {
    items: [
      { label: 'Connection Type', value: 'Edge', show: true },
      { label: 'Condition Plugin', value: '', show: false },
      { label: 'Edge ID', value: 'edge_default_12345', show: true },
      { label: 'Source', value: 'node_1', show: true },
      { label: 'Target', value: 'node_2', show: true },
    ],
  },
};
