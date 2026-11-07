<?php

namespace Drupal\geofield\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for geofield.
 */
class GeofieldHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() {
    return [
      'geofield_dms' => [
        'variables' => [
          'components' => [],
        ],
      ],
      'geofield_latlon' => [
        'variables' => [
          'lat' => 0,
          'lon' => 0,
        ],
      ],
    ];
  }

}
