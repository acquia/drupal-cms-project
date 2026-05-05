/**
 * @file
 * JavaScript behavior for UI Icons picker selector in Drupal.
 */
/* eslint-disable no-unused-vars, func-names */
(($, Drupal, once) => {
  function openDialog(event) {
    event.preventDefault();

    const element = event.target || event.srcElement;

    const ajaxSettings = {
      element,
      progress: { type: 'none' },
      url: element.getAttribute('data-dialog-url'),
      dialogType: 'modal',
      httpMethod: 'GET',
      dialog: {
        classes: {
          'ui-dialog': 'icon-library-widget-modal',
        },
        title: Drupal.t('Select icon'),
        height: '95%',
        width: '95%',
        query: {
          wrapper_id: element.getAttribute('data-wrapper-id'),
          allowed_icon_pack: element.getAttribute('data-allowed-icon-pack'),
        },
      },
    };

    const myAjaxObject = Drupal.ajax(ajaxSettings);
    myAjaxObject.execute();
  }

  /**
   * Attaches the Icon dialog behavior to all required fields.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the Icon dialog behaviors.
   */
  Drupal.behaviors.icon_dialog = {
    attach(context) {
      once('dialog', 'input.form-icon-dialog', context).forEach((element) => {
        element.addEventListener('click', openDialog);
      });
    },
  };

  /**
   * Updates the icon library selection.
   *
   * @param {Object} ajax
   *   The AJAX object.
   * @param {Object} response
   *   The response object from the AJAX call.
   * @param {string} response.wrapper_id
   *   The ID of the wrapper element.
   * @param {string} response.icon_full_id
   *   The full ID of the icon.
   * @param {string} status
   *   The status of the AJAX call.
   */
  Drupal.AjaxCommands.prototype.updateIconLibrarySelection = function (
    ajax,
    response,
    status,
  ) {
    const elem = document.querySelector(
      `#${response.wrapper_id} input[name$='icon_id]']`,
    );
    elem.value = response.icon_full_id;
    jQuery(elem).trigger('change');
  };
})(jQuery, Drupal, once);
