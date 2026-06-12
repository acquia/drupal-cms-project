/**
 * @file
 * Preact component for the focus widget (lightning bolt icon + popup).
 *
 * Rendered next to focusable elements that have template token selections.
 * Clicking the indicator opens a popup listing the templates associated
 * with the element. Selecting a template shows its configuration form.
 */

import { h } from 'preact';
import { useState, useEffect, useRef } from 'preact/hooks';
import { TokenPopup } from './token-popup';

/**
 * The data attribute prefix for purpose-specific metadata.
 *
 * @type {string}
 */
const ATTR_PREFIX = 'data-template-token-';

/**
 * Lightning bolt SVG icon component.
 *
 * A small inline SVG rendered as the visual indicator. Uses currentColor
 * so it inherits the color from CSS.
 *
 * @returns {import('preact').VNode} The SVG element.
 */
function LightningIcon() {
  return h('svg', {
    width: '16',
    height: '16',
    viewBox: '0 0 16 16',
    fill: 'currentColor',
    'aria-hidden': 'true',
    class: 'modeler-api-token-widget__icon',
  },
    h('path', {
      d: 'M9.5 1L3 9h4.5L6.5 15 13 7H8.5L9.5 1z',
    })
  );
}

/**
 * Extracts object IDs and dropdown keys from a target element's data attributes.
 *
 * Reads the data-template-token-select attribute (a JSON map of
 * {tokenPath: [objectId, ...]}) and separates regular object IDs from
 * dropdown keys (which start with '__dropdown__'). If the element itself
 * does not carry the attribute, walks up the DOM tree to find the nearest
 * ancestor that does.
 *
 * @param {Element} element - The DOM element (or its focusable descendant).
 * @returns {{objectIds: string[], dropdownKeys: string[]}} Unique object IDs
 *   and dropdown keys associated with this element.
 */
function getObjectIdsAndDropdownKeys(element) {
  // Walk up to find the element carrying the select attribute.
  var el = element;
  var selectAttr = null;
  while (el) {
    selectAttr = el.getAttribute(ATTR_PREFIX + 'select');
    if (selectAttr) {
      break;
    }
    el = el.parentElement;
  }

  if (!selectAttr) {
    return { objectIds: [], dropdownKeys: [] };
  }

  var selectMap;
  try {
    selectMap = JSON.parse(selectAttr);
  }
  catch (e) {
    return { objectIds: [], dropdownKeys: [] };
  }

  var ids = new Set();
  var dkeys = new Set();
  for (var path in selectMap) {
    if (Array.isArray(selectMap[path])) {
      for (var i = 0; i < selectMap[path].length; i++) {
        var value = selectMap[path][i];
        if (value.indexOf('__dropdown__') === 0) {
          dkeys.add(path);
        }
        else {
          ids.add(value);
        }
      }
    }
  }
  return { objectIds: Array.from(ids), dropdownKeys: Array.from(dkeys) };
}

/**
 * Focus widget component.
 *
 * Displays a lightning bolt indicator. Clicking it opens a popup listing
 * the templates associated with the focused element. The popup remains
 * open until dismissed via click-outside or Escape.
 *
 * @param {Object} props
 * @param {Element} props.targetElement - The DOM element this widget is
 *   attached to.
 * @returns {import('preact').VNode} The widget element.
 */
export function FocusWidget({ targetElement, onDismiss }) {
  const [open, setOpen] = useState(false);
  const widgetRef = useRef(null);

  /**
   * Closes the popup and notifies the parent that the widget can be
   * dismissed if the target element no longer has focus.
   */
  function closePopup() {
    setOpen(false);
    if (onDismiss) {
      // Defer so the Preact render cycle completes before the parent
      // checks for the popup DOM node.
      setTimeout(onDismiss, 0);
    }
  }

  // Close on Escape key.
  useEffect(function () {
    if (!open) {
      return;
    }

    function onKeyDown(e) {
      if (e.key === 'Escape') {
        closePopup();
      }
    }

    document.addEventListener('keydown', onKeyDown);
    return function () {
      document.removeEventListener('keydown', onKeyDown);
    };
  }, [open]);

  // Close on click outside the widget.
  useEffect(function () {
    if (!open) {
      return;
    }

    function onClick(e) {
      if (widgetRef.current && !widgetRef.current.contains(e.target)) {
        closePopup();
      }
    }

    // Use setTimeout so the current click event (the one that opened the
    // popup) does not immediately close it.
    var timer = setTimeout(function () {
      document.addEventListener('click', onClick, true);
    }, 0);
    return function () {
      clearTimeout(timer);
      document.removeEventListener('click', onClick, true);
    };
  }, [open]);

  /**
   * Prevents the mousedown on the indicator from stealing focus away
   * from the target element, which would trigger blur and unmount.
   */
  function handleIndicatorMouseDown(e) {
    e.preventDefault();
  }

  function handleIndicatorClick(e) {
    e.preventDefault();
    e.stopPropagation();
    if (open) {
      closePopup();
    }
    else {
      setOpen(true);
    }
  }

  var data = targetElement
    ? getObjectIdsAndDropdownKeys(targetElement)
    : { objectIds: [], dropdownKeys: [] };

  var hasContent = data.objectIds.length > 0 || data.dropdownKeys.length > 0;

  return h('span', {
    ref: widgetRef,
    class: 'modeler-api-token-widget__wrapper',
  },
    h('span', {
      class: 'modeler-api-token-widget__indicator',
      title: 'Template actions available',
      onMouseDown: handleIndicatorMouseDown,
      onClick: handleIndicatorClick,
      role: 'button',
      tabIndex: -1,
      'aria-expanded': open ? 'true' : 'false',
    },
      h(LightningIcon, null)
    ),
    open && hasContent
      ? h(TokenPopup, {
          objectIds: data.objectIds,
          dropdownKeys: data.dropdownKeys,
          targetElement: targetElement,
          onClose: closePopup,
        })
      : null
  );
}
