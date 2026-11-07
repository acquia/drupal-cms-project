<?php

declare(strict_types=1);

namespace Drupal\project_browser;

use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;

/**
 * Turns routed URLs into strings with real CSRF tokens (not placeholders).
 *
 * When a route has the `_csrf_token` requirement, URL generation may embed a
 * render placeholder instead of a session-bound token. That placeholder is only
 * replaced during HTML rendering. Callers that put URLs into JSON, Ajax, or
 * other non-HTML responses must finalize the token explicitly.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 *
 * @see \Drupal\Core\Access\RouteProcessorCsrf
 * @see \Drupal\ckeditor5\Plugin\CKEditor5Plugin\DynamicPluginConfigWithCsrfTokenUrlTrait
 */
final class CsrfTokenPath {

  public function __construct(
    private readonly RendererInterface $renderer,
  ) {}

  /**
   * Converts a URL to a string with CSRF token placeholders replaced.
   *
   * @param \Drupal\Core\Url $url
   *   A URL which may generate CSRF token placeholders.
   *
   * @return string
   *   The URL string, with all CSRF placeholders replaced by real tokens.
   */
  public function toString(Url $url): string {
    $generated_url = $url->toString(TRUE);
    $url_string = $generated_url->getGeneratedUrl();

    // Replace CSRF placeholders without rendering the whole URL as plain text.
    // #plain_text would HTML-escape "&" to "&amp;" and break multi-param query
    // strings (e.g. projects[] + token).
    foreach ($generated_url->getAttachments()['placeholders'] ?? [] as $placeholder => $element) {
      $url_string = str_replace(
        $placeholder,
        (string) $this->renderer->renderInIsolation($element),
        $url_string,
      );
    }

    return $url_string;
  }

}
