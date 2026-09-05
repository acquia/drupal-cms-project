/*!
 * Project Name: Morphos (morpht/morphos)
 * File: components/behaviors/palette_swap_behavior/palette_swap_behavior.js
 * Copyright (c) 2026 Morpht Pty Ltd
 */

/**
 * Palette Swap Behavior JavaScript.
 *
 * Uses IntersectionObserver to swap the color palette of parent components
 * when they enter the viewport.
 */

((Drupal, once) => {
  /**
   * Check if we're in Canvas editor mode (not preview mode).
   */
  const isCanvasEditor = () => {
    try {
      // Must be in iframe with Canvas parent, but NOT in preview iframe
      return (
        window?.parent?.drupalSettings?.canvas &&
        !window.parent.document.body.querySelector(
          '[class^=_PagePreviewIframe]',
        )
      );
    } catch (e) {
      // Cross-origin iframe access will throw, meaning we're not in Canvas
      return false;
    }
  };

  /**
   * Palette class prefix.
   */
  const PALETTE_PREFIX = 'color--';

  /**
   * All available palette classes.
   */
  const PALETTE_CLASSES = [
    'color--standard',
    'color--alternate',
    'color--primary',
    'color--secondary',
    'color--accent',
    'color--neutral',
  ];

  Drupal.behaviors.paletteSwapBehavior = {
    attach: (context) => {
      once('palette-swap', '[data-palette-swap]', context).forEach(
        (trigger) => {
          // Hide the behavior indicator on frontend (not in Canvas editor)
          if (!isCanvasEditor()) {
            trigger.style.display = 'none';
          }
        },
      );

      // Skip palette swap animation in Canvas editor mode
      if (isCanvasEditor()) {
        return;
      }

      once('palette-swap-init', '[data-palette-swap]', context).forEach(
        (trigger) => {
          // Get the parent component (the component containing the behaviors slot)
          const component = trigger.closest('.component');
          if (!component) {
            return;
          }

          // Skip if media query is set and doesn't match
          const mediaQuery = trigger.dataset.behaviorMediaQuery || '';
          if (mediaQuery && !window.matchMedia(mediaQuery).matches) {
            return;
          }

          // Get configuration from data attributes
          const config = {
            duration: parseInt(trigger.dataset.paletteSwapDuration, 10) || 500,
            swap: trigger.dataset.paletteSwapSwap || 'standard',
            threshold: parseInt(trigger.dataset.paletteSwapThreshold, 10) || 10,
          };

          // Store the original palette class
          let originalPalette = null;
          PALETTE_CLASSES.forEach((cls) => {
            if (component.classList.contains(cls)) {
              originalPalette = cls;
            }
          });

          // Target palette class
          const targetPalette = PALETTE_PREFIX + config.swap;

          // Skip if already has the target palette or no change needed
          if (originalPalette === targetPalette) {
            return;
          }

          // Set up transition for smooth color change
          const transitionValue = `background-color ${config.duration}ms ease-out, color ${config.duration}ms ease-out`;
          component.style.transition = transitionValue;

          // Animate palette swap by capturing old colors, swapping the class,
          // briefly pinning the old colors inline, then releasing them so the
          // transition animates to the new class-based values.
          const animateSwap = (fromClass, toClass) => {
            const oldBg = getComputedStyle(component).backgroundColor;
            const oldColor = getComputedStyle(component).color;

            if (fromClass) {
              component.classList.remove(fromClass);
            }
            component.classList.add(toClass);

            // Pin old colors inline so transition has a starting value.
            component.style.backgroundColor = oldBg;
            component.style.color = oldColor;

            // Next frame: remove inline overrides to trigger transition.
            requestAnimationFrame(() => {
              component.style.backgroundColor = '';
              component.style.color = '';
            });
          };

          // Function to swap to target palette
          const swapToTarget = () => {
            animateSwap(originalPalette, targetPalette);
          };

          // Function to swap back to original palette
          const swapToOriginal = () => {
            animateSwap(targetPalette, originalPalette);
          };

          // Create IntersectionObserver
          const thresholdValue = config.threshold / 100;

          const observer = new IntersectionObserver(
            (entries) => {
              entries.forEach((entry) => {
                if (entry.intersectionRatio >= thresholdValue) {
                  swapToTarget();
                } else {
                  swapToOriginal();
                }
              });
            },
            {
              threshold: [0, thresholdValue],
              rootMargin: '0px',
            },
          );

          // Start observing the component
          observer.observe(component);

          // Store observer for cleanup
          component._paletteSwapObserver = observer;
          component._paletteSwapOriginal = originalPalette;
        },
      );
    },
    detach: (context) => {
      // Clean up the visibility toggle
      once
        .remove('palette-swap', '[data-palette-swap]', context)
        .forEach((trigger) => {
          trigger.style.display = '';
        });

      // Clean up the palette swap
      once
        .remove('palette-swap-init', '[data-palette-swap]', context)
        .forEach((trigger) => {
          const component = trigger.closest('.component');
          if (component && component._paletteSwapObserver) {
            component._paletteSwapObserver.disconnect();
            delete component._paletteSwapObserver;

            // Reset to original palette
            if (component._paletteSwapOriginal) {
              PALETTE_CLASSES.forEach((cls) => {
                component.classList.remove(cls);
              });
              component.classList.add(component._paletteSwapOriginal);
            }

            component.style.transition = '';
            delete component._paletteSwapOriginal;
          }
        });
    },
  };
})(Drupal, once);
