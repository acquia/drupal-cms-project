<?php

declare(strict_types=1);

namespace Drupal\Tests\eca\Kernel\ProjectBrowserSource;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Plugin\ProjectBrowserSource\EcaGuideLibrary;
use Drupal\project_browser\Plugin\ProjectBrowserSourceInterface;
use Drupal\project_browser\Plugin\ProjectBrowserSourceManager;
use Drupal\project_browser\ProjectBrowser\Filter\MultipleChoiceFilter;
use Drupal\project_browser\ProjectType;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ECA Guide Library Project Browser source plugin.
 */
#[Group('eca')]
#[Group('eca_core')]
#[CoversClass(EcaGuideLibrary::class)]
#[RunTestsInSeparateProcesses]
final class EcaGuideLibrarySourceTest extends KernelTestBase {

  /**
   * The documentation domain used by the test feed.
   */
  private const string TEST_DOMAIN = 'https://example.test';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'modeler_api',
    'eca',
    'project_browser',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['eca']);
    // Point the feed at a controlled documentation domain. The mocked HTTP
    // client returns the fixture regardless of the exact URL requested.
    $this->config('eca.settings')
      ->set('documentation_domain', self::TEST_DOMAIN)
      ->save();
  }

  /**
   * Creates the source plugin with a mocked HTTP client.
   *
   * The mock client must be registered in the container before the plugin is
   * instantiated, because the plugin reads `http_client` from the container in
   * its create() method.
   *
   * @param \GuzzleHttp\Handler\MockHandler $handler
   *   The mock handler that provides the queued responses.
   *
   * @return \Drupal\project_browser\Plugin\ProjectBrowserSourceInterface
   *   The instantiated source plugin.
   */
  private function createSource(MockHandler $handler): ProjectBrowserSourceInterface {
    $client = new Client(['handler' => $handler]);
    $this->container->set('http_client', $client);

    $manager = $this->container->get(ProjectBrowserSourceManager::class);
    assert($manager instanceof ProjectBrowserSourceManager);
    $source = $manager->createInstance('eca_guide_library');
    assert($source instanceof ProjectBrowserSourceInterface);
    return $source;
  }

  /**
   * Returns the JSON fixture payload as a string.
   *
   * @return string
   *   The contents of the fixture feed.
   */
  private function getFixture(): string {
    $contents = file_get_contents(__DIR__ . '/../../../fixtures/project_browser_library-feed.json');
    $this->assertIsString($contents);
    return $contents;
  }

  /**
   * Tests that models are mapped to Project objects correctly.
   */
  public function testGetProjectsMapsModels(): void {
    $source = $this->createSource(new MockHandler([
      new Response(200, [], $this->getFixture()),
    ]));

    $results = $source->getProjects();
    $this->assertSame(2, $results->totalResults);
    $this->assertCount(2, $results->list);

    /** @var \Drupal\project_browser\ProjectBrowser\Project $project */
    $project = $results->list[0];
    $this->assertSame('welcome_email', $project->machineName);
    $this->assertSame('Welcome Email', (string) $project->title);
    $this->assertSame('drupal/eca_guide_welcome_email', $project->packageName);
    $this->assertSame(ProjectType::Recipe, $project->type);
    $this->assertTrue($project->isCompatible);
    $this->assertTrue($project->isMaintained);
    $this->assertTrue($project->isCovered);
    $this->assertSame(['workflow' => 'Workflow'], $project->categories);

    // The logo is a bare URL string in the v1.2 feed.
    $this->assertNotNull($project->logo);
    $this->assertSame('https://ecaguide.org/library/simple/welcome_email/logo.png', $project->logo->toString());

    // Screenshots are bare URL strings; each maps to an image with the model
    // title as alt text.
    $this->assertCount(1, $project->images);
    $image = $project->images[0];
    $this->assertInstanceOf(Url::class, $image['file']);
    $this->assertSame('https://ecaguide.org/library/simple/welcome_email/screenshot.png', $image['file']->toString());
    $this->assertSame('Welcome Email', $image['alt']);

    // The feed provides a per-model page URL for this model.
    $this->assertNotNull($project->url);
    $this->assertSame('https://ecaguide.org/library/simple/welcome_email/', $project->url->toString());

    // The second model has no logo, no screenshots and no URL.
    $this->assertSame('auto_publish', $results->list[1]->machineName);
    $this->assertNull($results->list[1]->logo);
    $this->assertSame([], $results->list[1]->images);
    $this->assertNull($results->list[1]->url);
  }

  /**
   * Tests that the categories filter narrows the result set.
   */
  public function testCategoriesFilterNarrowsResults(): void {
    $source = $this->createSource(new MockHandler([
      new Response(200, [], $this->getFixture()),
    ]));

    $results = $source->getProjects(['categories' => 'content']);
    $this->assertSame(1, $results->totalResults);
    $this->assertSame('auto_publish', $results->list[0]->machineName);
  }

  /**
   * Tests that the search filter matches title and summary.
   */
  public function testSearchFilter(): void {
    $source = $this->createSource(new MockHandler([
      new Response(200, [], $this->getFixture()),
    ]));

    $results = $source->getProjects(['search' => 'welcome']);
    $this->assertSame(1, $results->totalResults);
    $this->assertSame('welcome_email', $results->list[0]->machineName);
  }

  /**
   * Tests that the machine name filter returns an exact match.
   */
  public function testMachineNameFilter(): void {
    $source = $this->createSource(new MockHandler([
      new Response(200, [], $this->getFixture()),
    ]));

    $results = $source->getProjects(['machine_name' => 'auto_publish']);
    $this->assertSame(1, $results->totalResults);
    $this->assertSame('auto_publish', $results->list[0]->machineName);
  }

  /**
   * Tests that the categories filter definition is built from the feed.
   */
  public function testFilterDefinitions(): void {
    $source = $this->createSource(new MockHandler([
      new Response(200, [], $this->getFixture()),
    ]));

    $filters = $source->getFilterDefinitions();
    $this->assertArrayHasKey('search', $filters);
    $this->assertArrayHasKey('categories', $filters);

    $categories = $filters['categories'];
    $this->assertInstanceOf(MultipleChoiceFilter::class, $categories);
    $this->assertSame(
      ['content' => 'Content', 'workflow' => 'Workflow'],
      $categories->choices,
    );
  }

  /**
   * Tests that a failing HTTP client yields an empty page without throwing.
   */
  public function testFailedFetchReturnsEmptyPage(): void {
    $source = $this->createSource(new MockHandler([
      new \RuntimeException('Network is down.'),
    ]));

    $results = $source->getProjects();
    $this->assertSame(0, $results->totalResults);
    $this->assertSame([], $results->list);
  }

  /**
   * Tests that the A-Z sort orders projects by title ascending.
   *
   * The fixture emits the models in feed order ("Welcome Email" before
   * "Auto Publish"), which is not alphabetical, so a working sort must
   * reorder them.
   */
  public function testSortAscending(): void {
    $source = $this->createSource(new MockHandler([
      new Response(200, [], $this->getFixture()),
    ]));

    $results = $source->getProjects(['sort' => 'a_z']);
    $titles = array_map(
      static fn ($project): string => (string) $project->title,
      $results->list,
    );
    $this->assertSame(['Auto Publish', 'Welcome Email'], $titles);
  }

  /**
   * Tests that the Z-A sort orders projects by title descending.
   */
  public function testSortDescending(): void {
    $source = $this->createSource(new MockHandler([
      new Response(200, [], $this->getFixture()),
    ]));

    $results = $source->getProjects(['sort' => 'z_a']);
    $titles = array_map(
      static fn ($project): string => (string) $project->title,
      $results->list,
    );
    $this->assertSame(['Welcome Email', 'Auto Publish'], $titles);
  }

  /**
   * Tests that only data-backed sort options are advertised.
   *
   * The "updated" option was removed because the feed does not carry an
   * updated timestamp yet.
   */
  public function testSortOptions(): void {
    $source = $this->createSource(new MockHandler([
      new Response(200, [], $this->getFixture()),
    ]));

    $options = $source->getSortOptions();
    $this->assertSame(['a_z', 'z_a'], array_keys($options));
  }

}
