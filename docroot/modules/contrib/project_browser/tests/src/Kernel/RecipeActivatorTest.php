<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Kernel;

use Drupal\Core\Config\Checkpoint\CheckpointListInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipePreExistingConfigException;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\KernelTests\KernelTestBase;
use Drupal\project_browser\Activator\ActivationStatus;
use Drupal\project_browser\Activator\RecipeActivator;
use Drupal\project_browser\QueryManager;
use Drupal\project_browser\ProjectBrowser\Project;
use Drupal\project_browser\ProjectRepository;
use Drupal\project_browser\ProjectType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the recipe activator. Obviously.
 *
 * @group project_browser
 */
#[CoversClass(RecipeActivator::class)]
#[Group('project_browser')]
#[RunTestsInSeparateProcesses]
final class RecipeActivatorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['project_browser', 'system', 'user'];

  /**
   * The activator under test.
   *
   * @var \Drupal\project_browser\Activator\RecipeActivator
   */
  private RecipeActivator $activator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->activator = \Drupal::service(RecipeActivator::class);
    $this->setSetting('extension_discovery_scan_tests', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->getDefinition(RecipeActivator::class)->setPublic(TRUE);
  }

  /**
   * Tests that Project Browser stores fully resolved paths of applied recipes.
   */
  public function testAbsoluteRecipePathIsStoredOnApply(): void {
    $base_dir = $this->root . '/core/tests/fixtures/recipes';
    if (!is_dir($base_dir)) {
      $this->markTestSkipped('This test requires a version of Drupal that supports recipes.');
    }
    $recipe = Recipe::createFromDirectory($base_dir . '/invalid_config/../no_extensions');
    RecipeRunner::processRecipe($recipe);

    $applied_recipes = \Drupal::service(StateInterface::class)
      ->get('project_browser.applied_recipes', []);
    $this->assertContains($base_dir . '/no_extensions', $applied_recipes);
  }

  /**
   * Tests recipe activation with a project which is not installed physically.
   */
  public function testGetStatus(): void {
    $project = new Project(
      logo: NULL,
      isCompatible: TRUE,
      machineName: 'My Project',
      body: [],
      title: '',
      packageName: 'fake/project',
      type: ProjectType::Recipe,
    );
    // As this project is not installed, RecipeActivator::getPath() will return
    // NULL, and therefore getStatus() will report the status as absent.
    $this->assertSame(ActivationStatus::Absent, $this->activator->getStatus($project));
  }

  /**
   * Tests that recipes' follow-up tasks are exposed by the activator.
   */
  public function testFollowUpTasks(): void {
    // Enable the recipes source and prime the project cache.
    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', [
        'recipes' => [
          'additional_directories' => [
            __DIR__ . '/../../fixtures',
          ],
        ],
      ])
      ->save();
    \Drupal::service(QueryManager::class)->getProjects('recipes');
    $project = \Drupal::service(ProjectRepository::class)
      ->get('recipes/project-browser-test-recipe-with-tasks-recipe_with_tasks');
    // Tasks are not exposed unless the recipe has been applied.
    $this->assertEmpty($this->activator->getTasks($project));
    // Apply the recipe and ensure that the follow-up tasks are available.
    $this->activator->activate($project);
    $tasks = $this->activator->getTasks($project, 'recipes');
    $this->assertCount(3, $tasks);
    // Tasks can be unrouted URIs, or route names and parameters. Either way
    // should allow URL options.
    $this->assertSame('Visit Drupal.org', $tasks[0]->getText());
    $this->assertSame('https://drupal.org#hello', $tasks[0]->getUrl()->toString());
    $this->assertSame('Administer site compactly', $tasks[1]->getText());
    $this->assertStringEndsWith('/admin/compact/on?hi=there', $tasks[1]->getUrl()->toString());
    // The reapply task should always be last. We have to assert its type
    // explicitly to shut PHPStan up.
    $reapply_text = $tasks[2]->getText();
    assert($reapply_text instanceof TranslatableMarkup);
    $this->assertSame('Reapply', (string) $reapply_text);
  }

  /**
   * Tests that an un-instantiable applied recipe does not break getTasks().
   *
   * A strict recipe can no longer be instantiated once a config it ships has
   * diverged from the active configuration: Recipe::createFromDirectory()
   * throws RecipePreExistingConfigException. Because Project Browser reads
   * every applied recipe's follow-up tasks while normalizing the listing, an
   * uncaught throw there would blank the whole catalog. getTasks() must degrade
   * gracefully instead.
   */
  public function testFollowUpTasksForUninstantiableRecipe(): void {
    // Give the site an active system.site that the fixture recipe's strict,
    // shipped system.site can never match.
    $this->installConfig('system');

    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', [
        'recipes' => [
          'additional_directories' => [
            __DIR__ . '/../../fixtures',
          ],
        ],
      ])
      ->save();
    $this->container->get(QueryManager::class)->getProjects('recipes');
    $project = $this->container->get(ProjectRepository::class)
      ->get('recipes/project-browser-test-recipe-conflicting-config-recipe_conflicting_config');

    // Mark the recipe as applied so getTasks() tries to load its tasks.
    $path = $this->container->get('extension.list.module')->getPath('project_browser') . '/tests/fixtures/recipe_conflicting_config';
    $path = realpath($path);
    assert(is_string($path));
    $this->container->get(StateInterface::class)
      ->set('project_browser.applied_recipes', [$path]);

    // Precondition: the recipe genuinely cannot be instantiated.
    try {
      Recipe::createFromDirectory($path);
      $this->fail('Expected the recipe to be un-instantiable.');
    }
    catch (RecipePreExistingConfigException) {
      // Expected — now assert getTasks() survives it.
    }

    // getTasks() must not propagate the throw. It exposes only the generic
    // Reapply task, since the recipe-defined tasks can't be read.
    $tasks = $this->activator->getTasks($project, 'recipes');
    $this->assertCount(1, $tasks);
    $reapply_text = $tasks[0]->getText();
    assert($reapply_text instanceof TranslatableMarkup);
    $this->assertSame('Reapply', (string) $reapply_text);
  }

  /**
   * Tests that a config checkpoint is created before applying a recipe.
   */
  public function testCheckpointCreatedBeforeApply(): void {
    // Enable the recipes source and prime the project cache.
    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', [
        'recipes' => [
          'additional_directories' => [
            __DIR__ . '/../../fixtures',
          ],
        ],
      ])
      ->save();
    \Drupal::service(QueryManager::class)->getProjects('recipes');
    $project = \Drupal::service(ProjectRepository::class)
      ->get('recipes/project-browser-test-recipe-with-tasks-recipe_with_tasks');

    /** @var \Drupal\Core\Config\Checkpoint\CheckpointListInterface $checkpoint_list */
    $checkpoint_list = \Drupal::service(CheckpointListInterface::class);
    // There is no checkpoint yet.
    $inactive_checkpoint = $checkpoint_list->getActiveCheckpoint();
    $this->assertNull($inactive_checkpoint);
    $this->activator->activate($project);
    $this->assertSame('Project Browser checkpoint for Recipe with tasks', $checkpoint_list->getActiveCheckpoint()?->label);
  }

}
