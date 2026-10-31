import React from 'react';
import type { Decorator } from '@storybook/react';
import { ReactFlowProvider } from 'reactflow';
import 'reactflow/dist/style.css';

/**
 * Decorator that wraps components in ReactFlowProvider
 * Required for node and edge components that use ReactFlow hooks
 *
 * Usage in stories:
 * export const MyStory: Story = {
 *   decorators: [withReactFlow],
 * };
 */
export const withReactFlow: Decorator = (Story) => {
  return (
    <ReactFlowProvider>
      <div
        style={{
          width: '100%',
          height: '400px',
          position: 'relative',
          background: 'var(--modeler-color-bg-canvas)',
          borderRadius: '8px',
          overflow: 'hidden',
        }}
      >
        <Story />
      </div>
    </ReactFlowProvider>
  );
};

/**
 * Decorator for node components - provides positioning context
 */
export const withNodeContext: Decorator = (Story) => {
  return (
    <ReactFlowProvider>
      <div
        style={{
          padding: '40px',
          background: 'var(--modeler-color-bg-canvas)',
          borderRadius: '8px',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          minHeight: '200px',
        }}
      >
        <div style={{ position: 'relative' }}>
          <Story />
        </div>
      </div>
    </ReactFlowProvider>
  );
};

export default withReactFlow;
