(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.spaceSlider = {
    attach: function (context, settings) {
      if (typeof Splide === 'undefined' && typeof window.Splide === 'undefined') {
        console.error('Splide is not loaded');
        return;
      }
      
      const SplideConstructor = Splide || window.Splide;

      once('space-slider', '.space-slider', context).forEach(function (sliderElement) {
        // Default values from dataset (mapped from YML)
        const slidesPerView = parseInt(sliderElement.dataset.slidesPerView) || 3;
        const slidesOnTablet = parseInt(sliderElement.dataset.slidesPerViewTablet) || 2;
        const slidesOnMobile = parseInt(sliderElement.dataset.slidesPerViewMobile) || 1;
        const slideType = sliderElement.dataset.slideType || 'slide';
        const autoplay = sliderElement.dataset.autoplay === 'true';
        const arrows = sliderElement.dataset.arrows === 'true';
        const pagination = sliderElement.dataset.pagination === 'true';
        const slideFocus = sliderElement.dataset.slideFocus || 'none';

        const paginationType = sliderElement.dataset.paginationType || 'default';

        // Add splide__slide class to list items if missing (Crucial for slots)
        const list = sliderElement.querySelector('.splide__list');
        if (list) {
          Array.from(list.children).forEach((child) => {
            child.classList.add('splide__slide');
          });
        }

        const splide = new SplideConstructor(sliderElement, {
          type: slideType,
          perPage: slidesPerView,
          autoplay: autoplay,
          arrows: arrows,
          pagination: pagination,
          gap: '1rem',
          focus: slideFocus === 'center' ? 'center' : false,
          omitEnd: true, // Prevents sliding to empty space
          interval: 3000,
          rewind: false,
          breakpoints: {
            1024: { perPage: slidesOnTablet },
            767: { perPage: slidesOnMobile },
          },
        });

        if (paginationType === 'arrow-with-pagination') {
          splide.on('pagination:mounted', function (data) {
            const paginationTarget = sliderElement.querySelector('.splide__pagination-target');
            if (paginationTarget) {
               paginationTarget.appendChild(data.list);
            }
          });
        }

        splide.mount();
      });
    }
  };
})(Drupal, once);
