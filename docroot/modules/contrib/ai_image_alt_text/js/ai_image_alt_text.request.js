/**
 * @file
 * Sends CSRF protected alt text generation requests.
 */

(function ($, Drupal, drupalSettings) {
  let tokenRequest = null;

  Drupal.aiImageAltText = Drupal.aiImageAltText || {};

  /**
   * Fetches the CSRF token for the X-CSRF-Token request header once.
   *
   * @return {jQuery.Promise}
   *   Resolves with the token.
   */
  Drupal.aiImageAltText.getCsrfToken = function () {
    if (!tokenRequest) {
      tokenRequest = $.ajax({
        url: Drupal.url('session/token'),
        type: 'GET',
        dataType: 'text',
        cache: false,
      }).fail(function () {
        // Allow a retry on the next call.
        tokenRequest = null;
      });
    }
    return tokenRequest;
  };

  /**
   * Requests an alt text for a file.
   *
   * @param {number|string} fileId
   *   The file ID.
   * @param {string} langCode
   *   The language code.
   *
   * @return {jQuery.Promise}
   *   Resolves with the JSON response.
   */
  Drupal.aiImageAltText.generate = function (fileId, langCode) {
    const { baseUrl, pathPrefix } = drupalSettings.path;
    const file = encodeURIComponent(fileId);
    const lang = encodeURIComponent(langCode || 'en');
    const url = `${baseUrl}${pathPrefix}admin/config/ai/ai_image_alt_text/generate/${file}/${lang}`;
    return Drupal.aiImageAltText.getCsrfToken().then(function (token) {
      return $.ajax({
        url,
        type: 'POST',
        dataType: 'json',
        headers: { 'X-CSRF-Token': token },
      });
    });
  };

  /**
   * Gets a plain text error message from a failed request.
   *
   * @param {object} response
   *   The jqXHR object.
   * @param {string} fallback
   *   Message used when the response has no error message.
   *
   * @return {string}
   *   The escaped error message, safe to insert as HTML.
   */
  Drupal.aiImageAltText.errorMessage = function (response, fallback) {
    if (
      response &&
      response.responseJSON &&
      typeof response.responseJSON.error === 'string'
    ) {
      // The @ placeholder escapes the server supplied message.
      return Drupal.t('Error: @error', {
        '@error': response.responseJSON.error,
      });
    }
    return Drupal.checkPlain(fallback);
  };
})(jQuery, Drupal, drupalSettings);
