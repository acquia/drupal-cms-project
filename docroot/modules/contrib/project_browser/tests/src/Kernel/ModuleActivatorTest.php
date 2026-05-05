<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Link;
use Drupal\KernelTests\KernelTestBase;
use Drupal\project_browser\Activator\ActivationStatus;
use Drupal\project_browser\Activator\ModuleActivator;
use Drupal\project_browser\QueryManager;
use Drupal\project_browser\ProjectRepository;
use Drupal\user\PermissionHandlerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests the module activator.
 *
 * @group project_browser
 */
#[CoversClass(ModuleActivator::class)]
#[Group('project_browser')]
final class ModuleActivatorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'breakpoint',
    'help',
    'project_browser',
    'user',
  ];

  /**
   * The activator under test.
   *
   * @var \Drupal\project_browser\Activator\ModuleActivator
   */
  private ModuleActivator $activator;

  /**
   * The mocked module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  private ModuleHandlerInterface&MockObject $mockModuleHandler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->mockModuleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->mockModuleHandler->expects($this->atLeastOnce())
      ->method('moduleExists')
      ->willReturnMap(array_map(
        fn (string $module_name): array => [$module_name, TRUE],
        static::$modules,
      ));

    parent::setUp();

    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', [
        'drupal_core' => [],
      ])
      ->save();
    // Prime the project cache.
    $this->container->get(QueryManager::class)
      ->getProjects('drupal_core');

    $this->activator = $this->container->get(ModuleActivator::class);
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->getDefinition(ModuleActivator::class)
      ->setPublic(TRUE)
      ->setArgument('$moduleHandler', $this->mockModuleHandler);
  }

  /**
   * Tests that the module activator returns a "Configure" task if available.
   */
  public function testConfigureLinksAreExposedIfDefined(): void {
    /** @var \Drupal\project_browser\ProjectRepository $repository */
    $repository = $this->container->get(ProjectRepository::class);

    // The Breakpoint module has no configuration options, so it should not have
    // any tasks.
    $project = $repository->get('drupal_core/breakpoint');
    $this->assertSame(ActivationStatus::Active, $this->activator->getStatus($project));
    $this->assertNotContains('Configure', static::getTaskTitles($this->activator->getTasks($project)));

    // Block has a configure link, so that should be exposed as a task.
    $project = $repository->get('drupal_core/block');
    $this->assertSame(ActivationStatus::Active, $this->activator->getStatus($project));
    $tasks = $this->activator->getTasks($project);
    $this->assertNotEmpty($tasks);
    $link_text = $tasks[0]->getText();
    assert(is_string($link_text) || $link_text instanceof \Stringable);
    $this->assertSame('Configure', (string) $link_text);
    $this->assertStringStartsWith('block.', $tasks[0]->getUrl()->getRouteName());

    // We should not get any tasks for a module which isn't installed.
    $project = $repository->get('drupal_core/content_moderation');
    $this->assertSame(ActivationStatus::Present, $this->activator->getStatus($project));
    $this->assertEmpty($this->activator->getTasks($project));
  }

  /**
   * Tests that a `Permissions` task is exposed for modules that provide them.
   *
   * @testWith ["breakpoint", false]
   *   ["user", true]
   */
  public function testPermissionsTask(string $module_name, bool $task_expected): void {
    $project = $this->container->get(ProjectRepository::class)
      ->get('drupal_core/' . $module_name);
    $this->assertSame(ActivationStatus::Active, $this->activator->getStatus($project));
    $tasks = static::getTaskTitles($this->activator->getTasks($project));

    $has_permissions = $this->container->get(PermissionHandlerInterface::class)
      ->moduleProvidesPermissions($module_name);
    // Sanity check: ensure that the module actually does, or does not, provide
    // permissions as expected.
    $this->assertSame($task_expected, $has_permissions);

    if ($task_expected) {
      $this->assertContains('Permissions', $tasks);
    }
    else {
      $this->assertNotContains('Permissions', $tasks);
    }
  }

  /**
   * Tests that the module activator returns help links if Help is enabled.
   *
   * @testWith [true]
   *   [false]
   */
  public function testHelpLinksAreExposed(bool $implements_hook_help): void {
    $this->mockModuleHandler->expects($this->atLeastOnce())
      ->method('hasImplementations')
      ->with('help', 'breakpoint')
      ->willReturn($implements_hook_help);

    $project = $this->container->get(ProjectRepository::class)
      ->get('drupal_core/breakpoint');
    $this->assertSame(ActivationStatus::Active, $this->activator->getStatus($project));
    $tasks = $this->activator->getTasks($project);

    if ($implements_hook_help) {
      $this->assertNotEmpty($tasks);
      $link_text = $tasks[0]->getText();
      assert(is_string($link_text) || $link_text instanceof \Stringable);
      $this->assertSame('Help', (string) $link_text);
      $url = $tasks[0]->getUrl();
      $this->assertSame('help.page', $url->getRouteName());
      $this->assertSame('breakpoint', $url->getRouteParameters()['name']);
    }
    else {
      $this->assertNotContains('Help', static::getTaskTitles($tasks));
    }
  }

  /**
   * Returns the titles for a set of activation tasks.
   *
   * @param \Drupal\Core\Link[] $tasks
   *   The activation tasks.
   *
   * @return string[]
   *   The titles of those tasks.
   */
  private static function getTaskTitles(array $tasks): array {
    $map = function (Link $link): string {
      $text = $link->getText();
      assert(is_string($text) || $text instanceof \Stringable);
      return (string) $text;
    };
    return array_map($map, $tasks);
  }

}
