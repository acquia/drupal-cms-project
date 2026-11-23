(function (Drupal) {
  /**
   * Command to send a gtag event from an Ajax response.
   *
   * @param {Drupal.Ajax} [ajax]
   *   {@link Drupal.Ajax} object created by {@link Drupal.ajax}.
   * @param {object} response
   *   The response from the Ajax request.
   * @param {string} response.event_name
   *   The event name
   * @param {object} response.data
   *   The value of the event.
   */
  Drupal.AjaxCommands.prototype.gtagEvent = function (ajax, response) {
    // gtag.js is not aggregated, so content blockers can block it by file
    // name. Without gtag() the event cannot be sent, but throwing here would
    // stop the rest of the Ajax commands from running.
    if (typeof gtag !== 'function') {
      return;
    }
    gtag('event', response.event_name, response.data);
  };
})(Drupal);
