/*!
 * Project Name: Morphos (morpht/morphos)
 * File: components/behaviors/scroll_reveal_behavior/scroll_reveal_behavior.js
 * Copyright (c) 2026 Morpht Pty Ltd
 */

/**
 * Scroll Reveal Behavior JavaScript.
 *
 * Uses IntersectionObserver to reveal parent components when they enter the viewport.
 */

((Drupal, once) => {
  /**
   * Check if the current device is mobile.
   */
  const isMobile = () => {
    return window.matchMedia('(max-width: 768px)').matches;
  };

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

  Drupal.behaviors.scrollRevealBehavior = {
    attach: (context) => {
      once('scroll-reveal', '[data-scroll-reveal]', context).forEach(
        (trigger) => {
          // Hide the behavior indicator on frontend (not in Canvas editor)
          if (!isCanvasEditor()) {
            trigger.style.display = 'none';
          }
        },
      );

      // Skip scroll reveal animation in Canvas editor mode
      if (isCanvasEditor()) {
        return;
      }

      once('scroll-reveal-init', '[data-scroll-reveal]', context).forEach(
        (trigger) => {
          // Get the parent component (the component containing the behaviors slot)
          const component = trigger.closest('.component');
          if (!component) {
            return;
          }

          // Get configuration from data attributes
          const config = {
            delay: parseInt(trigger.dataset.scrollRevealDelay, 10) || 0,
            distance: trigger.dataset.scrollRevealDistance || '50px',
            duration: parseInt(trigger.dataset.scrollRevealDuration, 10) || 500,
            mobile: trigger.dataset.scrollRevealMobile !== 'false',
            opacity: parseInt(trigger.dataset.scrollRevealOpacity, 10) || 0,
            origin: trigger.dataset.scrollRevealOrigin || 'bottom',
            scale: parseInt(trigger.dataset.scrollRevealScale, 10) || 100,
            sequenceInterval:
              parseInt(trigger.dataset.scrollRevealSequenceInterval, 10) || 0,
            sequenceSelector:
              trigger.dataset.scrollRevealSequenceSelector || '',
          };

          // Check media query (takes precedence over mobile setting)
          const mediaQuery = trigger.dataset.behaviorMediaQuery || '';
          if (mediaQuery) {
            if (!window.matchMedia(mediaQuery).matches) {
              return;
            }
          } else if (!config.mobile && isMobile()) {
            return;
          }

          // Get elements to animate (either component or sequence children)
          let elements = [component];
          if (config.sequenceSelector) {
            const sequenceElements = component.querySelectorAll(
              config.sequenceSelector,
            );
            if (sequenceElements.length > 0) {
              elements = Array.from(sequenceElements);
            }
          }

          // Calculate initial transform based on origin and distance
          const getInitialTransform = () => {
            const scaleValue = config.scale / 100;
            const scaleTransform =
              config.scale !== 100 ? ` scale(${scaleValue})` : '';

            const transforms = {
              bottom: `translateY(${config.distance})${scaleTransform}`,
              top: `translateY(-${config.distance})${scaleTransform}`,
              left: `translateX(-${config.distance})${scaleTransform}`,
              right: `translateX(${config.distance})${scaleTransform}`,
            };
            return transforms[config.origin] || transforms.bottom;
          };

          // Apply initial hidden state to all elements
          elements.forEach((el, index) => {
            const elementDelay = config.delay + index * config.sequenceInterval;
            el.style.opacity = String(config.opacity / 100);
            el.style.transform = getInitialTransform();
            el.style.transition = `opacity ${config.duration}ms ease-out, transform ${config.duration}ms ease-out`;
            el.style.transitionDelay = `${elementDelay}ms`;
          });

          // Create IntersectionObserver
          const observer = new IntersectionObserver(
            (entries) => {
              entries.forEach((entry) => {
                if (entry.isIntersecting) {
                  // Reveal all elements
                  elements.forEach((el) => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0) translateX(0) scale(1)';
                  });

                  // Stop observing after reveal
                  observer.unobserve(component);
                }
              });
            },
            {
              threshold: 0.1,
              rootMargin: '0px 0px -50px 0px',
            },
          );

          // Start observing the component
          observer.observe(component);

          // Store observer and elements for cleanup
          component._scrollRevealObserver = observer;
          component._scrollRevealElements = elements;
        },
      );
    },
    detach: (context) => {
      // Clean up the visibility toggle
      once
        .remove('scroll-reveal', '[data-scroll-reveal]', context)
        .forEach((trigger) => {
          trigger.style.display = '';
        });

      // Clean up the scroll reveal animation
      once
        .remove('scroll-reveal-init', '[data-scroll-reveal]', context)
        .forEach((trigger) => {
          const component = trigger.closest('.component');
          if (component && component._scrollRevealObserver) {
            component._scrollRevealObserver.disconnect();
            delete component._scrollRevealObserver;

            // Reset styles on all elements
            const elements = component._scrollRevealElements || [component];
            elements.forEach((el) => {
              el.style.opacity = '';
              el.style.transform = '';
              el.style.transition = '';
              el.style.transitionDelay = '';
            });
            delete component._scrollRevealElements;
          }
        });
    },
  };
})(Drupal, once);
