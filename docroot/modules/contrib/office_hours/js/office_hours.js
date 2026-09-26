/**
 * @see https://www.drupal.org/docs/drupal-apis/javascript-api/javascript-api-overview
 */
(function updateElement($, Drupal, once) {
  /**
   * Traverses visible rows and applies even/odd classes.
   *
   * @param {HTMLElement} context
   */
  const fixStriping = (context) => {
    $('tbody tr:visible', context).each((i, element) => {
      const $element = $(element);
      $element
        .removeClass(i % 2 === 0 ? 'odd' : 'even')
        .addClass(i % 2 === 0 ? 'even' : 'odd');
    });
  };

  /**
   * Clears a slot element with an empty value.
   *
   * @param {string} formItem
   *   The time slot element (time, hours, minutes, comment).
   * @param {jQuery} context
   *   The context.
   */
  const clearTimeSlotElement = (selector, context) => {
    context.find(selector).each((_, el) => {
      if (el instanceof HTMLInputElement || el instanceof HTMLSelectElement) {
        el.value =
          document.querySelector('#target option:first-child')?.value || '';
      }
    });
  };

  /**
   * Fills a slot element with the new value,
   * and shows the next item slowly if needed.
   *
   * @param {string} formItem
   *   The time slot element (time, hours, minutes, comment).
   * @param {string} value
   *   The new value.
   */
  const setTimeSlotElement = (formItem, value) => {
    formItem.value = value;
    const $row = $(formItem).closest('tr');
    // Show the next slot item, slowly, using jQuery fadeIn.
    $row.fadeIn('slow');
  };

  /**
   * Shows the Add-link, conditionally.
   */
  const showAddLink = function () {
    const $this = $(this);
    $this.hide();
    const $nextTr = $this.closest('tr').next();
    if ($nextTr.hasClass('js-office-hours-hide')) {
      $this.show();
    }
  };

  /**
   * Enable/Disable the time input elements, depending on the all_day checkbox.
   *
   * @todo When 'all_day' is set, the link 'Add time slot' must be hidden #3322982.
   *
   * @param {Event} e The event.
   */
  const setAllDayTimeSlot = (e) => {
    const checkbox = e.currentTarget;
    // Get the name of the checkbox, which will be mostly the
    // same name for the start and end times.
    const name = checkbox.getAttribute('name');
    // Determine the state of the all_day checkbox.
    const isEnabled = checkbox.matches(':checked');
    // Collect all the name parts of the start/end times.
    const suffixes = [
      '[starthours][time]',
      '[endhours][time]',
      '[starthours][hour]',
      '[endhours][hour]',
      '[starthours][minute]',
      '[endhours][minute]',
      '[starthours][ampm]',
      '[endhours][ampm]',
    ];
    // Replace [all_day] with the names for start and end times.
    // For HTML5 element.
    const $form = $(checkbox.form);
    suffixes.forEach((suffix) => {
      const inputName = name.replace('[all_day]', suffix);
      const $input = $form.find(`[name="${inputName}"]`);
      // Enable/Disable the start and end time depending on all_day checkbox.
      if ($input.length) $input.prop('disabled', isEnabled);
    });
  };

  /**
   * Shows an office-hours-slot, when user clicks "Add more".
   *
   * @param {Event} e The event.
   */
  const addTimeSlot = (e) => {
    e.preventDefault();
    const $thisSlot = $(e.currentTarget);
    // Hide the link, the user clicked upon.
    $thisSlot.hide();
    // Show the next slot item, slowly, using jQuery fadeIn.
    const $nextSlot = $thisSlot.closest('tr').next();
    $nextSlot.fadeIn('slow');
    // @todo Close the dropbutton quickly.
    // $this.parents('.dropbutton-wrapper').removeClass('open');
    // Add spoken message for accessibility (a11y) screen readers.
    Drupal.announce(Drupal.t('Added time slot.'));
    fixStriping($thisSlot.closest('.field--type-office-hours')[0]);
  };

  /**
   * Clear a time slot when the delete link is selected.
   *
   * @param {Event} e The event.
   */
  const clearTimeSlot = (e) => {
    e.preventDefault();
    // Find the time slot.
    const $slot = $(e.currentTarget).closest('tr');

    // Clear the date (in Exception days).
    clearTimeSlotElement('.form-date', $slot);
    // Do the following for both widgets:
    // Clear the hours, minutes in the select box.
    clearTimeSlotElement('.form-select', $slot);
    clearTimeSlotElement('.form-time', $slot);
    clearTimeSlotElement('.form-text', $slot);
    // Clear the all_day checkbox and set depending fields.
    $slot
      .find('.form-checkbox')
      .prop('checked', false)
      .each((_, el) => setAllDayTimeSlot({ currentTarget: el }));

    // @todo Hide subsequent slot that is cleared.
    // @todo Close the dropbutton quickly.
    // $this.parents('.dropbutton-wrapper').removeClass('open');
    // Add spoken message for accessibility (a11y) screen readers.
    Drupal.announce(Drupal.t('Cleared input values.'));
  };

  /**
   * Copy the values of previous day into the current input fields.
   *
   * @param {Event} e The event.
   */
  const copyPreviousDay = (e) => {
    e.preventDefault();
    const $this = $(e.currentTarget);
    // Get current day using attribute, both for Week Widget and List Widget.
    // @todo Use only attribute, not both attribute and class name.
    let currentDay = parseInt($this.closest('tr').attr('office_hours_day'), 10);
    if (Number.isNaN(currentDay)) {
      // Basic List Widget.
      currentDay = parseInt(
        $this.closest('fieldset').attr('office_hours_day'),
        10,
      );
    }

    if (Number.isNaN(currentDay)) {
      // Error.
      console.warn('Unable to determine current day for office hours copy.');
      return;
    }

    // Week widget can have value 0 (sunday). List widget starts with value 1.
    const previousDay = currentDay === 0 ? 6 : currentDay - 1;
    // Select current table.
    const $tbody = $this.closest('tbody');
    // Get div's from current day using class name.
    const $currentSelector = $tbody.find(`.js-office-hours-day-${currentDay}`);
    // Get div's from previous day using class name.
    const $previousSelector = $tbody.find(
      `.js-office-hours-day-${previousDay}`,
    );

    // For better UX, first copy the comments, then hours and fadeIn.
    // Copy the comment.
    $previousSelector.find('.form-text').each((i, el) => {
      const target = $currentSelector.find('.form-text')[i];
      if (target) setTimeSlotElement(target, el.value);
    });

    // Do NOT copy the day/date in the select list/HTML5 date element (List widget).
    // previousSelector.find('.form-date').each(() => {
    //   setTimeSlotElement(currentSelector.find('.form-date').eq(index), $(this).val());
    // });
    // Copy the all_day checkbox and enable/disable dependent fields.
    $previousSelector.find('.form-checkbox').each((i, el) => {
      const target = $currentSelector.find('.form-checkbox')[i];
      if (target) {
        target.checked = el.matches(':checked');
        setAllDayTimeSlot({ currentTarget: target });
      }
    });

    // Copy the hours, minutes in the select list/HTML5 time element.
    $previousSelector.find('.form-select').each((i, el) => {
      const target = $currentSelector.find('.form-select')[i];
      if (target) setTimeSlotElement(target, el.value);
    });
    $previousSelector.find('.form-time').each((i, el) => {
      const target = $currentSelector.find('.form-time')[i];
      if (target) setTimeSlotElement(target, el.value);
    });

    // Show Add-link of each slot of the day, after "Copy previous day".
    $currentSelector
      .find('[data-drupal-selector$=add]')
      .each((_, el) => showAddLink.call(el));

    Drupal.announce(Drupal.t('Copied values of previous day.'));
  };

  /**
   * Hide every empty slot and every slot above the max slots per day.
   */
  const initializeTimeSlot = function () {
    const $this = $(this);
    if ($this.hasClass('js-office-hours-hide')) {
      $this.hide();
    }

    // For each all_day checkbox, enable/disable times if clicked upon.
    $('[data-drupal-selector$="all-day"]', this)
      .on('click', setAllDayTimeSlot)
      .each((_, el) => setAllDayTimeSlot({ currentTarget: el }));

    // For each add-link, show the next slot if clicked upon.
    // Show the add-link, except if the next time slot is hidden.
    $('.js-office-hours-operation[data-drupal-selector$=add]', this)
      .on('click', addTimeSlot)
      .each(showAddLink);

    // For each clear-link, clear the slot if clicked upon.
    $('.js-office-hours-operation[data-drupal-selector$=clear]', this).on(
      'click',
      clearTimeSlot,
    );

    // For each copy-link, copy the previous day's values if clicked upon.
    // @todo This works for Table widget, not yet for List Widget.
    $('.js-office-hours-operation[data-drupal-selector$=copy]', this).on(
      'click',
      copyPreviousDay,
    );
  };

  /**
   * Attaches office hours behavior.
   */
  Drupal.behaviors.officeHours = {
    attach(context) {
      // For the Widget element.
      once(
        'office-hours-widget-once',
        '.field--type-office-hours .office-hours-slot',
        context,
      ).forEach((el) => initializeTimeSlot.call(el));

      once(
        'office-hours-striping',
        '.field--type-office-hours',
        context,
      ).forEach(fixStriping);

      // For the WebformOfficeHours element.
      once(
        'office-hour-webform-once',
        '.js-webform-type-office-hours .office-hours-slot',
        context,
      ).forEach((el) => initializeTimeSlot.call(el));
    },
  };
})(jQuery, Drupal, once);
