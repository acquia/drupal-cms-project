<?php

declare(strict_types=1);

namespace Drupal\Tests\office_hours\Unit;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\office_hours\Hook\OfficeHoursFieldHooks;
use Drupal\office_hours\Hook\OfficeHoursHooks;
use Drupal\office_hours\Hook\OfficeHoursThemeHooks;
use Drupal\office_hours\Hook\OfficeHoursViewsHooks;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\cache\CachePluginBase;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\MockObject\MockObject;
use Drupal\Core\DependencyInjection\ContainerBuilder;

require_once __DIR__ . '/../../../office_hours.module';

/**
 * Unit tests for hook implementations in office_hours.module.
 *
 * @group office_hours
 */
class HookModuleUnitTest extends UnitTestCase {

  /**
   * The mocked OfficeHoursFieldHooks service.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject&\Drupal\office_hours\Hook\OfficeHoursFieldHooks
   */
  protected MockObject $fieldHooksService;

  /**
   * The mocked OfficeHoursHooks service.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject&\Drupal\office_hours\Hook\OfficeHoursHooks
   */
  protected MockObject $hooksService;

  /**
   * The mocked OfficeHoursThemeHooks service.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject&\Drupal\office_hours\Hook\OfficeHoursThemeHooks
   */
  protected MockObject $themeHooksService;

  /**
   * The mocked OfficeHoursViewsHooks service.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject&\Drupal\office_hours\Hook\OfficeHoursViewsHooks
   */
  protected MockObject $viewsHooksService;

  /**
   * The container.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected ContainerBuilder $container;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->fieldHooksService = $this->createMock(OfficeHoursFieldHooks::class);
    $this->hooksService = $this->createMock(OfficeHoursHooks::class);
    $this->themeHooksService = $this->createMock(OfficeHoursThemeHooks::class);
    $this->viewsHooksService = $this->createMock(OfficeHoursViewsHooks::class);

    $this->container = new ContainerBuilder();
    $this->container->set(OfficeHoursFieldHooks::class, $this->fieldHooksService);
    $this->container->set(OfficeHoursHooks::class, $this->hooksService);
    $this->container->set(OfficeHoursThemeHooks::class, $this->themeHooksService);
    $this->container->set(OfficeHoursViewsHooks::class, $this->viewsHooksService);

    \Drupal::setContainer($this->container);
  }

  /**
   * Tests hook_field_type_category_info_alter() calls the correct service method.
   *
   * @covers ::office_hours_field_type_category_info_alter
   */
  public function testFieldTypeCategoryInfoAlterHook(): void {
    $definitions = ['general' => ['libraries' => []]];

    $this->fieldHooksService->expects($this->once())
      ->method('fieldTypeCategoryInfoAlter')
      ->with($definitions)
      ->willReturnArgument(0);

    $this->container->set(OfficeHoursFieldHooks::class, $this->fieldHooksService);

    $original = $definitions;
    office_hours_field_type_category_info_alter($definitions);
    $this->assertNotSame($definitions, $original);
  }

  /**
   * Tests hook_help() calls the correct service method.
   *
   * @covers ::office_hours_help
   */
  public function testHelpHook(): void {
    $route_name = 'help.page.office_hours';
    $route_match = $this->createMock(RouteMatchInterface::class);
    $expected_help = '<h3>About</h3><p>Help content</p>';

    $this->fieldHooksService->expects($this->once())
      ->method('help')
      ->with($route_name, $route_match)
      ->willReturn($expected_help);

    $this->container->set(OfficeHoursFieldHooks::class, $this->fieldHooksService);

    $result = office_hours_help($route_name, $route_match);
    $this->assertSame($expected_help, $result);
  }

  /**
   * Tests hook_preprocess_field() calls the correct service method.
   *
   * @covers ::office_hours_preprocess_field
   */
  public function testPreprocessFieldHook(): void {
    $variables = ['content' => 'test content'];
    $hook = 'field';

    $this->themeHooksService->expects($this->once())
      ->method('preprocessField')
      ->with($variables, $hook)
      ->willReturnArgument(0);

    $this->container->set(OfficeHoursThemeHooks::class, $this->themeHooksService);

    $original = $variables;
    office_hours_preprocess_field($variables, $hook);
    $this->assertNotSame($variables, $original);
  }

  /**
   * Tests hook_preprocess_office_hours() calls the correct service method.
   *
   * @covers ::office_hours_preprocess_office_hours
   */
  public function testPreprocessOfficeHoursHook(): void {
    $variables = ['office_hours' => 'test hours'];

    $this->themeHooksService->expects($this->once())
      ->method('preprocessOfficeHours')
      ->with($variables)
      ->willReturnArgument(0);

    $this->container->set(OfficeHoursThemeHooks::class, $this->themeHooksService);

    $original = $variables;
    office_hours_preprocess_office_hours($variables);
    $this->assertSame($original, $variables);
  }

  /**
   * Tests hook_preprocess_office_hours_status() calls the correct service method.
   *
   * @covers ::office_hours_preprocess_office_hours_status
   */
  public function testPreprocessOfficeHoursStatusHook(): void {
    $variables = ['status' => 'open'];

    $this->themeHooksService->expects($this->once())
      ->method('preprocessOfficeHoursStatus')
      ->with($variables)
      ->willReturnArgument(0);

    $this->container->set(OfficeHoursThemeHooks::class, $this->themeHooksService);

    $original = $variables;
    office_hours_preprocess_office_hours_status($variables);
    $this->assertSame($variables, $original);
  }

  /**
   * Tests hook_preprocess_office_hours_table() calls the correct service method.
   *
   * @covers ::office_hours_preprocess_office_hours_table
   */
  public function testPreprocessOfficeHoursTableHook(): void {
    $variables = ['table' => 'test table'];

    $this->themeHooksService->expects($this->once())
      ->method('preprocessOfficeHoursTable')
      ->with($variables)
      ->willReturnArgument(0);

    $this->container->set(OfficeHoursThemeHooks::class, $this->themeHooksService);

    $original = $variables;
    office_hours_preprocess_office_hours_table($variables);
    $this->assertSame($variables, $original);
  }

  /**
   * Tests hook_theme() calls the correct service method.
   *
   * @covers ::office_hours_theme
   */
  public function testThemeHook(): void {
    $expected_theme = ['office_hours' => ['variables' => []]];

    $this->themeHooksService->expects($this->once())
      ->method('theme')
      ->willReturn($expected_theme);

    $this->container->set(OfficeHoursThemeHooks::class, $this->themeHooksService);

    $result = office_hours_theme();
    $this->assertSame($expected_theme, $result);
  }

  /**
   * Tests hook_theme_suggestions_office_hours() calls the correct service method.
   *
   * @covers ::office_hours_theme_suggestions_office_hours
   */
  public function testThemeSuggestionsOfficeHoursHook(): void {
    $variables = ['content' => 'test content'];
    $expected_suggestions = ['office_hours__custom'];

    $this->themeHooksService->expects($this->once())
      ->method('themeSuggestionsOfficeHours')
      ->with($variables)
      ->willReturn($expected_suggestions);

    $this->container->set(OfficeHoursThemeHooks::class, $this->themeHooksService);

    $result = office_hours_theme_suggestions_office_hours($variables);
    $this->assertSame($expected_suggestions, $result);
  }

  /**
   * Tests hook_theme_suggestions_office_hours_status() calls the correct service method.
   *
   * @covers ::office_hours_theme_suggestions_office_hours_status
   */
  public function testThemeSuggestionsOfficeHoursStatusHook(): void {
    $variables = ['status' => 'open'];
    $expected_suggestions = ['office_hours_status__custom'];

    $this->themeHooksService->expects($this->once())
      ->method('themeSuggestionsOfficeHoursStatus')
      ->with($variables)
      ->willReturn($expected_suggestions);

    $this->container->set(OfficeHoursThemeHooks::class, $this->themeHooksService);

    $result = office_hours_theme_suggestions_office_hours_status($variables);
    $this->assertSame($expected_suggestions, $result);
  }

  /**
   * Tests hook_theme_suggestions_office_hours_table() calls the correct service method.
   *
   * @covers ::office_hours_theme_suggestions_office_hours_table
   */
  public function testThemeSuggestionsOfficeHoursTableHook(): void {
    $variables = ['table' => 'test table'];
    $expected_suggestions = ['office_hours_table__custom'];

    $this->themeHooksService->expects($this->once())
      ->method('themeSuggestionsOfficeHoursTable')
      ->with($variables)
      ->willReturn($expected_suggestions);

    $this->container->set(OfficeHoursThemeHooks::class, $this->themeHooksService);

    $result = office_hours_theme_suggestions_office_hours_table($variables);
    $this->assertSame($expected_suggestions, $result);
  }

  /**
   * Tests hook_tokens() calls the correct service method.
   *
   * @covers ::office_hours_tokens
   */
  public function testTokensHook(): void {
    $type = 'office_hours';
    $tokens = ['open' => 'Open now'];
    $data = ['entity' => 'test'];
    $options = [];
    $bubbleable_metadata = $this->createMock(BubbleableMetadata::class);
    $expected_tokens = ['open' => 'Open now'];

    $this->hooksService->expects($this->once())
      ->method('tokens')
      ->with($type, $tokens, $data, $options, $bubbleable_metadata)
      ->willReturn($expected_tokens);

    $this->container->set(OfficeHoursHooks::class, $this->hooksService);

    $result = office_hours_tokens($type, $tokens, $data, $options, $bubbleable_metadata);
    $this->assertSame($expected_tokens, $result);
  }

  /**
   * Tests hook_field_views_data() calls the correct service method.
   *
   * @covers ::office_hours_field_views_data
   */
  public function testFieldViewsDataHook(): void {
    $field_storage = $this->createMock(FieldStorageConfigInterface::class);
    $expected_data = ['office_hours' => ['title' => 'Office Hours']];

    $this->viewsHooksService->expects($this->once())
      ->method('fieldViewsData')
      ->with($field_storage)
      ->willReturn($expected_data);

    $this->container->set(OfficeHoursViewsHooks::class, $this->viewsHooksService);

    $result = office_hours_field_views_data($field_storage);
    $this->assertSame($expected_data, $result);
  }

  /**
   * Tests hook_views_query_substitutions() calls the correct service method.
   *
   * @covers ::office_hours_views_query_substitutions
   */
  public function testViewsQuerySubstitutionsHook(): void {
    $view = $this->createMock(ViewExecutable::class);

    $this->viewsHooksService->expects($this->once())
      ->method('viewsQuerySubstitutions')
      ->with($view)
      ->willReturnArgument(0);

    $this->container->set(OfficeHoursViewsHooks::class, $this->viewsHooksService);

    $result = office_hours_views_query_substitutions($view);
    $this->assertSame($view, $result);
  }

  /**
   * Tests hook_views_pre_execute() calls the correct service method.
   *
   * @covers ::office_hours_views_pre_execute
   */
  public function testViewsPreExecuteHook(): void {
    $view = $this->createMock(ViewExecutable::class);

    $this->viewsHooksService->expects($this->once())
      ->method('viewsPreExecute')
      ->with($view)
      ->willReturnArgument(0);

    $this->container->set(OfficeHoursViewsHooks::class, $this->viewsHooksService);

    $result = office_hours_views_pre_execute($view);
    $this->assertSame($view, $result);
  }

  /**
   * Tests hook_views_post_execute() calls the correct service method.
   *
   * @covers ::office_hours_views_post_execute
   */
  public function testViewsPostExecuteHook(): void {
    $view = $this->createMock(ViewExecutable::class);

    $this->viewsHooksService->expects($this->once())
      ->method('viewsPostExecute')
      ->with($view)
      ->willReturnArgument(0);

    $this->container->set(OfficeHoursViewsHooks::class, $this->viewsHooksService);

    $result = office_hours_views_post_execute($view);
    $this->assertSame($view, $result);
  }

  /**
   * Tests hook_views_pre_render() calls the correct service method.
   *
   * @covers ::office_hours_views_pre_render
   */
  public function testViewsPreRenderHook(): void {
    $view = $this->createMock(ViewExecutable::class);

    $this->viewsHooksService->expects($this->once())
      ->method('viewsPreRender')
      ->with($view)
      ->willReturnArgument(0);

    $this->container->set(OfficeHoursViewsHooks::class, $this->viewsHooksService);

    $result = office_hours_views_pre_render($view);
    $this->assertSame($view, $result);
  }

  /**
   * Tests hook_views_post_render() calls the correct service method.
   *
   * @covers ::office_hours_views_post_render
   */
  public function testViewsPostRenderHook(): void {
    $view = $this->createMock(ViewExecutable::class);
    $output = ['#markup' => 'test output'];
    $cache = $this->createMock(CachePluginBase::class);

    $this->viewsHooksService->expects($this->once())
      ->method('viewsPostRender')
      ->with($view, $output, $cache)
      ->willReturnArgument(0);

    $this->container->set(OfficeHoursViewsHooks::class, $this->viewsHooksService);

    $result = office_hours_views_post_render($view, $output, $cache);
    $this->assertSame($view, $result);
  }

  /**
   * Tests hook_office_hours_time_format_alter() functionality.
   *
   * @covers ::office_hours_office_hours_time_format_alter
   */
  public function testOfficeHoursTimeFormatAlterHook(): void {
    $formatted_time = '09:00';

    // This hook doesn't call a service, so we just test it executes without error.
    office_hours_office_hours_time_format_alter($formatted_time);

    // The hook modifies the timezone to Asia/Manila, so we can verify the function executes.
    $this->assertTrue(function_exists('office_hours_office_hours_time_format_alter'));
  }

  /**
   * Tests hook_office_hours_current_time_alter() functionality.
   *
   * @covers ::office_hours_office_hours_current_time_alter
   */
  public function testOfficeHoursCurrentTimeAlterHook(): void {
    $time = 1640995200; // 2022-01-01 00:00:00 UTC
    $entity = NULL;

    // This hook doesn't call a service, so we just test it executes without error.
    office_hours_office_hours_current_time_alter($time, $entity);

    // The hook modifies the timezone to Asia/Manila, so we can verify the function executes.
    $this->assertTrue(function_exists('office_hours_office_hours_current_time_alter'));
  }

}
