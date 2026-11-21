(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.aiAltImage = {
    trackedImages: {},
    finishedWorking: (that) => {
      // Untrack the file.
      Drupal.behaviors.aiAltImage.trackedImages[$(that).data('file-id')] =
        false;
      // Remove the throbber.
      $(that).parent().find('.ajax-progress').remove();
      // Enable the button.
      if (!drupalSettings.ai_image_alt_text.hide_button) {
        $(that).show();
      }
      // Enable the text field.
      $(that)
        .parent('.form-managed-file')
        .find("input[name$='[alt]']")
        .removeAttr('disabled');
    },
    attach: (context) => {
      $('.ai-alt-text-generation')
        .off('click')
        .on('click', function (e) {
          // Set that it is being tracked.
          Drupal.behaviors.aiAltImage.trackedImages[$(this).data('file-id')] =
            true;
          e.preventDefault();
          // Manually add the throbber.
          const throbber = $(
            `<div class="ajax-progress ajax-progress--throbber"><div class="ajax-progress__throbber">&nbsp;</div><div class="ajax-progress__message">${Drupal.t('Generating alt text...')}</div></div>`,
          );
          $(this).parent().append(throbber);
          $(this)
            .parent('.form-managed-file')
            .find("input[name$='[alt]']")
            .attr('disabled', 'disabled');
          // Disable the button.
          $(this).hide();
          const that = $(this);
          const { lang } = drupalSettings.ai_image_alt_text;
          Drupal.aiImageAltText
            .generate($(this).data('file-id'), lang)
            .done(function (response) {
              if (response && 'alt_text' in response) {
                $(that)
                  .parents('.form-managed-file')
                  .find("input[name$='[alt]']")
                  .each(function () {
                    this.value = response.alt_text;
                  });
              }
              Drupal.behaviors.aiAltImage.finishedWorking(that);
            })
            .fail(function (response) {
              // The message is escaped, Drupal.Message inserts it as HTML.
              const errorMessage = Drupal.aiImageAltText.errorMessage(
                response,
                Drupal.t(
                  'We could not create an Alt Text, please try again later.',
                ),
              );

              const modalContainer = $(that)
                .parents('.ui-dialog-content')
                .first();
              if (modalContainer.length) {
                let messagesWrapper = modalContainer
                  .find('[data-drupal-messages]')
                  .first();
                if (!messagesWrapper.length) {
                  messagesWrapper = $('<div data-drupal-messages></div>');
                  modalContainer.prepend(messagesWrapper);
                }
                const messenger = new Drupal.Message(messagesWrapper[0]);
                messenger.add(errorMessage, { type: 'warning' });
              } else {
                const messenger = new Drupal.Message();
                messenger.add(errorMessage, { type: 'warning' });
              }
              Drupal.behaviors.aiAltImage.finishedWorking(that);
            });
        });

      // Check for newly created elements without alt text.
      if (drupalSettings.ai_image_alt_text.autogenerate) {
        $(context)
          .find('.ai-alt-text-generation')
          .each(function () {
            const fileId = $(this).data('file-id');
            const altInput = $(this)
              .parents('.form-managed-file')
              .find("input[name$='[alt]']")
              .get(0);
            if (altInput && altInput.value === '') {
              // Check so the file id is not already being worked on.
              if (Drupal.behaviors.aiAltImage.trackedImages[fileId] !== true) {
                $(this).trigger('click');
              }
            }
          });
      }
    },
  };
})(jQuery, Drupal, drupalSettings);
