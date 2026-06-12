/*!
 * Project Name: Morphos (morpht/morphos)
 * File: components/slider/slider.js
 * Copyright (c) 2026 Morpht Pty Ltd
 */

((Drupal, once) => {
  const isCanvasEditor = () => {
    try {
      return (
        !!window?.parent?.drupalSettings?.canvas &&
        !window.parent.document.body.querySelector(
          '[class^=_PagePreviewIframe]',
        )
      );
    } catch (e) {
      return false;
    }
  };

  /**
   * Measure tallest slide and set container height for grid layout.
   *
   * Swiper grid sets slide heights to calc((100% - gap) / rows), which
   * requires the container to have an explicit height. Swiper does not
   * do this automatically.
   */
  const applyGridHeight = (el, rows, spaceBetween) => {
    if (rows <= 1) {
      return;
    }

    const slides = el.querySelectorAll('.swiper-slide');
    let maxHeight = 0;

    slides.forEach((slide) => {
      const prev = slide.style.height;
      slide.style.height = 'auto';
      maxHeight = Math.max(maxHeight, slide.offsetHeight);
      slide.style.height = prev;
    });

    if (maxHeight > 0) {
      const next = maxHeight * rows + spaceBetween * (rows - 1) + 'px';
      if (el.style.height !== next) {
        el.style.height = next;
      }
    }
  };

  Drupal.behaviors.swiperSlider = {
    attach: (context) => {
      if (isCanvasEditor()) {
        return;
      }

      once('swiper-init', '.slider', context).forEach((el) => {
        const wrapper = el.querySelector('.swiper-wrapper');
        if (wrapper) {
          Array.from(wrapper.children).forEach((child) => {
            child.classList.add('swiper-slide');
          });
        }

        let config = {};
        const raw = el.getAttribute('data-swiper-config');
        if (raw) {
          try {
            config = JSON.parse(raw) || {};
          } catch (e) {
            Drupal.throwError(e);
          }
        }

        const perView = config.slidesPerView || 1;
        const perGroup = config.slidesPerGroup || 1;
        const space = config.spaceBetween || 0;
        const hasGrid = config.grid && config.grid.rows > 1;

        // Vertical mode: set container height to tallest slide.
        if (config.direction === 'vertical' && wrapper) {
          let maxHeight = 0;
          Array.from(wrapper.children).forEach((slide) => {
            maxHeight = Math.max(maxHeight, slide.offsetHeight);
          });
          if (maxHeight > 0) {
            el.style.height = maxHeight + 'px';
          }
        }

        // Swiper options.
        const options = {
          effect: config.effect || 'slide',
          direction: config.direction || 'horizontal',
          speed: config.speed || 300,
          loop: config.loop || false,
          slidesPerView: 1,
          spaceBetween: space,
          pagination: config.pagination
            ? {
                el: el.querySelector('.swiper-pagination'),
                type: config.pagination.type || 'bullets',
                clickable: true,
              }
            : false,
          navigation: config.navigation
            ? {
                nextEl: el.querySelector('.swiper-button-next'),
                prevEl: el.querySelector('.swiper-button-prev'),
              }
            : false,
          scrollbar: el.querySelector('.swiper-scrollbar')
            ? { el: el.querySelector('.swiper-scrollbar') }
            : false,
        };

        // Responsive breakpoints.
        const mobileBreakpoint = 768;

        if (perView > 1 || hasGrid) {
          options.breakpointsBase = 'container';
          options.breakpoints = {
            [mobileBreakpoint]: {
              slidesPerView: perView,
              slidesPerGroup: perGroup,
              spaceBetween: space,
              ...(hasGrid ? { grid: config.grid } : {}),
            },
          };
        }

        const swiper = new Swiper(el, options);
        el._swiperInstance = swiper;

        // Observe container size changes to manage grid height.
        if (hasGrid) {
          let pending = false;
          const observer = new ResizeObserver(() => {
            if (pending) {
              return;
            }
            pending = true;
            requestAnimationFrame(() => {
              pending = false;
              if (el.clientWidth > mobileBreakpoint) {
                applyGridHeight(el, config.grid.rows, space);
              } else if (el.style.height !== '') {
                el.style.height = '';
              }
              swiper.update();
            });
          });
          observer.observe(el);
          el._swiperResizeObserver = observer;
        }
      });
    },

    detach: (context) => {
      once.remove('swiper-init', '.slider', context).forEach((el) => {
        if (el._swiperInstance) {
          el._swiperInstance.destroy(true, true);
          delete el._swiperInstance;
        }
        if (el._swiperResizeObserver) {
          el._swiperResizeObserver.disconnect();
          delete el._swiperResizeObserver;
        }
        el.style.height = '';
      });
    },
  };
})(Drupal, once);
