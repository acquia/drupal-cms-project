<?php

namespace Drupal\ai_dashboard\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Contains hooks for ai_dashboard modules.
 */
class AiDashboardHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() {
    return [
      'ai_dashboard_provider_setup_status' => [
        'variables' => [
          'status_text' => '',
          'is_key_set' => 'not-available',
          'configuration_link' => '',
        ],
      ],
    ];
  }

}
