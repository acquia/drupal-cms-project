/*!
 * Project Name: Morphos (morpht/morphos)
 * File: components/accordion_group/accordion_group.js
 * Copyright (c) 2026 Morpht Pty Ltd
 */

((Drupal, once) => {
  Drupal.behaviors.accordion_group = {
    attach: (context) => {
      once('accordion_group', '[data-accordion-group-id]', context).forEach(
        (group) => {
          const groupId = group.dataset.accordionGroupId;
          const accordions = group.querySelectorAll('details');
          accordions.forEach((accordion) => {
            accordion.setAttribute('name', `accordion--${groupId}`);
          });
        },
      );
    },
  };
})(Drupal, once);
