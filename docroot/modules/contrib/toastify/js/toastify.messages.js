/**
 * @file
 * Message template overrides.
 */

((Drupal) => {
  /**
   * Overrides message theme function.
   *
   * @param {object} message
   *   The message object.
   * @param {string} message.text
   *   The message text.
   * @param {object} options
   *   The message context.
   * @param {string} options.type
   *   The message type.
   * @param {string} options.id
   *   ID of the message, for reference.
   *
   * @return {HTMLElement}
   *   A DOM Node.
   */
  Drupal.theme.message = ({ text }, { type, id }) => {
    const messagesTypes = Drupal.Message.getMessageTypeLabels();
    const messageWrapper = document.createElement('div');

    if (!drupalSettings.toastify) {
      // If Toastify is not enabled, use the default message theme.
      messageWrapper.setAttribute('class', `messages messages--${type}`);
      messageWrapper.setAttribute(
        'role',
        type === 'error' || type === 'warning' ? 'alert' : 'status',
      );
      messageWrapper.setAttribute('data-drupal-message-id', id);
      messageWrapper.setAttribute('data-drupal-message-type', type);

      messageWrapper.setAttribute('aria-label', messagesTypes[type]);

      messageWrapper.innerHTML = `${text}`;

      return messageWrapper;
    }

    const toastifySettings = Drupal.toastify.getDefaultSettings(type);
    toastifySettings.text = text;

    Toastify(toastifySettings).showToast();

    return messageWrapper;
  };
})(Drupal);