/**
 * @file
 * JavaScript behavior for UI Icons picker library in Drupal.
 */
/* eslint-disable no-restricted-syntax, max-nested-callbacks, no-unused-vars, func-names, no-continue */
((Drupal, drupalSettings, once) => {
  /**
   * UI Icons picker library search.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.IconPickerLibrarySearch = {
    attach(context) {
      let typingTimer;
      const typingInterval = 600;

      // Auto submit filter by name.
      once('setIconPickerSearch', '.icon-filter-input', context).forEach(
        (element) => {
          element.addEventListener('keypress', function (event) {
            if (event.keyCode === 13) {
              document
                .querySelector('.icon-ajax-search-submit')
                .dispatchEvent(new MouseEvent('mousedown'));
            }
          });

          element.addEventListener('keyup', function () {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(function () {
              document
                .querySelector('.icon-ajax-search-submit')
                .dispatchEvent(new MouseEvent('mousedown'));
            }, typingInterval);
          });

          element.addEventListener('keydown', function () {
            clearTimeout(typingTimer);
          });
        },
      );

      // Submit the form when clicked any icon.
      once('setIconPick', '.icon-preview-load', context).forEach((element) => {
        element.addEventListener('click', function (event) {
          document.querySelector('.icon-ajax-select-submit').click();
        });
      });
    },
  };

  /**
   * UI Icons picker library preview.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.IconPickerLibraryPreview = {
    attach(context, settings) {
      once('loadIconPreview', '.icon-picker-modal__content', context).forEach(
        () => {
          if (!settings.ui_icons_preview_data) {
            return;
          }
          Drupal.Icon.loadIconPreview(settings.ui_icons_preview_data);
        },
      );
    },
  };
})(Drupal, drupalSettings, once);
