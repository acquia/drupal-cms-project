<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_dashboard\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_dashboard\Plugin\ProjectBrowserSource\AiDashboardRecommended;
use Drupal\project_browser\Plugin\ProjectBrowserSourceManager;
use Drupal\project_browser\ProjectBrowser\ProjectsResultsPage;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the recommended source plugin survives serialization.
 *
 * @group ai_dashboard
 * @coversDefaultClass \Drupal\ai_dashboard\Plugin\ProjectBrowserSource\AiDashboardRecommended
 */
#[RunTestsInSeparateProcesses]
class AiDashboardRecommendedSerializationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'key',
    'ai',
    'project_browser',
    'ai_dashboard',
  ];

  /**
   * Ensures the plugin survives a serialize()/unserialize() round trip.
   *
   * `DependencySerializationTrait::__sleep()`/`__wakeup()` are inherited via
   * `ProjectBrowserSourceBase`'s parent class, and rely on
   * `get_object_vars($this)` to find injected service properties. Before
   * this plugin declared its constructor-promoted properties as `protected`
   * and used the trait directly, those properties were invisible to the
   * inherited methods (private properties are only visible to
   * `get_object_vars()` from their exact declaring class, and protected
   * ones only became visible once the trait ran in this class's own
   * scope), so they were silently dropped during serialization and left
   * uninitialized after unserialize() — causing a fatal error the next
   * time one of them was accessed. This is the Drupal 12 / PHP 8.4
   * compatibility fix this test guards.
   *
   * @see https://www.drupal.org/node/3110266
   */
  public function testSurvivesSerialization(): void {
    /** @var \Drupal\project_browser\Plugin\ProjectBrowserSourceManager $manager */
    $manager = $this->container->get(ProjectBrowserSourceManager::class);

    $plugin = $manager->createInstance('ai_dashboard_recommended');
    $this->assertInstanceOf(AiDashboardRecommended::class, $plugin);

    $unserialized = unserialize(serialize($plugin));
    $this->assertInstanceOf(AiDashboardRecommended::class, $unserialized);

    // With no configured source URI, getProjects() short-circuits to an
    // empty result set, but only after touching the cacheBackend property
    // first. Before the fix this line threw "must not be accessed before
    // initialization" on the unserialized object.
    $result = $unserialized->getProjects();
    $this->assertInstanceOf(ProjectsResultsPage::class, $result);
  }

}
