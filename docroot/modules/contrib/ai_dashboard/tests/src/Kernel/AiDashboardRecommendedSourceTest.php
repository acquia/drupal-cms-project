<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_dashboard\Kernel;

use Drupal\ai_dashboard\Plugin\ProjectBrowserSource\AiDashboardRecommended;
use Drupal\KernelTests\KernelTestBase;
use Drupal\project_browser\Plugin\ProjectBrowserSourceManager;
use Drupal\project_browser\QueryManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the recommended recipes source of Project Browser.
 */
#[Group('ai_dashboard')]
#[RunTestsInSeparateProcesses]
#[CoversClass(AiDashboardRecommended::class)]
final class AiDashboardRecommendedSourceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_dashboard',
    'dashboard',
    'project_browser',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['project_browser']);
  }

  /**
   * Writes a recipes list to a temporary file and returns its path.
   */
  private function createRecipesFile(): string {
    $path = $this->siteDirectory . '/recipes.yml';
    file_put_contents($path, <<<YAML
drupal/ai_recipe_test:
  title: 'AI Test Recipe'
  type: recipe
  logo: null
  core: ^10.4 || ^11
  machineName: ai_recipe_test
  body:
    summary: 'A recipe used in tests.'
  packageName: drupal/ai_recipe_test
YAML);
    return $path;
  }

  /**
   * Sets the stored configuration of the source.
   *
   * @param array<string, mixed> $configuration
   *   The source configuration.
   */
  private function setSourceConfiguration(array $configuration): void {
    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', ['ai_dashboard_recommended' => $configuration])
      ->save();
  }

  /**
   * Tests that saving the Project Browser settings keeps the source URI.
   */
  public function testDefaultUriIsUsedWhenNoConfigurationIsStored(): void {
    // The Project Browser settings form stores an empty configuration for
    // each enabled source.
    $this->setSourceConfiguration([]);

    $source = $this->container->get(ProjectBrowserSourceManager::class)
      ->createInstance('ai_dashboard_recommended');
    $this->assertSame(AiDashboardRecommended::DEFAULT_URI, $source->getConfiguration()['uri']);
  }

  /**
   * Tests that a failed load is reported and is not cached.
   */
  public function testFailedLoadIsNotCached(): void {
    $query_manager = $this->container->get(QueryManager::class);

    // Without a URI, no projects can be loaded.
    $this->setSourceConfiguration(['uri' => '']);
    $results = $query_manager->getProjects('ai_dashboard_recommended');
    $this->assertCount(0, $results->list);
    $this->assertNotEmpty($results->error);

    // Once the data is available, the projects must show up right away,
    // rather than the empty result being served from the cache.
    $this->setSourceConfiguration(['uri' => $this->createRecipesFile()]);
    $results = $query_manager->getProjects('ai_dashboard_recommended');
    $this->assertEmpty($results->error);
    $this->assertCount(1, $results->list);
    $this->assertSame('AI Test Recipe', (string) $results->list[0]->title);
  }

  /**
   * Tests that the update hook clears stale cached results.
   */
  public function testUpdateClearsStaleCache(): void {
    $cache = $this->container->get('cache.project_browser');
    $cache->set('query:stale', ['list' => []], -1 + time() + 86400);

    require_once $this->root . '/' . $this->container->get('extension.list.module')->getPath('ai_dashboard') . '/ai_dashboard.install';
    ai_dashboard_update_11002();

    $this->assertFalse($cache->get('query:stale'));
  }

}
