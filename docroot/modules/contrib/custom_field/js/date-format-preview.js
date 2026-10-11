/**
 * @file
 * Provides date format preview for custom date format table.
 */

(function ($, Drupal, drupalSettings) {
  const dateFormats = drupalSettings.dateFormats;

  /**
   * Display the preview for date format parts in a table.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attach behavior for previewing date formats in a table.
   */
  Drupal.behaviors.customDateFormatPreview = {
    attach(context) {
      const $tables = once(
        'customDateFormatPreview',
        '[data-drupal-date-format-table]',
        context,
      );

      $tables.forEach((table) => {
        const $table = $(table);
        const $preview = $table
          .closest('fieldset')
          .find('[data-drupal-date-format-preview] em');
        let $rows = $table.find('tbody tr.draggable');

        /**
         * Updates the date format preview based on current table state.
         */
        function updatePreview() {
          let dateString = '';

          // Iterate over rows in their current order.
          $rows.each((index, row) => {
            const $row = $(row);
            const format =
              $row.find('[data-drupal-date-format-source="format"]')[0].value ||
              '';
            const suffix =
              $row.find('[data-drupal-date-format-source="suffix"]')[0].value ||
              '';

            if (format) {
              // Replace format characters with sample values from dateFormats.
              const formatted = format.replace(/\\?(.?)/gi, (key, value) =>
                dateFormats[key] ? dateFormats[key] : value,
              );
              dateString += formatted + suffix;
            }
          });

          // Update preview text and visibility.
          $preview[0].textContent = dateString;
          $preview.parent().toggleClass('js-hide', !dateString.length);
        }

        /**
         * Event handler for input changes.
         */
        function handleInputChange() {
          updatePreview();
        }

        /**
         * Event handler for tabledrag changes.
         */
        function handleTableDrag() {
          $rows = $table.find('tbody tr.draggable');
          updatePreview();
        }

        // Bind change/input/keyup events to format and suffix fields.
        $table.on(
          'change.customDateFormat input.customDateFormat keyup.customDateFormat',
          '[data-drupal-date-format-source]',
          handleInputChange,
        );

        // Set up MutationObserver to detect row reordering.
        const observer = new MutationObserver((mutations) => {
          let rowOrderChanged = false;
          mutations.forEach((mutation) => {
            if (
              mutation.type === 'childList' &&
              mutation.target.tagName === 'TBODY'
            ) {
              rowOrderChanged = true;
            }
          });
          if (rowOrderChanged) {
            handleTableDrag();
          }
        });

        // Observe changes to the table's tbody.
        const tbody = $table.find('tbody')[0];
        if (tbody) {
          observer.observe(tbody, { childList: true });
        }

        // Also try binding to tabledrag event for redundancy.
        $table.on('tabledrag.customDateFormat', handleTableDrag);

        // Initialize preview.
        updatePreview();
      });
    },

    /**
     * Detach behavior to clean up MutationObserver.
     *
     * @prop {Drupal~behaviorDetach} detach
     *   Detach behavior for cleanup.
     */
    detach(context, settings, trigger) {
      if (trigger === 'unload') {
        const $tables = once.remove(
          'customDateFormatPreview',
          '[data-drupal-date-format-table]',
          context,
        );
        $tables.forEach((table) => {
          const $table = $(table);
          // Remove event listeners.
          $table.off('.customDateFormat');
          // Disconnect any MutationObservers (handled automatically by garbage collection).
        });
      }
    },
  };
})(jQuery, Drupal, drupalSettings);
