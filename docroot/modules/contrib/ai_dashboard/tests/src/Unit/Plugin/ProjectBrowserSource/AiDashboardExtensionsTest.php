<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_dashboard\Unit\Plugin\ProjectBrowserSource;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ai_dashboard\Plugin\ProjectBrowserSource\AiDashboardExtensions;
use Drupal\project_browser\Plugin\ProjectBrowserSourceInterface;
use Drupal\project_browser\ProjectBrowser\ProjectsResultsPage;

/**
 * @coversDefaultClass \Drupal\ai_dashboard\Plugin\ProjectBrowserSource\AiDashboardExtensions
 * @group ai_dashboard
 */
class AiDashboardExtensionsTest extends UnitTestCase {

  /**
   * Builds a plugin instance whose wrapped source is always the given mock.
   */
  protected function createPlugin(ProjectBrowserSourceInterface $wrapped_source, array $configuration = []): AiDashboardExtensions {
    $source_manager = $this->createMock(PluginManagerInterface::class);
    $source_manager->method('createInstance')->willReturn($wrapped_source);

    return new AiDashboardExtensions($source_manager, $configuration, 'ai_dashboard_extensions', ['label' => 'AI extensions']);
  }

  /**
   * @covers ::getProjects
   */
  public function testGetProjectsAlwaysForcesTheAiCategory(): void {
    $results_page = new ProjectsResultsPage(0, [], 'AI extensions', 'ai_dashboard_extensions');

    $wrapped_source = $this->createMock(ProjectBrowserSourceInterface::class);
    $wrapped_source->expects($this->once())
      ->method('getProjects')
      ->with([
        'search' => 'chatbot',
        'categories' => AiDashboardExtensions::AI_CATEGORY_UUID,
      ])
      ->willReturn($results_page);

    // Even though the caller asked for a different category, only the AI
    // category should ever be requested from the wrapped source.
    $plugin = $this->createPlugin($wrapped_source);
    $result = $plugin->getProjects([
      'search' => 'chatbot',
      'categories' => ['some-other-category-uuid'],
    ]);

    $this->assertSame('ai_dashboard_extensions', $result->pluginId);
    $this->assertSame($results_page->list, $result->list);
  }

  /**
   * @covers ::getProjects
   * @covers ::defaultConfiguration
   */
  public function testGetProjectsUsesTheConfiguredWrappedSourceByDefault(): void {
    $wrapped_source = $this->createMock(ProjectBrowserSourceInterface::class);
    $wrapped_source->method('getProjects')
      ->willReturn(new ProjectsResultsPage(0, [], 'AI extensions', 'ai_dashboard_extensions'));

    $source_manager = $this->createMock(PluginManagerInterface::class);
    $source_manager->expects($this->once())
      ->method('createInstance')
      ->with('drupalorg_jsonapi')
      ->willReturn($wrapped_source);

    $plugin = new AiDashboardExtensions($source_manager, [], 'ai_dashboard_extensions', ['label' => 'AI extensions']);
    $plugin->getProjects();
  }

  /**
   * @covers ::getProjects
   */
  public function testGetProjectsUsesTheConfiguredWrappedSource(): void {
    $wrapped_source = $this->createMock(ProjectBrowserSourceInterface::class);
    $wrapped_source->method('getProjects')
      ->willReturn(new ProjectsResultsPage(0, [], 'AI extensions', 'ai_dashboard_extensions'));

    $source_manager = $this->createMock(PluginManagerInterface::class);
    $source_manager->expects($this->once())
      ->method('createInstance')
      ->with('a_custom_source')
      ->willReturn($wrapped_source);

    $plugin = new AiDashboardExtensions($source_manager, ['source' => 'a_custom_source'], 'ai_dashboard_extensions', ['label' => 'AI extensions']);
    $plugin->getProjects();
  }

  /**
   * @covers ::getFilterDefinitions
   */
  public function testGetFilterDefinitionsHidesTheCategoriesFilter(): void {
    $wrapped_source = $this->createMock(ProjectBrowserSourceInterface::class);
    $wrapped_source->method('getFilterDefinitions')->willReturn([
      'search' => 'search filter',
      'categories' => 'categories filter',
      'maintenance_status' => 'maintenance filter',
    ]);

    $plugin = $this->createPlugin($wrapped_source);
    $filters = $plugin->getFilterDefinitions();

    $this->assertArrayNotHasKey('categories', $filters);
    $this->assertSame([
      'search' => 'search filter',
      'maintenance_status' => 'maintenance filter',
    ], $filters);
  }

  /**
   * @covers ::getSortOptions
   */
  public function testGetSortOptionsDelegatesToTheWrappedSource(): void {
    $wrapped_source = $this->createMock(ProjectBrowserSourceInterface::class);
    $wrapped_source->method('getSortOptions')->willReturn(['a_z' => 'A-Z']);

    $plugin = $this->createPlugin($wrapped_source);
    $this->assertSame(['a_z' => 'A-Z'], $plugin->getSortOptions());
  }

}
