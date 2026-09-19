<?php

declare(strict_types=1);

namespace Drupal\navigation_extra_tools;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;

/**
 * Service to provide functions for the Devel module.
 */
final class DevelMenu {

  /**
   * Constructs a DevelMenu object.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly MenuLinkManagerInterface $pluginManagerMenuLink,
  ) {}

  /**
   * Items to enable by default.
   *
   * Legacy list copied from Admin Toolbar Extra Tools module. However, list was
   * hard coded in that module, can now be configured.
   */
  private const TO_ENABLE = [
    'devel.admin_settings_link',
    'devel.configs_list',
    'devel.reinstall',
    'devel.menu_rebuild',
    'devel.state_system_page',
    'devel.theme_registry',
    'devel.entity_info_page',
    'devel.session',
    'devel.elements_page',
  ];

  /**
   * Ensure initial menu options are enabled.
   */
  public function enableInitialOptions(): void {
    // Only continue if Devel module enabled.
    if ($this->moduleHandler->moduleExists('devel')) {
      // Get initial enabled list.
      $config = $this->configFactory->getEditable('devel.toolbar.settings');
      $toolbarItems = $config->get('toolbar_items');
      if ($toolbarItems) {
        // Loop through items to be enabled.
        foreach (self::TO_ENABLE as $item) {
          // Add item to toolbar items if not there already.
          if (!in_array($item, $toolbarItems)) {
            $toolbarItems[] = $item;
          }
        }
        // Update and save config.
        $config
          ->set('toolbar_items', $toolbarItems)
          ->save();
        // Rebuild menu.
        $this->pluginManagerMenuLink->rebuild();
      }
    }
  }

}
