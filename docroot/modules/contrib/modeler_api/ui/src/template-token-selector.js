/**
 * @file
 * Core logic for applying template tokens to DOM elements.
 *
 * Processes resolved token data from the backend and uses the incremental
 * CSS selector chains to find and mark DOM elements with data attributes.
 *
 * The input data from drupalSettings is an array of object entries, each
 * identified by model_owner_id, model_id, and component_id. Each entry
 * contains 'select' and 'config' arrays of resolved tokens.
 *
 * For select-purpose tokens, this module walks the CSS selector chain to
 * find matching DOM elements and marks them with data attributes. Focusable
 * elements receive a lightning bolt widget on focus to indicate that
 * additional actions are available.
 */

import { h, render } from 'preact';
import { registerEntry, registerDropdownItem } from './entry-store';
import { FocusWidget } from './focus-widget';

/**
 * The data attribute name used to mark elements with token paths.
 *
 * @type {string}
 */
const ATTR_TOKEN = 'data-template-token';

/**
 * The data attribute prefix for purpose-specific metadata.
 *
 * @type {string}
 */
const ATTR_PREFIX = 'data-template-token-';

/**
 * CSS class added to the widget container element.
 *
 * @type {string}
 */
const WIDGET_CLASS = 'modeler-api-token-widget';

/**
 * Selector for focusable elements.
 *
 * @type {string}
 */
const FOCUSABLE = 'input, select, textarea, button, [tabindex], a[href], [contenteditable="true"]';

/**
 * WeakMap tracking elements that already have focus listeners attached.
 *
 * @type {WeakMap<Element, boolean>}
 */
const managedElements = new WeakMap();

/**
 * Applies template token selections to the DOM.
 *
 * Iterates over all object entries from drupalSettings and processes their
 * select-purpose tokens. Each matched DOM element receives attributes that
 * identify which tokens and which objects (model owner + model + component)
 * are associated with it. Focusable matched elements get a lightning bolt
 * widget shown on focus.
 *
 * @param {Array<Object>} entries - Resolved object entries from drupalSettings.
 * @param {Document|Element} context - The DOM context to search within.
 */
export function applyTemplateTokens(entries, context) {
  if (!entries || !Array.isArray(entries)) {
    return;
  }

  const root = context === document ? document.documentElement : context;

  for (const entry of entries) {
    const objectId = entry.model_owner_id + ':' + entry.model_id + ':' + entry.component_id;

    // Register entry in the store so popup components can look it up.
    registerEntry(entry);

    // Process select-purpose tokens.
    if (entry.select && Array.isArray(entry.select)) {
      for (const token of entry.select) {
        applySelectToken(token, objectId, entry, root);
      }
    }

    // Config-purpose tokens are not applied to DOM elements.
    // They are available in drupalSettings for other JS to consume.
  }
}

/**
 * Applies a single select-purpose token to the DOM.
 *
 * Walks the incremental selector chain: starting from the root, each
 * selector narrows the set of candidate elements by querying within the
 * elements matched by the previous selector.
 *
 * @param {Object} token - The resolved token data with selectors array.
 * @param {string} objectId - The composite object identifier
 *   (modelOwnerId:modelId:componentId).
 * @param {Object} entry - The full object entry (for label/config access).
 * @param {Element} root - The root element to start selection from.
 */
function applySelectToken(token, objectId, entry, root) {
  const selectors = token.selectors;

  if (!selectors || !Array.isArray(selectors) || selectors.length === 0) {
    return;
  }

  // Start with the root as the initial set of candidate elements.
  let currentElements = [root];

  for (const selector of selectors) {
    const nextElements = [];

    for (const element of currentElements) {
      try {
        const matches = element.querySelectorAll(selector);
        for (const match of matches) {
          nextElements.push(match);
        }
      }
      catch (e) {
        // Invalid CSS selector - skip silently.
        console.warn(
          '[modeler_api] Invalid CSS selector in token "' +
            token.path +
            '": ' +
            selector,
          e
        );
        return;
      }
    }

    currentElements = nextElements;

    if (currentElements.length === 0) {
      // No matches at this level - stop drilling down.
      return;
    }
  }

  // Mark all final matched elements.
  for (const element of currentElements) {
    markElement(element, token, objectId);
    attachFocusWidget(element);
  }
}

/**
 * Marks a DOM element with template token data attributes.
 *
 * Supports multiple tokens and multiple objects per element by appending
 * to existing attribute values rather than replacing them.
 *
 * Applied attributes:
 * - data-template-token: Space-separated list of all token paths.
 * - data-template-token-select: JSON object mapping token paths to arrays
 *   of object IDs that reference this element via that token.
 * - data-template-token-name: JSON object mapping token paths to their
 *   human-readable names.
 *
 * @param {Element} element - The DOM element to mark.
 * @param {Object} token - The resolved token data.
 * @param {string} objectId - The composite object identifier.
 */
function markElement(element, token, objectId) {
  const path = token.path;

  // Append to the main token attribute (space-separated paths).
  const existing = element.getAttribute(ATTR_TOKEN) || '';
  const paths = existing ? existing.split(' ') : [];
  if (!paths.includes(path)) {
    paths.push(path);
    element.setAttribute(ATTR_TOKEN, paths.join(' '));
  }

  // Append to the select attribute: maps token paths to arrays of object IDs.
  const selectAttr = ATTR_PREFIX + 'select';
  const existingSelect = element.getAttribute(selectAttr);
  let selectMap;
  try {
    selectMap = existingSelect ? JSON.parse(existingSelect) : {};
  }
  catch (e) {
    selectMap = {};
  }
  if (!selectMap[path]) {
    selectMap[path] = [];
  }
  if (!selectMap[path].includes(objectId)) {
    selectMap[path].push(objectId);
  }
  element.setAttribute(selectAttr, JSON.stringify(selectMap));

  // Set the human-readable name.
  if (token.name) {
    const nameAttr = ATTR_PREFIX + 'name';
    const existingNames = element.getAttribute(nameAttr);
    let names;
    try {
      names = existingNames ? JSON.parse(existingNames) : {};
    }
    catch (e) {
      names = {};
    }
    names[path] = token.name;
    element.setAttribute(nameAttr, JSON.stringify(names));
  }
}

/**
 * Attaches focus/blur listeners to a focusable element.
 *
 * If the element itself is focusable, listeners are attached directly.
 * Otherwise, focusable descendants are searched and each gets listeners.
 * The listeners show/hide a lightning bolt widget via Preact rendering.
 *
 * Each element is only processed once (tracked via WeakMap).
 *
 * @param {Element} element - The matched element to potentially attach to.
 */
function attachFocusWidget(element) {
  if (isFocusable(element)) {
    addListeners(element);
  }
  else {
    // The matched element itself is not focusable (e.g. a .form-item
    // wrapper). Look for focusable descendants.
    const focusableChildren = element.querySelectorAll(FOCUSABLE);
    for (const child of focusableChildren) {
      // Only attach if the child itself or an ancestor up to (but not
      // including) the current element carries the token attribute.
      // This ensures we don't attach to unrelated focusable elements
      // that happen to be inside the container.
      if (child.hasAttribute(ATTR_TOKEN) || hasTokenAncestor(child, element)) {
        addListeners(child);
      }
    }
  }
}

/**
 * Checks whether an element is focusable.
 *
 * @param {Element} el - The element to check.
 * @returns {boolean} TRUE if the element can receive focus.
 */
function isFocusable(el) {
  return el.matches(FOCUSABLE);
}

/**
 * Checks if any ancestor between child and boundary has the token attribute.
 *
 * @param {Element} child - The starting element.
 * @param {Element} boundary - The boundary element (exclusive).
 * @returns {boolean} TRUE if a token-marked ancestor exists.
 */
function hasTokenAncestor(child, boundary) {
  let current = child.parentElement;
  while (current && current !== boundary) {
    if (current.hasAttribute(ATTR_TOKEN)) {
      return true;
    }
    current = current.parentElement;
  }
  return boundary.hasAttribute(ATTR_TOKEN);
}

/**
 * Adds focus and blur event listeners to an element.
 *
 * On focus, a widget container is created (or reused) and the Preact
 * FocusWidget component is rendered into it. The FocusWidget manages
 * its own popup lifecycle (click to open, click-outside/Escape to close).
 *
 * On blur, the indicator is hidden only if the popup is not currently
 * open. If the popup is open, it stays visible until explicitly closed.
 *
 * @param {Element} element - The focusable element.
 */
function addListeners(element) {
  if (managedElements.has(element)) {
    return;
  }
  managedElements.set(element, true);

  let container = null;

  /**
   * Renders the widget into its container, passing the cleanup callback.
   */
  function renderWidget() {
    render(h(FocusWidget, {
      targetElement: element,
      onDismiss: dismissWidget,
    }), container);
  }

  /**
   * Unmounts the widget when neither the element has focus nor the popup
   * is open. Called from the FocusWidget when the popup closes.
   */
  function dismissWidget() {
    if (container && document.activeElement !== element) {
      render(null, container);
    }
  }

  element.addEventListener('focus', function () {
    if (!container) {
      container = document.createElement('span');
      container.className = WIDGET_CLASS;
      element.parentNode.insertBefore(container, element.nextSibling);
    }
    renderWidget();
  });

  element.addEventListener('blur', function () {
    if (!container) {
      return;
    }
    // Defer the check: when the user clicks the indicator or the popup,
    // the blur fires before the click. By deferring we give the browser
    // time to update document.activeElement and let the click land.
    setTimeout(function () {
      // Keep the widget if focus moved into the widget container (the
      // indicator or the popup) or if the popup is open.
      if (container.contains(document.activeElement)) {
        return;
      }
      var popup = container.querySelector('.modeler-api-token-popup');
      if (popup) {
        return;
      }
      render(null, container);
    }, 0);
  });
}

/**
 * Applies dropdown items to DOM elements found via CSS selector chains.
 *
 * Iterates over all dropdown item entries from drupalSettings and uses
 * their incremental CSS selector chains to find and mark DOM elements
 * with data attributes — the same mechanism used by applyTemplateTokens.
 * This causes the lightning bolt focus widget to appear on matched
 * elements. The dropdown items are then rendered as link entries inside
 * the template token popup alongside any regular template entries.
 *
 * @param {Array<Object>} items - Dropdown items from drupalSettings.
 *   Each item has:
 *   - selectors: CSS selector chain (array of strings).
 *   - target: Target attribute selector (e.g. '[name]').
 *   - label: Human-readable label for the link.
 *   - url: URL to navigate to when the item is clicked.
 *   - is_new: Whether this links to creating a new model.
 * @param {Document|Element} context - The DOM context to search within.
 */
export function applyDropdownItems(items, context) {
  if (!items || !Array.isArray(items)) {
    return;
  }

  var root = context === document ? document.documentElement : context;

  for (var i = 0; i < items.length; i++) {
    applyDropdownItem(items[i], root, i);
  }
}

/**
 * Applies a single dropdown item to matched DOM elements.
 *
 * Walks the incremental CSS selector chain to find matching elements,
 * marks them with data attributes so the focus widget appears, and
 * registers the item in the entry store so the popup can render it.
 *
 * @param {Object} item - The dropdown item data.
 * @param {Element} root - The root element to start selection from.
 * @param {number} index - The item's index for generating a unique key.
 */
function applyDropdownItem(item, root, index) {
  var selectors = item.selectors;

  if (!selectors || !Array.isArray(selectors) || selectors.length === 0) {
    return;
  }

  // Walk the selector chain to find matching elements.
  var currentElements = [root];

  for (var s = 0; s < selectors.length; s++) {
    var selector = selectors[s];
    var nextElements = [];

    for (var e = 0; e < currentElements.length; e++) {
      try {
        var matches = currentElements[e].querySelectorAll(selector);
        for (var m = 0; m < matches.length; m++) {
          nextElements.push(matches[m]);
        }
      }
      catch (err) {
        console.warn(
          '[modeler_api] Invalid CSS selector in dropdown item: ' + selector,
          err
        );
        return;
      }
    }

    currentElements = nextElements;

    if (currentElements.length === 0) {
      return;
    }
  }

  // Generate a synthetic key for this dropdown item. This key is used
  // in the data-template-token-select attribute to link DOM elements to
  // the dropdown item in the entry store.
  var dropdownKey = '__dropdown__' + index;

  // Register the item in the store so the popup can retrieve it.
  registerDropdownItem(dropdownKey, item);

  // Mark all matched elements with data attributes and attach the
  // focus widget, using the same mechanism as template token entries.
  for (var j = 0; j < currentElements.length; j++) {
    markDropdownElement(currentElements[j], dropdownKey);
    attachFocusWidget(currentElements[j]);
  }
}

/**
 * Marks a DOM element with a dropdown item's data-attribute key.
 *
 * Uses the same data-template-token-select attribute as regular template
 * tokens, so the focus widget and popup can discover dropdown items via
 * the same mechanism. The key is stored under a synthetic token path
 * so it does not collide with real token paths.
 *
 * @param {Element} element - The DOM element to mark.
 * @param {string} dropdownKey - The synthetic key for the dropdown item.
 */
function markDropdownElement(element, dropdownKey) {
  // Append to the main token attribute (space-separated paths).
  var existing = element.getAttribute(ATTR_TOKEN) || '';
  var paths = existing ? existing.split(' ') : [];
  if (!paths.includes(dropdownKey)) {
    paths.push(dropdownKey);
    element.setAttribute(ATTR_TOKEN, paths.join(' '));
  }

  // Append to the select attribute: map the dropdown key to itself
  // so the popup can discover it.
  var selectAttr = ATTR_PREFIX + 'select';
  var existingSelect = element.getAttribute(selectAttr);
  var selectMap;
  try {
    selectMap = existingSelect ? JSON.parse(existingSelect) : {};
  }
  catch (e) {
    selectMap = {};
  }
  if (!selectMap[dropdownKey]) {
    selectMap[dropdownKey] = [dropdownKey];
  }
  element.setAttribute(selectAttr, JSON.stringify(selectMap));
}
