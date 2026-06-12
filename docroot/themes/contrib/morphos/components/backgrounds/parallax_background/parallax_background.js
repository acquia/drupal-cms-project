/*!
 * Project Name: Morphos (morpht/morphos)
 * File: components/backgrounds/parallax_background/parallax_background.js
 * Copyright (c) 2026 Morpht Pty Ltd
 */

/**
 * Parallax background JavaScript.
 *
 * Uses the Jarallax library to apply scroll-based parallax effects
 * to background images. Supports media queries to disable on small screens.
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

  Drupal.behaviors.parallaxBackground = {
    attach: (context) => {
      // Skip parallax in Canvas editor mode.
      if (isCanvasEditor()) {
        return;
      }

      once(
        'parallax-background',
        '[data-parallax-background]',
        context,
      ).forEach((wrap) => {
        // Find the image via DOM (direct child img).
        const img = wrap.querySelector(':scope > img');
        if (!img) {
          return;
        }

        // Speed is stored as integer (-100 to 200), divide by 100 for Jarallax.
        const speed = wrap.dataset.parallaxSpeed
          ? parseInt(wrap.dataset.parallaxSpeed, 10) / 100
          : 1.5;
        const mediaQuery = wrap.dataset.backgroundMediaQuery || '';

        /**
         * Initialize Jarallax on the wrapper element.
         */
        const initJarallax = () => {
          // Avoid double-init.
          if (wrap._jarallaxActive) {
            return;
          }
          wrap.style.display = '';
          jarallax(wrap, {
            imgElement: img,
            speed,
          });
          wrap._jarallaxActive = true;
        };

        /**
         * Destroy Jarallax instance on the wrapper element.
         */
        const destroyJarallax = () => {
          if (!wrap._jarallaxActive) {
            return;
          }
          jarallax(wrap, 'destroy');
          wrap._jarallaxActive = false;
          wrap.style.display = 'none';
        };

        // Handle media query: only init when query matches.
        if (mediaQuery) {
          const mql = window.matchMedia(mediaQuery);

          const handleMediaChange = (e) => {
            if (e.matches) {
              initJarallax();
            } else {
              destroyJarallax();
            }
          };

          // Initial check.
          if (mql.matches) {
            initJarallax();
          } else {
            // Hide component until media query matches.
            wrap.style.display = 'none';
          }

          mql.addEventListener('change', handleMediaChange);

          // Store for cleanup.
          wrap._parallaxMql = mql;
          wrap._parallaxMediaHandler = handleMediaChange;
        } else {
          initJarallax();
        }
      });
    },

    detach: (context) => {
      once
        .remove('parallax-background', '[data-parallax-background]', context)
        .forEach((wrap) => {
          // Remove media query listener.
          if (wrap._parallaxMql && wrap._parallaxMediaHandler) {
            wrap._parallaxMql.removeEventListener(
              'change',
              wrap._parallaxMediaHandler,
            );
            delete wrap._parallaxMql;
            delete wrap._parallaxMediaHandler;
          }

          // Destroy Jarallax instance.
          if (wrap._jarallaxActive) {
            jarallax(wrap, 'destroy');
            delete wrap._jarallaxActive;
          }
        });
    },
  };
})(Drupal, once);
