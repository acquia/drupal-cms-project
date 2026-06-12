<?php

declare(strict_types=1);

namespace Drupal\dashboard\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Navigation module integration hooks.
 */
class NavigationIntegration {

  public function __construct(protected ModuleHandlerInterface $moduleHandler) {}

  /**
   * Provide default block for navigation.
   */
  #[Hook('navigation_defaults')]
  public function navigationBlockDefaults(): array {
    $blocks = [];

    $blocks[] = [
      'delta' => 0,
      'configuration' => [
        'id' => 'navigation_dashboard',
        'label' => 'Dashboard',
        'label_display' => FALSE,
        'provider' => 'dashboard',
      ],
    ];

    return $blocks;
  }

  /**
   * Hide dashboard blocks from the blocks UI, and mark our navigation as safe.
   *
   * @todo Revisit if https://www.drupal.org/project/drupal/issues/3443882 lands.
   */
  #[Hook('block_alter')]
  public function blockAlter(array &$definitions): void {
    $block_ids = [
      'dashboard_placeholder',
      'dashboard_text_block',
      'dashboard_site_status',
      'navigation_dashboard',
    ];
    // Hide blocks from the blocks UI.
    foreach ($block_ids as $block_id) {
      if (isset($definitions[$block_id])) {
        $definitions[$block_id]['_block_ui_hidden'] = TRUE;
      }
    }

    // Allow the navigation dashboard block in navigation.
    if (isset($definitions['navigation_dashboard'])) {
      $definitions['navigation_dashboard']['allow_in_navigation'] = TRUE;
    }
  }

  /**
   * Implements hook_navigation_menu_link_tree_alter().
   */
  #[Hook('navigation_menu_link_tree_alter')]
  public function navigationMenuLinkTreeAlter(array &$tree): void {
    foreach ($tree as $key => $item) {
      // Skip elements where menu is not the 'admin' one.
      $menu_name = $item->link->getMenuName();
      if ($menu_name != 'admin') {
        continue;
      }

      // Remove unwanted Dashboard menu link from the admin menu,
      // as it has its own link at the first level.
      $plugin_id = $item->link->getPluginId();
      if ($plugin_id == 'system.dashboard') {
        unset($tree[$key]);
      }
    }
  }

  /**
   * Navigation block theme hook.
   */
  #[Hook('theme')]
  public function theme($existing, $type, $theme, $path): array {
    $items['menu_region__dashboard'] = [
      'variables' => [
        'url' => [],
        'title' => NULL,
        'icon' => NULL,
      ],
    ];
    return $items;
  }

}
