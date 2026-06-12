/*!
 * Project Name: Morphos (morpht/morphos)
 * File: components/back_to_top/back_to_top.js
 * Copyright (c) 2026 Morpht Pty Ltd
 */

((Drupal, once) => {
  const isCanvasEditor = () => {
    try {
      return !!window?.parent?.drupalSettings?.canvas;
    } catch (e) {
      return false;
    }
  };

  Drupal.behaviors.backToTop = {
    attach: (context) => {
      if (isCanvasEditor()) return;

      once('back-to-top', '.back-to-top', context).forEach((nav) => {
        const button = nav.querySelector('a');
        if (!button) return;

        const showThreshold = 250;

        const toggleVisibility = () => {
          if (window.scrollY > showThreshold) {
            nav.classList.remove('opacity-0', 'invisible');
            nav.classList.add('opacity-100', 'visible');
          } else {
            nav.classList.remove('opacity-100', 'visible');
            nav.classList.add('opacity-0', 'invisible');
          }
        };

        window.addEventListener('scroll', toggleVisibility, { passive: true });
        toggleVisibility();

        button.addEventListener('click', (e) => {
          e.preventDefault();
          window.scrollTo({ top: 0, behavior: 'smooth' });
        });
      });
    },
  };
})(Drupal, once);
