/*!
 * Project Name: Morphos (morpht/morphos)
 * File: components/toc/toc.js
 * Copyright (c) 2026 Morpht Pty Ltd
 */

((Drupal, once) => {
  Drupal.behaviors.toc = {
    attach: (context) => {
      once('toc', '.toc', context).forEach((el) => {
        const contentSelector = el.dataset.contentSelector;
        const headingLevels = el.dataset.headingLevels;
        const ignoreSelector = el.dataset.ignoreSelector || '';

        if (!contentSelector || !headingLevels) return;

        const headingSelector = headingLevels.split(',').join(', ');

        const contentElement = document.querySelector(contentSelector);
        if (!contentElement) return;

        // Generate IDs for headings that don't have them.
        contentElement.querySelectorAll(headingSelector).forEach((heading) => {
          if (!heading.id) {
            heading.id = heading.textContent
              .trim()
              .toLowerCase()
              .replace(/[^a-z0-9]+/g, '-')
              .replace(/(^-|-$)/g, '');
          }
        });

        const tocBody = el.querySelector('.toc__body') || el;
        tocbot.init({
          tocElement: tocBody,
          contentSelector,
          headingSelector,
          ignoreSelector,
        });
      });
    },

    detach: (context) => {
      once.remove('toc', '.toc', context).forEach(() => {
        tocbot.destroy();
      });
    },
  };
})(Drupal, once);
