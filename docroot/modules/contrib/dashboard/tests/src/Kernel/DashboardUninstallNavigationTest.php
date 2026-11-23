<?php

declare(strict_types=1);

namespace Drupal\Tests\dashboard\Kernel;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that uninstalling the module cleans up its navigation block.
 */
#[Group('dashboard')]
#[RunTestsInSeparateProcesses]
class DashboardUninstallNavigationTest extends KernelTestBase {

  /**
   * Uninstalling the module removes the navigation_dashboard block placement.
   */
  public function testNavigationBlockRemovedOnUninstall(): void {
    $module_installer = $this->container->get(ModuleInstallerInterface::class);

    // The navigation_dashboard block is placed when the modules are installed:
    // navigation's hook_modules_installed reacts to the navigation_defaults
    // hook this module implements.
    $module_installer->install(['dashboard', 'navigation']);
    $this->assertTrue($this->hasNavigationDashboardBlock());

    $module_installer->uninstall(['dashboard']);

    // The placement must not survive the uninstall, otherwise the navigation
    // renders a "plugin not found" error for the now-missing block.
    $this->assertFalse($this->hasNavigationDashboardBlock());
  }

  /**
   * Whether navigation.block_layout contains the navigation_dashboard block.
   */
  private function hasNavigationDashboardBlock(): bool {
    $sections = $this->config('navigation.block_layout')->get('sections') ?? [];
    foreach ($sections as $section) {
      foreach ($section['components'] ?? [] as $component) {
        if (($component['configuration']['id'] ?? NULL) === 'navigation_dashboard') {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

}
