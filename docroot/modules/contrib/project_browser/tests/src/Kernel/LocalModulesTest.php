<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Kernel;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\project_browser\Plugin\ProjectBrowserSourceManager;
use Drupal\project_browser\Plugin\ProjectBrowserSource\LocalModules;
use Drupal\project_browser\Plugin\ProjectBrowserSourceInterface;
use Drupal\project_browser\ProjectBrowser\ProjectsResultsPage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the LocalModules source plugin.
 *
 * @group project_browser
 */
#[CoversClass(LocalModules::class)]
#[Group('project_browser')]
final class LocalModulesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['project_browser', 'user'];

  /**
   * Tests that the plugin sets the machine_name query filter if needed.
   */
  public function testDecoratorFiltersByMachineName(): void {
    $expectation = function (array $query): bool {
      return $query['machine_name'] === 'module_one,module_two,module_three';
    };
    $mock_decorated_source = $this->createMock(ProjectBrowserSourceInterface::class);
    $mock_decorated_source->expects($this->atLeastOnce())
      ->method('getProjects')
      ->with($this->callback($expectation))
      ->willReturn(new ProjectsResultsPage(0, [], 'Decorated', 'decorated'));

    $plugin = new LocalModules(
      $mock_decorated_source,
      [
        'package_names' => [
          'drupal/module_one',
          'drupal/module_two',
          'drupal/module_three',
        ],
      ],
      'local_modules',
      ['label' => 'Decorator'],
    );
    $result = $plugin->getProjects();
    $this->assertSame('local_modules', $result->pluginId);
    $this->assertSame('Decorator', $result->pluginLabel);
  }

  /**
   * Tests that the plugin skips extra filtering if no modules are installed.
   */
  public function testNoFilteringIfNoModulesAreInstalled(): void {
    $expectation = function (array $query): bool {
      return !array_key_exists('machine_name', $query);
    };
    $mock_decorated_source = $this->createMock(ProjectBrowserSourceInterface::class);
    $mock_decorated_source->expects($this->atLeastOnce())
      ->method('getProjects')
      ->with($this->callback($expectation))
      ->willReturn(new ProjectsResultsPage(0, [], 'Decorated', 'decorated'));

    $plugin = new LocalModules(
      $mock_decorated_source,
      [
        // Simulate a situation where no packages are installed.
        'package_names' => [],
      ],
      'local_modules',
      ['label' => 'Decorator'],
    );
    $result = $plugin->getProjects();
    $this->assertSame('local_modules', $result->pluginId);
    $this->assertSame('Decorator', $result->pluginLabel);
  }

  /**
   * Tests that the decorator properly populates non-volatile project storage.
   */
  public function testProjectStoreIsPopulated(): void {
    $this->container->get(ModuleInstallerInterface::class)->install([
      'project_browser_test',
    ]);
    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', [
        'local_modules' => [],
        'project_browser_test_mock' => [],
      ])
      ->save();

    $result = $this->container->get(ProjectBrowserSourceManager::class)
      ->createInstance('local_modules')
      ->getProjects();
    // Ensure that the decorator "took ownership" of the projects returned by
    // the decorated plugin, even though the mock plugin (which is being
    // decorated) did the actual work.
    $this->assertSame('local_modules', $result->pluginId);
    $this->assertContains('cream_cheese', array_column($result->list, 'machineName'));
  }

}
