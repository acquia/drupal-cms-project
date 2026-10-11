/**
 * @file
 * Preact component for the template token popup.
 *
 * Displays a list of templates (entries) associated with the focused
 * element. Each item shows the entry's label. Clicking an item expands
 * its configuration form. Config field values and applied states are
 * managed here so they persist when switching between templates.
 *
 * A "Save" button at the bottom collects all applied templates and
 * their configuration values for submission.
 */

import { h } from 'preact';
import { useState, useRef, useEffect } from 'preact/hooks';
import { getEntries, getDropdownItems, findAppliedTemplate } from './entry-store';
import { getDrupal } from './drupal-context';
import { ConfigForm } from './config-form';

/**
 * Returns the composite object ID for an entry.
 *
 * @param {Object} entry - The entry data.
 * @returns {string} The composite key.
 */
function getObjectId(entry) {
  return entry.model_owner_id + ':' + entry.model_id + ':' + entry.component_id;
}

/**
 * Generates a display label for an entry.
 *
 * Uses the entry's label if available, otherwise falls back to the
 * component ID.
 *
 * @param {Object} entry - The entry data.
 * @returns {string} The display label.
 */
function getLabel(entry) {
  return entry.label || entry.component_id || 'Unknown';
}

/**
 * Resolves the target value from a DOM element using a target CSS selector.
 *
 * The target selector (e.g. ':is(input, select, textarea)[name]' or '[name]')
 * identifies which element to query and which attribute to read. The last
 * bracket-enclosed attribute name in the selector is the attribute to extract.
 *
 * The function first tries to match the element itself against the selector
 * (without the attribute part). If no match, it searches descendants. For a
 * bare attribute selector like '[name]', the element's own attribute is read
 * directly.
 *
 * @param {Element} element - The DOM element to resolve against.
 * @param {string} targetSelector - The target CSS selector string.
 * @returns {string|null} The extracted attribute value, or null.
 */
function resolveTarget(element, targetSelector) {
  if (!element || !targetSelector) {
    return null;
  }

  // Extract the attribute name from the last [attr] in the selector.
  var attrMatch = targetSelector.match(/\[([a-zA-Z_][\w-]*)\]\s*$/);
  if (!attrMatch) {
    return null;
  }
  var attrName = attrMatch[1];

  // Strip the trailing [attr] to get the element selector part.
  var elemSelector = targetSelector.replace(/\[[a-zA-Z_][\w-]*\]\s*$/, '').trim();

  var targetEl = null;

  if (!elemSelector) {
    // Bare attribute selector like '[name]' — read from the element itself.
    targetEl = element;
  }
  else {
    // Try matching the element itself first.
    try {
      if (element.matches(elemSelector)) {
        targetEl = element;
      }
    }
    catch (e) {
      // Invalid selector.
    }

    // If the element itself doesn't match, try the parent context
    // (e.g. .form-item wrapper) and search within.
    if (!targetEl) {
      var context = element.closest('.form-item') || element.parentElement;
      if (context) {
        try {
          targetEl = context.querySelector(elemSelector);
        }
        catch (e) {
          // Invalid selector.
        }
      }
    }
  }

  if (!targetEl) {
    return null;
  }

  return targetEl.getAttribute(attrName) || null;
}

/**
 * Resolves {{ target }} placeholders in a pre-rendered link HTML string.
 *
 * The backend generates the link including query parameters, but context
 * config values may contain '{{ target }}' placeholders that must be
 * replaced with the actual target value from the DOM element. Since these
 * placeholders appear URL-encoded in the href attribute, this function
 * replaces both the raw and URL-encoded forms.
 *
 * @param {string} linkHtml - The pre-rendered <a> tag HTML string.
 * @param {string} targetSelector - The target CSS selector (e.g. '[name]').
 * @param {Element|null} element - The DOM element the popup is attached to.
 * @returns {string} The link HTML with placeholders resolved.
 */
function resolveDropdownLink(linkHtml, targetSelector, element) {
  if (!element || !targetSelector) {
    return linkHtml;
  }

  var targetValue = resolveTarget(element, targetSelector);
  if (targetValue === null) {
    return linkHtml;
  }

  // Replace both URL-encoded and raw placeholder forms.
  // The backend encodes {{ target }} as part of the JSON query parameter,
  // so in the rendered href it appears URL-encoded.
  var result = linkHtml;
  result = result.replace(/\{\{\s*target\s*\}\}/g, targetValue);
  result = result.replace(/%7B%7B%20target%20%7D%7D/gi, encodeURIComponent(targetValue));
  result = result.replace(/%7B%7Btarget%7D%7D/gi, encodeURIComponent(targetValue));
  result = result.replace(/%7B%7B\+target\+%7D%7D/gi, encodeURIComponent(targetValue));

  return result;
}

/**
 * Attaches Drupal behaviors to a newly inserted DOM element.
 *
 * When server-rendered link HTML is injected via dangerouslySetInnerHTML,
 * Drupal behaviors (such as HTMX processing) are not automatically applied.
 * This function calls Drupal.attachBehaviors() on the element so that any
 * behavior-driven attributes (e.g. HTMX) are activated.
 *
 * @param {Element} element - The DOM element to attach behaviors to.
 */
function attachDrupalBehaviors(element) {
  var Drupal = getDrupal();
  if (Drupal && Drupal.attachBehaviors) {
    Drupal.attachBehaviors(element, window.drupalSettings || {});
  }
}

/**
 * Resolves the target value for an entry from a DOM element.
 *
 * Looks through the entry's select tokens for the first one that has a
 * 'target' property, then uses that selector to extract the identifying
 * value from the DOM element.
 *
 * @param {Object} entry - The entry data with select tokens.
 * @param {Element} element - The DOM element to resolve against.
 * @returns {string|null} The resolved target value, or null if none found.
 */
function resolveEntryTarget(entry, element) {
  var selectTokens = entry.select || [];

  for (var i = 0; i < selectTokens.length; i++) {
    var token = selectTokens[i];
    if (!token.target) {
      continue;
    }
    var value = resolveTarget(element, token.target);
    if (value !== null) {
      return value;
    }
  }

  return null;
}

/**
 * Submits the collected save data to the backend.
 *
 * Fetches a CSRF token from drupalSettings.modeler_api.token_url, then
 * posts the save data to drupalSettings.modeler_api.template_apply_url
 * using Drupal.ajax.
 *
 * @param {Array<Object>} data - The collected save data from collectSaveData().
 */
function submitSaveData(data) {
  var settings = (window.drupalSettings || {}).modeler_api || {};
  var tokenUrl = settings.token_url;
  var applyUrl = settings.template_apply_url;
  var Drupal = getDrupal();

  if (!tokenUrl || !applyUrl) {
    if (typeof console !== 'undefined' && console.warn) {
      console.warn('[modeler_api] Missing token_url or template_apply_url in drupalSettings.modeler_api');
    }
    return;
  }

  if (!Drupal || !Drupal.ajax) {
    if (typeof console !== 'undefined' && console.warn) {
      console.warn('[modeler_api] Drupal.ajax not available');
    }
    return;
  }

  fetch(tokenUrl)
    .then(function (response) {
      if (!response.ok) {
        throw new Error('Token fetch failed: ' + response.status);
      }
      return response.text();
    })
    .then(function (token) {
      var request = Drupal.ajax({
        url: applyUrl,
        submit: JSON.stringify(data),
        beforeSend: function (xhr) {
          xhr.overrideMimeType('application/json;charset=UTF-8');
          xhr.setRequestHeader('X-CSRF-Token', token);
        },
        progress: {
          type: 'fullscreen',
          message: Drupal.t ? Drupal.t('Applying template...') : 'Applying template...',
        },
      });
      request.execute();
    })
    .catch(function (err) {
      if (typeof console !== 'undefined' && console.error) {
        console.error('[modeler_api] Save failed:', err);
      }
    });
}

/**
 * Template token popup component.
 *
 * Shows a panel with a list of templates. When a template is selected,
 * its configuration form is displayed below the list item. Config values
 * and applied flags are held in component state so they persist when the
 * user switches between templates.
 *
 * The "Save" button collects all templates where the "Apply" checkbox is
 * checked, together with their config field values.
 *
 * @param {Object} props
 * @param {string[]} props.objectIds - The object IDs to display.
 * @param {function} props.onClose - Callback to close the popup.
 * @returns {import('preact').VNode|null} The popup element.
 */
export function TokenPopup({ objectIds, dropdownKeys, targetElement, onClose }) {
  var [selectedId, setSelectedId] = useState(null);

  // Config values per object, keyed by objectId → { tokenPath: value }.
  var configValuesRef = useRef({});

  // Target values per object, keyed by objectId → { tokenPath: string[] }.
  // These are hidden identifiers extracted via the target CSS selector.
  var targetValuesRef = useRef({});

  // Applied state per object, keyed by objectId → boolean.
  var appliedRef = useRef({});

  // Force re-render counter (since we use refs for mutable state).
  var [, setTick] = useState(0);
  function forceUpdate() {
    setTick(function (t) { return t + 1; });
  }

  var entries = getEntries(objectIds);
  var dropdownItemsList = getDropdownItems(dropdownKeys || []);

  // Track which entries have a previously applied template match.
  // Keyed by objectId → the matched applied record (or null).
  var appliedMatchRef = useRef({});

  // Track whether initialization has run.
  var initializedRef = useRef(false);

  // On first render, detect previously applied templates and pre-populate.
  useEffect(function () {
    if (initializedRef.current || entries.length === 0) {
      return;
    }
    initializedRef.current = true;

    var needsUpdate = false;
    for (var i = 0; i < entries.length; i++) {
      var entry = entries[i];
      var objectId = getObjectId(entry);
      var resolvedTarget = targetElement
        ? resolveEntryTarget(entry, targetElement)
        : null;
      var match = findAppliedTemplate(
        entry.model_owner_id,
        entry.component_id,
        resolvedTarget,
        entry.hidden_config || {}
      );
      appliedMatchRef.current[objectId] = match;

      if (match) {
        // Pre-populate config values from the previously applied template.
        // Both the applied config and the config form now use the full
        // token path (including the token_label suffix) as the key, so
        // they can be matched directly.
        if (match.config) {
          if (!configValuesRef.current[objectId]) {
            configValuesRef.current[objectId] = {};
          }
          var keys = Object.keys(match.config);
          for (var j = 0; j < keys.length; j++) {
            var key = keys[j];
            if (configValuesRef.current[objectId][key] === undefined) {
              configValuesRef.current[objectId][key] = match.config[key];
            }
          }
        }
        // Auto-check the applied checkbox.
        if (appliedRef.current[objectId] === undefined) {
          appliedRef.current[objectId] = true;
          needsUpdate = true;
        }
      }
    }
    if (needsUpdate) {
      forceUpdate();
    }
  }, [entries, targetElement]);

  if (entries.length === 0 && dropdownItemsList.length === 0) {
    return null;
  }

  function handleItemClick(objectId) {
    setSelectedId(selectedId === objectId ? null : objectId);
  }

  /**
   * Handles a config field value change for a specific object.
   *
   * @param {string} objectId - The object's composite ID.
   * @param {string} tokenPath - The config token path.
   * @param {string} value - The new field value.
   */
  function handleValueChange(objectId, tokenPath, value) {
    if (!configValuesRef.current[objectId]) {
      configValuesRef.current[objectId] = {};
    }
    configValuesRef.current[objectId][tokenPath] = value;
    forceUpdate();
  }

  /**
   * Handles a target value change for a specific object.
   *
   * Target values are hidden identifiers extracted by the field selector
   * using the token's target CSS selector.
   *
   * @param {string} objectId - The object's composite ID.
   * @param {string} tokenPath - The config token path.
   * @param {string[]} targetValues - The resolved target values.
   */
  function handleTargetChange(objectId, tokenPath, targetValues) {
    if (!targetValuesRef.current[objectId]) {
      targetValuesRef.current[objectId] = {};
    }
    targetValuesRef.current[objectId][tokenPath] = targetValues;
  }

  /**
   * Handles the applied toggle for a specific object.
   *
   * @param {string} objectId - The object's composite ID.
   * @param {boolean} applied - Whether the template is applied.
   */
  function handleAppliedChange(objectId, applied) {
    appliedRef.current[objectId] = applied;
    forceUpdate();
  }

  /**
   * Compares two config objects for equality.
   *
   * @param {Object} a - First config object.
   * @param {Object} b - Second config object.
   * @returns {boolean} TRUE if both have the same key/value pairs.
   */
  function configEqual(a, b) {
    var aKeys = Object.keys(a || {});
    var bKeys = Object.keys(b || {});
    if (aKeys.length !== bKeys.length) {
      return false;
    }
    for (var i = 0; i < aKeys.length; i++) {
      var key = aKeys[i];
      if ((a[key] || '') !== (b[key] || '')) {
        return false;
      }
    }
    return true;
  }

  /**
   * Collects templates that have changed compared to the applied state.
   *
   * Skips entries where the Apply checkbox is checked and the config
   * values are identical to the previously applied template (nothing
   * changed). Includes a record with `remove: true` for templates that
   * were previously applied but are now unchecked.
   *
   * @returns {Array<Object>} The collected save data.
   */
  function collectSaveData() {
    var data = [];
    for (var i = 0; i < entries.length; i++) {
      var entry = entries[i];
      var objectId = getObjectId(entry);
      var isApplied = !!appliedRef.current[objectId];
      var match = appliedMatchRef.current[objectId] || null;

      // Resolve the element's target value from the select token definition.
      var elementTarget = targetElement
        ? resolveEntryTarget(entry, targetElement)
        : null;

      if (isApplied) {
        // If this was previously applied and config is unchanged, skip.
        if (match && configEqual(configValuesRef.current[objectId], match.config)) {
          continue;
        }

        var item = {
          model_owner_id: entry.model_owner_id,
          model_id: entry.model_id,
          component_id: entry.component_id,
          config: configValuesRef.current[objectId] || {},
          hidden_config: entry.hidden_config || {},
        };

        // Include config-field target values if any were collected.
        var configTargets = targetValuesRef.current[objectId];
        if (configTargets && Object.keys(configTargets).length > 0) {
          item.config_targets = configTargets;
        }

        // Include the element target value resolved from the select token.
        if (elementTarget !== null) {
          item.target = elementTarget;
        }

        data.push(item);
      }
      else if (match) {
        // Previously applied but now unchecked — tell the backend to remove.
        data.push({
          model_owner_id: entry.model_owner_id,
          model_id: entry.model_id,
          component_id: entry.component_id,
          hidden_config: entry.hidden_config || {},
          target: elementTarget,
          remove: true,
        });
      }
    }
    return data;
  }

  function handleSave(e) {
    e.preventDefault();
    e.stopPropagation();
    var data = collectSaveData();
    if (data.length === 0) {
      return;
    }
    submitSaveData(data);
    onClose();
  }

  // Check if any template is applied.
  var hasApplied = false;
  for (var i = 0; i < entries.length; i++) {
    if (appliedRef.current[getObjectId(entries[i])]) {
      hasApplied = true;
      break;
    }
  }

  return h('div', {
    class: 'modeler-api-token-popup',
    onMouseDown: preventBlur,
  },
    h('div', { class: 'modeler-api-token-popup__header' },
      h('span', { class: 'modeler-api-token-popup__title' }, 'Templates'),
      h('button', {
        class: 'modeler-api-token-popup__close',
        onClick: function (e) {
          e.preventDefault();
          e.stopPropagation();
          onClose();
        },
        title: 'Close',
        type: 'button',
        'aria-label': 'Close popup',
      }, '\u00D7')
    ),
    h('ul', { class: 'modeler-api-token-popup__list' },
      entries.map(function (entry) {
        var objectId = getObjectId(entry);
        var isSelected = objectId === selectedId;
        var isApplied = !!appliedRef.current[objectId];
        var wasPreviouslyApplied = !!appliedMatchRef.current[objectId];

        return h('li', {
          key: objectId,
          class: 'modeler-api-token-popup__item'
            + (isSelected ? ' modeler-api-token-popup__item--active' : '')
            + (isApplied ? ' modeler-api-token-popup__item--applied' : ''),
        },
          h('button', {
            class: 'modeler-api-token-popup__item-btn',
            type: 'button',
            onClick: function (e) {
              e.preventDefault();
              e.stopPropagation();
              handleItemClick(objectId);
            },
          },
            getLabel(entry),
            wasPreviouslyApplied
              ? h('span', {
                  class: 'modeler-api-token-popup__applied-badge',
                  title: 'Previously applied',
                }, '\u2713')
              : null
          ),
          isSelected
            ? h(ConfigForm, {
                entry: entry,
                values: configValuesRef.current[objectId] || {},
                onValueChange: function (path, val) {
                  handleValueChange(objectId, path, val);
                },
                onTargetChange: function (path, targetVals) {
                  handleTargetChange(objectId, path, targetVals);
                },
                applied: isApplied,
                onAppliedChange: function (val) {
                  handleAppliedChange(objectId, val);
                },
              })
            : null
        );
      }),
      dropdownItemsList.map(function (item, idx) {
        var linkHtml = resolveDropdownLink(
          item.link || '',
          item.target || '',
          targetElement
        );
        return h('li', {
          key: '__dropdown__' + idx,
          class: 'modeler-api-token-popup__item modeler-api-token-popup__item--dropdown',
          dangerouslySetInnerHTML: { __html: linkHtml },
          ref: function (node) {
            if (node) {
              attachDrupalBehaviors(node);
            }
          },
        });
      })
    ),
    h('div', { class: 'modeler-api-token-popup__footer' },
      h('button', {
        class: 'modeler-api-token-popup__save',
        type: 'button',
        disabled: !hasApplied,
        onClick: handleSave,
      }, 'Save')
    )
  );
}

/**
 * Prevents mousedown events inside the popup from stealing focus
 * from the target form element, which would trigger the blur handler.
 *
 * Only prevents default on non-interactive elements. Interactive
 * elements (inputs, selects, textareas) and labels that wrap or
 * reference them need their default behavior preserved so that
 * clicking a label still toggles its associated input.
 *
 * @param {Event} e - The mousedown event.
 */
function preventBlur(e) {
  var target = e.target;
  var tag = target.tagName;

  // Allow interactive form elements to receive focus.
  if (tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA') {
    return;
  }

  // Allow clicks on or inside <label> elements so the browser's
  // built-in label-to-input association works (e.g. clicking a label
  // toggles its checkbox).
  if (tag === 'LABEL' || target.closest('label')) {
    return;
  }

  e.preventDefault();
}
