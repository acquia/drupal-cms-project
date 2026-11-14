<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_dashboard\Unit;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the default AI Dashboard layout shipped in config/install.
 *
 * @group ai_dashboard
 */
class DashboardLayoutConfigTest extends UnitTestCase {

  /**
   * Returns the block components of the default dashboard layout.
   */
  protected function getComponents(): array {
    $path = dirname(__DIR__, 3) . '/config/install/dashboard.dashboard.ai_dashboard.yml';
    $config = Yaml::parseFile($path);

    return $config['layout'][0]['components'];
  }

  /**
   * The old "Extensions" block used to list installed modules; it's gone.
   */
  public function testTheModuleListPackagesBlockHasBeenRemoved(): void {
    $ids = array_column(array_column($this->getComponents(), 'configuration'), 'id');
    $this->assertNotContains('module_list_packages', $ids);
  }

  /**
   * The "Extensions" region is now a Project Browser block scoped to AI.
   */
  public function testTheExtensionsRegionUsesTheAiProjectBrowserSource(): void {
    $components = array_filter(
      $this->getComponents(),
      fn (array $component): bool => $component['configuration']['id'] === 'project_browser_block:ai_dashboard_extensions',
    );
    $this->assertCount(1, $components, 'Expected exactly one AI extensions project browser block.');

    $configuration = reset($components)['configuration'];
    $this->assertSame('Extensions', $configuration['label']);
    $this->assertSame('project_browser', $configuration['provider']);
    // The category filter is fixed by the source itself, so it must not be
    // one of the filters the block exposes to the user.
    $this->assertNotContains('categories', $configuration['filters']);
  }

}
