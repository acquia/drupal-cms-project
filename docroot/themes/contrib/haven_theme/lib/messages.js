/**
 * @file
 * Customization of messages.
 */

((Drupal, once) => {
  /**
   * Adds a close button to the message.
   *
   * @param {HTMLElement} message
   *   The message element.
   */
  const closeMessage = (message) => {
    const closeBtn = message.querySelector("[data-message-close]");
    if (closeBtn) {
      closeBtn.addEventListener("click", () => {
        // Add animation classes for fade out and slide up.
        message.classList.add("opacity-0", "-translate-y-2");

        // Wait for animation to complete before hiding.
        message.addEventListener(
          "transitionend",
          () => {
            message.classList.add("hidden");
          },
          { once: true },
        );
      });
    }
  };

  /**
   * Get messages from context.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the close button behavior for messages.
   */
  Drupal.behaviors.byteThemeMessages = {
    attach(context) {
      once("byte-theme-messages", "[data-message-item]", context).forEach(closeMessage);
    },
  };
})(Drupal, once);
