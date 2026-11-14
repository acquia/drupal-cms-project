<?php

declare(strict_types=1);

namespace Drupal\telemetry_test\Controller;

/**
 * Serves the stand-in admin pages used to test telemetry.
 */
final class TelemetryTestController {

  /**
   * Returns an empty page.
   *
   * The page's content doesn't matter. Telemetry only looks at the route name
   * and its parameters.
   *
   * @return array<mixed>
   *   An empty render array.
   */
  public function page(): array {
    return [];
  }

}
