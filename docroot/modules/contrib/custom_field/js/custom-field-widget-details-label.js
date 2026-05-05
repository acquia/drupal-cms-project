/**
 * @file
 * Defines Javascript behaviors for widget details labels.
 */

((Drupal, once) => {
  /**
   * Update details title based on selected property / subfield values.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   * Attaches the behavior for details titles.
   */
  Drupal.behaviors.customFieldWidgetDetailsLabel = {
    attach(context, settings) {
      // Find all <details> elements with the appropriate data attribute.
      const detailsElements = context.querySelectorAll(
        'details[data-details-label-target]',
      );

      once('custom-field-details-label', detailsElements, context).forEach(
        (details) => {
          const summary = details.querySelector('summary');
          const labelLimit = settings?.custom_field?.label_limit || 60;

          // Find the input element to sync the details title with.
          const inputElement = details.querySelector(
            '[data-details-label-provider]',
          );

          // Ensure we found both the summary and the input.
          if (!summary || !inputElement) {
            return;
          }

          /**
           * Updates the details summary text based on the linked input's value.
           */
          const updateSummaryText = () => {
            // Create a temporary element to strip HTML.
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = inputElement.value;

            // Get the plain text content and remove extra whitespace.
            let cleanTitle = (tempDiv.textContent || tempDiv.innerText || '')
              .replace(/\s+/g, ' ')
              .trim();

            // Fallback if empty.
            if (!cleanTitle) {
              cleanTitle = Drupal.t('(Untitled)');
            }

            // Limit to 255 characters.
            if (cleanTitle.length > labelLimit) {
              cleanTitle = `${cleanTitle.substring(0, labelLimit)}...`;
            }

            // Update the title of the detail element.
            summary.textContent = cleanTitle;
          };

          // Initial update.
          updateSummaryText();
          // Add an event listener to update the summary on key presses, pastes, etc.
          inputElement.addEventListener('input', updateSummaryText);
        },
      );
    },
  };
})(Drupal, once);
