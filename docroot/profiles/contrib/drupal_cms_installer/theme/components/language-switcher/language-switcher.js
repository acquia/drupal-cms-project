(function (Drupal, once) {
  "use strict";

  /**
   * Handles switching the selected language during installation.
   */
  Drupal.behaviors.languageSwitcherDialog = {
    attach: function (context) {
      once('language-switcher-dialog', '#language-switcher', context).forEach((languageSwitcher) => {
        const languagesList = languageSwitcher.querySelector('#language-switcher-list');
        const searchInput = languageSwitcher.querySelector('#language-switcher-search');
        const languageSwitcherDialog = languageSwitcher.querySelector('#language-switcher-dialog');
        const switchLanguageButton = languageSwitcher.querySelector('[commandfor="language-switcher-dialog"]');
        const loadingOverlay = languageSwitcher.querySelector('.cms-installer__language-loading');

        if (!languagesList || !searchInput || !languageSwitcherDialog || !switchLanguageButton) {
          return;
        }

        // Store the element that had focus before opening the dialog.
        let focusTrapHandler = null;

        // Animated close — intercepts all close mechanisms (polyfill, native, programmatic).
        const nativeClose = languageSwitcherDialog.close.bind(languageSwitcherDialog);

        function closeWithAnimation() {
          if (languageSwitcherDialog.hasAttribute('data-closing')) return;
          languageSwitcherDialog.setAttribute('data-closing', '');
          languageSwitcherDialog.addEventListener('transitionend', function onEnd(e) {
            if (e.propertyName !== 'opacity') return;
            languageSwitcherDialog.removeEventListener('transitionend', onEnd);
            languageSwitcherDialog.removeAttribute('data-closing');
            nativeClose();
          });
        }

        languageSwitcherDialog.close = closeWithAnimation;
        languageSwitcherDialog.addEventListener('cancel', (e) => { e.preventDefault(); closeWithAnimation(); });

        const closeButton = languageSwitcherDialog.querySelector('.cms-installer__language-switcher-close');
        if (closeButton) {
          closeButton.removeAttribute('command');
          closeButton.removeAttribute('commandfor');
          closeButton.addEventListener('click', closeWithAnimation);
        }

        /**
         * Resets the search field and language list visibility.
         */
        function resetSearch() {
          searchInput.value = '';
          applySearchFilter('');
        }

        /**
         * Gets all focusable elements within the dialog that are visible.
         *
         * @returns {Array<Element>}
         *   Array of focusable elements.
         */
        function getFocusableElements() {
          const focusableSelectors = [
            'a[href]',
            'button:not([disabled])',
            'textarea:not([disabled])',
            'input:not([disabled])',
            'select:not([disabled])',
            '[tabindex]:not([tabindex="-1"])'
          ].join(', ');

          const allFocusable = Array.from(languageSwitcherDialog.querySelectorAll(focusableSelectors));

          // Filter out hidden elements.
          return allFocusable.filter((element) => {
            // Check if element or its parent is hidden.
            const li = element.closest('li');
            if (li && li.hasAttribute('hidden')) {
              return false;
            }
            // Check if element itself is hidden.
            return !element.hasAttribute('hidden') &&
              element.offsetParent !== null;
          });
        }

        /**
         * Handles keyboard navigation to trap focus within the dialog.
         *
         * @param {KeyboardEvent} event
         *   The keyboard event.
         */
        function onTabKeyPress(event) {
          // Only handle Tab key.
          if (event.key !== 'Tab') {
            return;
          }

          const focusableElements = getFocusableElements();

          // If no focusable elements, prevent default.
          if (focusableElements.length === 0) {
            event.preventDefault();
            return;
          }

          const firstElement = focusableElements[0];
          const lastElement = focusableElements[focusableElements.length - 1];
          const currentElement = document.activeElement;

          // If Shift+Tab on first element, move to last.
          if (event.shiftKey && currentElement === firstElement) {
            event.preventDefault();
            lastElement.focus();
            return;
          }

          // If Tab on last element, move to first.
          if (!event.shiftKey && currentElement === lastElement) {
            event.preventDefault();
            firstElement.focus();
          }
        }

        /**
         * Filters the language list based on the search input value.
         *
         * @param {string} searchValue
         *   The raw search input value.
         */
        function applySearchFilter(searchValue) {
          const normalizedSearch = searchValue.toLowerCase();
          const allLanguages = languagesList.querySelectorAll('li');

          if (normalizedSearch.length <= 2) {
            allLanguages.forEach((language) => {
              language.removeAttribute('hidden');
            });
            return;
          }

          const matches = languagesList.querySelectorAll(`a[data-keywords*="${normalizedSearch}"]`);

          // Initialise all languages as hidden.
          allLanguages.forEach((language) => {
            language.setAttribute('hidden', '');
          });

          // Show only the matching languages.
          matches.forEach((match) => {
            match.closest('li')?.removeAttribute('hidden');
          });
        }

        searchInput.addEventListener('input', (event) => {
          applySearchFilter(event.target.value);
        });

        switchLanguageButton.addEventListener('click', () => {
          resetSearch();
          switchLanguageButton.setAttribute('aria-expanded', 'true');

          // Set up "focus" trap when dialog opens. This ensures that as you
          // tab through the dialog, you don't accidentally tab out of it.
          focusTrapHandler = onTabKeyPress;
          languageSwitcherDialog.addEventListener('keydown', focusTrapHandler);
        });

        languageSwitcherDialog.addEventListener('close', () => {
          resetSearch();
          switchLanguageButton.setAttribute('aria-expanded', 'false');

          // Remove focus trap handler.
          if (focusTrapHandler) {
            languageSwitcherDialog.removeEventListener('keydown', focusTrapHandler);
            focusTrapHandler = null;
          }

          // On close, focus on trigger button.
          switchLanguageButton.focus();
        });

        // When a language is selected, close the dialog and show a spinner
        // while the language loads.
        languagesList.addEventListener('click', (event) => {
          if (event.target.closest('a[href]')) {
            languageSwitcherDialog.close();
            loadingOverlay.removeAttribute('hidden');
          }
        });
      });
    },
  };

})(Drupal, once);
