(function ($, Drupal) {
  if ($('.slide-show-with-items-container').length > 0) {
    $('.slide-show-with-items-container').each(function() {
      const sliderSpeed = $(this).attr('data-slide-speed');
      const sliderAutoplay = $(this).attr('data-autoplay');
      $(this).on('init reInit beforeChange', function(event, slick){
        let dots = $(this).find('.slick-dots')
        let dot = dots.find('li');
        let dotBtn = dot.find('button');
        let slickTrack = $(this).find('.slick-track');
        let currentSlide = $(this).find('.slick-current');
        setTimeout( function() {
          $(dots).removeAttr('role');
          $(dot).removeAttr('role aria-controls aria-hidden aria-selected');
          $(dotBtn).removeAttr('role');
          $(dotBtn).removeAttr('tabindex');
          $(slickTrack).attr('aria-label', 'Slideshow');
          $(dot).each(function(index) {
            $(this).attr('aria-label', 'Slide ' + (index + 1));
            $(this).find('button').text('Slide ' + (index + 1) + ' button');
          });
          slick.$slides.each( function(index) {
            $(this).attr('title', 'Slide ' + (index + 1));
            $(this).removeAttr('tabindex');
          });
        }, 60);
      });
      $(this).slick({
        slidesToShow: 1,
        slidesToScroll: 1,
        arrows: false,
        dots: true,
        fade: false,
        speed: 1000,
        pauseOnHover: false,
        autoplay: sliderAutoplay == 'true' ? true : false,
        autoplaySpeed: sliderSpeed,
        infinite: true
      });
    });

    // Slideshow.
    window.addEventListener('load',function(){
      $(".slide-show-with-items-container").each(function(){
        let playPauseContainer = document.createElement('ul');
        let playBtn = document.createElement('li');
        let pauseBtn = document.createElement('li');

        $(playPauseContainer).addClass('play-pause-container');
        $(playBtn).attr('aria-label','play');
        $(playBtn).addClass('play-btn').attr('tabindex','0');
        $(playBtn).addClass('hide');
        $(pauseBtn).addClass('pause-btn').attr('tabindex','0');
        $(pauseBtn).attr('aria-label','pause');
        $(playBtn).appendTo($(playPauseContainer));
        $(pauseBtn).appendTo($(playPauseContainer));
        $(playPauseContainer).appendTo($(this));

        $(pauseBtn).on('click keydown', function(event){
          if (event.type === 'click' || event.key === ' ' || event.key === 'Enter') {
            event.preventDefault();
            $(this).addClass('hide');
            $(playBtn).removeClass('hide');
            $(this).closest('.slide-show-with-items-container').slick('slickPause');
            $(playBtn).focus();
          }
        });

        $(playBtn).on('click keydown', function(event){
          if (event.type === 'click' || event.key === ' ' || event.key === 'Enter') {
            event.preventDefault();
            $(this).addClass('hide');
            $(pauseBtn).removeClass('hide');
            $(this).closest('.slide-show-with-items-container').slick('slickPlay');
            $(pauseBtn).focus();
          }
        });
      });
    });
  }
})(jQuery, Drupal);
