<?php

declare(strict_types=1);

namespace Drupal\google_tag_test\Controller;

/**
 * Returns a cacheable test page.
 */
final class TestPageController {

  /**
   * Builds a page that the Dynamic Page Cache is able to cache.
   *
   * @param string $name
   *   Name shown on the page.
   *
   * @return array
   *   Render array.
   */
  public function page(string $name): array {
    return [
      '#markup' => 'Test page ' . $name,
    ];
  }

}
