<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Kernel;

use Drupal\Component\Assertion\Inspector;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\project_browser\Plugin\ProjectBrowserSource\SortHelper;
use Drupal\project_browser\Plugin\ProjectBrowserSourceManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the SortHelper class.
 *
 * @group project_browser
 */
#[CoversClass(SortHelper::class)]
#[Group('project_browser')]
final class SortHelperTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get(ModuleInstallerInterface::class)->install([
      'project_browser_test',
      'user',
    ]);
  }

  /**
   * Tests returning projects in an order defined by configuration.
   *
   * @legacy-covers ::sortInDefinedOrder
   */
  public function testDefinableOrder(): void {
    $projects = $this->container->get(ProjectBrowserSourceManager::class)
      ->createInstance('project_browser_test_mock')
      ->getProjects()
      ->list;

    $original_order = array_column($projects, 'id');
    $configured_order = array_slice($original_order, -3);
    assert(!empty($configured_order) && Inspector::assertAllNotEmpty($configured_order));
    SortHelper::sortInDefinedOrder($projects, $configured_order);
    $sorted_order = array_column($projects, 'id');
    $this->assertSame($configured_order, array_slice($sorted_order, 0, 3));
    // Projects that are not part of the defined order appear in their original
    // order, after the projects that are in defined order.
    $this->assertSame($original_order[0], $sorted_order[3]);
  }

}
