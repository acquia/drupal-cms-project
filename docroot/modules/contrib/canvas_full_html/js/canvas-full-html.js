/**
 * @file
 * JavaScript to handle CKEditor toolbar overflow fixes in Canvas.
 *
 * Adds .ckeditor-dropdown-open class to body when the toolbar "Show more items"
 * dropdown is expanded, enabling CSS overflow fixes.
 */

(function (Drupal) {
  /**
   * Class added to body when a CKEditor dropdown is open.
   *
   * @type {string}
   */
  const DROPDOWN_OPEN_CLASS = 'ckeditor-dropdown-open';

  /**
   * Checks if a CKEditor dropdown is expanded and toggles body class.
   */
  function checkDropdownState() {
    // Check for the "Show more items" button with aria-expanded="true".
    const expandedButton = document.querySelector(
      '.ck-toolbar__grouped-dropdown .ck-dropdown__button[aria-expanded="true"]',
    );

    if (expandedButton) {
      document.body.classList.add(DROPDOWN_OPEN_CLASS);
    } else {
      document.body.classList.remove(DROPDOWN_OPEN_CLASS);
    }
  }

  /**
   * Initializes the MutationObserver to watch for dropdown state changes.
   */
  function init() {
    // Observe for attribute changes (aria-expanded).
    const observer = new MutationObserver(checkDropdownState);

    observer.observe(document.body, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['aria-expanded'],
    });

    // Check on click events.
    document.addEventListener(
      'click',
      function () {
        setTimeout(checkDropdownState, 10);
      },
      true,
    );
  }

  // Initialize when DOM is ready.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(Drupal);
