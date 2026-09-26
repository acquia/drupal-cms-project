(($) => {
  Drupal.behaviors.carousel3Items = {
    attach(context) {
      const breakpointSmall = 720;
      const breakpointLarge = 980;
      const marginBig = 160;

      if (!$('body').hasClass('layout-builder-active')) {
        once('provus-carousel-3', ".carousel-3-items .lightslider", context).forEach(function (value) {
          const items = 3;
          const slider = $(value).lightSlider({
            onSliderLoad: function maxHeightFunc(el) {
              const container = $(el);
              const card = container.find('.card');
              let maxHeight = 0;

              card.each(function () {
                const height = $(this).outerHeight();
                if (height > maxHeight) {
                  maxHeight = height;
                }
              });

              // Set all cards to the max height
              card.css('height', maxHeight + 'px');

              // MatchHeight for content inside cards
              const content = card.find('.card-content');
              const title = card.find('.card-title');
              const body = card.find('.card-text');
              const body2 = card.find('.card-body');

              content.matchHeight({ byRow: true });
              title.matchHeight({ byRow: true });
              body.matchHeight({ byRow: true });
              card.find('.card-extra').matchHeight({ byRow: true });
              card.matchHeight({ byRow: true });
              container.find('.list').matchHeight({ byRow: true });

              // Trigger a resize so everything falls into place.
              $(window).trigger('resize');
            },
            item: items,
            loop: false,
            controls: true,
            slideMove: items,
            slideMargin: 15,
            enableDrag: false,
            easing: 'cubic-bezier(0.25, 0, 0.25, 1)',
            speed: 600,
            keyPress: true,
            //adaptiveHeight: true,
            responsive: [
              {
                breakpoint: breakpointLarge + marginBig,
                settings: {
                  slideMargin: 10,
                  item: 2,
                  slideMove: 2,
                },
              },
              {
                breakpoint: breakpointSmall,
                settings: {
                  item: 1,
                  slideMove: 1,
                },
              },
            ],
          });
        });
      }
    },
  };
})(jQuery);
