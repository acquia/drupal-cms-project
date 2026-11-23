<?php

declare(strict_types=1);

namespace Drupal\Tests\recipe_locale\Kernel;

use Composer\InstalledVersions;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\locale\LocaleProjectRepository;
use Drupal\recipe_locale\LocaleIntegration;
use Drupal\recipe_locale\RecipeTracker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests recording recipes and handing them to Locale.
 */
#[Group('recipe_locale')]
#[RunTestsInSeparateProcesses]
final class RecipeTrackerTest extends RecipeLocaleKernelTestBase {

  /**
   * Tests that applying a recipe records it and everything it applied.
   */
  public function testAppliedRecipesAreRecorded(): void {
    $this->createRecipeWithComposer('base_recipe', ['name' => 'Base recipe'], [
      'name' => 'drupal/base_recipe',
      'version' => '1.0.3',
    ]);
    // No composer.json: this is what core's own recipes look like.
    $this->createRecipeWithComposer('plain_recipe', [], NULL);
    // Not a drupal.org project, so there are no translations for it.
    $this->createRecipeWithComposer('foreign_recipe', [], [
      'name' => 'acme/foreign_recipe',
      'version' => '3.0.0',
    ]);
    $top = $this->createRecipeWithComposer('top_recipe', [
      'name' => 'Top recipe',
      'recipes' => ['base_recipe', 'plain_recipe', 'foreign_recipe'],
    ], [
      'name' => 'drupal/top_recipe',
      'version' => '2.x-dev',
    ]);

    RecipeRunner::processRecipe($top);

    $recorded = $this->config(RecipeTracker::CONFIG_NAME)->get('recipes');
    $this->assertSame([
      'base_recipe' => [
        'package' => 'drupal/base_recipe',
        'version' => '1.0.3',
        'label' => 'Base recipe',
      ],
      'top_recipe' => [
        'package' => 'drupal/top_recipe',
        'version' => '2.x-dev',
        'label' => 'Top recipe',
      ],
    ], $recorded);

    // Locale's project list now has the recipes, with the "-dev" suffix
    // stripped, because that is how the translation server names the files.
    $projects = $this->container->get(LocaleProjectRepository::class)->buildProjects();
    $this->assertSame('recipe', $projects['base_recipe']->type);
    $this->assertSame('1.0.3', $projects['base_recipe']->version);
    $this->assertSame('Base recipe', $projects['base_recipe']->info['name']);
    $this->assertSame('2.x', $projects['top_recipe']->version);
    $this->assertArrayNotHasKey('plain_recipe', $projects);
    $this->assertArrayNotHasKey('foreign_recipe', $projects);

    // Applying the recipe again with a new version updates the record.
    file_put_contents($top->path . '/composer.json', '{"name": "drupal/top_recipe", "version": "2.1.0"}');
    RecipeRunner::processRecipe($top);
    $this->assertSame('2.1.0', $this->config(RecipeTracker::CONFIG_NAME)->get('recipes.top_recipe.version'));
  }

  /**
   * Tests where the version comes from.
   */
  public function testVersionSources(): void {
    $tracker = $this->container->get(RecipeTracker::class);

    // Composer's data wins over the composer.json of the recipe. Core is a
    // package Composer always knows about.
    $this->assertSame(InstalledVersions::getPrettyVersion('drupal/core'), $tracker->findVersion('drupal/core', ['version' => '0.0.0']));

    // A package Composer does not know: the version key in composer.json.
    $this->assertSame('4.5.6', $tracker->findVersion('drupal/not_installed', ['version' => '4.5.6']));

    // No version anywhere: the recipe is recorded but gets no translations.
    $this->assertSame('', $tracker->findVersion('drupal/not_installed', []));
    $this->config(RecipeTracker::CONFIG_NAME)
      ->set('recipes', [
        'not_installed' => ['package' => 'drupal/not_installed', 'version' => '', 'label' => 'No version'],
      ])
      ->save();
    $this->assertSame([], $tracker->getProjects());
  }

  /**
   * Tests that the recipes survive Locale rebuilding its project list.
   *
   * Core's "Check manually" link empties the project list and rebuilds it.
   */
  public function testProjectsSurviveRebuild(): void {
    $this->config(RecipeTracker::CONFIG_NAME)
      ->set('recipes', [
        'my_recipe' => ['package' => 'drupal/my_recipe', 'version' => '1.x-dev', 'label' => 'My recipe'],
      ])
      ->save();
    $repository = $this->container->get(LocaleProjectRepository::class);
    $repository->deleteAll();
    $projects = $repository->getAll();
    $this->assertSame('recipe', $projects['my_recipe']->type);
    $this->assertSame('1.x', $projects['my_recipe']->version);
    $this->assertTrue($projects['my_recipe']->getStatus());
  }

  /**
   * Tests removing a recipe from the list.
   */
  public function testForget(): void {
    $this->config(RecipeTracker::CONFIG_NAME)
      ->set('recipes', [
        'my_recipe' => ['package' => 'drupal/my_recipe', 'version' => '1.0.0', 'label' => 'My recipe'],
      ])
      ->save();
    $repository = $this->container->get(LocaleProjectRepository::class);
    $this->assertArrayHasKey('my_recipe', $repository->buildProjects());
    $file = $this->translationsDirectory . '/my_recipe-1.0.0.de.po';
    file_put_contents($file, "msgid \"\"\nmsgstr \"\"\n");

    $this->container->get(LocaleIntegration::class)->remove('my_recipe');

    $this->assertSame([], $this->config(RecipeTracker::CONFIG_NAME)->get('recipes'));
    $this->assertArrayNotHasKey('my_recipe', $repository->getAll());
    $this->assertArrayNotHasKey('my_recipe', $repository->buildProjects());
    $this->assertFileDoesNotExist($file);
  }

  /**
   * Tests that uninstalling the module cleans up Locale's data.
   */
  public function testUninstall(): void {
    $this->config(RecipeTracker::CONFIG_NAME)
      ->set('recipes', [
        'my_recipe' => ['package' => 'drupal/my_recipe', 'version' => '1.0.0', 'label' => 'My recipe'],
      ])
      ->save();
    $this->assertArrayHasKey('my_recipe', $this->container->get(LocaleProjectRepository::class)->buildProjects());

    $this->container->get('module_installer')->uninstall(['recipe_locale']);

    $projects = \Drupal::keyValue('locale.project')->getAll();
    $this->assertArrayNotHasKey('my_recipe', $projects);
  }

}
