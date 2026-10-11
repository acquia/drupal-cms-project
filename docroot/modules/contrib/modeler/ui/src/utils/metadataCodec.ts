/**
 * Codec between stored model metadata and the flat values of the metadata
 * form.
 *
 * The store holds metadata in its typed form (arrays of tags, recipes, config
 * names; a list of config actions), while the form - rendered from the Drupal
 * form array the backend converts to JSON - speaks strings: a comma-separated
 * list, one entry per line, a YAML document. This module owns both directions
 * of that translation so neither the dialog nor the form has to know it.
 */

import yaml from 'js-yaml';
import type { ModelData } from '../types/settings';

/**
 * Model metadata as the store holds it, plus the machine name the dialog
 * sends for a new model.
 *
 * Every member is optional: the backend decides which fields the metadata
 * form contains, and a field it hides (Drupal's `#access => FALSE`) is
 * neither rendered nor sent.
 */
export type MetadataFormData = NonNullable<ModelData['metadata']> & {
  /** Only sent for new models, where it becomes the config entity id. */
  id?: string;
};

/** Form key of the machine name element, which maps to the payload's `id`. */
export const MODEL_ID_KEY = 'model_id';

/**
 * How a metadata value is spelled in the form. Keys not listed here are
 * carried through unchanged (strings and booleans).
 */
const FIELD_KINDS = {
  tags: 'csv',
  recipes: 'lines',
  export_config: 'lines',
  modules: 'lines',
  config_actions: 'yaml',
} as const;

type FieldKind = typeof FIELD_KINDS[keyof typeof FIELD_KINDS];

/** Keys of the metadata the form spells differently from the store. */
type ListKey = keyof typeof FIELD_KINDS;

/**
 * Turn stored metadata into the flat values the metadata form starts from.
 *
 * @param metadata  Metadata as held in the store.
 * @param isNew     Whether this is a model that does not exist yet.
 * @param modelId   The existing model's machine name, if any.
 */
export function encodeMetadata(
  metadata: MetadataFormData | undefined,
  isNew: boolean,
  modelId?: string,
): Record<string, unknown> {
  const values: Record<string, unknown> = {
    // A new model has no machine name yet: the form derives one from the
    // label until the user types their own.
    [MODEL_ID_KEY]: isNew ? '' : (modelId || ''),
  };

  for (const [key, value] of Object.entries(metadata || {})) {
    if (key === 'id') {
      continue;
    }
    const kind: FieldKind | undefined = FIELD_KINDS[key as ListKey];
    if (kind === 'csv') {
      values[key] = Array.isArray(value) ? value.join(', ') : (value ?? '');
      continue;
    }
    if (kind === 'lines') {
      values[key] = Array.isArray(value) ? value.join('\n') : (value ?? '');
      continue;
    }
    if (kind === 'yaml') {
      // An empty list is an empty editor, not a literal "[]" document.
      values[key] = Array.isArray(value) && value.length > 0 ? yaml.dump(value) : '';
      continue;
    }
    values[key] = value;
  }

  return values;
}

/**
 * Turn the form's flat values back into stored metadata.
 *
 * Only keys the form actually carries are returned, so a field the backend
 * hid stays out of the payload and the stored value survives untouched.
 *
 * @param values    Flat form values, keyed by form key.
 * @param previous  Metadata the dialog opened with, used as the fallback for
 *                  a config actions document that does not parse.
 */
export function decodeMetadata(
  values: Record<string, unknown>,
  previous?: MetadataFormData,
): MetadataFormData {
  const metadata: Record<string, unknown> = {};

  for (const [key, value] of Object.entries(values)) {
    if (key === MODEL_ID_KEY) {
      continue;
    }
    const kind: FieldKind | undefined = FIELD_KINDS[key as ListKey];
    if (kind === 'csv' || kind === 'lines') {
      metadata[key] = typeof value === 'string'
        ? value.split(kind === 'csv' ? ',' : '\n').map((entry) => entry.trim()).filter((entry) => entry !== '')
        : [];
      continue;
    }
    if (kind === 'yaml') {
      metadata[key] = decodeConfigActions(value, previous?.config_actions);
      continue;
    }
    metadata[key] = value;
  }

  return metadata as MetadataFormData;
}

/**
 * Parse the config actions editor.
 *
 * An empty editor is a deliberate clear. A document that does not parse, or
 * one that is not a list, keeps the value the dialog opened with: the editor
 * already flags the syntax error, and a half-typed document must never
 * silently drop the stored actions.
 */
function decodeConfigActions(
  value: unknown,
  previous: MetadataFormData['config_actions'],
): MetadataFormData['config_actions'] {
  if (typeof value !== 'string' || value.trim() === '') {
    return [];
  }
  let parsed: unknown;
  try {
    parsed = yaml.load(value);
  } catch {
    return previous;
  }
  if (!Array.isArray(parsed)) {
    return previous;
  }
  return parsed as MetadataFormData['config_actions'];
}
