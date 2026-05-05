<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Kernel;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\project_browser\QueryManager;
use Drupal\project_browser_test\Plugin\ProjectBrowserSource\ProjectBrowserTestMock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the query manager service.
 *
 * @group project_browser
 */
#[CoversClass(QueryManager::class)]
#[Group('project_browser')]
final class QueryManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'project_browser',
    'project_browser_test',
    'user',
  ];

  /**
   * Tests that query results are not cached if there was an error.
   */
  public function testErrorsAreNotCached(): void {
    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', [
        'project_browser_test_mock' => [],
      ])
      ->save();

    // Mock a cache backend that should only be called once.
    $cache_backend = $this->createMock(CacheBackendInterface::class);
    $cache_backend->expects($this->once())
      ->method('set')
      ->withAnyParameters();
    $this->container->set('cache.project_browser', $cache_backend);

    /** @var \Drupal\project_browser\QueryManager $query_manager */
    $query_manager = $this->container->get(QueryManager::class);
    $query_manager->getProjects('project_browser_test_mock', ['error' => FALSE]);

    ProjectBrowserTestMock::$resultsError = 'Nope!';
    $query_manager->getProjects('project_browser_test_mock', ['error' => TRUE]);
  }

}
