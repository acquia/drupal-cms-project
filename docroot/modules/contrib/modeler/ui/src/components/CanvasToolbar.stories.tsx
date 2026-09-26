import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import CanvasToolbar from './CanvasToolbar';
import { withReactFlow, withStore } from '../../.storybook/decorators';

const meta: Meta<typeof CanvasToolbar> = {
  title: 'Components/CanvasToolbar',
  component: CanvasToolbar,
  parameters: {
    layout: 'fullscreen',
  },
  decorators: [
    withReactFlow,
  ],
  args: {
    isLocked: false,
    isReadOnly: false,
    onCopy: fn(),
    onPaste: fn(),
    onUndo: fn(),
    onRedo: fn(),
    onAutoLayout: fn(),
    hasSelection: false,
    canPaste: false,
    canUndo: false,
    canRedo: false,
  },
  argTypes: {
    isLocked: {
      control: 'boolean',
      description: 'Whether the canvas is in read-only mode',
    },
    isReadOnly: {
      control: 'boolean',
      description: 'Whether the modeler is in read-only mode (hides edit buttons)',
    },
    hasSelection: {
      control: 'boolean',
      description: 'Whether there are selected elements (enables Copy)',
    },
    canPaste: {
      control: 'boolean',
      description: 'Whether the clipboard has content to paste',
    },
    canUndo: {
      control: 'boolean',
      description: 'Whether undo is available',
    },
    canRedo: {
      control: 'boolean',
      description: 'Whether redo is available',
    },
  },
};

export default meta;
type Story = StoryObj<typeof CanvasToolbar>;

/**
 * Default state: View dropdown on the left, all edit and zoom buttons on the right.
 * Edit buttons (copy/paste/undo/redo) are disabled since there is no selection or history.
 */
export const Default: Story = {};

/**
 * Canvas toolbar with an active selection. The Copy button becomes enabled.
 */
export const WithSelection: Story = {
  args: {
    hasSelection: true,
  },
};

/**
 * Canvas toolbar with paste and undo available, simulating a state after
 * the user has copied elements and made some changes.
 */
export const WithClipboardAndHistory: Story = {
  args: {
    hasSelection: true,
    canPaste: true,
    canUndo: true,
  },
};

/**
 * Canvas toolbar with full undo/redo state. All edit buttons are active.
 */
export const AllButtonsActive: Story = {
  args: {
    hasSelection: true,
    canPaste: true,
    canUndo: true,
    canRedo: true,
  },
};

/**
 * Read-only mode hides copy/paste/undo/redo and the Auto Layout option.
 * Only the View dropdown and zoom controls remain visible.
 */
export const ReadOnly: Story = {
  args: {
    isReadOnly: true,
  },
};

/**
 * Locked canvas: edit buttons are visible but disabled.
 */
export const Locked: Story = {
  args: {
    isLocked: true,
    hasSelection: true,
    canPaste: true,
  },
};

/**
 * Canvas toolbar with context selector dropdown (no context selected).
 */
export const WithContextSelector: Story = {
  args: {
    contexts: [
      { id: 'ctx_1', topic: 'Content Publishing', model_owner: 'example_owner', components: {} },
      { id: 'ctx_2', topic: 'User Registration', model_owner: 'example_owner', components: {} },
      { id: 'ctx_3', topic: 'Commerce Orders', model_owner: 'example_owner', components: {} },
    ],
    selectedContextId: null,
    onContextChange: fn(),
  },
};

/**
 * Canvas toolbar with context selector and a context pre-selected.
 */
export const WithContextSelected: Story = {
  args: {
    contexts: [
      { id: 'ctx_1', topic: 'Content Publishing', model_owner: 'example_owner', components: {} },
      { id: 'ctx_2', topic: 'User Registration', model_owner: 'example_owner', components: {} },
      { id: 'ctx_3', topic: 'Commerce Orders', model_owner: 'example_owner', components: {} },
    ],
    selectedContextId: 'ctx_2',
    onContextChange: fn(),
  },
};

/**
 * Canvas toolbar with Start Flow Filter visible (multiple start nodes).
 * The filter appears because there are multiple start nodes in the store.
 */
export const WithFlowFilter: Story = {
  decorators: [
    withStore({
      initialState: {
        nodes: [
          { id: 'start_1', type: 'start', position: { x: 0, y: 0 }, data: { label: 'Content Created', plugin: 'content:entity_insert' } },
          { id: 'start_2', type: 'start', position: { x: 0, y: 300 }, data: { label: 'User Registered', plugin: 'user:user_insert' } },
          { id: 'action_1', type: 'element', position: { x: 200, y: 0 }, data: { label: 'Send Email', plugin: 'mail:send_mail' } },
        ],
        visibleStartNodeIds: null,
      },
    }),
  ],
};
