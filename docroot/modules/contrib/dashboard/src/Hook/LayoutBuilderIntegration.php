<?php

declare(strict_types=1);

namespace Drupal\dashboard\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Block placement module integration hooks.
 */
class LayoutBuilderIntegration {

  /**
   * Implements hook_plugin_filter_TYPE__CONSUMER_alter().
   */
  #[Hook('plugin_filter_block__layout_builder_alter')]
  public function pluginFilterBlockLayoutBuilderAlter(array &$definitions, array $extra): void {
    // Remove blocks that are not useful within Layout Builder.
    // The dashboard placeholders should only placed using recipes, so hide from
    // the UI.
    unset($definitions['dashboard_placeholder']);
  }

}
