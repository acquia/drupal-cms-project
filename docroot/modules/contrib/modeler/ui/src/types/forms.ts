/**
 * Shared form-schema types.
 *
 * These describe the JSON that the backend FormToJsonConverter emits for any
 * Drupal form array: plugin configuration forms as well as the model metadata
 * form. Both are rendered by the same `ConfigurationForm` component, so the
 * shape lives here rather than inside one of its consumers.
 */

import type { YamlSchema } from '../components/YamlEditor';

/**
 * A single normalized Drupal #states condition.
 *
 * Mirrors the structure emitted by the backend FormToJsonConverter: the
 * selector ":input[name=\"KEY\"]" is simplified to the bare field key, and the
 * common Drupal condition keys (value / checked / empty) are carried verbatim.
 */
export interface StateCondition {
  /** The (flat) field key this condition observes. */
  field: string;
  /**
   * Match when the observed field equals this value. When an array is given,
   * match when the observed field equals ANY listed value (Drupal's
   * "equals any" semantics). The backend normally expands array values into
   * OR groups, but the array form is accepted here for robustness.
   */
  value?: (string | number | boolean) | (string | number | boolean)[];
  /** Match when the observed field's checked state equals this. */
  checked?: boolean;
  /** Match when the observed field's empty state equals this. */
  empty?: boolean;
}

/**
 * A group of conditions combined with logical AND. All conditions in a group
 * must hold for the group to match.
 */
export type StateGroup = StateCondition[];

/**
 * Normalized Drupal #states, keyed by state type. Each value is a list of
 * OR groups: conditions within a group combine with logical AND, and groups
 * combine with logical OR (the state holds when ANY group fully matches).
 */
export interface FieldStates {
  visible?: StateGroup[];
  invisible?: StateGroup[];
  required?: StateGroup[];
  optional?: StateGroup[];
}

export interface FormField {
  key: string;
  type: string;
  /**
   * Widget format derived from the field's config-schema contract (e.g. a Json
   * constraint → 'json'), independent of the Drupal form element type. Lets the
   * modeler pick a specialized editor without hard-coding vendor type names.
   */
  format?: string;
  title?: string;
  description?: string;
  placeholder?: string;
  required?: boolean;
  /** Mirrors Drupal's #disabled: the element is rendered but not editable. */
  disabled?: boolean;
  /** Mirrors Drupal's #maxlength for text elements. */
  maxlength?: number;
  /**
   * For `machine_name` fields: the key of the field the machine name is
   * derived from (Drupal's #machine_name['source']).
   */
  source?: string;
  default_value?: unknown;
  min?: number;
  max?: number;
  step?: number;
  options?: Record<string, string>;
  markup?: string;
  token_support?: boolean;
  /**
   * Inline YAML schema discovered from Drupal config schema.
   * When present on a textarea field, the structured YAML editor is rendered.
   * The backend auto-discovers this from a config schema definition at
   * "yaml.{plugin_schema_key}.{field_key}".
   */
  yaml_schema?: YamlSchema;
  /**
   * For use_yaml / validate_yaml checkboxes: the key of the textarea field
   * they control.  Set by the backend when it detects ECA's
   * FormFieldYamlTrait pattern.
   */
  yaml_field?: string;
  /**
   * Normalized Drupal #states driving conditional visibility / required
   * behavior. Evaluated against the flat `values` map by the states engine.
   */
  states?: FieldStates;
  /**
   * Child fields for a `group` (details / fieldset / container) field. Child
   * values flow through the SAME flat `values` map keyed by their own field
   * key, mirroring Drupal's flat form-value structure.
   */
  children?: FormField[];
  /** For `details` groups: whether the group starts expanded (default true). */
  open?: boolean;
  /**
   * Empty/placeholder option for a `select`, decided and labeled server-side
   * (PHP owns the empty-option rule). When present, the UI renders it as the
   * first option; when absent, no empty option is rendered. The label is
   * already translated server-side and is rendered verbatim.
   */
  empty_option?: { value: string; label: string };
}
