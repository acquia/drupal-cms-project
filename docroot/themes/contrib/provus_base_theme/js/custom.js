/**
 * @file
 * Global utilities.
 *
 */
(function($, Drupal) {

  'use strict';

  Drupal.behaviors.provus = {
    attach: function(context, settings) {

      $('.layout-builder__link').each(function(i, v) {
        $(v).contents().eq(0).wrap('<span class="layout-builder__link-wrapped"></span>');
      });
      // Begin: Displaying LB Region
      // When using the layout builder,
      //   remember the user's preference regarding showing region lines.
      let glbpreviewaction = localStorage.getItem('glbpreviewaction');
      if (glbpreviewaction == 1) {
        $('#glb-toolbar-preview-regions').prop('checked', true);
        $('body').addClass('glb-preview-regions--enable')
      }

      $('.layout-builder-active #glb-toolbar-preview-regions').on('change', function () {
        let is_checked = $(this).is(':checked') ? 1 : 0;
        localStorage.setItem('glbpreviewaction', is_checked);
      })
      // End: LB Region Display

      // Custom code here
      if (!$('body').hasClass('layout-builder-active')) {
        $('#searchCollapse').on('shown.bs.collapse', function () {
          $('#searchCollapse .form-search').focus()
          $('form .form-type-textfield input#edit-keys').focus()
        });

        $('#searchCollapse').on('hidden.bs.collapse', function () {
          $('#searchCollapse .form-search').blur();
        });

        // Menu hovered class.
        $("nav.menu--main ul.navbar-nav li.nav-item.dropdown a").focus(function(){
          $(this).parent().addClass('hovered');
        });

        $("nav.menu--main ul.navbar-nav li a.nav-link:not('.dropdown-toggle-main')").focus(function(){
          $('nav.menu--main ul.navbar-nav li.hovered').removeClass('hovered');
        });

        // Grid MatchHeight.
        let gridComponent = $('.block-group-column, .block-group-grid-2, .block-group-grid-3, .block-group-grid-4');
        if (gridComponent.length > 0) {
          let gridCard = gridComponent.find('.card');
          let gridTitle = gridCard.find('h3');
          let gridBody = gridCard.find('.field--name-body');

          gridCard.matchHeight({byRow: true});
          gridTitle.matchHeight({byRow: true});
          gridBody.matchHeight({byRow: true});
        }
      }
    }
  }

})(jQuery, Drupal);
