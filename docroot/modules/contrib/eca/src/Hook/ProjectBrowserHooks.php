<?php

namespace Drupal\eca\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Implements Project Browser related hooks for the ECA module.
 *
 * When both the ECA module and the Project Browser module are installed, the
 * `eca_guide_library` source plugin is automatically added to Project Browser's
 * enabled sources, regardless of the order in which the two modules were
 * installed.
 *
 * This class deliberately references Project Browser only by its string config
 * keys, never by any of its PHP symbols, so that it remains safe to autoload
 * when Project Browser is not installed.
 */
class ProjectBrowserHooks {

  /**
   * The Project Browser source plugin ID exposed by ECA.
   */
  protected const string SOURCE_ID = 'eca_guide_library';

  /**
   * Constructs a new ProjectBrowserHooks object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   */
  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_modules_installed().
   *
   * @param string[] $modules
   *   The machine names of the modules that were just installed.
   * @param bool $is_syncing
   *   TRUE if the module is being installed as part of a configuration import.
   */
  #[Hook('modules_installed')]
  public function modulesInstalled(array $modules, bool $is_syncing): void {
    // Never write config during a configuration import; the desired state must
    // come from the sync source in that case.
    if ($is_syncing) {
      return;
    }

    // Only react when this install event concerns one of the two relevant
    // modules. Unrelated installs are ignored.
    if (!in_array('eca', $modules, TRUE) && !in_array('project_browser', $modules, TRUE)) {
      return;
    }

    // Both modules must be enabled for the source to be usable. ECA is
    // obviously enabled (its own hook is running), so only Project Browser
    // needs confirming.
    if (!$this->moduleHandler->moduleExists('project_browser')) {
      return;
    }

    $config = $this->configFactory->getEditable('project_browser.admin_settings');
    $enabled = $config->get('enabled_sources') ?? [];

    // Idempotency: do not re-save when the source is already enabled.
    if (array_key_exists(self::SOURCE_ID, $enabled)) {
      return;
    }

    // Add the source while preserving existing entries. Do not touch
    // `default_source` or any other property.
    $enabled[self::SOURCE_ID] = [];
    $config->set('enabled_sources', $enabled)->save();
  }

}
