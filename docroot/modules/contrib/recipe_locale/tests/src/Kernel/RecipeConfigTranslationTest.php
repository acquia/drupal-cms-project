<?php

declare(strict_types=1);

namespace Drupal\Tests\recipe_locale\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\locale\LocaleConfigManager;
use Drupal\recipe_locale\LocaleIntegration;
use Drupal\recipe_locale\RecipeTracker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that Locale translates the configuration a recipe shipped.
 */
#[Group('recipe_locale')]
#[RunTestsInSeparateProcesses]
final class RecipeConfigTranslationTest extends RecipeLocaleKernelTestBase {

  /**
   * Tests that recipe config gets translated like module config.
   */
  public function testRecipeConfigIsTranslated(): void {
    $recipe = $this->createRecipeWithComposer('menu_recipe', ['name' => 'Menu recipe'], [
      'name' => 'drupal/menu_recipe',
      'version' => '1.0.0',
    ]);
    mkdir($recipe->path . '/config');
    file_put_contents($recipe->path . '/config/system.menu.recipe-menu.yml', Yaml::encode([
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [],
      'id' => 'recipe-menu',
      'label' => 'Recipe menu',
      'description' => '',
      'locked' => FALSE,
    ]));
    // A recipe looks for its config directory when it is loaded, so load it
    // again now that the directory exists.
    $recipe = Recipe::createFromDirectory($recipe->path);
    RecipeRunner::processRecipe($recipe);

    // The recipe installer marks the config as shipped, and the module kept
    // the shipped copy of its translatable values.
    $this->assertNotEmpty($this->config('system.menu.recipe-menu')->get('_core.default_config_hash'));
    $this->assertSame([
      'recipe' => 'Menu recipe',
      'config' => [
        [
          'name' => 'system.menu.recipe-menu',
          'data' => ['label' => 'Recipe menu'],
        ],
      ],
    ], array_diff_key($this->config('recipe_locale.shipped.menu_recipe')->get(), ['_core' => 1]));

    // The recipe directory is gone, as it is on a site deployed from git after
    // Composer unpacked the recipe. Locale still knows the shipped copy.
    $this->container->get('file_system')->deleteRecursive($recipe->path);
    $manager = $this->container->get(LocaleConfigManager::class);
    $this->assertTrue($manager->isSupported('system.menu.recipe-menu'));
    $this->assertContains('system.menu.recipe-menu', $manager->getComponentNames());

    // Pretend the recipe's German file was imported.
    $strings = $this->container->get('locale.storage');
    $source = $strings->createString(['source' => 'Recipe menu'])->save();
    $strings->createTranslation(['lid' => $source->lid, 'language' => 'de', 'translation' => 'Rezeptmenü'])->save();

    $changed = $this->container->get(LocaleIntegration::class)->translateConfig(['menu_recipe'], ['de']);
    $this->assertSame(1, $changed);
    $override = $this->container->get('language_manager')->getLanguageConfigOverride('de', 'system.menu.recipe-menu');
    $this->assertSame('Rezeptmenü', $override->get('label'));

    // Once the recipe is removed from the list, its config is no longer
    // Locale's business.
    $this->container->get(LocaleIntegration::class)->remove('menu_recipe');
    $this->assertTrue($this->config('recipe_locale.shipped.menu_recipe')->isNew());
    $this->assertSame([], $this->container->get(RecipeTracker::class)->getShippedConfigNames());
    $this->assertFalse($manager->isSupported('system.menu.recipe-menu'));
  }

}
