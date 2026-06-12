/**
 * @file
 * JavaScript behavior for UI Icons preview in Drupal.
 */
/* eslint-disable no-restricted-syntax, func-names, no-continue */
((Drupal, drupalSettings, once) => {
  /**
   * @namespace
   */
  Drupal.Icon = {};

  Drupal.Icon.loadIconPreview = async function (data) {
    try {
      const iconData = await fetch(Drupal.url('ui-icons/ajax/preview/icons'), {
        method: 'POST',
        body: JSON.stringify(data),
      });
      if (!iconData.ok) {
        // eslint-disable-next-line no-console
        console.error('Cannot retrieve icon data!');
        return;
      }
      const iconsPreview = await iconData.json();
      for (const [iconFullId, iconPreview] of Object.entries(iconsPreview)) {
        // Standard mode, direct replacement.
        if (!data.target_input_label) {
          const iconTarget = document.querySelector(
            `.icon-preview-load[data-icon-id='${iconFullId}']`,
          );
          iconTarget.outerHTML = iconPreview;
          continue;
        }

        // Form with input mode, for icon picker.
        const iconTarget = document.querySelector(
          `.icon-preview-load[value='${iconFullId}']`,
        );
        if (!iconTarget) {
          continue;
        }
        const iconLabel = document.querySelector(
          `label[for='${iconTarget.id}']`,
        );
        iconLabel.innerHTML = iconPreview;
      }
    } catch (err) {
      // eslint-disable-next-line no-console
      console.error(`Something went wrong! ${err.message}`);
    }
  };

  /**
   * UI Icons preview loader.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.IconPreview = {
    attach(context, settings) {
      once('loadIconPreview', 'body', context).forEach(() => {
        if (!settings.ui_icons_preview_data) {
          return;
        }
        Drupal.Icon.loadIconPreview(settings.ui_icons_preview_data);
      });
    },
  };
})(Drupal, drupalSettings, once);
