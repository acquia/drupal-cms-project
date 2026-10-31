<?php

declare(strict_types=1);

namespace Drupal\Tests\recipe_locale\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\recipe_locale\RecipeTracker;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that one copy is kept when several recipes ship the same config.
 */
#[Group('recipe_locale')]
#[RunTestsInSeparateProcesses]
final class SharedConfigTest extends RecipeLocaleKernelTestBase {

  private const string CONFIG_NAME = 'system.menu.shared-menu';

  /**
   * Creates a recipe that ships the shared menu with the given label.
   */
  private function createMenuRecipe(string $machine_name, string $label): Recipe {
    $recipe = $this->createRecipeWithComposer($machine_name, [
      'name' => ucfirst(str_replace('_', ' ', $machine_name)),
      // Existing config that differs from the file is skipped, not an error.
      'config' => ['strict' => FALSE],
    ], [
      'name' => 'drupal/' . $machine_name,
      'version' => '1.0.0',
    ]);
    mkdir($recipe->path . '/config');
    file_put_contents($recipe->path . '/config/' . self::CONFIG_NAME . '.yml', Yaml::encode([
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [],
      'id' => 'shared-menu',
      'label' => $label,
      'description' => '',
      'locked' => FALSE,
    ]));
    return Recipe::createFromDirectory($recipe->path);
  }

  /**
   * Returns the recipes that keep a copy of the shared menu.
   *
   * @return string[]
   *   Recipe keys.
   */
  private function keepers(): array {
    $keepers = [];
    foreach ($this->container->get('config.factory')->listAll(RecipeTracker::SHIPPED_PREFIX) as $config_name) {
      foreach ($this->config($config_name)->get('config') ?? [] as $item) {
        if ($item['name'] === self::CONFIG_NAME) {
          $keepers[] = substr($config_name, strlen(RecipeTracker::SHIPPED_PREFIX));
        }
      }
    }
    return $keepers;
  }

  /**
   * Tests that the recipe applied first keeps the copy.
   */
  public function testFirstRecipeKeepsTheCopy(): void {
    RecipeRunner::processRecipe($this->createMenuRecipe('first_recipe', 'Shared menu, first'));
    RecipeRunner::processRecipe($this->createMenuRecipe('second_recipe', 'Shared menu, second'));

    // Core skipped the second file, so the site has the first recipe's text.
    $this->assertSame('Shared menu, first', $this->config(self::CONFIG_NAME)->get('label'));
    $this->assertSame(['first_recipe'], $this->keepers());
    $copy = $this->container->get(RecipeTracker::class)->readShippedConfig(self::CONFIG_NAME);
    $this->assertSame('Shared menu, first', $copy['label']);
    // The second recipe shipped nothing else, so it has no copies at all.
    $this->assertTrue($this->config(RecipeTracker::SHIPPED_PREFIX . 'second_recipe')->isNew());
  }

  /**
   * Tests that a recipe recreating a deleted object takes the copy over.
   */
  public function testRecreatedObjectMovesTheCopy(): void {
    RecipeRunner::processRecipe($this->createMenuRecipe('first_recipe', 'Shared menu, first'));
    Menu::load('shared-menu')->delete();
    RecipeRunner::processRecipe($this->createMenuRecipe('second_recipe', 'Shared menu, second'));

    // The copy went with the deleted object, so the second recipe's file
    // created the object and keeps the new copy.
    $this->assertSame('Shared menu, second', $this->config(self::CONFIG_NAME)->get('label'));
    $this->assertSame(['second_recipe'], $this->keepers());
    $copy = $this->container->get(RecipeTracker::class)->readShippedConfig(self::CONFIG_NAME);
    $this->assertSame('Shared menu, second', $copy['label']);
    $this->assertTrue($this->config(RecipeTracker::SHIPPED_PREFIX . 'first_recipe')->isNew());
  }

  /**
   * Tests that deleting the object removes its copy.
   */
  public function testDeletedObjectLosesItsCopy(): void {
    RecipeRunner::processRecipe($this->createMenuRecipe('first_recipe', 'Shared menu'));
    $this->assertSame(['first_recipe'], $this->keepers());

    Menu::load('shared-menu')->delete();

    $this->assertSame([], $this->keepers());
    $this->assertNotContains(self::CONFIG_NAME, $this->container->get(RecipeTracker::class)->getShippedConfigNames());
  }

  /**
   * Tests that renaming the object removes its copy.
   */
  public function testRenamedObjectLosesItsCopy(): void {
    RecipeRunner::processRecipe($this->createMenuRecipe('first_recipe', 'Shared menu'));

    Menu::load('shared-menu')->set('id', 'renamed-menu')->save();

    $this->assertSame([], $this->keepers());
    $this->assertSame([], $this->container->get(RecipeTracker::class)->getShippedConfigNames());
  }

  /**
   * Tests that identical files leave the copy with the first recipe.
   */
  public function testIdenticalFilesLeaveTheCopyWithTheFirstRecipe(): void {
    RecipeRunner::processRecipe($this->createMenuRecipe('first_recipe', 'Shared menu'));
    RecipeRunner::processRecipe($this->createMenuRecipe('third_recipe', 'Shared menu'));

    $this->assertSame(['first_recipe'], $this->keepers());
    $this->assertTrue($this->config(RecipeTracker::SHIPPED_PREFIX . 'third_recipe')->isNew());
  }

}
