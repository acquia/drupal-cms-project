<?php

namespace Drupal\navigation_extra_tools\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\navigation_extra_tools\DevelMenu;

/**
 * Provide hooks for navigation extra tools.
 */
class NavigationExtraToolsHooks {

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly AccountInterface $currentUser,
    private readonly DevelMenu $develMenu,
  ) {}

  /**
   * Implements hook_library_info_alter().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(&$libraries, $extension) {
    if ($extension === 'navigation_extra_tools' && isset($libraries['icon'])) {
      if ($this->moduleHandler->moduleExists('toolbar')) {
        $libraries['icon']['dependencies'][] = 'toolbar/drupal.toolbar';
      }
    }
  }

  /**
   * Implements hook_page_attachments().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$page) {
    if ($this->currentUser->hasPermission('access navigation')) {
      $page['#attached']['library'][] = 'navigation_extra_tools/icon';
    }
  }

  /**
   * Implements hook_modules_installed().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('modules_installed')]
  public function modulesInstalled($modules, $is_syncing) {
    if (in_array('devel', $modules)) {
      $this->develMenu->enableInitialOptions();
    }
  }

}
