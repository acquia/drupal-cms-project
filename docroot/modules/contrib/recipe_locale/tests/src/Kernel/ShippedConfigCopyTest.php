<?php

declare(strict_types=1);

namespace Drupal\Tests\recipe_locale\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\recipe_locale\LocaleIntegration;
use Drupal\recipe_locale\RecipeTracker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the copy of shipped config that Locale gets to compare with.
 *
 * The copy is put together from the site's current config and the stored
 * shipped values. These tests check what may and may not end up in it.
 *
 * @see \Drupal\recipe_locale\RecipeTracker::readShippedConfig()
 */
#[Group('recipe_locale')]
#[RunTestsInSeparateProcesses]
final class ShippedConfigCopyTest extends RecipeLocaleKernelTestBase {

  /**
   * The config name the test recipe ships.
   */
  private const string CONFIG_NAME = 'system.menu.copy-menu';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $recipe = $this->createRecipeWithComposer('copy_recipe', ['name' => 'Copy recipe'], [
      'name' => 'drupal/copy_recipe',
      'version' => '1.0.0',
    ]);
    mkdir($recipe->path . '/config');
    file_put_contents($recipe->path . '/config/' . self::CONFIG_NAME . '.yml', Yaml::encode([
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [],
      'id' => 'copy-menu',
      'label' => 'Copy menu',
      // Shipped empty on purpose: the site may fill this in later.
      'description' => '',
      'locked' => FALSE,
    ]));
    RecipeRunner::processRecipe(Recipe::createFromDirectory($recipe->path));
  }

  /**
   * Tests that a value the site changed does not replace the shipped value.
   */
  public function testShippedValuesWinOverSiteChanges(): void {
    $this->config(self::CONFIG_NAME)->set('label', 'Renamed on the site')->save();

    $copy = $this->container->get(RecipeTracker::class)->readShippedConfig(self::CONFIG_NAME);
    $this->assertSame('Copy menu', $copy['label']);
    $this->assertSame('en', $copy['langcode']);
    // Everything else comes from the site's config, so schema classes that
    // look at neighboring keys find what they expect.
    $this->assertSame('copy-menu', $copy['id']);
    $this->assertSame(array_keys($this->config(self::CONFIG_NAME)->get()), array_keys($copy));
  }

  /**
   * Tests that site text in a slot the recipe shipped empty is not leaked.
   *
   * Locale turns the translatable values of the copy into source strings. A
   * slot the recipe shipped empty stays empty in the copy, whatever the site
   * put there, so the site's text never becomes a source string.
   */
  public function testSiteTextInEmptySlotIsNoSourceString(): void {
    $this->config(self::CONFIG_NAME)->set('description', 'Added on the site')->save();

    $copy = $this->container->get(RecipeTracker::class)->readShippedConfig(self::CONFIG_NAME);
    $this->assertSame('', $copy['description']);

    $this->container->get(LocaleIntegration::class)->translateConfig(['copy_recipe'], ['de']);
    $strings = $this->container->get('locale.storage');
    $this->assertNull($strings->findString(['source' => 'Added on the site']));
    $this->assertNotNull($strings->findString(['source' => 'Copy menu']));
  }

  /**
   * Tests that stored values without a place in the site's config are dropped.
   *
   * Only the paths of the site's current config are visited. A stored value
   * at a path the site no longer has is left out.
   */
  public function testStoredValuesWithoutLiveStructureAreDropped(): void {
    $this->config('recipe_locale.shipped.copy_recipe')
      ->set('config', [
        [
          'name' => self::CONFIG_NAME,
          'data' => [
            'label' => 'Copy menu',
            'gone' => ['deeper' => 'Removed from the site since'],
          ],
        ],
      ])
      ->save();

    $copy = $this->container->get(RecipeTracker::class)->readShippedConfig(self::CONFIG_NAME);
    $this->assertArrayNotHasKey('gone', $copy);
    $this->assertSame(array_keys($this->config(self::CONFIG_NAME)->get()), array_keys($copy));
    $this->assertSame('Copy menu', $copy['label']);
  }

  /**
   * Tests that a stored value of the wrong shape becomes an empty slot.
   */
  public function testStoredValueOfWrongShapeIsEmptied(): void {
    $this->config('recipe_locale.shipped.copy_recipe')
      ->set('config', [
        [
          'name' => self::CONFIG_NAME,
          'data' => ['label' => ['not' => 'a string']],
        ],
      ])
      ->save();

    $copy = $this->container->get(RecipeTracker::class)->readShippedConfig(self::CONFIG_NAME);
    $this->assertSame('', $copy['label']);
  }

  /**
   * Tests that config shipped in another language than English is not stored.
   *
   * Locale translates from English only, so there is nothing it could do with
   * a German original.
   */
  public function testNonEnglishShippedConfigIsNotStored(): void {
    $recipe = $this->createRecipeWithComposer('german_recipe', ['name' => 'German recipe'], [
      'name' => 'drupal/german_recipe',
      'version' => '1.0.0',
    ]);
    mkdir($recipe->path . '/config');
    file_put_contents($recipe->path . '/config/system.menu.german-menu.yml', Yaml::encode([
      'langcode' => 'de',
      'status' => TRUE,
      'dependencies' => [],
      'id' => 'german-menu',
      'label' => 'Deutsches Menü',
      'description' => '',
      'locked' => FALSE,
    ]));
    RecipeRunner::processRecipe(Recipe::createFromDirectory($recipe->path));

    $this->assertSame('Deutsches Menü', $this->config('system.menu.german-menu')->get('label'));
    $this->assertNotContains('system.menu.german-menu', $this->container->get(RecipeTracker::class)->getShippedConfigNames());
    $this->assertTrue($this->config('recipe_locale.shipped.german_recipe')->isNew());
  }

  /**
   * Tests that config the site deleted has no copy.
   */
  public function testDeletedConfigHasNoCopy(): void {
    $this->config(self::CONFIG_NAME)->delete();
    $this->assertNull($this->container->get(RecipeTracker::class)->readShippedConfig(self::CONFIG_NAME));
  }

}
