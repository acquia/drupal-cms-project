/**
 * @file
 * Preact component for selecting form fields from the DOM.
 *
 * Scans the current page for form elements and presents them as selectable
 * options. Supports both single-select and multi-select modes.
 *
 * When a `target` CSS selector is provided (e.g. ':is(input, select,
 * textarea)[name]'), each form field's identifying value is extracted by
 * running the target selector within the field's .form-item wrapper and
 * reading the attribute specified in the bracket part of the selector.
 * This target value is carried through as a hidden identifier alongside
 * the user's selection.
 */

import { h } from 'preact';
import { useMemo } from 'preact/hooks';

/**
 * @typedef {Object} FormFieldInfo
 * @property {string} name - The field's name attribute (used as internal key).
 * @property {string} label - Human-readable label for display.
 * @property {string} type - The field type (text, select, textarea, etc.).
 * @property {string} targetValue - The value extracted via the target selector.
 */

/**
 * Extracts the attribute name from a CSS selector's bracket part.
 *
 * Given a selector like ':is(input, select, textarea)[name]', this
 * returns 'name'. If no bracket attribute is found, returns null.
 *
 * @param {string} selector - The CSS selector string.
 * @returns {string|null} The attribute name, or null.
 */
function extractTargetAttribute(selector) {
  // Match the last [attr] pattern in the selector, excluding value
  // selectors like [attr="value"].
  var match = selector.match(/\[([a-zA-Z_][\w-]*)\]\s*$/);
  return match ? match[1] : null;
}

/**
 * Strips the trailing attribute selector from a CSS selector string.
 *
 * Given ':is(input, select, textarea)[name]', returns
 * ':is(input, select, textarea)'. This produces a selector that can
 * be used with querySelectorAll to find elements.
 *
 * @param {string} selector - The CSS selector string.
 * @returns {string} The selector without the trailing bracket part.
 */
function stripTargetAttribute(selector) {
  return selector.replace(/\[[a-zA-Z_][\w-]*\]\s*$/, '').trim();
}

/**
 * Scans the DOM for form fields and returns a list of field descriptors.
 *
 * When a target selector is provided, it is used to find the target
 * element within each field's .form-item wrapper and extract the
 * identifying attribute value.
 *
 * @param {string|null} target - Optional target CSS selector
 *   (e.g. ':is(input, select, textarea)[name]').
 * @returns {FormFieldInfo[]} Deduplicated list of form fields.
 */
function scanFormFields(target) {
  var fields = [];
  var seen = new Set();

  var targetAttr = target ? extractTargetAttribute(target) : null;
  var targetSelector = target ? stripTargetAttribute(target) : null;

  var elements = document.querySelectorAll(
    'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="image"]), select, textarea'
  );

  for (var i = 0; i < elements.length; i++) {
    var el = elements[i];
    var name = el.getAttribute('name');
    if (!name || seen.has(name)) {
      continue;
    }
    seen.add(name);

    var label = findLabel(el);
    var type = el.tagName.toLowerCase() === 'input'
      ? (el.getAttribute('type') || 'text')
      : el.tagName.toLowerCase();

    // Resolve the target value for this field.
    var targetValue = name;
    if (targetAttr && targetSelector) {
      var context = el.closest('.form-item') || el.parentElement;
      if (context) {
        try {
          var targetEl = context.querySelector(targetSelector);
          if (targetEl) {
            targetValue = targetEl.getAttribute(targetAttr) || name;
          }
        }
        catch (e) {
          // Invalid selector — fall back to name.
        }
      }
    }
    else if (targetAttr) {
      // Target selector is just an attribute selector like [name].
      targetValue = el.getAttribute(targetAttr) || name;
    }

    fields.push({
      name: name,
      label: label || name,
      type: type,
      targetValue: targetValue,
    });
  }

  return fields;
}

/**
 * Finds a human-readable label for a form element.
 *
 * @param {Element} el - The form element.
 * @returns {string} The label text, or empty string if not found.
 */
function findLabel(el) {
  // 1. Try the associated <label> via the "for" attribute.
  var id = el.getAttribute('id');
  if (id) {
    var label = document.querySelector('label[for="' + CSS.escape(id) + '"]');
    if (label) {
      return label.textContent.trim();
    }
  }

  // 2. Try wrapping <label>.
  var parent = el.closest('label');
  if (parent) {
    var clone = parent.cloneNode(true);
    var inputs = clone.querySelectorAll('input, select, textarea');
    for (var i = 0; i < inputs.length; i++) {
      inputs[i].remove();
    }
    var text = clone.textContent.trim();
    if (text) {
      return text;
    }
  }

  // 3. Try the .form-item wrapper's label (Drupal convention).
  var formItem = el.closest('.form-item');
  if (formItem) {
    var itemLabel = formItem.querySelector('label');
    if (itemLabel) {
      return itemLabel.textContent.trim();
    }
  }

  // 4. Placeholder.
  var placeholder = el.getAttribute('placeholder');
  if (placeholder) {
    return placeholder;
  }

  return '';
}

/**
 * Builds a map from field name to target value.
 *
 * @param {FormFieldInfo[]} fields - The scanned field list.
 * @returns {Object<string, string>} Map of name → targetValue.
 */
function buildTargetMap(fields) {
  var map = {};
  for (var i = 0; i < fields.length; i++) {
    map[fields[i].name] = fields[i].targetValue;
  }
  return map;
}

/**
 * Single-select field selector component.
 *
 * Renders a <select> dropdown populated with form fields from the DOM.
 *
 * @param {Object} props
 * @param {string} props.value - Currently selected field name.
 * @param {function} props.onChange - Callback(selectedName) when selection changes.
 * @param {function} props.onTargetChange - Callback(targetValues) with the
 *   resolved target values for the current selection.
 * @param {string} props.target - The target CSS selector from the token definition.
 * @param {string} props.id - The HTML id for the select element.
 * @returns {import('preact').VNode}
 */
export function SingleFieldSelector({ value, onChange, onTargetChange, target, id }) {
  var fields = useMemo(function () {
    return scanFormFields(target);
  }, [target]);
  var targetMap = useMemo(function () {
    return buildTargetMap(fields);
  }, [fields]);

  function handleChange(e) {
    var selected = e.target.value;
    onChange(selected);
    if (onTargetChange) {
      if (selected && targetMap[selected]) {
        onTargetChange([targetMap[selected]]);
      }
      else {
        onTargetChange([]);
      }
    }
  }

  return h('select', {
    id: id,
    class: 'modeler-api-config-form__input modeler-api-field-selector',
    value: value || '',
    onChange: handleChange,
  },
    h('option', { value: '' }, '- Select a field -'),
    fields.map(function (field) {
      return h('option', {
        key: field.name,
        value: field.name,
      }, field.label + ' (' + field.type + ')');
    })
  );
}

/**
 * Multi-select field selector component.
 *
 * Renders a list of checkboxes, one per form field found in the DOM.
 * Selected field names are stored as comma-separated values.
 *
 * @param {Object} props
 * @param {string} props.value - Comma-separated list of selected field names.
 * @param {function} props.onChange - Callback(commaSeparatedString) on change.
 * @param {function} props.onTargetChange - Callback(targetValues) with the
 *   resolved target values for the current selection.
 * @param {string} props.target - The target CSS selector from the token definition.
 * @param {string} props.id - The HTML id prefix for checkbox elements.
 * @returns {import('preact').VNode}
 */
export function MultiFieldSelector({ value, onChange, onTargetChange, target, id }) {
  var fields = useMemo(function () {
    return scanFormFields(target);
  }, [target]);
  var targetMap = useMemo(function () {
    return buildTargetMap(fields);
  }, [fields]);
  var selected = useMemo(function () {
    if (!value) {
      return new Set();
    }
    return new Set(value.split(',').map(function (s) { return s.trim(); }).filter(Boolean));
  }, [value]);

  function handleToggle(fieldName) {
    var next = new Set(selected);
    if (next.has(fieldName)) {
      next.delete(fieldName);
    }
    else {
      next.add(fieldName);
    }
    var nextNames = Array.from(next);
    onChange(nextNames.join(','));
    if (onTargetChange) {
      var targets = [];
      for (var i = 0; i < nextNames.length; i++) {
        if (targetMap[nextNames[i]]) {
          targets.push(targetMap[nextNames[i]]);
        }
      }
      onTargetChange(targets);
    }
  }

  if (fields.length === 0) {
    return h('div', {
      class: 'modeler-api-field-selector__empty',
    }, 'No form fields found on this page.');
  }

  return h('div', {
    class: 'modeler-api-field-selector__list',
    role: 'group',
    'aria-label': 'Select fields',
  },
    fields.map(function (field) {
      var checkId = id + '--' + field.name;
      var isChecked = selected.has(field.name);

      return h('label', {
        key: field.name,
        class: 'modeler-api-field-selector__option'
          + (isChecked ? ' modeler-api-field-selector__option--checked' : ''),
        for: checkId,
      },
        h('input', {
          type: 'checkbox',
          id: checkId,
          class: 'modeler-api-field-selector__checkbox',
          checked: isChecked,
          onChange: function () {
            handleToggle(field.name);
          },
        }),
        h('span', { class: 'modeler-api-field-selector__label' },
          field.label
        ),
        h('span', { class: 'modeler-api-field-selector__type' },
          field.type
        )
      );
    })
  );
}
