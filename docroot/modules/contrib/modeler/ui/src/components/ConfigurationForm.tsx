/**
 * ConfigurationForm - Dynamic form component for workflow element configuration
 * 
 * Renders form fields based on a schema provided by the backend.
 * Supports various field types including text, textarea, select, checkboxes,
 * and rich text fields with token support.
 *
 * Textarea fields that include an inline `yaml_schema` (discovered
 * automatically from Drupal config schema) are rendered with a structured
 * YAML editor widget instead of a plain content-editable area.
 */

import React, { useState, useCallback, useEffect, useMemo } from 'react';
import yaml from 'js-yaml';
import { sanitizeHtml } from '../utils/sanitize';
import { t } from '../utils/translation';
import { labelToSnakeCase } from '../utils/modelUtils';
import ContentEditableField from './ContentEditableField';
import YamlEditor from './YamlEditor';
import type { FormField, StateCondition, StateGroup } from '../types/forms';

/**
 * Coerce a non-string value into a YAML string so the YAML editors can always
 * work on text, whatever the backend put into `default_value`.
 */
function coerceToYaml(val: unknown): string {
  if (typeof val === 'string') return val;
  if (val === null || val === undefined) return '';
  try {
    return yaml.dump(val, {
      indent: 2,
      lineWidth: -1,
      noRefs: true,
      sortKeys: false,
    }).replace(/\n$/, '');
  } catch {
    return String(val);
  }
}

interface ConfigurationFormProps {
  form?: FormField[] | null;
  configuration?: Record<string, unknown> | null;
  onChange?: (values: Record<string, unknown>) => void;
  disabled?: boolean;
}

/**
 * Determine whether a single #states condition currently holds, given the
 * flat form values.
 */
function evaluateCondition(condition: StateCondition, values: Record<string, unknown>): boolean {
  const current = values[condition.field];
  if (condition.value !== undefined) {
    // An array value means "equals any listed value" (Drupal's match-any).
    if (Array.isArray(condition.value)) {
      return condition.value.some((v) => String(current ?? '') === String(v));
    }
    return String(current ?? '') === String(condition.value);
  }
  if (condition.checked !== undefined) {
    return Boolean(current) === condition.checked;
  }
  if (condition.empty !== undefined) {
    // A value is "empty" when it is null/undefined, an empty string, or an
    // empty array (mirroring Drupal's notion of empty form values).
    const isEmpty =
      current === null ||
      current === undefined ||
      current === '' ||
      (Array.isArray(current) && current.length === 0);
    return isEmpty === condition.empty;
  }
  // An unsupported / empty condition object never matches.
  return false;
}

/**
 * Evaluate a single group with logical AND: every condition in the group must
 * hold. An empty group trivially holds.
 */
function evaluateGroup(group: StateGroup, values: Record<string, unknown>): boolean {
  return group.every((condition) => evaluateCondition(condition, values));
}

/**
 * Evaluate a list of OR groups: the state holds when ANY group fully matches.
 * An empty or missing list is treated as "not applicable" and trivially holds,
 * so callers can guard on the presence of the state type.
 */
function evaluateGroups(groups: StateGroup[] | undefined, values: Record<string, unknown>): boolean {
  if (!groups || groups.length === 0) {
    return true;
  }
  return groups.some((group) => evaluateGroup(group, values));
}

/**
 * The resolved presentation state for a field after applying its #states.
 */
interface ResolvedFieldState {
  hidden: boolean;
  required: boolean;
}

/**
 * Resolve a field's visibility and required state from its #states against the
 * current flat form values.
 */
function resolveFieldState(field: FormField, values: Record<string, unknown>): ResolvedFieldState {
  let hidden = false;
  let required = !!field.required;

  const states = field.states;
  if (states) {
    if (states.visible) {
      // Field is shown only while ANY visible group holds.
      hidden = hidden || !evaluateGroups(states.visible, values);
    }
    if (states.invisible) {
      // Field is hidden while ANY invisible group holds.
      hidden = hidden || evaluateGroups(states.invisible, values);
    }
    if (states.required) {
      required = evaluateGroups(states.required, values);
    }
    if (states.optional) {
      // Optional is the inverse of required.
      required = !evaluateGroups(states.optional, values);
    }
  }

  return { hidden, required };
}

/**
 * Drupal's `machine_name` element: a text input that mirrors its source field
 * (usually the label) until the user types an ID of their own.
 *
 * Derivation is deliberately a component-local concern: the derived value is
 * pushed up through `onChange` so the flat form values - and therefore the
 * submitted payload - always carry what the user sees.
 */
const MachineNameField: React.FC<{
  field: FormField;
  value: string;
  /** Current value of the field named by `field.source`, when there is one. */
  sourceValue?: unknown;
  onChange: (value: unknown) => void;
  disabled: boolean;
  required: boolean;
}> = ({ field, value, sourceValue, onChange, disabled, required }) => {
  // Set once the user types a value; cleared again when they empty the input,
  // which hands control back to the source field.
  const [userEdited, setUserEdited] = useState(false);
  const derive = !!field.source && !disabled && !userEdited;
  const derived = derive ? labelToSnakeCase(typeof sourceValue === 'string' ? sourceValue : '') : '';

  useEffect(() => {
    if (derive && derived !== value) {
      onChange(derived);
    }
  }, [derive, derived, value, onChange]);

  return (
    <input
      id={`config-field-${field.key}`}
      type="text"
      value={value}
      onChange={(e) => {
        setUserEdited(e.target.value !== '');
        onChange(e.target.value);
      }}
      className="form-control"
      placeholder={field.placeholder}
      pattern="[a-z0-9_]+"
      title={t('Only lowercase letters, numbers, and underscores allowed')}
      maxLength={field.maxlength}
      required={required}
      disabled={disabled}
    />
  );
};

/**
 * Render a single form field based on its type
 */
const FormFieldRenderer: React.FC<{
  field: FormField;
  value: unknown;
  /** Current value of the field named by `field.source` (machine_name only). */
  sourceValue?: unknown;
  onChange: (value: unknown) => void;
  disabled: boolean;
  acceptsTokens: boolean;
  /** Resolved required state (after applying #states). Drives the input's required attribute. */
  required: boolean;
  /** Id of the rendered field label, for widgets that are not labelable elements. */
  labelId?: string;
  /** When true, the textarea should switch to the YAML editor (no schema). */
  useYaml?: boolean;
  /** When true (and useYaml is true), validate YAML syntax while typing. */
  validateYaml?: boolean;
}> = ({ field, value, sourceValue, onChange, disabled, acceptsTokens, required, labelId, useYaml, validateYaml }) => {
  const currentValue = value ?? field.default_value ?? '';
  // String-coerced view of the current value for string-based widgets
  // (ContentEditableField, native text/number/select inputs).
  const stringValue = typeof currentValue === 'string' ? currentValue : String(currentValue ?? '');

  // Drupal enforces #maxlength natively on real inputs, which get the
  // attribute below. ContentEditableField has no such attribute, so the limit
  // is applied to the value on its way out instead - the submitted value is
  // what matters, and an over-long paste is truncated the same way.
  const emitChange = (next: unknown): void => {
    if (field.maxlength && typeof next === 'string' && next.length > field.maxlength) {
      onChange(next.slice(0, field.maxlength));
      return;
    }
    onChange(next);
  };

  // Schema-derived format wins over the raw element type: a field whose config
  // schema declares a Json constraint gets the JSON editor regardless of which
  // Drupal form element produced it. The JSON editor reuses the schema-less
  // YamlEditor in JSON mode (textarea + inline validation + Format action).
  if (field.format === 'json') {
    return (
      <YamlEditor
        id={`config-field-${field.key}`}
        value={typeof currentValue === 'string' ? currentValue : ''}
        onChange={onChange}
        disabled={disabled}
        format="json"
        validate
      />
    );
  }

  // Same contract for YAML: the field's format, not its element type, picks
  // the schema-less YAML editor with inline syntax validation.
  if (field.format === 'yaml') {
    return (
      <YamlEditor
        id={`config-field-${field.key}`}
        value={coerceToYaml(currentValue)}
        onChange={onChange}
        disabled={disabled}
        format="yaml"
        validate
      />
    );
  }

  switch (field.type) {
    case 'machine_name':
      return (
        <MachineNameField
          field={field}
          value={stringValue}
          sourceValue={sourceValue}
          onChange={onChange}
          disabled={disabled}
          required={required}
        />
      );

    case 'textfield':
    case 'email':
    case 'url':
      return (
        <ContentEditableField
          value={stringValue}
          onChange={emitChange}
          className="form-control"
          placeholder={field.placeholder || t('Enter @field...', { '@field': field.title || field.type })}
          disabled={disabled}
          multiline={false}
          acceptsTokens={acceptsTokens}
          ariaLabelledBy={labelId}
        />
      );

    case 'textarea': {
      // If the backend provided an inline YAML schema, render the
      // structured editor. The schema is discovered automatically from
      // Drupal config schema at "yaml.{plugin_schema_key}.{field_key}".
      if (field.yaml_schema) {
        return (
          <YamlEditor
            value={coerceToYaml(currentValue)}
            onChange={onChange}
            schema={field.yaml_schema}
            disabled={disabled}
          />
        );
      }

      // When the use_yaml checkbox is checked for this textarea, render
      // a schema-less YAML editor (raw YAML mode only, with optional
      // syntax validation controlled by validate_yaml).
      if (useYaml) {
        return (
          <YamlEditor
            id={`config-field-${field.key}`}
            value={coerceToYaml(currentValue)}
            onChange={onChange}
            disabled={disabled}
            validate={validateYaml}
          />
        );
      }

      return (
        <ContentEditableField
          value={stringValue}
          onChange={emitChange}
          className="form-control"
          placeholder={field.placeholder || t('Enter @field...', { '@field': field.title || t('text') })}
          disabled={disabled}
          multiline={true}
          acceptsTokens={acceptsTokens}
          ariaLabelledBy={labelId}
        />
      );
    }

    case 'number': {
      // Use ContentEditableField when tokens are accepted, or when the
      // current value already contains a token pattern (to prevent data
      // loss when loading models with token values in number fields).
      const hasTokenValue = typeof currentValue === 'string' && /\[.+:.+\]/.test(currentValue);
      if (acceptsTokens || hasTokenValue) {
        return (
          <ContentEditableField
            value={stringValue}
            onChange={emitChange}
            className="form-control"
            placeholder={field.placeholder || t('Enter @field...', { '@field': field.title || field.type })}
            disabled={disabled}
            multiline={false}
            acceptsTokens={acceptsTokens}
            ariaLabelledBy={labelId}
          />
        );
      }
      return (
        <input
          id={`config-field-${field.key}`}
          type="number"
          value={stringValue}
          onChange={(e) => onChange(e.target.value)}
          className="form-control"
          min={field.min}
          max={field.max}
          step={field.step}
          required={required}
          disabled={disabled}
        />
      );
    }

    case 'checkbox':
      return (
        <label className="checkbox-wrapper">
          <input
            type="checkbox"
            checked={!!currentValue}
            onChange={(e) => onChange(e.target.checked)}
            required={required}
            disabled={disabled}
          />
          <span className="checkbox-label">{field.title}</span>
        </label>
      );

    case 'select':
      return (
        <select
          id={`config-field-${field.key}`}
          value={stringValue}
          onChange={(e) => onChange(e.target.value)}
          className="form-control"
          required={required}
          disabled={disabled}
        >
          {field.empty_option && (
            <option value={field.empty_option.value}>{field.empty_option.label}</option>
          )}
          {field.options && Object.entries(field.options).map(([optionKey, optionLabel]) => (
            <option key={optionKey} value={optionKey}>
              {optionLabel}
            </option>
          ))}
        </select>
      );

    case 'radios':
      return (
        <div className="radio-group">
          {field.options && Object.entries(field.options).map(([optionKey, optionLabel]) => (
            <label key={optionKey} className="radio-wrapper">
              <input
                type="radio"
                name={field.key}
                value={optionKey}
                checked={stringValue === optionKey}
                onChange={(e) => onChange(e.target.value)}
                required={required}
                disabled={disabled}
              />
              <span className="radio-label">{optionLabel}</span>
            </label>
          ))}
        </div>
      );

    case 'checkboxes': {
      const checkboxValues: string[] = Array.isArray(currentValue) ? (currentValue as string[]) : [];
      return (
        <div className="checkbox-group">
          {field.options && Object.entries(field.options).map(([optionKey, optionLabel]) => (
            <label key={optionKey} className="checkbox-wrapper">
              <input
                type="checkbox"
                value={optionKey}
                checked={checkboxValues.includes(optionKey)}
                onChange={(e) => {
                  const newValues = e.target.checked
                    ? [...checkboxValues, optionKey]
                    : checkboxValues.filter((v: string) => v !== optionKey);
                  onChange(newValues);
                }}
                disabled={disabled}
              />
              <span className="checkbox-label">{optionLabel}</span>
            </label>
          ))}
        </div>
      );
    }

    case 'markup':
      return (
        <div className="markup-content" dangerouslySetInnerHTML={{ __html: sanitizeHtml(field.markup) }} />
      );

    default:
      return (
        <input
          id={`config-field-${field.key}`}
          type="text"
          value={stringValue}
          onChange={(e) => onChange(e.target.value)}
          className="form-control"
          maxLength={field.maxlength}
          required={required}
          disabled={disabled}
        />
      );
  }
};

/**
 * Collapsible/static container wrapping a group's child fields.
 *
 * `details` groups render as native <details>/<summary> (collapsible,
 * honoring the backend `open` flag); `fieldset`/`container` groups render as a
 * static titled container.
 */
const FieldGroup: React.FC<{
  field: FormField;
  children: React.ReactNode;
}> = ({ field, children }) => {
  if (field.type === 'group' && field.open !== undefined) {
    // Details-style group: collapsible via native disclosure widget.
    return (
      <details className="form-group form-group-details" open={field.open}>
        {field.title && <summary className="form-group-title">{field.title}</summary>}
        <div className="form-group-body">{children}</div>
      </details>
    );
  }
  // Fieldset / container style: static titled container.
  return (
    <div className="form-group">
      {field.title && <div className="form-group-title">{field.title}</div>}
      <div className="form-group-body">{children}</div>
    </div>
  );
};

const ConfigurationForm: React.FC<ConfigurationFormProps> = ({
  form,
  configuration,
  onChange,
  disabled = false
}) => {
  // Initialize state with configuration
  const [values, setValues] = useState<Record<string, unknown>>(configuration || {});

  const handleFieldChange = useCallback((fieldKey: string, value: unknown) => {
    const newValues = { ...values, [fieldKey]: value };
    setValues(newValues);
    if (onChange) {
      onChange(newValues);
    }
  }, [values, onChange]);

  // Determine whether all fields accept tokens (when replace_tokens checkbox is checked)
  const replaceTokensEnabled = useMemo(() => {
    if (!form || !Array.isArray(form)) return false;
    // Look through nested groups too, since replace_tokens may live in a group.
    const hasReplaceTokens = (fields: FormField[]): boolean =>
      fields.some(
        (f) =>
          (f.key === 'replace_tokens' && f.type === 'checkbox') ||
          (f.children ? hasReplaceTokens(f.children) : false)
      );
    if (!hasReplaceTokens(form)) return false;
    return !!values.replace_tokens;
  }, [form, values.replace_tokens]);

  // Build a lookup: for each textarea key, find whether a use_yaml and/or
  // validate_yaml checkbox targets it via the yaml_field annotation. Walks
  // nested group children so linked checkboxes can live inside a group.
  const yamlFieldMap = useMemo(() => {
    const map: Record<string, { useYamlKey: string; validateYamlKey: string }> = {};
    const walk = (fields: FormField[]): void => {
      for (const f of fields) {
        if (f.key === 'use_yaml' && f.yaml_field) {
          if (!map[f.yaml_field]) map[f.yaml_field] = { useYamlKey: '', validateYamlKey: '' };
          map[f.yaml_field].useYamlKey = f.key;
        }
        if (f.key === 'validate_yaml' && f.yaml_field) {
          if (!map[f.yaml_field]) map[f.yaml_field] = { useYamlKey: '', validateYamlKey: '' };
          map[f.yaml_field].validateYamlKey = f.key;
        }
        if (f.children) walk(f.children);
      }
    };
    if (form && Array.isArray(form)) walk(form);
    return map;
  }, [form]);

  /**
   * Recursively render a single field (or group of fields). Group children
   * reuse this same path, so #states visibility/required and token support
   * apply to nested children too. All values stay flat, keyed by field key.
   */
  const renderField = useCallback((field: FormField): React.ReactNode => {
    // A field accepts tokens if replace_tokens is checked globally,
    // or if the field has token_support set to true.
    const fieldAcceptsTokens = replaceTokensEnabled || !!field.token_support;

    // Resolve visibility / required from the generic #states engine.
    const { hidden: stateHidden, required } = resolveFieldState(field, values);

    // Minimal fallback for ECA's validate_yaml checkbox: when the backend
    // does NOT supply #states for it, keep the legacy behavior of hiding it
    // until use_yaml is checked. When the backend DOES supply states, the
    // generic engine above already handles it.
    const yamlFallbackHidden =
      !field.states &&
      field.key === 'validate_yaml' &&
      !!field.yaml_field &&
      !values.use_yaml;

    const hideField = stateHidden || yamlFallbackHidden;

    // Group field: render the container + recurse into children.
    if (field.type === 'group' && field.children) {
      return (
        <div
          key={field.key}
          className="form-field form-field-group"
          style={hideField ? { display: 'none' } : undefined}
        >
          <FieldGroup field={field}>
            {field.children.map((child) => renderField(child))}
          </FieldGroup>
        </div>
      );
    }

    // Determine use_yaml / validate_yaml state for textarea fields.
    const yamlLink = field.type === 'textarea' ? yamlFieldMap[field.key] : undefined;
    const useYaml = yamlLink ? !!values[yamlLink.useYamlKey] : false;
    const validateYaml = yamlLink ? !!values[yamlLink.validateYamlKey] : false;

    // The label is only rendered for field types that have a separate title.
    // Widgets that are not labelable elements (the contenteditable fields) are
    // named through this id instead of htmlFor, which browsers and the
    // accessibility tree only honor for real form controls.
    const hasLabel = field.type !== 'checkbox' && field.type !== 'markup' && !!field.title;
    const labelId = `config-field-${field.key}-label`;

    return (
      <div
        key={field.key}
        className="form-field"
        style={hideField ? { display: 'none' } : undefined}
      >
        {hasLabel && (
          <label className="field-label" id={labelId} htmlFor={`config-field-${field.key}`}>
            {field.title}
            {required && <span className="required">*</span>}
          </label>
        )}

        {field.type === 'markup' && field.title && (
          <h4 className="markup-title">{field.title}</h4>
        )}

        <div className="field-input">
          <FormFieldRenderer
            field={field}
            value={values[field.key]}
            sourceValue={field.source ? values[field.source] : undefined}
            onChange={(value) => handleFieldChange(field.key, value)}
            disabled={disabled || !!field.disabled}
            acceptsTokens={fieldAcceptsTokens}
            required={required}
            labelId={hasLabel ? labelId : undefined}
            useYaml={useYaml}
            validateYaml={validateYaml}
          />
        </div>

        {field.description && (
          <div className="field-description" dangerouslySetInnerHTML={{ __html: sanitizeHtml(field.description) }} />
        )}
      </div>
    );
  }, [values, disabled, replaceTokensEnabled, yamlFieldMap, handleFieldChange]);

  if (!form || !Array.isArray(form)) {
    return <div className="no-configuration">{t('No configuration available')}</div>;
  }

  return (
    <div className="configuration-form">
      {form.map((field) => renderField(field))}
    </div>
  );
};

export default ConfigurationForm;
