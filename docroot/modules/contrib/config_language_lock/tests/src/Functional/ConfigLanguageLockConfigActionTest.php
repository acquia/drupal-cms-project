<?php

declare(strict_types=1);

namespace Drupal\Tests\config_language_lock\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests config action langcode handling with and without a language lock.
 *
 * Covers three scenarios:
 * - Direct config action via URL-prefix request: without a lock the saved
 *   langcode follows the request language; with a lock it is always the locked
 *   language regardless of URL prefix.
 * - Recipe of valid config actions: always_valid entities (FullyValidatable)
 *   follow the request language or preserve explicit valid langcodes; for
 *   maybe_invalid entities (no FullyValidatable) even empty-string and unknown
 *   langcodes are saved as-is without validation. With a lock every entity is
 *   normalized to the locked language.
 * - Recipe of invalid config actions: always_valid entities with langcode ''
 *   or zz cause a 500 because ConfigActionManager runs FullyValidatable
 *   validation after each action. With a lock the entity is normalized before
 *   the action's save so validation passes (200).
 */
#[Group('config_language_lock')]
#[RunTestsInSeparateProcesses]
class ConfigLanguageLockConfigActionTest extends ConfigLanguageLockTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'config_language_lock',
    'config_language_lock_test_config_action',
  ];

  /**
   * Tests config action save uses prefixed request language without a lock.
   */
  public function testConfigActionCreateFollowsRequestLanguageWhenUnlocked(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('xx', 'yy');
    $this->enableLanguageUrlPrefixNegotiation();

    foreach (['xx' => 'lock_action_xx', 'yy' => 'lock_action_yy'] as $prefix => $entity_id) {
      $this->drupalGet($prefix . '/admin/config/regional/config-language-lock/test-config-action/create-always-valid-entity/' . $entity_id);
      $this->assertSession()->statusCodeEquals(200);

      $entity = \Drupal::entityTypeManager()->getStorage('lock_test_always_valid')->load($entity_id);
      $this->assertNotNull($entity);
      $this->assertSame($prefix, $entity->get('langcode'));
    }
  }

  /**
   * Tests config action saves in lock language despite prefixed request lang.
   */
  public function testConfigActionCreateIgnoresPrefixedRequestLanguage(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('de', 'xx', 'yy');
    $this->enableLanguageUrlPrefixNegotiation();
    $this->setLockedLanguage('de');

    foreach (['xx' => 'lock_action_xx', 'yy' => 'lock_action_yy'] as $prefix => $entity_id) {
      $this->drupalGet($prefix . '/admin/config/regional/config-language-lock/test-config-action/create-always-valid-entity/' . $entity_id);
      $this->assertSession()->statusCodeEquals(200);

      $entity = \Drupal::entityTypeManager()->getStorage('lock_test_always_valid')->load($entity_id);
      $this->assertNotNull($entity);
      $this->assertSame('de', $entity->get('langcode'));
    }
  }

  /**
   * Tests valid-action recipe preserves langcodes without a lock.
   *
   * The lock_langcode_valid_action_import recipe creates both always_valid and
   * maybe_invalid entities via config actions. Without a lock:
   * - always_valid entities with no explicit langcode follow the request
   *   language (xx); with a valid explicit langcode (yy) it is preserved.
   * - maybe_invalid entities have no FullyValidatable constraint, so empty
   *   string and unknown langcodes (zz) are saved as-is without validation.
   */
  public function testNoErrorRecipeActionKeepsLangcodesWhenUnlocked(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('xx', 'yy');
    $this->enableLanguageUrlPrefixNegotiation();

    $this->drupalGet('xx/admin/config/regional/config-language-lock/test-config-action/apply-recipe-valid-langcode-action');
    $this->assertSession()->statusCodeEquals(200);

    $expected_always_valid = [
      'lock_action_always_valid' => 'xx',
      'lock_action_always_valid_other' => 'yy',
    ];
    foreach ($expected_always_valid as $entity_id => $expected_langcode) {
      $entity = \Drupal::entityTypeManager()
        ->getStorage('lock_test_always_valid')->load($entity_id);
      $this->assertNotNull($entity);
      $this->assertSame($expected_langcode, $entity->get('langcode'));
    }

    $expected_maybe_invalid = [
      'lock_action_maybe_invalid_missing' => 'xx',
      'lock_action_maybe_invalid_empty' => '',
      'lock_action_maybe_invalid_other' => 'yy',
      'lock_action_maybe_invalid_unknown' => 'zz',
    ];
    foreach ($expected_maybe_invalid as $entity_id => $expected_langcode) {
      $entity = \Drupal::entityTypeManager()
        ->getStorage('lock_test_maybe_invalid')->load($entity_id);
      $this->assertNotNull($entity);
      $this->assertSame($expected_langcode, $entity->get('langcode'));
    }
  }

  /**
   * Tests valid-action recipe normalizes all entities to the locked language.
   *
   * With a lock set, every entity created by the recipe's config actions is
   * saved with the locked language regardless of which langcode was specified
   * — including empty string and unknown langcodes for maybe_invalid entities.
   */
  public function testNoErrorRecipeActionUsesLockedLanguage(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('de', 'xx', 'yy');
    $this->enableLanguageUrlPrefixNegotiation();
    $this->setLockedLanguage('de');

    $this->drupalGet('xx/admin/config/regional/config-language-lock/test-config-action/apply-recipe-valid-langcode-action');
    $this->assertSession()->statusCodeEquals(200);

    $always_valid_ids = [
      'lock_action_always_valid',
      'lock_action_always_valid_other',
    ];
    foreach ($always_valid_ids as $entity_id) {
      $entity = \Drupal::entityTypeManager()
        ->getStorage('lock_test_always_valid')->load($entity_id);
      $this->assertNotNull($entity);
      $this->assertSame('de', $entity->get('langcode'));
    }

    $maybe_invalid_ids = [
      'lock_action_maybe_invalid_missing',
      'lock_action_maybe_invalid_empty',
      'lock_action_maybe_invalid_other',
      'lock_action_maybe_invalid_unknown',
    ];
    foreach ($maybe_invalid_ids as $entity_id) {
      $entity = \Drupal::entityTypeManager()
        ->getStorage('lock_test_maybe_invalid')->load($entity_id);
      $this->assertNotNull($entity);
      $this->assertSame('de', $entity->get('langcode'));
    }
  }

  /**
   * Tests empty-langcode config action saves the entity but fails validation.
   *
   * ConfigActionManager validates FullyValidatable after applying each action.
   * Always_valid has the FullyValidatable marker, so an empty langcode fails
   * the Choice constraint and causes a 500. The entity exists with langcode ''
   * after the failure because it was saved before validation ran.
   */
  public function testEmptyLangcodeActionFailsValidationWhenUnlocked(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/config/regional/config-language-lock/test-config-action/apply-action-empty-langcode');
    $this->assertSession()->statusCodeEquals(500);

    $entity = \Drupal::entityTypeManager()
      ->getStorage('lock_test_always_valid')->load('lock_action_empty');
    $this->assertNotNull($entity);
    $this->assertSame('', $entity->get('langcode'));
  }

  /**
   * Tests empty-langcode config action normalizes the entity to locked lang.
   *
   * With a lock the entity is normalized before save, so validation passes and
   * the action succeeds with 200.
   */
  public function testEmptyLangcodeActionUsesLockedLanguage(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('de');
    $this->setLockedLanguage('de');

    $this->drupalGet('admin/config/regional/config-language-lock/test-config-action/apply-action-empty-langcode');
    $this->assertSession()->statusCodeEquals(200);

    $entity = \Drupal::entityTypeManager()
      ->getStorage('lock_test_always_valid')->load('lock_action_empty');
    $this->assertNotNull($entity);
    $this->assertSame('de', $entity->get('langcode'));
  }

  /**
   * Tests unknown-langcode config action saves the entity but fails validation.
   *
   * ConfigActionManager validates FullyValidatable after applying each action.
   * Always_valid has the FullyValidatable marker, so langcode zz (not an
   * installed or known language) fails the Choice constraint and causes a 500.
   * The entity exists with langcode zz after the failure.
   */
  public function testUnknownLangcodeActionFailsValidationWhenUnlocked(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/config/regional/config-language-lock/test-config-action/apply-action-unknown-langcode');
    $this->assertSession()->statusCodeEquals(500);

    $entity = \Drupal::entityTypeManager()
      ->getStorage('lock_test_always_valid')->load('lock_action_unknown');
    $this->assertNotNull($entity);
    $this->assertSame('zz', $entity->get('langcode'));
  }

  /**
   * Tests unknown-langcode action normalizes the entity to locked language.
   *
   * With a lock the unknown langcode zz is normalized before save, so
   * validation passes and the action succeeds with 200.
   */
  public function testUnknownLangcodeActionUsesLockedLanguage(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('de');
    $this->setLockedLanguage('de');

    $this->drupalGet('admin/config/regional/config-language-lock/test-config-action/apply-action-unknown-langcode');
    $this->assertSession()->statusCodeEquals(200);

    $entity = \Drupal::entityTypeManager()
      ->getStorage('lock_test_always_valid')->load('lock_action_unknown');
    $this->assertNotNull($entity);
    $this->assertSame('de', $entity->get('langcode'));
  }

}
