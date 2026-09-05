/**
 * @file
 * Search behaviors.
 *
 */
(function ($, Drupal) {

    'use strict';
  
    Drupal.behaviors.mainNav = {
      attach: function (context, settings) {
        // Hide the menu initialy until everything is loaded.
        $(window).on('load resize orientationchange', function () {
          if ($('body').hasClass('menu-ready')) {
            var $menuBlock = $('#block-provus-meridian-v2-provus-bootstrap-main-menu');
            setTimeout(function () {
              $menuBlock.addClass('menu-visible');
            }, 1000);
            setTimeout(function () {
              $(window).trigger('resize');
            }, 1100);
          }
        });
  
      }
    }
  
  })(jQuery, Drupal);
  