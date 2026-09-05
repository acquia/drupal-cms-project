/**
 * @file
 * Main Navigation.
 *
 */
(function ($, Drupal) {

  'use strict';

  $(document).ready(function () {
    var $wWidth = window.innerWidth;
    var $wHeight = window.innerHeight;
    var $navBarParent = $('#CollapsingNavbar');
    var $navBar = $("nav.menu--main ul.navbar-nav");
    var $mainMenuToggler = $(".navbar-toggler");
    var $levelUp = $("nav.menu--main ul.navbar-nav li.menu-item--expanded:not('init') > a.level-up");
    var $dropdownToggle = $("nav.menu--main ul.navbar-nav > .menu-item--expanded.dropdown.init");
    var $dropdownToggleSecond = $("nav.menu--main ul.navbar-nav .dropdown-item.dropdown.init");
    var $navLinkFirst = $("nav.menu--main ul.navbar-nav > .menu-item--expanded > a.nav-link");
    var $navLinkSecond = $("nav.menu--main ul.navbar-nav ul.dropdown-cor-menu.level-1 > li.dropdown > a.nav-link");
    var $navLinkSecondnoDropdown = $("nav.menu--main ul.navbar-nav ul.dropdown-cor-menu.level-1 > li.dropdown-item:not(.dropdown) > a.nav-link");
    var $navLinkThirdnoDropdown = $("nav.menu--main ul.navbar-nav ul.dropdown-menu.level-2 > li.dropdown-item:not(.dropdown) > a.nav-link");
    var $navLinkBack = $("nav.menu--main ul.navbar-nav > li.menu-item--expanded:not('init') > a.nav-link");
    var $dropdownLinkFirst = $("nav.menu--main ul.navbar-nav > .menu-item--expanded > a.dropdown-toggle");
    var $dropdownLinkSecond = $("nav.menu--main ul.navbar-nav ul.dropdown-cor-menu.level-1 > li > a.dropdown-toggle");
    var menuLevelSecond = $navBar.find('.dropdown-item.level-1');
    var menuLevelThird = $navBar.find('.dropdown-item.level-2');
    var calloutLlink = $navBar.find('.field--name-field-provus-menu-callout-link > a');
    var levelUpHandlerAttached = false;

    // Detect if mobile or not.
    var isMobile;
    var currentState;

    function menuResponsive() {
      var $wWidth = window.innerWidth;

      /*$mobileMaxWidth = 992;*/
      var $mobileMaxWidth = 992;
      var newState = $wWidth < $mobileMaxWidth && $navBar.length > 0 ? 'mobile' : 'desktop';

      // Only run if the state has changed.
      if (newState !== currentState) {
        if (newState === 'mobile') {
          $navBar.removeClass('desktop-nav').addClass('mobile-nav');
          isMobile = true;
        }
        else {
          $navBar.removeClass('mobile-nav').addClass('desktop-nav');
          isMobile = false;
        }
        currentState = newState;

        // Trigger an event and log to console only if the state changes.
        $(document).trigger('mobileStatusChanged', [isMobile]);
        console.log('Is mobile: ' + isMobile);
      }
    }

    // Define the click event handler function for level up buttons.
    function attachLevelUpHandler() {
      if (!levelUpHandlerAttached) {
        $navLinkBack.add($levelUp).on('click', levelUpHandler);
        levelUpHandlerAttached = true;
      }
    }
    function detachLevelUpHandler() {
      $navLinkBack.add($levelUp).off('click', levelUpHandler);
      levelUpHandlerAttached = false;
    }
    // Create "Overview" links.
    function addOverviewLinks() {
      $('ul.dropdown-cor-menu.level-1 > li.dropdown-item.dropdown').each(function () {
        var $thisDropdownItem = $(this);
        var $navLink = $thisDropdownItem.find('> a.nav-link');
        var $dropdownMenu = $thisDropdownItem.find('> ul.dropdown-menu.level-2');

        if ($navLink.attr('href') && $dropdownMenu.find('a.nav-link:contains("Overview")').length === 0) {
          var $overviewLink = $('<li class="dropdown-item overview-link"><a class="nav-link">Overview</a></li>');
          $overviewLink.find('a').attr('href', $navLink.attr('href'));
          $dropdownMenu.prepend($overviewLink);
        }
      });
    };
    // When switching to Desktop.
    function resetMobileState() {
      console.log('running resetMobileState');
      $navBarParent.removeClass('provus-mega-menu-mobile').addClass('provus-mega-menu-desktop');
      $navBar.find('> li').removeClass('item-open').show();
      $navBar.find('.item-open').removeClass('item-open');
      $navBar.find('.nav-link').removeClass('selected');
      $navBar.find('.level-up').removeClass('d-block');
      $navBar.find('.submenu-open').removeClass('submenu-open');
      $navBar.find('.dropdown-menu.show').hide().removeClass('show');
      $navBar.find('.dropdown-cor-menu.level-1').removeClass('dropdown-menu').removeClass('show-important').hide();
      $navBar.find('.menu-item--expanded').show();
      $navBar.find('.dropdown-cor-menu.level-1').removeClass('show-important').hide();
      $navBar.find('.dropdown-menu.level-2').hide();
      $navBar.find('.dropdown-toggle').addClass('d-none');
      $navBar.find('.show').removeClass('show');
      $navBar.removeClass('first-level-open');
      $navBar.find('.second-level-open').removeClass('second-level-open');
      $('body').removeClass('mobile-menu-open');
      if ($mainMenuToggler.attr('aria-expanded') === 'true') {
        $mainMenuToggler.click();
      }
      $navBar.find("[aria-expanded='true']").attr('aria-expanded', 'false');
      $navLinkSecondnoDropdown.on('click', restoreLink);
      $navLinkThirdnoDropdown.on('click', restoreLink);
      calloutLlink.on('click', restoreLink);
      $dropdownToggle.off('click', dropDownMobileFirst).on('click', dropDownDesktopFirst);
      $dropdownToggleSecond.off('click', dropDownMobileSecond).on('click', DropdownDesktopSecond);
      detachLevelUpHandler();
    }
    // When switching to Mobile.
    function resetDesktopState() {
      console.log('running resetDesktopState');
      $navBarParent.removeClass('provus-mega-menu-desktop').addClass('provus-mega-menu-mobile');
      console.log('navBarParent', $navBarParent);
      $navBar.removeClass('first-level-open');
      $navBar.find('.second-level-open').removeClass('second-level-open');
      $navBar.find('.dropdown-cor-menu.level-1').addClass('dropdown-menu').removeClass('show-important').hide();
      $navBar.find('.dropdown-cor-menu.level-1').removeAttr('style');
      $navBar.find('.dropdown-menu.level-2').hide();
      $navBar.find('.dropdown-toggle').removeClass('d-none');
      $navBar.find('.dropdown-toggle').removeAttr('style');
      $navBar.find('> ul.dropdown-menu.level-2 .col-title').remove();
      $navBar.find('.match').removeClass('match');
      $navBar.find('.show').removeClass('show');
      $('body').removeClass('mobile-menu-open');
      $navBar.find('li.col-title').remove();
      if ($mainMenuToggler.attr('aria-expanded') === 'true') {
        $mainMenuToggler.click();
      }
      $navBar.find("[aria-expanded='true']").attr('aria-expanded', 'false');
      $dropdownLinkFirst.on('click', preventDefaultLinks);
      $levelUp.on('click', preventDefaultLinks);
      $dropdownToggle.off('click', dropDownDesktopFirst).on('click', dropDownMobileFirst);
      $dropdownToggleSecond.off('click', DropdownDesktopSecond).on('click', dropDownMobileSecond);
      detachLevelUpHandler();
    }
    function levelUpHandler(event) {
      event.preventDefault();
      $navBar.removeClass('first-level-open');
      $(this).parent().addClass('init');
      // Hide back arrow.
      $levelUp.removeClass('d-block');
      // Hide dropdowns.
      $(this).siblings('ul.dropdown-menu').hide().removeClass('show');
      $navBar.find('ul.dropdown-menu.level-2').hide().removeClass('show');
      // Show right arrow when first level open.
      $(this).siblings('.dropdown-toggle').show();
      // Show other first levels.
      $dropdownToggle.show();
      // Close all opened submenus.
      $navBar.find('.submenu-open').removeClass('submenu-open');
      $navBar.find('.item-open').removeClass('item-open');
      itemsInit();
      detachLevelUpHandler();
      event.stopPropagation();
    };
    function itemsInit() {
      setTimeout(function () {
        if (!$("nav.menu--main ul.navbar-nav li.menu-item--expanded").hasClass('init')) {
          $("nav.menu--main ul.navbar-nav li.menu-item--expanded").addClass('init');
        }
        if (!$("nav.menu--main ul.navbar-nav li.dropdown-item").hasClass('init')) {
          $("nav.menu--main ul.navbar-nav li.dropdown-item").addClass('init');
        }
      }, 300);
    };
    // Toggler for the mobile menu.
    $mainMenuToggler.off('click').on('click', function (event) {
      // Close the mobile menu.
      if ($(this).attr('aria-expanded') === 'false') {
        $('body').removeClass('mobile-menu-open');
        $('html').css('height', 'initial');
        $navBar.find('ul.dropdown-menu.level-2').hide().removeClass('show');
        $navBar.find('ul.dropdown-cor-menu.level-1').hide().removeClass('show').removeClass('show-important');
        $navBar.find('.submenu-open').removeClass('submenu-open');
        $navBar.find('.item-open').removeClass('item-open');
        $navBar.removeClass('first-level-open');
        $navBar.removeClass('second-level-open');
        $levelUp.removeClass('d-block');
        $navBar.find('.menu-item--expanded').show();
        itemsInit();
      }
      // Open the mobile menu.
      else if ($(this).attr('aria-expanded') === 'true') {
        $('body').addClass('mobile-menu-open');
        $('html').css('height', '100%');
        itemsInit();
      }
    });
    // Function to prevent default.
    function preventDefaultLinks (event) {
      event.preventDefault();
    };
    // Click on first level item on mobile.
    function dropDownMobileFirst (event) {
      if (!$(this).closest('.navbar-nav').hasClass('first-level-open') && $(this).hasClass('init')) {
        $navBar.addClass('first-level-open');
        $(this).removeClass('init');
        $(this).addClass('item-open');
        // Show back arrow.
        $(this).find('> .level-up').addClass('d-block');
        // Show dropdown.
        $(this).find('> ul.dropdown-menu').show().addClass('show');
        // Hide right arrow when first level open.
        $(this).find('> .dropdown-toggle').hide();
        // Hide other first levels.
        $dropdownToggle.not($(this)).hide();
        attachLevelUpHandler();
      }
      else {
        $(this).addClass('init');
        $(this).removeClass('item-open');
        detachLevelUpHandler();
      }
    };
    // Click on first level on desktop.
    function dropDownDesktopFirst (event) {
      event.preventDefault();
      var $currentOpenMenu = $(this).find('.dropdown-cor-menu.level-1');
      if (!$(this).closest('ul').hasClass('first-level-open') || $(this).find('> .nav-link').attr('aria-expanded') == 'false') {
        $(this).closest('ul').addClass('first-level-open');
      }
      else {
        $(this).closest('ul').removeClass('first-level-open');
      }
      // $(this).closest('ul').find('.dropdown-cor-menu.level-1').not($currentOpenMenu).hide();
      $(this).closest('ul').find('.dropdown-cor-menu.level-1').not($currentOpenMenu).removeClass('show-important');
      $(this).closest('ul').find('.dropdown-cor-menu.level-1').removeClass('second-level-open');
      $(this).closest('ul').find('.dropdown-menu.level-2').hide();
      $(this).find('> .nav-link').attr('aria-expanded', function(index, attr) {
        return attr === 'true' ? 'false' : 'true';
      });

      // Toggle is not working. Probably there is a double toggle happening somewhere.
      // This is a workaround to make sure the menu is shown.
      // $currentOpenMenu.toggle();
      if ($currentOpenMenu.css('display') ==  'none') {
        $currentOpenMenu.addClass('show-important');
      } else {
        $currentOpenMenu.removeClass('show-important');
      }
    };
    // Click on second level item on mobile.
    function dropDownMobileSecond (event) {
      if ($(this).hasClass('init')) {
        $(this).removeClass('init');
        $(this).find('> .dropdown-toggle').addClass('submenu-open');
        $(this).find('> .nav-link').addClass('submenu-open');
        $(this).addClass('submenu-open');
        $(this).find('> ul.level-2').show().addClass('show');
        // Hide other second level.
        $(this).siblings().not($(this)).removeClass('submenu-open').find('> ul.level-2').hide().removeClass('show');
        $(this).siblings().not($(this)).find('.submenu-open').removeClass('submenu-open');
      }
      else {
        $(this).addClass('init');
        $(this).find('> .dropdown-toggle').removeClass('submenu-open');
        $(this).find('> .nav-link').removeClass('submenu-open');
        $(this).removeClass('submenu-open');
        $(this).find('> ul.level-2').hide().removeClass('show');
      }
      event.stopImmediatePropagation();
    };
    // Toggle second level on Desktop (Click).
    function DropdownDesktopSecond (event) {
      var $currentOpenMenu = $(this).find('.dropdown-menu.level-2');
      var $currentParent = $(this).closest('.dropdown-cor-menu.level-1');
      $navBar.find('.match').removeClass('match');
      if ($(this).hasClass('dropdown')) {
        if (!$(this).closest('ul').hasClass('second-level-open')) {
          $(this).closest('ul').addClass('second-level-open');
        }
        else {
          $(this).closest('ul').removeClass('second-level-open');
        }
        // Find all '.dropdown-menu.level-2' under the 'ul', excluding the current open menu.
        $(this).closest('ul').find('.dropdown-menu.level-2').not($currentOpenMenu).hide();
        //$(this).closest('ul.navbar-nav').find('.dropdown-cor-menu.level-1').not($currentParent).hide();
        $currentOpenMenu.toggle();

        $currentOpenMenu.addClass('match');
        $currentParent.addClass('match');
        // Equalize the heigh of News Listing cards.
        $('.match').matchHeight({property: 'min-height'});
        event.stopImmediatePropagation();
      }
    }
    // Click on third level links on Desktop.
    function restoreLink (event) {
      event.stopImmediatePropagation();
      event.stopPropagation();
      window.location.href = this.href;
    };
    $navLinkFirst.on('click', preventDefaultLinks);
    $navLinkSecond.on('click', preventDefaultLinks);

    // Centered when 2 cols.
    function centerColumns() {
      var $header = $('header');
      var $navBar = $("nav.menu--main ul.navbar-nav");

      var headerHeight = $header.outerHeight();

      var adminBarHeight = 0;
      if ($('.top-bar').length) {
        adminBarHeight = $('.top-bar').outerHeight() || 0;
      }

      var positionTop = headerHeight + adminBarHeight;

      var navOffset = $navBar.offset();
      var navWidth = $navBar.outerWidth();

      $("nav.menu--main ul.navbar-nav > li").each(function () {
        var menuLevelSecond = $(this).find("ul.dropdown-cor-menu.level-1");

        var elementWidth = menuLevelSecond.outerWidth();

        var positionLeft = navOffset.left + (navWidth / 2) - (elementWidth / 2) - 200;

        menuLevelSecond.css({
          'left': positionLeft,
          'top': positionTop
        });
      });
    }

    $(document).on('mobileStatusChanged', function (event, mobileStatus) {
      // Mobile.
      if (isMobile) {
        resetDesktopState();
      }
      // Desktop.
      if (!isMobile) {
        resetMobileState();
        // Click outside close the menu.
        $(document).off('keydown click').on('keydown click', function (event) {
          // Close menu when clicking outside of it.
          if (!$(event.target).closest('#block-rochester-main-menu').length) {
            $dropdownToggle.find('.dropdown-menu').hide();
            $dropdownToggle.find('.dropdown-cor-menu').hide();
          }
          // Close menu when pressing the Escape key.
          else if (event.keyCode == 27) {
            $dropdownToggle.find('.dropdown-menu').hide();
            $dropdownToggle.find('.dropdown-cor-menu').hide();
          }
        });
        $navBar.on('click', '.dropdown-cor-menu > .menu-item--expanded', function (event) {
          event.stopPropagation();
        });

        // Create Column Title.
        $('ul.dropdown-cor-menu.level-1 > li.dropdown-item.dropdown').each(function () {
          var $link = $(this).find('> a.nav-link');
          var $dropdownMenu = $(this).find('> ul.dropdown-menu.level-2');
          // Initialize titleText.
          var titleText = '';
          // Check if .link-text exists and use it if it does.
          if ($link.find('.link-text').length > 0) {
            titleText = $link.find('.link-text').text().trim();
          }
          else {
            // Otherwise, use the text directly from the anchor link.
            titleText = $link.text().trim();
          }
          // Check if the title list item already exists in the dropdown.
          setTimeout(function () {
            var $existingTitleLi = $dropdownMenu.find('li.col-title');
            // Only proceed if we have a valid title text, a dropdown menu, and no existing title list item.
            if (titleText && $dropdownMenu.length && !$existingTitleLi.length) {
              var $titleLi = $('<li class="dropdown-item col-title">' + titleText + '</li>');
              $dropdownMenu.prepend($titleLi);
            }
          }, 150);
        });
      }
    });

    var provusMainNavbehavior = Drupal.debounce(function () {
      menuResponsive();
      addOverviewLinks();

      if (!$('body').hasClass('menu-ready')) {
        $('body').addClass('menu-ready');
      }

      if (isMobile === false) {
        centerColumns();
      }
    }, 100);

    $(window).on('resize orientationchange', provusMainNavbehavior);
    provusMainNavbehavior();

    var debounceCenterColumns = Drupal.debounce(function () {
      centerColumns();
    }, 50);

    $(window).on('resize orientationchange', debounceCenterColumns);
    debounceCenterColumns();

    setTimeout(function () {
      $navBarParent.addClass('provus-mega-menu');
    }, 500);
  });

})(jQuery, Drupal);
