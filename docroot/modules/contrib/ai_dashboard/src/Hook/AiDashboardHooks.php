<?php

namespace Drupal\ai_dashboard\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Contains hooks for ai_dashboard modules.
 */
class AiDashboardHooks {

  use StringTranslationTrait;

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

  /**
   * Implements hook_preprocess_block().
   */
  #[Hook('preprocess_block')]
  public function preprocessBlock(&$variables) {
    if ($variables['plugin_id'] == 'project_browser_block:ai_dashboard_recommended') {
      $variables['title_suffix'] = [
        '#markup' => '<p class="block-help ai-font-size-xs">' . $this->t('Apply pre-configured settings to enable safe and effective AI functionality.') . '</p>',
      ];
    }
    if ($variables['plugin_id'] == 'ai_documentation') {
      $variables['title_suffix'] = [
        '#markup' => '<p class="block-help ai-font-size-xs">' . $this->t('Access detailed guides, reference materials, and tutorials for all AI features.') . '</p>',
      ];
    }
    if ($variables['plugin_id'] == 'admin_menu_links') {
      $variables['title_suffix'] = [
        '#markup' => '<p class="block-help ai-font-size-xs">' . $this->t('Configure custom AI features and integrations tailored to your workflows.') . '</p>',
      ];
    }
  }

}
