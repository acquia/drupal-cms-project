/*!
 * Project Name: Morphos (morpht/morphos)
 * File: components/alert/alert.js
 * Copyright (c) 2026 Morpht Pty Ltd
 */

((Drupal, once) => {
  Drupal.behaviors.alert = {
    attach: (context) => {
      once('alert', '.alert__close', context).forEach((element) => {
        const alert = element.closest('.alert');

        element.addEventListener('click', (event) => {
          event.preventDefault();
          if (alert) {
            alert.remove();
          }
        });
      });
    },
  };
})(Drupal, once);
