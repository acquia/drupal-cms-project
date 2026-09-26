import { join, resolve } from 'node:path';
import fs from 'fs';
import yaml from 'js-yaml';
import { globSync } from 'glob';

// Load only non-obsolete components
const componentFiles = globSync('../components/**/*.component.yml', { cwd: __dirname })
  .filter(file => {
    const content = fs.readFileSync(resolve(__dirname, file), 'utf8');
    const data = yaml.load(content);
    return data?.status !== 'obsolete'; // 👈 skip obsolete components
  });

const config = {
  staticDirs: ['../assets'],
  stories: componentFiles,
  addons: [
    {
      name: 'storybook-addon-sdc',
      options: {
        sdcStorybookOptions: {
          namespace: 'ssdc',
          validate: true,
        },
        vitePluginTwigDrupalOptions: {
          namespaces: {
            pulse_theme: join(__dirname, '../components'),
          },
        },
      },
    },
    '@storybook/addon-essentials',
    '@storybook/addon-interactions',
  ],
  framework: {
    name: '@storybook/html-vite',
    options: {},
  },

  viteFinal: async (config) => {
    config.publicDir = false;
    config.server = {
      ...(config.server || {}),
      fs: {
        allow: [
          ...(config.server?.fs?.allow || []),
          resolve(__dirname, '../'),
        ],
      },
    };

    config.resolve.alias = {
      ...(config.resolve.alias || {}),
      '/themes/contrib/pulse_theme/components': resolve(__dirname, '../components'),
    };

    // Add PostCSS loader for Tailwind CSS
    config.css = {
      postcss: {
        plugins: [
          require('@tailwindcss/postcss')(resolve(__dirname, '../tailwind.config.js')),
          require('autoprefixer'),
        ],
      },
    };

    return config;
  },
};

export default config;
