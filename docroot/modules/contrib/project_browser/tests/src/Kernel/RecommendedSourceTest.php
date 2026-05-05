<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Kernel;

use Drupal\Component\FileSystem\FileSystem;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\project_browser\QueryManager;
use Drupal\project_browser\Plugin\ProjectBrowserSource\Recommended;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the `recommended` source plugin.
 */
#[Group('project_browser')]
#[CoversClass(Recommended::class)]
final class RecommendedSourceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['project_browser', 'user'];

  /**
   * The URI of the projects list file.
   */
  private readonly string $uri;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->uri = 'public://recommended-projects.yml';
    file_put_contents($this->uri, Yaml::encode([
      [
        'machineName' => 'test',
        'logo' => 'https://example.com/logo.png',
        'core' => \Drupal::VERSION,
        'body' => ['value' => 'A test project.'],
        'title' => 'Test',
        'packageName' => 'drupal/test',
        'projectUsageTotal' => 100,
        'isCovered' => FALSE,
        'isMaintained' => FALSE,
        'url' => 'https://www.drupal.org/project/test',
      ],
      [
        'machineName' => 'not_compatible',
        'core' => '^9',
        'body' => ['value' => 'This project is not compatible.'],
        'title' => 'Not compatible',
        'packageName' => 'drupal/not_compatible',
      ],
    ]));
  }

  /**
   * Tests loading projects from a local filesystem path.
   */
  public function testLoadFromPath(): void {
    $file = uniqid(FileSystem::getOsTemporaryDirectory() . '/');
    copy($this->uri, $file);
    $this->assertFileExists($file);
    $this->assertProjectsAreLoaded($file);
  }

  /**
   * Tests loading projects from a local stream URI.
   */
  public function testLoadFromUri(): void {
    $this->assertProjectsAreLoaded($this->uri);
  }

  /**
   * Tests loading projects from a remote URL.
   */
  public function testLoadFromUrl(): void {
    // Mock the HTTP client response when fetching the list.
    $file = fopen($this->uri, 'r');
    $this->assertIsResource($file);
    $response = new Response(body: $file);
    $client = new Client([
      'handler' => new MockHandler([$response]),
    ]);
    $this->container->set('http_client', $client);

    $this->assertProjectsAreLoaded('http://www.example.com/recommended-projects.yml');
  }

  /**
   * Tests that the plugin loads nothing when no source URI is configured.
   */
  public function testNoUri(): void {
    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', [
        // This is not allowed by config schema, but it could still happen if
        // validation is skipped.
        'recommended' => ['uri' => NULL],
      ])
      ->save();

    $this->assertCount(0, $this->container->get(QueryManager::class)->getProjects('recommended')->list);
  }

  /**
   * Tests that the plugin loads data correctly in different ways.
   *
   * @param string $uri
   *   The URI, path, or URL from which to load the list of projects.
   */
  private function assertProjectsAreLoaded(string $uri): void {
    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', [
        'recommended' => ['uri' => $uri],
      ])
      ->save();

    $query_manager = $this->container->get(QueryManager::class);

    $results = $query_manager->getProjects('recommended');
    $this->assertSame(2, $results->totalResults);
    /** @var \Drupal\project_browser\ProjectBrowser\Project $project */
    $project = $results->list[0];
    $this->assertSame('test', $project->machineName);
    $this->assertTrue($project->isCompatible);
    $this->assertSame('Test', $project->title);
    $this->assertSame('drupal/test', $project->packageName);
    $this->assertSame('drupal-test-test', $project->id);
    $this->assertInstanceOf(Url::class, $project->logo);
    $this->assertInstanceOf(Url::class, $project->url);
    // These are always hard-coded, regardless of what is actually in the file.
    $this->assertNull($project->projectUsageTotal);
    $this->assertTrue($project->isCovered);
    $this->assertTrue($project->isMaintained);

    // The second project is not compatible with the current Drupal version.
    $this->assertFalse($results->list[1]->isCompatible);
    // It also has no logo or URL.
    $this->assertNull($results->list[1]->logo);
    $this->assertNull($results->list[1]->url);
  }

}
