<?php

declare(strict_types=1);

namespace Drupal\Tests\recipe_locale\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\locale\LocaleConfigManager;
use Drupal\recipe_locale\RecipeTracker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests which config needs this module and which does not.
 *
 * Config that a recipe imports from a module is installed from the module's
 * own files. Locale finds the original there and needs no help. Config a
 * recipe ships itself has no such original, and only the stored copy makes it
 * translatable.
 */
#[Group('recipe_locale')]
#[RunTestsInSeparateProcesses]
final class ImportedModuleConfigTest extends RecipeLocaleKernelTestBase {

  /**
   * Tests module config against recipe-shipped config.
   */
  public function testOnlyRecipeShippedConfigNeedsTheStoredCopy(): void {
    $recipe = $this->createRecipeWithComposer('import_recipe', [
      'name' => 'Import recipe',
      // The main menu is shipped by the System module.
      'config' => ['import' => ['system' => ['system.menu.main']]],
    ], [
      'name' => 'drupal/import_recipe',
      'version' => '1.0.0',
    ]);
    mkdir($recipe->path . '/config');
    file_put_contents($recipe->path . '/config/system.menu.own.yml', Yaml::encode([
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [],
      'id' => 'own',
      'label' => 'Own menu',
      'description' => '',
      'locked' => FALSE,
    ]));
    RecipeRunner::processRecipe(Recipe::createFromDirectory($recipe->path));

    // Both were installed by the recipe and both are marked as shipped.
    $this->assertNotEmpty($this->config('system.menu.main')->get('_core.default_config_hash'));
    $this->assertNotEmpty($this->config('system.menu.own')->get('_core.default_config_hash'));

    // Only the recipe's own config was stored; the module's was not needed.
    $tracker = $this->container->get(RecipeTracker::class);
    $this->assertSame(['system.menu.own'], $tracker->getShippedConfigNames());

    // Locale can translate both right now.
    $manager = $this->container->get(LocaleConfigManager::class);
    $this->assertTrue($manager->isSupported('system.menu.main'));
    $this->assertTrue($manager->isSupported('system.menu.own'));

    // Without the stored copy, the module's config is still fine, and the
    // recipe's own config is not.
    $this->config('recipe_locale.shipped.import_recipe')->delete();
    $this->assertTrue($manager->isSupported('system.menu.main'));
    $this->assertFalse($manager->isSupported('system.menu.own'));
  }

}
