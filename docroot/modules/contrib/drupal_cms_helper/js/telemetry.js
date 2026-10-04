(function (Drupal, once) {
  "use strict";

  /**
   * Opens the telemetry opt-in dialog on page load.
   *
   * The dialog is non-modal: it stays out of the way, pinned to a corner of
   * the screen, and doesn't block the page or show a backdrop.
   */
  Drupal.behaviors.telemetryOptIn = {
    attach: function (context) {
      console.log(context);
      // The form gets its ID from Drupal, so find the dialog that wraps it.
      once('telemetry-opt-in', 'dialog:has(#drupal-cms-telemetry-opt-in-form)', context).forEach((dialog) => {
        dialog.show();
      });
    },
  };
})(Drupal, once);
