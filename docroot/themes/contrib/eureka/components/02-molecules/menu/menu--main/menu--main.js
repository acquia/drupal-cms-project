/**
 * @file Main menu JS
 *
 * JS Behaviors for handling the main menu.
 */
Drupal.behaviors.EurekaMainMenu = {
  attach(context) {
    const menus = once('EurekaMainMenu', '.menu--main', context);

    if (!menus.length) return;

    menus.forEach((menu) => {
      const expanders = menu.querySelectorAll('.js-submenu-expand');

      if (expanders.length > 0) {
        expanders.forEach((expander) => {
          const parent = expander.parentElement;
          const dropdown = expander.nextElementSibling;

          expander.addEventListener('click', (event) => {
            const target = event.currentTarget;
            const ariaExpanded = target.getAttribute('aria-expanded');
            const windowWidth = window.innerWidth;

            target.setAttribute('aria-expanded', ariaExpanded === 'false');
            dropdown.classList.toggle('is-expanded');

            if (
              windowWidth > 1024 &&
              dropdown.getBoundingClientRect().right > windowWidth
            ) {
              dropdown.classList.add('left-aligned');
            }
          });

          // Close desktop submenus on focus out.
          parent.addEventListener('focusout', (event) => {
            event.stopPropagation();
            if (!dropdown.contains(event.relatedTarget)) {
              expander.setAttribute('aria-expanded', 'false');
              dropdown.classList.remove('is-expanded');
            }
          });
        });
      }
    });
  },
};
