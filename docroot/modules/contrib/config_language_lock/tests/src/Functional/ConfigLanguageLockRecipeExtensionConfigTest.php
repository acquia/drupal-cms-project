<?php

declare(strict_types=1);

namespace Drupal\Tests\config_language_lock\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests config langcode handling for extension config installed via a recipe.
 *
 * When a recipe installs a module and imports its config, the config goes
 * through RecipeConfigInstaller which runs FullyValidatable validation after
 * saving. Uses lock_test_always_valid (FullyValidatable) from
 * config_language_lock_test_types. Direct recipe config file scenarios are in
 * ConfigLanguageLockRecipeDirectConfigTest. Config action scenarios are in
 * ConfigLanguageLockConfigActionTest.
 *
 * - lock_langcode_valid_ext_config: installs config_language_lock_test_shipped_
 *   config and imports its config. Ships always_valid entities (langcode en
 *   and missing, which normalizes to en). No error without a lock; with a lock
 *   all entities are normalized to the locked language.
 * - lock_langcode_ext_invalid: installs config_language_lock_test_extension_
 *   import and imports the entity with langcode zz. Always_valid has
 *   FullyValidatable so RecipeConfigInstaller validates and throws — causing a
 *   500 without a lock. With a lock the entity is normalized and succeeds.
 */
#[Group('config_language_lock')]
#[RunTestsInSeparateProcesses]
class ConfigLanguageLockRecipeExtensionConfigTest extends ConfigLanguageLockTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'config_language_lock',
    'config_language_lock_test_recipe_ext_config',
  ];

  /**
   * Tests extension config installed via recipe keeps shipped langcodes.
   *
   * Without a lock, always_valid entities installed from an extension module
   * keep their shipped langcodes. A missing langcode key normalizes to en
   * (site default) before save, which passes FullyValidatable validation.
   */
  public function testExtConfigRecipeKeepsShippedLangcodesWhenUnlocked(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);

    // Create xx so that the shipped xx langcode on lock_recipe_ext_xx is valid.
    $this->createLanguages('xx');

    $this->drupalGet('admin/config/regional/config-language-lock/test-recipe-ext-config/apply-recipe-valid-langcode-ext-config');
    $this->assertSession()->statusCodeEquals(200);

    // lock_recipe_ext_en ships langcode en; lock_recipe_ext_missing has no
    // langcode key which normalizes to en — both pass FullyValidatable.
    // lock_recipe_ext_xx ships langcode xx which is a valid installed language.
    $expected = [
      'lock_recipe_ext_en' => 'en',
      'lock_recipe_ext_missing' => 'en',
      'lock_recipe_ext_xx' => 'xx',
    ];
    foreach ($expected as $entity_id => $expected_langcode) {
      $entity = \Drupal::entityTypeManager()
        ->getStorage('lock_test_always_valid')->load($entity_id);
      $this->assertNotNull($entity);
      $this->assertSame($expected_langcode, $entity->get('langcode'));
    }
  }

  /**
   * Tests extension config installed via recipe uses the locked language.
   *
   * With a lock set, all entities installed from the extension module are
   * normalized to the locked language regardless of their shipped langcode.
   */
  public function testExtConfigRecipeUsesLockedLanguage(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('de');
    $this->setLockedLanguage('de');

    $this->drupalGet('admin/config/regional/config-language-lock/test-recipe-ext-config/apply-recipe-valid-langcode-ext-config');
    $this->assertSession()->statusCodeEquals(200);

    foreach (['lock_recipe_ext_en', 'lock_recipe_ext_missing', 'lock_recipe_ext_xx'] as $entity_id) {
      $entity = \Drupal::entityTypeManager()
        ->getStorage('lock_test_always_valid')->load($entity_id);
      $this->assertNotNull($entity);
      $this->assertSame('de', $entity->get('langcode'));
    }

    // Simple config from install: modules should also be rewritten.
    $simple = \Drupal::config('config_language_lock_test_shipped_config.simple');
    $this->assertSame('de', $simple->get('langcode'), 'Simple config langcode from install: module should be rewritten to lock language.');
  }

  /**
   * Tests invalid-langcode extension config saves the entity but fails.
   *
   * Always_valid has FullyValidatable in its schema. The langcode zz shipped
   * by the extension is not an installed or known language, so it fails the
   * Choice constraint and causes a 500. The entity exists with langcode zz
   * after the failure because it was saved before validation ran.
   */
  public function testExtInvalidLangcodeRecipeFailsValidationWhenUnlocked(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/config/regional/config-language-lock/test-recipe-ext-config/apply-recipe-ext-invalid-langcode');
    $this->assertSession()->statusCodeEquals(500);

    $entity = \Drupal::entityTypeManager()
      ->getStorage('lock_test_always_valid')->load('lock_ext_invalid');
    $this->assertNotNull($entity);
    $this->assertSame('zz', $entity->get('langcode'));
  }

  /**
   * Tests invalid-langcode extension config normalizes the entity to locked.
   *
   * With a lock the unknown langcode zz is normalized to the locked language
   * before save, so validation passes and the recipe succeeds with 200.
   */
  public function testExtInvalidLangcodeRecipeUsesLockedLanguage(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('de');
    $this->setLockedLanguage('de');

    $this->drupalGet('admin/config/regional/config-language-lock/test-recipe-ext-config/apply-recipe-ext-invalid-langcode');
    $this->assertSession()->statusCodeEquals(200);

    $entity = \Drupal::entityTypeManager()
      ->getStorage('lock_test_always_valid')->load('lock_ext_invalid');
    $this->assertNotNull($entity);
    $this->assertSame('de', $entity->get('langcode'));
  }

}
