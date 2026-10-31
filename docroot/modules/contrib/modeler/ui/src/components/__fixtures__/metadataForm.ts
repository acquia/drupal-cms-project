/**
 * The converted model metadata form, exactly as the backend
 * FormToJsonConverter emits it for `ModelerBase::defaultModelConfigForm()`.
 *
 * Shared by the metadata dialog tests and its Storybook stories so both
 * exercise the real payload rather than a hand-trimmed approximation.
 */

import type { FormField } from '../../types/forms';

/** The form for a model that already exists: its machine name is fixed. */
export const metadataForm: FormField[] = [
  { key: 'label', type: 'textfield', title: 'Label', description: '', required: false, default_value: '', token_support: false },
  { key: 'model_id', type: 'machine_name', title: 'Model ID', description: '', required: false, default_value: '', token_support: false, source: 'label', disabled: true },
  { key: 'executable', type: 'checkbox', title: 'Enabled', description: '', required: false, default_value: true, token_support: false },
  { key: 'template', type: 'checkbox', title: 'Template', description: 'If checked, the model will be used as a template for new models.', required: false, default_value: false, token_support: false },
  { key: 'documentation', type: 'textarea', title: 'Documentation', description: '', required: false, default_value: '', token_support: false },
  { key: 'tags', type: 'textfield', title: 'Tags', description: 'Comma-separated list of tags.', required: false, default_value: '', token_support: false },
  {
    key: 'recipe_export',
    type: 'group',
    title: 'Recipe export',
    open: false,
    children: [
      { key: 'summary', type: 'textfield', title: 'Summary', description: 'A one-line description of this model, used as the description of a recipe exported from it.', required: false, default_value: '', token_support: false, maxlength: 255 },
      { key: 'recipes', type: 'textarea', title: 'Included recipes', description: 'Recipes that an exported recipe includes, one per line.', required: false, default_value: '', token_support: false },
      { key: 'export_config', type: 'textarea', title: 'Additional config to export', description: 'Names of config objects to ship with an exported recipe, one per line.', required: false, default_value: '', token_support: false },
      { key: 'modules', type: 'textarea', title: 'Additional required modules', description: 'Modules an exported recipe installs in addition to those the model depends on, one per line.', required: false, default_value: '', token_support: false },
      { key: 'config_actions', type: 'textarea', title: 'Config actions', description: 'Config actions for an exported recipe as YAML.', required: false, default_value: '', token_support: false, format: 'yaml' },
    ],
  },
  {
    key: 'advanced',
    type: 'group',
    title: 'Advanced',
    open: false,
    children: [
      { key: 'version', type: 'textfield', title: 'Version', description: '', required: false, default_value: '', token_support: false },
      {
        key: 'storage',
        type: 'select',
        title: 'Storage of raw data',
        description: 'Controls if and how the raw modeler data is stored.',
        required: false,
        default_value: '',
        token_support: false,
        options: {
          '': 'Default',
          none: 'Do not store raw model data',
          separate: 'Store raw data in separate config entity',
          'third-party': 'Store raw data with config as third-party setting',
        },
      },
      { key: 'changelog', type: 'textarea', title: 'Changelog', description: '', required: false, default_value: '', token_support: false },
    ],
  },
];

/**
 * The form for a model that does not exist yet: the machine name is editable
 * and derived from the label until the user types one.
 */
export const newModelMetadataForm: FormField[] = metadataForm.map((field) => {
  if (field.key !== 'model_id') {
    return field;
  }
  const { disabled: _disabled, ...editable } = field;
  return editable;
});
