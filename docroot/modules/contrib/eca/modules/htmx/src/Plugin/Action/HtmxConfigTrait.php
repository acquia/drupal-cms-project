<?php

declare(strict_types=1);

namespace Drupal\eca_htmx\Plugin\Action;

use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Url;

/**
 * Shared helpers to map ECA action configuration onto a core Htmx builder.
 *
 * Both the generic "eca_htmx_element" action and the "eca_htmx_poll" action use
 * this trait so that all data-hx-* attributes are produced through the core
 * \Drupal\Core\Htmx\Htmx fluent builder rather than being hand-assembled.
 */
trait HtmxConfigTrait {

  /**
   * Converts a user-provided URL string to a Url object.
   *
   * Internal paths (starting with "/") use Url::fromUserInput(); everything
   * else (absolute URLs, entity URIs, route URIs) uses Url::fromUri(). Returns
   * NULL when the string is empty or cannot be resolved.
   *
   * @param string $value
   *   The URL string.
   *
   * @return \Drupal\Core\Url|null
   *   The Url object, or NULL if it could not be built.
   */
  protected function htmxUrl(string $value): ?Url {
    $value = trim($value);
    if ($value === '') {
      return NULL;
    }
    try {
      if (str_starts_with($value, '/')) {
        return Url::fromUserInput($value);
      }
      return Url::fromUri($value);
    }
    catch (\Exception) {
      return NULL;
    }
  }

  /**
   * Applies a request-method attribute (get/post/put/patch/delete) to a Htmx.
   *
   * @param \Drupal\Core\Htmx\Htmx $htmx
   *   The Htmx builder.
   * @param string $method
   *   The HTTP method (lowercase): get, post, put, patch or delete.
   * @param string $url
   *   The URL string. May be empty to issue the request to the current URL.
   */
  protected function htmxApplyRequestMethod(Htmx $htmx, string $method, string $url): void {
    $url_object = $this->htmxUrl($url);
    match ($method) {
      'post' => $htmx->post($url_object),
      'put' => $htmx->put($url_object),
      'patch' => $htmx->patch($url_object),
      'delete' => $htmx->delete($url_object),
      default => $htmx->get($url_object),
    };
  }

}
