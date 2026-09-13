/**
 * @file
 * Preact component for the template token configuration form.
 *
 * Renders a frontend form based on an entry's config-purpose tokens.
 * Each config token becomes a form field. The field type is determined
 * by the token path:
 * - Paths containing ':form:field' use a single-select field picker.
 * - Paths containing ':form:fields' use a multi-select field picker.
 * - All other tokens use a plain text input.
 *
 * The token_label (trailing segments from the original token string) is
 * used as the field label. Falls back to the token's name property.
 *
 * When a token carries a 'target' property, the field selector uses it
 * to extract a hidden identifying value from each form field element.
 * These target values are stored separately and included in save data.
 *
 * Hidden config data is retained in the entry but not displayed.
 */

import { h } from 'preact';
import { SingleFieldSelector, MultiFieldSelector } from './field-selector';

/**
 * Returns the full token path including the token_label suffix.
 *
 * The resolved token path from the backend stops at the deepest matched
 * node. If the original token string had trailing segments (captured as
 * token_label), they must be appended to reconstruct the full path that
 * the backend uses as the canonical config key.
 *
 * @param {Object} token - The config token data.
 * @returns {string} The full token path.
 */
function fullPath(token) {
  if (token.token_label) {
    return token.path + ':' + token.token_label;
  }
  return token.path;
}

/**
 * Tests whether a token path contains a given segment pattern.
 *
 * Checks for the pattern both as an infix (followed by ':') and as a
 * suffix (at the end of the path).
 *
 * @param {string} path - The colon-separated token path.
 * @param {string} segment - The segment pattern to look for, including
 *   leading colon (e.g. ':form:fields').
 * @returns {boolean} TRUE if the pattern is found.
 */
function pathContains(path, segment) {
  return path.indexOf(segment + ':') !== -1
    || path.length >= segment.length
       && path.indexOf(segment) === path.length - segment.length;
}

/**
 * Determines the widget type for a config token based on its path.
 *
 * @param {Object} token - The config token data.
 * @returns {string} One of 'single-field', 'multi-field', or 'text'.
 */
function getWidgetType(token) {
  var path = token.path || '';
  if (pathContains(path, ':form:fields')) {
    return 'multi-field';
  }
  if (pathContains(path, ':form:field')) {
    return 'single-field';
  }
  return 'text';
}

/**
 * Configuration form component.
 *
 * Displays a form with fields derived from the entry's config-purpose
 * tokens. Includes an "Apply" toggle so the user can opt in or out of
 * applying this particular template.
 *
 * Config field values, target values, and the applied state are managed
 * by the parent (TokenPopup) so they persist across template switches.
 *
 * @param {Object} props
 * @param {Object} props.entry - The full entry data from the store.
 * @param {Object} props.values - Current config values keyed by token path.
 * @param {function} props.onValueChange - Callback(path, value) when a field changes.
 * @param {function} props.onTargetChange - Callback(path, targetValues) when
 *   target values change for a field selector.
 * @param {boolean} props.applied - Whether this template is marked as applied.
 * @param {function} props.onAppliedChange - Callback(boolean) to toggle applied state.
 * @returns {import('preact').VNode|null} The form element.
 */
export function ConfigForm({ entry, values, onValueChange, onTargetChange, applied, onAppliedChange }) {
  var configTokens = entry.config || [];

  if (configTokens.length === 0) {
    return h('div', { class: 'modeler-api-config-form' },
      h('div', { class: 'modeler-api-config-form__apply' },
        h('label', { class: 'modeler-api-config-form__apply-label' },
          h('input', {
            type: 'checkbox',
            class: 'modeler-api-config-form__apply-checkbox',
            checked: applied,
            onChange: function (e) {
              onAppliedChange(e.target.checked);
            },
          }),
          'Apply this template'
        )
      ),
      h('div', { class: 'modeler-api-config-form__empty' },
        'No configuration available.'
      )
    );
  }

  return h('div', { class: 'modeler-api-config-form' },
    h('div', { class: 'modeler-api-config-form__apply' },
      h('label', { class: 'modeler-api-config-form__apply-label' },
        h('input', {
          type: 'checkbox',
          class: 'modeler-api-config-form__apply-checkbox',
          checked: applied,
          onChange: function (e) {
            onAppliedChange(e.target.checked);
          },
        }),
        'Apply this template'
      )
    ),
    h('div', { class: 'modeler-api-config-form__fields' },
      configTokens.map(function (token) {
        var configKey = fullPath(token);
        return h(ConfigField, {
          key: configKey,
          token: token,
          configKey: configKey,
          value: values[configKey] !== undefined ? values[configKey] : '',
          onValueChange: onValueChange,
          onTargetChange: onTargetChange,
        });
      })
    )
  );
}

/**
 * A single configuration field derived from a config token.
 *
 * The field label comes from token_label (the trailing segments the
 * modeler author provided, e.g. "Default value" or "Fields to hide"),
 * falling back to the token's name. The widget type is determined by
 * the token path. When the token has a 'target' property, it is passed
 * to the field selector to extract hidden identifying values.
 *
 * @param {Object} props
 * @param {Object} props.token - The config token data.
 * @param {string} props.value - Current field value.
 * @param {function} props.onValueChange - Callback(path, value).
 * @param {function} props.onTargetChange - Callback(path, targetValues).
 * @returns {import('preact').VNode} The field element.
 */
function ConfigField({ token, configKey, value, onValueChange, onTargetChange }) {
  var label = token.token_label || token.name || token.path;
  var description = token.value || '';
  var widgetType = getWidgetType(token);
  var fieldId = 'modeler-api-cfg-' + configKey;

  function handleChange(newValue) {
    onValueChange(configKey, newValue);
  }

  function handleTargetChange(targetValues) {
    if (onTargetChange) {
      onTargetChange(configKey, targetValues);
    }
  }

  var widget;
  if (widgetType === 'single-field') {
    widget = h(SingleFieldSelector, {
      value: value,
      onChange: handleChange,
      onTargetChange: handleTargetChange,
      target: token.target || null,
      id: fieldId,
    });
  }
  else if (widgetType === 'multi-field') {
    widget = h(MultiFieldSelector, {
      value: value,
      onChange: handleChange,
      onTargetChange: handleTargetChange,
      target: token.target || null,
      id: fieldId,
    });
  }
  else {
    widget = h('input', {
      type: 'text',
      id: fieldId,
      class: 'modeler-api-config-form__input',
      value: value,
      placeholder: description,
      onInput: function (e) {
        handleChange(e.target.value);
      },
      'data-token-path': token.path,
    });
  }

  return h('div', { class: 'modeler-api-config-form__field' },
    h('label', {
      class: 'modeler-api-config-form__label',
      for: fieldId,
    }, label),
    widget,
    description && widgetType === 'text'
      ? h('div', { class: 'modeler-api-config-form__description' }, description)
      : null
  );
}
