/*!
 * Project Name: Morphos (morpht/morphos)
 * File: components/menu_mega/menu_mega.js
 * Copyright (c) 2026 Morpht Pty Ltd
 */

/**
 * Accessible megamenu initialization.
 */

((Drupal, once, $) => {
  Drupal.behaviors.menuMega = {
    attach: (context) => {
      once('menuMega', '.js-megamenu', context).forEach((nav) => {
        $(nav).accessibleMegaMenu({
          uuidPrefix: 'morphos-megamenu',
          menuClass: 'megamenu__nav-menu',
          topNavItemClass: 'megamenu__nav-item',
          panelClass: 'megamenu__sub-nav',
          panelGroupClass: 'megamenu__sub-nav-group',
          hoverClass: 'megamenu--hover',
          focusClass: 'megamenu--focus',
          openClass: 'megamenu--open',
        });
      });
    },

    detach: (context) => {
      once.remove('menuMega', '.js-megamenu', context).forEach((nav) => {
        $(nav).accessibleMegaMenu('destroy');
      });
    },
  };
})(Drupal, once, jQuery);
