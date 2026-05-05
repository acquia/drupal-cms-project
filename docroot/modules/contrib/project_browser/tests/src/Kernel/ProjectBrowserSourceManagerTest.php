<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\project_browser\Plugin\ProjectBrowserSourceManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the source plugin manager service.
 */
#[Group('project_browser')]
#[CoversClass(ProjectBrowserSourceManager::class)]
final class ProjectBrowserSourceManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['project_browser', 'user'];

  /**
   * Tests that enabled sources' configuration is stored correctly.
   */
  public function testStoredConfiguration(): void {
    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', [
        'drupal_core' => [
          'order' => ['views', 'jsonapi'],
        ],
      ])
      ->save();

    $sources = $this->container->get(ProjectBrowserSourceManager::class)
      ->getAllEnabledSources();
    ['order' => $order] = $sources['drupal_core']->getConfiguration();
    $this->assertSame(['views', 'jsonapi'], $order);

    // Only enabled sources should be instantiated.
    $this->assertSame(['drupal_core'], array_keys($sources));
  }

}
