import prettier from 'eslint-plugin-prettier';
import globals from 'globals';
import babelParser from '@babel/eslint-parser';

export default [
  {
    plugins: {
      prettier,
    },

    languageOptions: {
      globals: {
        ...globals.browser,
        jQuery: true,
        Drupal: true,
        drupalSettings: true,
        accessibleAutocomplete: true,
        once: true,
      },

      parser: babelParser,
    },

    rules: {
      strict: 0,
      'no-param-reassign': 0,
      'import/no-extraneous-dependencies': 0,
      'prettier/prettier': 'error',
      'consistent-return': 0,
      'no-console': 1,
    },
  },
  {
    files: ['**/*.stories.js'],

    rules: {
      'react/no-danger': 0,
      'import/prefer-default-export': 0,
    },
  },
];
