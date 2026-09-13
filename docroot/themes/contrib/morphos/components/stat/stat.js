/*!
 * Project Name: Morphos (morpht/morphos)
 * File: components/stat/stat.js
 * Copyright (c) 2026 Morpht Pty Ltd
 */

/**
 * JS for Stat count-up animation.
 *
 * Uses IntersectionObserver to trigger a count-up animation when the stat
 * value scrolls into view. Animates from 0 to the target value with
 * ease-out easing over 2 seconds.
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

  /**
   * Ease-out cubic easing function.
   */
  const easeOutCubic = (t) => 1 - Math.pow(1 - t, 3);

  /**
   * Format a number with comma separators.
   */
  const formatNumber = (num) => Math.round(num).toLocaleString();

  /**
   * Animate a numeric value from start to end over duration ms.
   */
  const animateValue = (el, start, end, duration) => {
    const startTime = performance.now();

    const update = (currentTime) => {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      const easedProgress = easeOutCubic(progress);
      const current = start + (end - start) * easedProgress;

      el.textContent = formatNumber(current);

      if (progress < 1) {
        requestAnimationFrame(update);
      }
    };

    requestAnimationFrame(update);
  };

  Drupal.behaviors.statCountUp = {
    attach: (context) => {
      // Skip animation in Canvas editor mode.
      if (isCanvasEditor()) {
        return;
      }

      once('stat-count-up', '.stat-number[data-value]', context).forEach(
        (el) => {
          const endValue = parseFloat(el.dataset.value);

          if (isNaN(endValue) || endValue === 0) {
            return;
          }

          // Set initial display to 0.
          el.textContent = '0';

          const observer = new IntersectionObserver(
            (entries) => {
              entries.forEach((entry) => {
                if (entry.isIntersecting) {
                  animateValue(el, 0, endValue, 2000);
                  observer.unobserve(el);
                }
              });
            },
            { threshold: 0.1 },
          );

          observer.observe(el);
        },
      );
    },
  };
})(Drupal, once);
