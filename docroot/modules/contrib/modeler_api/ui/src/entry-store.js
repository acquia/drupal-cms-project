/**
 * @file
 * Simple data store for resolved template token entries.
 *
 * Provides a module-level lookup so any component can retrieve the full
 * entry data (label, config tokens, hidden_config) for a given object ID.
 *
 * Object IDs are composite keys: "modelOwnerId:modelId:componentId".
 */

/**
 * Map of object IDs to their full entry data.
 *
 * @type {Map<string, Object>}
 */
const entries = new Map();

/**
 * Registers an entry in the store.
 *
 * @param {Object} entry - The full entry from drupalSettings containing
 *   model_owner_id, model_id, component_id, select, config, label,
 *   hidden_config, etc.
 */
export function registerEntry(entry) {
  const id = entry.model_owner_id + ':' + entry.model_id + ':' + entry.component_id;
  entries.set(id, entry);
}

/**
 * Retrieves an entry by its composite object ID.
 *
 * @param {string} objectId - The composite key "modelOwnerId:modelId:componentId".
 * @returns {Object|undefined} The entry data, or undefined if not found.
 */
export function getEntry(objectId) {
  return entries.get(objectId);
}

/**
 * Retrieves multiple entries by their object IDs.
 *
 * @param {string[]} objectIds - An array of composite object IDs.
 * @returns {Object[]} The matched entries (skipping any not found).
 */
export function getEntries(objectIds) {
  const result = [];
  for (const id of objectIds) {
    const entry = entries.get(id);
    if (entry) {
      result.push(entry);
    }
  }
  return result;
}

/**
 * Dropdown items indexed by the element data-attribute key they belong to.
 *
 * Each key maps to an array of dropdown items that should appear in the
 * popup for elements carrying that key in their data-template-token-select
 * attribute.
 *
 * @type {Map<string, Array<Object>>}
 */
const dropdownItemsByKey = new Map();

/**
 * Registers a dropdown item and associates it with a data-attribute key.
 *
 * The key is a synthetic identifier used to link the item to marked DOM
 * elements via the same data-template-token-select attribute that template
 * token entries use.
 *
 * @param {string} key - The synthetic key for associating with DOM elements.
 * @param {Object} item - The dropdown item data (label, url, is_new).
 */
export function registerDropdownItem(key, item) {
  if (!dropdownItemsByKey.has(key)) {
    dropdownItemsByKey.set(key, []);
  }
  dropdownItemsByKey.get(key).push(item);
}

/**
 * Retrieves all dropdown items for a given set of data-attribute keys.
 *
 * Collects dropdown items from all keys present on a DOM element,
 * deduplicating by URL.
 *
 * @param {string[]} keys - Data-attribute keys from the element.
 * @returns {Array<Object>} The matched dropdown items.
 */
export function getDropdownItems(keys) {
  var result = [];
  var seen = new Set();
  for (var i = 0; i < keys.length; i++) {
    var items = dropdownItemsByKey.get(keys[i]);
    if (items) {
      for (var j = 0; j < items.length; j++) {
        if (!seen.has(items[j].url)) {
          seen.add(items[j].url);
          result.push(items[j]);
        }
      }
    }
  }
  return result;
}

/**
 * Previously applied template records from drupalSettings.
 *
 * Each record contains model_owner_id, component_id, target,
 * hidden_config, and config.
 *
 * @type {Array<Object>}
 */
let appliedTemplates = [];

/**
 * Registers the list of previously applied templates.
 *
 * @param {Array<Object>} templates - The applied template records from
 *   drupalSettings.modelerApiAppliedTemplates.
 */
export function registerAppliedTemplates(templates) {
  appliedTemplates = templates || [];
}

/**
 * Checks whether a template has been previously applied to a given target.
 *
 * Matching compares model_owner_id, component_id, target, and every
 * key/value pair in hidden_config. The config values are NOT compared
 * (they may have changed).
 *
 * @param {string} modelOwnerId - The model owner plugin ID.
 * @param {string} componentId - The component ID.
 * @param {string} target - The target value (e.g. form field name).
 * @param {Object} hiddenConfig - The hidden config key/value pairs.
 * @returns {Object|null} The matching applied template record (including
 *   its config), or null if not found.
 */
export function findAppliedTemplate(modelOwnerId, componentId, target, hiddenConfig) {
  for (var i = 0; i < appliedTemplates.length; i++) {
    var applied = appliedTemplates[i];

    if (applied.model_owner_id !== modelOwnerId) {
      continue;
    }
    if (applied.component_id !== componentId) {
      continue;
    }
    if (applied.target !== target) {
      continue;
    }

    // Compare every key/value pair in hidden_config.
    var appliedHidden = applied.hidden_config || {};
    var currentHidden = hiddenConfig || {};
    var appliedKeys = Object.keys(appliedHidden);
    var currentKeys = Object.keys(currentHidden);

    if (appliedKeys.length !== currentKeys.length) {
      continue;
    }

    var match = true;
    for (var j = 0; j < appliedKeys.length; j++) {
      var key = appliedKeys[j];
      if (appliedHidden[key] !== currentHidden[key]) {
        match = false;
        break;
      }
    }

    if (match) {
      return applied;
    }
  }

  return null;
}

/**
 * Clears all stored entries, applied templates, and dropdown items.
 */
export function clearEntries() {
  entries.clear();
  dropdownItemsByKey.clear();
  appliedTemplates = [];
}
