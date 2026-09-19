/**
 * @file Header JS
 *
 * JS Behaviors for handling the site header.
 */

Drupal.behaviors.EurekaHeader = {
  attach(context) {
    const header = once('EurekaHeader', '.js-header', context)[0];

    // If there is no header in the context, finish the execution.
    if (!header) return;

    const menu = header.querySelector('#header-menu');
    const open = header.querySelector('.js-header-open');
    const close = header.querySelector('.js-header-close');

    if (open) {
      open.addEventListener('click', () => {
        Drupal.behaviors.EurekaHeader.openHeaderMenu(menu, open);
      });
    }

    if (close) {
      close.addEventListener('click', () => {
        Drupal.behaviors.EurekaHeader.closeHeaderMenu(menu, open);
      });
    }
  },

  /**
   * Opens the header menu.
   * @param {HTMLElement} menu - The header element.
   * @param {HTMLElement} open - The open button.
   */
  openHeaderMenu(menu, open) {
    document.body.classList.add('header-is-open');
    menu.classList.add('is-open');
    open.setAttribute('aria-expanded', true);
    window.headerContext = Drupal.tabbingManager.constrain(menu, {
      trapFocus: true,
    });
  },

  /**
   * Closes the header menu.
   * @param {HTMLElement} menu - The header element.
   * @param {HTMLElement} open - The open button.
   */
  closeHeaderMenu(menu, open) {
    document.body.classList.remove('header-is-open');
    menu.classList.remove('is-open');
    open.setAttribute('aria-expanded', false);
    window.headerContext.release();
  },
};
