(function ($, Drupal, once) {
  function getSuggestion(item) {
    const unique = $(item).data('unique-id');
    $(`.load-${unique}`).show();
    Drupal.aiImageAltText
      .generate($(item).data('file-id'), $(item).data('entity-language'))
      .done(function (data) {
        $(`.alt-text-${unique}`).each(function () {
          this.value = data.alt_text;
        });
      })
      .fail(function (response) {
        let message = Drupal.t(
          'A general error occurred, please try again later.',
        );
        if (
          response &&
          response.responseJSON &&
          typeof response.responseJSON.error === 'string'
        ) {
          // alert() renders plain text, so no escaping is needed here.
          message = `${Drupal.t('Error:')} ${response.responseJSON.error}`;
        }
        window.alert(message);
      })
      .always(function () {
        $(`.load-${unique}`).hide();
        $('input[type=submit]').attr('disabled', false);
      });
  }

  Drupal.behaviors.aiImageBulkAltText = {
    attach(context) {
      $(once('ai-bulk-alt-text-loader', '.textarea-loader', context)).hide();

      $(once('ai-bulk-alt-text-suggest', '.suggest-alt-text', context)).on(
        'click',
        function (e) {
          $('input[type=submit]').attr('disabled', true);
          e.preventDefault();
          let delay = 0;
          $('.alt-text-item').each(function () {
            const self = this;
            // Make sure they run sequentially.
            // And delay each call a bit:
            setTimeout(function () {
              getSuggestion(self);
            }, delay);
            delay += 500;
          });
        },
      );

      $(once('ai-bulk-alt-text-item', '.alt-text-item', context)).on(
        'click',
        function (e) {
          $('input[type=submit]').attr('disabled', true);
          e.preventDefault();
          getSuggestion(this);
        },
      );
    },
  };
})(jQuery, Drupal, once);
