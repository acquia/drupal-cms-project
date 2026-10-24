(function ($, Drupal, drupalSettings) {

  $(document).ready(function() {
    $('.textarea-loader').hide();
    $('.suggest-alt-text').on('click', function(e) {
      $('input[type=submit]').attr('disabled', true);
      e.preventDefault();
      let delay = 0;
      $('.alt-text-item').each(function() {
        let self = this;
        // Make sure they run sequentially.
        // And delay each call a bit:
        setTimeout(function(){
          getSuggestion(self);
        }, delay);
        delay += 500;
      });
    });

    $('.alt-text-item').on('click', function(e) {
      $('input[type=submit]').attr('disabled', true);
      e.preventDefault();
      getSuggestion(this);
    });
  });

  function getSuggestion(data) {
    let unique = $(data).data('unique-id');
    $('.load-' + unique).show();
    $.ajax({
      url: drupalSettings.path.baseUrl + 'admin/config/ai/ai_image_alt_text/generate/' + $(data).data('file-id') + '/' + $(data).data('entity-language'),
      type: 'GET',
      success: function (data) {
        $('.alt-text-' + unique).val(data.alt_text);
        $('.load-' + unique).hide();
        $('input[type=submit]').attr('disabled', false);
      },
      error: function () {
        if ('error' in response.responseJSON) {
          alert('Error: ' + response.responseJSON.error);
        }
        else {
          alert(Drupal.t('A general error occurred, please try again later.'));
        }
        $('.load-' + unique).hide();
        $('input[type=submit]').attr('disabled', false);
      }
    })
  }

})(jQuery, Drupal, drupalSettings);
