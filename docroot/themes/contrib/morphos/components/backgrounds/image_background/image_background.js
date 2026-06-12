/*!
 * Project Name: Morphos (morpht/morphos)
 * File: components/backgrounds/image_background/image_background.js
 * Copyright (c) 2026 Morpht Pty Ltd
 */

/**
 * Image background JavaScript.
 *
 * Shows or hides background elements based on a CSS media query.
 * Works with any background type that outputs a
 * data-background-media-query attribute.
 */

((Drupal, once) => {
  /**
   * Check if we're in Canvas editor mode (not preview mode).
   */
  const isCanvasEditor = () => {
    try {
      return (
        window?.parent?.drupalSettings?.canvas &&
        !window.parent.document.body.querySelector(
          '[class^=_PagePreviewIframe]',
        )
      );
    } catch (e) {
      return false;
    }
  };

  Drupal.behaviors.imageBackground = {
    attach: (context) => {
      // Skip in Canvas editor mode.
      if (isCanvasEditor()) {
        return;
      }

      once(
        'background-media-query',
        '.bg-image-wrap[data-background-media-query]',
        context,
      ).forEach((el) => {
        const mediaQuery = el.dataset.backgroundMediaQuery;
        if (!mediaQuery) {
          return;
        }

        const mql = window.matchMedia(mediaQuery);

        const handleChange = (e) => {
          el.style.display = e.matches ? '' : 'none';
        };

        // Initial check.
        handleChange(mql);

        // Listen for viewport changes across the media query threshold.
        mql.addEventListener('change', handleChange);
      });
    },
  };
})(Drupal, once);
