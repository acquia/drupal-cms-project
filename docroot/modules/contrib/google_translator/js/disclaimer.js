/**
 * @file
 * File disclaimer.js.
 */

(function ($, Drupal, cookies) {
  'use strict';

  Drupal.behaviors.googleTranslatorDisclaimer = {
    attach: function (context, settings) {
      var disclaimerLink;
      var config = settings.googleTranslatorDisclaimer || {},
        disclaimerLink = once('google-translate-disclaimer-init', config.selector, context),
        swap = function () {
          $(disclaimerLink).replaceWith(config.element);

          // When the gadget has loaded, focus on it.
          var translateGadgetObserver = new MutationObserver((mutations, obs) => {
            var displayMode = settings.googleTranslatorDisclaimer.displayMode;
            if (displayMode == "SIMPLE") {
              var gadget = document.querySelector('.goog-te-gadget-simple a');
            } else {
              var gadget = document.getElementsByClassName('goog-te-combo')[0];
            }
            if (gadget) {
              // Focus on the link. I don't know why we need delay but something
              // keeps the focus from happening without it.
              window.setTimeout(() => gadget.focus(), 500);

              // Found the gadget, end the MutationObserver.
              obs.disconnect();
              return;
            }
          })

          // start observing
          translateGadgetObserver.observe(document, {
            childList: true,
            subtree: true
          });
        };

      // When the user has previously activated google translate, the cookie
      // will be set and we can proceed straight to exposing the language
      // button without the disclaimer interstitial.
      const checkCookie = config.disclaimer.trim().length != 0;

      // If there's a disclaimer, and the cookie can be evaluated, swaps it. Otherwise adds event listeners.
      if (
        disclaimerLink.length &&
        (
          !checkCookie ||
          typeof cookies.get('googtrans') != 'undefined'
        )
      ) {
        swap();
      }
      else {
        // Listen for user click on the translate interstitial (disclaimer) link.
        $(disclaimerLink).click(function (event) {
          // Show the disclaimer text if available.
          if (config.disclaimer &&
              config.disclaimer.trim().length > 0) {

            var disclaimerModal = $('<div class="message">' + config.disclaimer + '<div>').appendTo('body');
            Drupal.dialog(disclaimerModal, {
              title: config.disclaimerTitle,
              classes: {
                'ui-dialog': 'google-translator-disclaimer-modal',
              },
              width: 'auto',
              buttons: [
                {
                  text: config.acceptText,
                  click: function() {
                    $(this).dialog('close');
                    cookies.set('googtrans', '1');
                    swap();
                  }
                },
                {
                  text: config.dontAcceptText,
                  click: function() {
                    $(this).dialog('close');
                  }
                }
              ]
            }).showModal();
          }

          // If the disclaimer text is not available, then just show the gadget.
          else {
            swap();
          }
        });
      }
    }

  }
})(jQuery, Drupal, window.Cookies);
