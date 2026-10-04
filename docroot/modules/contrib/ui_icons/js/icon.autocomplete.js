/**
 * @file
 * JavaScript behavior for UI Icons autocomplete selector in Drupal.
 */
(($, Drupal, once) => {
  /**
   * UI Icons autocomplete tweaks.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.IconAutocompleteSelect = {
    attach(context) {
      once(
        'setIconAutocompletePreview',
        '.ui-icons-wrapper .ui-icons-input-wrapper input',
        context,
      )
        .filter(
          (iconSelector) => typeof $(iconSelector).autocomplete() === 'object',
        )
        .forEach((iconSelector) => {
          jQuery(iconSelector).autocomplete('option', {
            delay: 500,
            minLength: 2,
          });
        });
    },
  };
})(jQuery, Drupal, once);
