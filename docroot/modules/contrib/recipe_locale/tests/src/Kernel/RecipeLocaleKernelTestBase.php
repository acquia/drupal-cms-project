<?php

declare(strict_types=1);

namespace Drupal\Tests\recipe_locale\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Recipe\Recipe;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Sets up Locale with a real translations directory and a German language.
 */
abstract class RecipeLocaleKernelTestBase extends KernelTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'language', 'locale', 'recipe_locale'];

  /**
   * A real directory for translation files.
   *
   * Locale's translations stream wrapper needs a real path. The kernel test
   * site directory is a virtual file system, so it cannot be used.
   */
  protected string $translationsDirectory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('locale', ['locales_source', 'locales_target', 'locales_location', 'locale_file']);
    $this->installConfig(['language', 'locale', 'recipe_locale']);

    $this->translationsDirectory = sys_get_temp_dir() . '/recipe_locale_' . $this->databasePrefix;
    mkdir($this->translationsDirectory, 0777, TRUE);
    $this->setSetting('locale_translation_path', $this->translationsDirectory);

    ConfigurableLanguage::createFromLangcode('de')->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if (isset($this->translationsDirectory) && is_dir($this->translationsDirectory)) {
      foreach (glob($this->translationsDirectory . '/*') ?: [] as $file) {
        unlink($file);
      }
      rmdir($this->translationsDirectory);
    }
    parent::tearDown();
  }

  /**
   * Creates a recipe with a composer.json next to its recipe.yml.
   *
   * @param string $machine_name
   *   The directory name. Recipes find each other by directory name.
   * @param array<string, mixed> $recipe
   *   The recipe.yml contents.
   * @param array<string, mixed>|null $composer
   *   The composer.json contents, or NULL for no composer.json at all.
   *
   * @return \Drupal\Core\Recipe\Recipe
   *   The recipe.
   */
  protected function createRecipeWithComposer(string $machine_name, array $recipe, ?array $composer): Recipe {
    $recipe += ['name' => $machine_name, 'type' => 'Test'];
    $created = $this->createRecipe($recipe, $machine_name);
    if ($composer !== NULL) {
      file_put_contents($created->path . '/composer.json', Json::encode($composer + ['type' => 'drupal-recipe']));
    }
    return $created;
  }

}
