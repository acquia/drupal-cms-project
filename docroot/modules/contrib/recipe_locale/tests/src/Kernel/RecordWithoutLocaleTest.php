<?php

declare(strict_types=1);

namespace Drupal\Tests\recipe_locale\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\locale\LocaleProjectRepository;
use Drupal\recipe_locale\LocaleIntegration;
use Drupal\recipe_locale\RecipeTracker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests recording recipes on a site without the Interface Translation module.
 */
#[Group('recipe_locale')]
#[RunTestsInSeparateProcesses]
final class RecordWithoutLocaleTest extends KernelTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'recipe_locale'];

  /**
   * Tests that recipes are recorded first and handed to Locale later.
   */
  public function testRecordThenInstallLocale(): void {
    $this->installConfig(['recipe_locale']);
    $this->assertFalse($this->container->has(LocaleIntegration::class));
    // No Locale, no translation status report, so no tabs on it either.
    $this->assertSame([], $this->recipeLocaleTabs());

    $recipe = $this->createRecipe(['name' => 'Early recipe', 'type' => 'Test'], 'early_recipe');
    file_put_contents($recipe->path . '/composer.json', Json::encode([
      'name' => 'drupal/early_recipe',
      'type' => 'drupal-recipe',
      'version' => '1.2.0',
    ]));
    mkdir($recipe->path . '/config');
    file_put_contents($recipe->path . '/config/system.menu.early-menu.yml', Yaml::encode([
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [],
      'id' => 'early-menu',
      'label' => 'Early menu',
      'description' => '',
      'locked' => FALSE,
    ]));
    $recipe = Recipe::createFromDirectory($recipe->path);
    RecipeRunner::processRecipe($recipe);

    // The shipped copy is kept without Locale, so Locale can use it later.
    $this->assertSame(['system.menu.early-menu'], $this->container->get(RecipeTracker::class)->getShippedConfigNames());
    // The copy Locale gets carries the shipped values. What else is in it is
    // covered by ShippedConfigCopyTest.
    $shipped = $this->container->get(RecipeTracker::class)->readShippedConfig('system.menu.early-menu');
    $this->assertSame('Early menu', $shipped['label']);
    $this->assertSame('en', $shipped['langcode']);

    $this->assertSame([
      'early_recipe' => [
        'package' => 'drupal/early_recipe',
        'version' => '1.2.0',
        'label' => 'Early recipe',
      ],
    ], $this->config(RecipeTracker::CONFIG_NAME)->get('recipes'));

    // Now Locale arrives. The recipe is on its project list right away.
    $this->enableModules(['language', 'locale']);
    $this->installSchema('locale', ['locales_source', 'locales_target', 'locales_location', 'locale_file']);
    $this->installConfig(['language', 'locale']);
    $this->assertTrue($this->container->has(LocaleIntegration::class));
    $this->assertSame(['recipe_locale.tabs:recipes', 'recipe_locale.tabs:translate_status'], $this->recipeLocaleTabs());
    $projects = $this->container->get(LocaleProjectRepository::class)->buildProjects();
    $this->assertSame('recipe', $projects['early_recipe']->type);
    $this->assertSame('1.2.0', $projects['early_recipe']->version);
    $this->assertTrue($this->container->get('locale.config_manager')->isSupported('system.menu.early-menu'));
  }

  /**
   * Returns the IDs of the tabs this module defines right now.
   *
   * @return string[]
   *   Sorted local task plugin IDs.
   */
  private function recipeLocaleTabs(): array {
    $manager = $this->container->get('plugin.manager.menu.local_task');
    $manager->clearCachedDefinitions();
    $ids = array_filter(array_keys($manager->getDefinitions()), fn (string $id): bool => str_starts_with($id, 'recipe_locale.'));
    sort($ids);
    return $ids;
  }

}
