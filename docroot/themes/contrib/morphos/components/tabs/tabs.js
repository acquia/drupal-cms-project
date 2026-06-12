/*!
 * Project Name: Morphos (morpht/morphos)
 * File: components/tabs/tabs.js
 * Copyright (c) 2026 Morpht Pty Ltd
 */

((Drupal, once) => {
  Drupal.behaviors.tabs_group = {
    attach: (context) => {
      once('tabs_group', '[data-tabs-id]', context).forEach((group) => {
        const groupId = group.dataset.tabsId;

        // Unwrap Canvas slot wrappers so label.tab and .tab-content
        // divs become direct children of .tabs (required by DaisyUI CSS).
        Array.from(group.children).forEach((child) => {
          if (
            !child.classList.contains('tab') &&
            !child.classList.contains('tab-content')
          ) {
            while (child.firstChild) {
              group.insertBefore(child.firstChild, child);
            }
            child.remove();
          }
        });

        // Assign shared name to all radio inputs for mutual exclusivity.
        const radios = group.querySelectorAll(
          ':scope > label.tab input[type="radio"]',
        );
        radios.forEach((radio) => {
          radio.setAttribute('name', `tabs--${groupId}`);
        });

        // Auto-check first tab if none is checked.
        const checked = group.querySelector(
          ':scope > label.tab input[type="radio"]:checked',
        );
        if (!checked && radios.length > 0) {
          radios[0].checked = true;
        }
      });
    },
  };
})(Drupal, once);
