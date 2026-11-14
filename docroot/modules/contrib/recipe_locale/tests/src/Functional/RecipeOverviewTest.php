<?php

declare(strict_types=1);

namespace Drupal\Tests\recipe_locale\Functional;

use Drupal\recipe_locale\RecipeTracker;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the recipe tab next to the translation status report.
 */
#[Group('recipe_locale')]
#[RunTestsInSeparateProcesses]
final class RecipeOverviewTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'language', 'locale', 'recipe_locale'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests listing and removing a recipe.
   */
  public function testOverviewAndRemove(): void {
    $this->config(RecipeTracker::CONFIG_NAME)
      ->set('recipes', [
        'my_recipe' => ['package' => 'drupal/my_recipe', 'version' => '1.x-dev', 'label' => 'My recipe'],
        'no_version' => ['package' => 'drupal/no_version', 'version' => '', 'label' => 'No version'],
      ])
      ->save();
    $this->config('recipe_locale.shipped.my_recipe')
      ->setData([
        'recipe' => 'My recipe',
        'config' => [['name' => 'system.menu.mine', 'data' => ['label' => 'Mine']]],
      ])
      ->save();

    $this->drupalGet('admin/reports/translations/recipes');
    $this->assertSession()->statusCodeEquals(403);

    // Setup the environment for the test.
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('system_messages_block');
    $this->drupalLogin($this->drupalCreateUser(['translate interface']));
    $assert = $this->assertSession();

    // The status report got a tab for the recipe list.
    $this->drupalGet('admin/reports/translations');
    $assert->linkExists('Recipe translation updates');
    $this->clickLink('Recipe translation updates');
    $assert->addressEquals('admin/reports/translations/recipes');
    $assert->pageTextContains('My recipe');
    $assert->pageTextContains('drupal/my_recipe');
    $assert->pageTextContains('1.x-dev');
    $assert->pageTextContains('No version');
    $assert->elementTextContains('css', 'table', 'unknown');

    // Remove one recipe.
    $this->drupalGet('admin/reports/translations/recipes/my_recipe/remove');
    $assert->pageTextContains('Remove My recipe from the list of recipes with translations?');
    $this->submitForm([], 'Remove');
    $assert->addressEquals('admin/reports/translations/recipes');
    $assert->pageTextContains('My recipe was removed from the list.');
    $assert->pageTextNotContains('drupal/my_recipe');
    $assert->pageTextContains('No version');
    $this->assertSame(['no_version'], array_keys($this->config(RecipeTracker::CONFIG_NAME)->get('recipes')));
    // The copy of the config the recipe shipped goes with it.
    $this->assertTrue($this->config('recipe_locale.shipped.my_recipe')->isNew());

    // Unknown recipes are a 404.
    $this->drupalGet('admin/reports/translations/recipes/my_recipe/remove');
    $assert->statusCodeEquals(404);
  }

}
