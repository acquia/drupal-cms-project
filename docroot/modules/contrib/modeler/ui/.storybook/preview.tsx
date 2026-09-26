import type { Preview } from '@storybook/react';
import React from 'react';

// Import styles
import 'reactflow/dist/style.css';
import '../src/styles/modeler.css';

// Global decorators
const preview: Preview = {
  globalTypes: {
    theme: {
      description: 'Dark mode toggle',
      toolbar: {
        title: 'Theme',
        icon: 'moon',
        items: [
          { value: 'light', title: 'Light', icon: 'sun' },
          { value: 'dark', title: 'Dark', icon: 'moon' },
        ],
        dynamicTitle: true,
      },
    },
  },
  initialGlobals: {
    theme: 'light',
  },
  parameters: {
    actions: { argTypesRegex: '^on[A-Z].*' },
    controls: {
      matchers: {
        color: /(background|color)$/i,
        date: /Date$/i,
      },
    },
    backgrounds: {
      default: 'light',
      values: [
        { name: 'light', value: '#ffffff' },
        { name: 'dark', value: '#0f172a' },
        { name: 'canvas', value: '#f5f5f5' },
      ],
    },
    layout: 'centered',
  },
  decorators: [
    (Story, context) => {
      const theme = context.globals.theme || 'light';
      const isDark = theme === 'dark';
      return (
        <div
          className={`modeler ${isDark ? 'dark-mode' : ''}`}
          style={{
            fontFamily: 'system-ui, -apple-system, sans-serif',
            backgroundColor: isDark ? '#0f172a' : undefined,
          }}
        >
          <Story />
        </div>
      );
    },
  ],
};

export default preview;
