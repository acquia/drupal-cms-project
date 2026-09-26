(function ($, Drupal, once) {
  'use strict';

  /**
   * Automatically propagate accordion_id from parent to child accordion items.
   *
   * This ensures that all accordion items within an accordion container
   * have the correct data-bs-parent attribute set, even if accordion_id wasn't
   * explicitly passed as a prop to each accordion_item component.
   */
  Drupal.behaviors.provusAccordionCanvas = {
    attach: function (context, settings) {
      // Find all accordion containers with data-accordion-id attribute.
      once('provus-accordion-canvas', '.accordion[data-accordion-id]', context).forEach(function (accordionElement) {
        const $accordion = $(accordionElement);
        const accordionId = $accordion.attr('data-accordion-id');
        
        if (accordionId) {
          // Find all accordion collapse elements within this container.
          const $collapseElements = $accordion.find('.accordion-collapse');
          
          // Set data-bs-parent attribute on each collapse element.
          $collapseElements.each(function () {
            const $collapse = $(this);
            // Only set if it doesn't already have a data-bs-parent attribute
            // (to allow manual override if needed).
            if (!$collapse.attr('data-bs-parent')) {
              $collapse.attr('data-bs-parent', '#accordion-' + accordionId);
            }
          });
        }
      });
    }
  };
})(jQuery, Drupal, once);
