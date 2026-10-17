<?php

declare(strict_types=1);

namespace Drupal\Tests\config_language_lock\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests config langcode handling for config files shipped directly in a recipe.
 *
 * Uses lock_test_always_valid (FullyValidatable) and lock_test_maybe_invalid
 * (not FullyValidatable) entity types from config_language_lock_test_types.
 * Extension-install-via-recipe scenarios are in
 * ConfigLanguageLockRecipeExtensionConfigTest. Config action scenarios are in
 * ConfigLanguageLockConfigActionTest.
 *
 * - lock_langcode_valid_direct_config: ships always_valid entities with valid
 *   installed langcodes (xx, yy, missing key) and maybe_invalid entities with
 *   the full range — valid (xx, yy), empty (''), and unknown (zz). All
 *   maybe_invalid entities are grouped here because they never cause a
 *   validation error regardless of langcode value (no FullyValidatable).
 *   Produces no error without a lock; with a lock all entities are normalized
 *   to the locked language.
 * - lock_langcode_empty_langcode: ships only an always_valid entity with
 *   langcode ''. Always_valid has FullyValidatable so RecipeConfigInstaller
 *   validates after saving and throws — causing a 500 without a lock. With a
 *   lock the entity is normalized before save (200).
 * - lock_langcode_unknown_langcode: same as above but with langcode zz, which
 *   is not an installed or known language, failing the Choice constraint on
 *   always_valid.
 */
#[Group('config_language_lock')]
#[RunTestsInSeparateProcesses]
class ConfigLanguageLockRecipeDirectConfigTest extends ConfigLanguageLockTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'config_language_lock',
    'config_language_lock_test_recipe_direct_config',
  ];

  /**
   * Tests recipe saves each entity with its shipped langcode without a lock.
   *
   * Always_valid has FullyValidatable in its schema, so valid installed
   * langcodes (xx, yy) pass. maybe_invalid does not have FullyValidatable,
   * so its langcodes are never validated — including empty and unknown values.
   */
  public function testDirectConfigRecipeKeepsShippedLangcodesWhenUnlocked(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('xx', 'yy');
    $this->enableLanguageUrlPrefixNegotiation();

    $this->drupalGet('xx/admin/config/regional/config-language-lock/test-recipe-direct-config/apply-recipe-valid-langcode-direct-config');
    $this->assertSession()->statusCodeEquals(200);

    // always_valid entities keep their shipped langcodes. The request language
    // (xx) has no effect on entities that ship a different one. A missing
    // langcode key normalizes to the site default (en here, since no site
    // default change is made in this test).
    $expected_always_valid = [
      'lock_recipe_request' => 'xx',
      'lock_recipe_other' => 'yy',
      'lock_recipe_missing' => 'en',
    ];
    foreach ($expected_always_valid as $entity_id => $expected_langcode) {
      $entity = \Drupal::entityTypeManager()
        ->getStorage('lock_test_always_valid')->load($entity_id);
      $this->assertNotNull($entity);
      $this->assertSame($expected_langcode, $entity->get('langcode'));
    }
    // maybe_invalid entities have no FullyValidatable constraint — langcodes
    // are never validated and ship as-is, including empty and unknown values.
    $expected_maybe_invalid = [
      'lock_recipe_maybe_request' => 'xx',
      'lock_recipe_maybe_other' => 'yy',
      'lock_recipe_maybe_empty' => '',
      'lock_recipe_maybe_unknown' => 'zz',
    ];
    foreach ($expected_maybe_invalid as $entity_id => $expected_langcode) {
      $entity = \Drupal::entityTypeManager()
        ->getStorage('lock_test_maybe_invalid')->load($entity_id);
      $this->assertNotNull($entity);
      $this->assertSame($expected_langcode, $entity->get('langcode'));
    }
  }

  /**
   * Tests recipe normalizes all entities to the locked language.
   *
   * With a lock set, all config entities are saved in the locked language
   * regardless of which langcode they shipped with. This applies equally to
   * always_valid (FullyValidatable) and maybe_invalid (not FullyValidatable)
   * entities — the lock intercepts every save regardless of validation opt-in.
   */
  public function testDirectConfigRecipeUsesLockedLanguage(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('de', 'xx', 'yy');
    $this->enableLanguageUrlPrefixNegotiation();
    $this->setLockedLanguage('de');

    $this->drupalGet('xx/admin/config/regional/config-language-lock/test-recipe-direct-config/apply-recipe-valid-langcode-direct-config');
    $this->assertSession()->statusCodeEquals(200);

    foreach (['lock_recipe_request', 'lock_recipe_other', 'lock_recipe_missing'] as $entity_id) {
      $entity = \Drupal::entityTypeManager()
        ->getStorage('lock_test_always_valid')->load($entity_id);
      $this->assertNotNull($entity);
      $this->assertSame('de', $entity->get('langcode'));
    }
    foreach ([
      'lock_recipe_maybe_request',
      'lock_recipe_maybe_other',
      'lock_recipe_maybe_empty',
      'lock_recipe_maybe_unknown',
    ] as $entity_id) {
      $entity = \Drupal::entityTypeManager()
        ->getStorage('lock_test_maybe_invalid')->load($entity_id);
      $this->assertNotNull($entity);
      $this->assertSame('de', $entity->get('langcode'));
    }
  }

  /**
   * Tests empty-langcode recipe saves the entity but fails validation.
   *
   * Always_valid has FullyValidatable in its schema. RecipeConfigInstaller
   * saves all config first then validates; an empty string fails the Choice
   * constraint and causes a 500. The entity exists with langcode '' after the
   * failure because it was saved before validation ran.
   */
  public function testEmptyLangcodeRecipeFailsValidationWhenUnlocked(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/config/regional/config-language-lock/test-recipe-direct-config/apply-recipe-empty-langcode');
    $this->assertSession()->statusCodeEquals(500);

    $entity = \Drupal::entityTypeManager()
      ->getStorage('lock_test_always_valid')->load('lock_recipe_empty');
    $this->assertNotNull($entity);
    $this->assertSame('', $entity->get('langcode'));
  }

  /**
   * Tests empty-langcode recipe normalizes the entity to the locked language.
   *
   * With a lock the entity is normalized to the locked language before save,
   * so the Choice constraint passes and the recipe succeeds with 200.
   */
  public function testEmptyLangcodeRecipeUsesLockedLanguage(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('de');
    $this->setLockedLanguage('de');

    $this->drupalGet('admin/config/regional/config-language-lock/test-recipe-direct-config/apply-recipe-empty-langcode');
    $this->assertSession()->statusCodeEquals(200);

    $entity = \Drupal::entityTypeManager()
      ->getStorage('lock_test_always_valid')->load('lock_recipe_empty');
    $this->assertNotNull($entity);
    $this->assertSame('de', $entity->get('langcode'));
  }

  /**
   * Tests unknown-langcode recipe saves the entity but fails validation.
   *
   * Always_valid has FullyValidatable in its schema. The langcode zz is not
   * an installed or known language, so it fails the Choice constraint after
   * being saved. The entity exists with langcode zz after the 500.
   */
  public function testUnknownLangcodeRecipeFailsValidationWhenUnlocked(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/config/regional/config-language-lock/test-recipe-direct-config/apply-recipe-unknown-langcode');
    $this->assertSession()->statusCodeEquals(500);

    $entity = \Drupal::entityTypeManager()
      ->getStorage('lock_test_always_valid')->load('lock_recipe_unknown');
    $this->assertNotNull($entity);
    $this->assertSame('zz', $entity->get('langcode'));
  }

  /**
   * Tests unknown-langcode recipe normalizes the entity to the locked language.
   *
   * With a lock the unknown langcode zz is normalized to the locked language
   * before save, so validation passes and the recipe succeeds with 200.
   */
  public function testUnknownLangcodeRecipeUsesLockedLanguage(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('de');
    $this->setLockedLanguage('de');

    $this->drupalGet('admin/config/regional/config-language-lock/test-recipe-direct-config/apply-recipe-unknown-langcode');
    $this->assertSession()->statusCodeEquals(200);

    $entity = \Drupal::entityTypeManager()
      ->getStorage('lock_test_always_valid')->load('lock_recipe_unknown');
    $this->assertNotNull($entity);
    $this->assertSame('de', $entity->get('langcode'));
  }

}
