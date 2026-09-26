/*!
 * Project Name: Morphos (morpht/morphos)
 * File: components/google_translate/google_translate.js
 * Copyright (c) 2026 Morpht Pty Ltd
 */

/**
 * Google Translate component JavaScript.
 */

((Drupal, once) => {
  Drupal.behaviors.googleTranslate = {
    attach: (context) => {
      once('google_translate', '.google-translate', context).forEach((el) => {
        const element = el.querySelector('.google-translate__element');
        if (!element) {
          return;
        }

        const language = el.dataset.language || 'en';

        // Initialize Google Translate when the page loads.
        window.addEventListener('load', () => {
          new google.translate.TranslateElement(
            { pageLanguage: language },
            element,
          );
        });

        // Target the <html> element.
        const htmlElement = document.documentElement;

        // Set up the observer to watch for attribute changes.
        const observer = new MutationObserver((mutations) => {
          mutations.forEach((mutation) => {
            if (mutation.attributeName === 'class') {
              // Check if Google Translate added the RTL class.
              if (htmlElement.classList.contains('translated-rtl')) {
                htmlElement.setAttribute('dir', 'rtl');
              } else {
                // Reset to LTR if translation is turned off or changed to LTR.
                htmlElement.setAttribute('dir', 'ltr');
              }
            }
          });
        });

        // Start observing the <html> element.
        observer.observe(htmlElement, { attributes: true });
      });
    },
  };
})(Drupal, once);
