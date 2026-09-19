/*!
 * Project Name: Morphos (morpht/morphos)
 * File: components/menu_sidebar/menu_sidebar.js
 * Copyright (c) 2026 Morpht Pty Ltd
 */

((Drupal, once) => {
  Drupal.behaviors.menu_sidebar = {
    attach: (context) => {
      once('menu_sidebar', '.menu-sidebar__toggle', context).forEach(
        (button) => {
          button.addEventListener('click', (e) => {
            e.preventDefault();
            const li = button.closest('li');
            const dropdown = li.querySelector('.menu-dropdown');
            const iconCollapsed = button.querySelector(
              '.menu-sidebar__icon--collapsed',
            );
            const iconExpanded = button.querySelector(
              '.menu-sidebar__icon--expanded',
            );
            const isExpanded = button.getAttribute('aria-expanded') === 'true';

            dropdown.classList.toggle('menu-dropdown-show');
            iconCollapsed.classList.toggle('hidden');
            iconExpanded.classList.toggle('hidden');
            button.setAttribute('aria-expanded', !isExpanded);
          });
        },
      );
    },
  };
})(Drupal, once);
