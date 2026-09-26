import js from '@eslint/js';
import tseslint from '@typescript-eslint/eslint-plugin';
import tsparser from '@typescript-eslint/parser';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import noUntranslatedStrings from './eslint-rules/no-untranslated-strings.js';
import noBareSetCenter from './eslint-rules/no-bare-set-center.js';

export default [
  js.configs.recommended,
  {
    files: ['src/**/*.{ts,tsx,js,jsx}'],
    languageOptions: {
      parser: tsparser,
      parserOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
        ecmaFeatures: {
          jsx: true,
        },
      },
      globals: {
        React: 'readonly',
        window: 'readonly',
        document: 'readonly',
        console: 'readonly',
        setTimeout: 'readonly',
        clearTimeout: 'readonly',
        setInterval: 'readonly',
        clearInterval: 'readonly',
        requestAnimationFrame: 'readonly',
        cancelAnimationFrame: 'readonly',
        HTMLElement: 'readonly',
        HTMLInputElement: 'readonly',
        HTMLTextAreaElement: 'readonly',
        HTMLDivElement: 'readonly',
        Element: 'readonly',
        Event: 'readonly',
        MouseEvent: 'readonly',
        KeyboardEvent: 'readonly',
        WheelEvent: 'readonly',
        DragEvent: 'readonly',
        CustomEvent: 'readonly',
        MutationObserver: 'readonly',
        ResizeObserver: 'readonly',
        fetch: 'readonly',
        localStorage: 'readonly',
        Drupal: 'readonly',
        drupalSettings: 'readonly',
        jQuery: 'readonly',
        crypto: 'readonly',
        TextEncoder: 'readonly',
        TextDecoder: 'readonly',
        Uint8Array: 'readonly',
        ArrayBuffer: 'readonly',
        btoa: 'readonly',
        atob: 'readonly',
        navigator: 'readonly',
        NodeList: 'readonly',
        global: 'readonly',
        process: 'readonly',
      },
    },
    plugins: {
      '@typescript-eslint': tseslint,
      'react': react,
      'react-hooks': reactHooks,
      'i18n': {
        rules: {
          'no-untranslated-strings': noUntranslatedStrings,
        },
      },
      'viewport': {
        rules: {
          'no-bare-set-center': noBareSetCenter,
        },
      },
    },
    rules: {
      // TypeScript rules
      '@typescript-eslint/no-unused-vars': ['warn', { argsIgnorePattern: '^_', varsIgnorePattern: '^_', caughtErrorsIgnorePattern: '^_' }],
      '@typescript-eslint/no-explicit-any': 'off',
      
      // React rules
      'react/jsx-uses-react': 'error',
      'react/jsx-uses-vars': 'error',
      'react/react-in-jsx-scope': 'off',
      
      // React Hooks rules
      'react-hooks/rules-of-hooks': 'error',
      'react-hooks/exhaustive-deps': 'warn',
      
      // General rules
      'no-unused-vars': 'off', // Use TypeScript version instead
      'no-undef': 'off', // TypeScript handles this
      'no-console': 'off',
      'no-debugger': 'warn',
      
      // i18n rules - enforce translation of user-facing strings
      'i18n/no-untranslated-strings': ['warn', {
        // Add additional attributes to ignore if needed
        ignoreAttributes: [],
        // Add additional function names to ignore if needed
        ignoreFunctions: [],
        // Add additional regex patterns to ignore if needed
        ignorePatterns: [],
      }],

      // Viewport rules - prevent invasive pan/zoom operations
      'viewport/no-bare-set-center': 'error',
    },
    settings: {
      react: {
        version: 'detect',
      },
    },
  },
  {
    ignores: [
      'node_modules/**',
      'dist/**',
      '*.config.js',
      '*.config.ts',
      'build.sh',
      '.storybook/**',
      'tests/**',
      'docs/**',
    ],
  },
];
