<?php

declare(strict_types=1);

namespace Drupal\Tests\config_language_lock\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests config langcode handling when modules are installed.
 *
 * Without a lock, Drupal preserves whatever langcode is shipped in the
 * module's config files (missing normalizes to en). With locale enabled and a
 * non-English site default, locale's updateDefaultConfigLangcodes() rewrites
 * only en langcodes to the site default — non-en, invalid, and empty langcodes
 * are left as-is. With a lock set, the lock takes precedence over everything:
 * all newly installed config is saved in the locked language regardless of
 * shipped langcode or site default.
 *
 * Unlike recipes, the standard module installer does not run FullyValidatable
 * validation after saving config. Empty and invalid langcodes ship and install
 * without errors in the no-lock case.
 *
 * Uses both lock_test_always_valid (FullyValidatable) and
 * lock_test_maybe_invalid (no FullyValidatable) entity types from
 * config_language_lock_test_types.
 */
#[Group('config_language_lock')]
#[RunTestsInSeparateProcesses]
class ConfigLanguageLockExtensionInstallTest extends ConfigLanguageLockTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'node',
    'config_language_lock',
    'config_language_lock_test_types',
  ];

  /**
   * Tests module install keeps original langcodes without a lock or locale.
   *
   * Without a locked language or locale, Drupal installs config as-is:
   * - shipped en stays en
   * - missing langcode key defaults to en
   * - non-en valid langcode (xx) stays xx
   * - invalid (zz) and empty ('') langcodes ship as-is with no validation
   *   error.
   *
   * This applies equally to always_valid (FullyValidatable) and maybe_invalid
   * (no FullyValidatable) entities — the module installer does not validate.
   */
  public function testExtensionInstallKeepsOriginalLangcodesWhenUnlocked(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer modules',
    ]);
    $this->drupalLogin($admin_user);

    // Set de as the site default to confirm that without locale enabled, the
    // site default has no effect on installed config langcodes.
    $this->createLanguages('de', 'xx');
    $this->config('system.site')->set('default_langcode', 'de')->save();

    $this->drupalGet('admin/modules');
    $this->submitForm(['modules[config_language_lock_test_extension_import][enable]' => TRUE], 'Install');
    $this->rebuildContainer();

    $expected_always_valid = [
      'lock_ext_en' => 'en',
      'lock_ext_missing' => 'en',
      'lock_ext_other' => 'xx',
      'lock_ext_invalid' => 'zz',
      'lock_ext_empty' => '',
      'lock_ext_no_translatable_en' => 'en',
    ];
    foreach ($expected_always_valid as $entity_id => $expected_langcode) {
      $entity = \Drupal::entityTypeManager()->getStorage('lock_test_always_valid')->load($entity_id);
      $this->assertNotNull($entity, "Entity $entity_id should exist.");
      $this->assertSame($expected_langcode, $entity->get('langcode'), "Entity $entity_id langcode.");
    }

    $expected_maybe_invalid = [
      'lock_ext_maybe_en' => 'en',
      'lock_ext_maybe_missing' => 'en',
      'lock_ext_maybe_other' => 'xx',
      'lock_ext_maybe_invalid' => 'zz',
      'lock_ext_maybe_empty' => '',
    ];
    foreach ($expected_maybe_invalid as $entity_id => $expected_langcode) {
      $entity = \Drupal::entityTypeManager()->getStorage('lock_test_maybe_invalid')->load($entity_id);
      $this->assertNotNull($entity, "Entity $entity_id should exist.");
      $this->assertSame($expected_langcode, $entity->get('langcode'), "Entity $entity_id langcode.");
    }
  }

  /**
   * Tests module install rewrites en langcodes to site default with locale.
   *
   * With locale enabled and the site default set to de, locale's
   * updateDefaultConfigLangcodes rewrites active config where langcode is
   * exactly 'en' to de. Non-en langcodes (xx, zz) are not touched.
   *
   * This applies equally to always_valid and maybe_invalid entities — locale's
   * rewrite does not distinguish by validation marker.
   */
  public function testExtensionInstallRewritesEnLangcodeToSiteDefaultWhenUnlockedWithLocale(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer modules',
    ]);
    $this->drupalLogin($admin_user);

    $this->container->get('module_installer')->install(['locale']);
    $this->rebuildContainer();

    // Set de as the site default so locale rewrites en configs to de.
    $this->createLanguages('de', 'xx');
    $this->config('system.site')->set('default_langcode', 'de')->save();

    $this->drupalGet('admin/modules');
    $this->submitForm(['modules[config_language_lock_test_extension_import][enable]' => TRUE], 'Install');
    $this->rebuildContainer();

    // Locale rewrites en/missing to de. Non-en langcodes are left as-is.
    // Empty langcode is treated as en by locale (getDefaultConfigLangcode()
    // normalizes empty to 'en'), so it is also rewritten to the site default.
    // Since core fix 73847ce09f3 (#3600904), config entities with no
    // translatable elements are also rewritten when langcode is en/empty.
    $expects_no_translatable_rewrite = $this->coreHas3600904LocaleRewriteFix();
    $expected_always_valid = [
      'lock_ext_en' => 'de',
      'lock_ext_missing' => 'de',
      'lock_ext_other' => 'xx',
      'lock_ext_invalid' => 'zz',
      'lock_ext_empty' => 'de',
      'lock_ext_no_translatable_en' => $expects_no_translatable_rewrite ? 'de' : 'en',
    ];
    foreach ($expected_always_valid as $entity_id => $expected_langcode) {
      $entity = \Drupal::entityTypeManager()->getStorage('lock_test_always_valid')->load($entity_id);
      $this->assertNotNull($entity, "Entity $entity_id should exist.");
      $this->assertSame($expected_langcode, $entity->get('langcode'), "Entity $entity_id langcode.");
    }

    $expected_maybe_invalid = [
      'lock_ext_maybe_en' => 'de',
      'lock_ext_maybe_missing' => 'de',
      'lock_ext_maybe_other' => 'xx',
      'lock_ext_maybe_invalid' => 'zz',
      'lock_ext_maybe_empty' => 'de',
    ];
    foreach ($expected_maybe_invalid as $entity_id => $expected_langcode) {
      $entity = \Drupal::entityTypeManager()->getStorage('lock_test_maybe_invalid')->load($entity_id);
      $this->assertNotNull($entity, "Entity $entity_id should exist.");
      $this->assertSame($expected_langcode, $entity->get('langcode'), "Entity $entity_id langcode.");
    }
  }

  /**
   * Detects whether core includes locale fix #3600904.
   *
   * Fingerprint from commit 73847ce09f3: updateDefaultConfigLangcodes() was
   * broadened to rewrite config entities even with no translatable elements.
   */
  protected function coreHas3600904LocaleRewriteFix(): bool {
    $path = DRUPAL_ROOT . '/core/modules/locale/src/LocaleConfigManager.php';
    $source = @file_get_contents($path);
    if (!is_string($source)) {
      return FALSE;
    }

    return str_contains($source, '$this->configManager->getEntityTypeIdByName($config->getName()) || !empty($this->getTranslatableData($typed_config))');
  }

  /**
   * Tests module install normalizes all config to lock language without locale.
   *
   * With a lock set, all installed config is rewritten to the lock language
   * regardless of shipped langcode — including en, missing, non-en, invalid,
   * and empty. This applies to both always_valid and maybe_invalid entities.
   */
  public function testExtensionInstallImportUsesLockedLanguage(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer modules',
    ]);
    $this->drupalLogin($admin_user);

    $this->assertExtensionInstallUsesLockLanguage();
  }

  /**
   * Tests module install normalizes all config to lock language, with locale.
   *
   * With locale enabled, this module takes ownership of the extension install
   * hook and normalizes all config to the lock language. Locale's own hook is
   * removed. The site default (de) is different from the lock (xx) and from
   * the shipped langcodes to confirm the lock — not the site default — wins.
   */
  public function testExtensionInstallImportUsesLockedLanguageWithLocale(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer modules',
    ]);
    $this->drupalLogin($admin_user);

    $this->container->get('module_installer')->install(['locale']);
    $this->rebuildContainer();

    $this->assertExtensionInstallUsesLockLanguage();
  }

  /**
   * Tests a stale/unknown locked langcode is ignored on module install.
   *
   * If the stored locked_langcode value is a string that is not a known
   * language (e.g. left over after a language is deleted), getLockedLangcode()
   * must return NULL so the stale value does not silently rewrite config to a
   * nonexistent language.
   */
  public function testStaleLockLangcodeIsIgnored(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer modules',
    ]);
    $this->drupalLogin($admin_user);

    // Bypass the settings form to plant an invalid langcode directly in config.
    \Drupal::configFactory()->getEditable('config_language_lock.settings')
      ->set('locked_langcode', 'nonexistent')->save();

    $this->drupalGet('admin/modules');
    $this->submitForm(['modules[config_language_lock_test_extension_import][enable]' => TRUE], 'Install');
    $this->rebuildContainer();

    // The stale lock is ignored; lock_ext_en keeps its shipped en langcode.
    $entity = \Drupal::entityTypeManager()->getStorage('lock_test_always_valid')->load('lock_ext_en');
    $this->assertNotNull($entity);
    $this->assertSame('en', $entity->get('langcode'));
  }

  /**
   * Asserts extension install entities were normalized to the lock language.
   */
  protected function assertExtensionInstallUsesLockLanguage(): void {
    // Site default de and shipped non-en xx are both distinct from lock xx to
    // show the lock wins over everything.
    $this->createLanguages('de', 'xx');
    $this->config('system.site')->set('default_langcode', 'de')->save();
    $this->setLockedLanguage('xx');

    $this->drupalGet('admin/modules');
    $this->submitForm(['modules[config_language_lock_test_extension_import][enable]' => TRUE], 'Install');
    $this->rebuildContainer();

    $always_valid_ids = [
      'lock_ext_en',
      'lock_ext_missing',
      'lock_ext_other',
      'lock_ext_invalid',
      'lock_ext_empty',
      'lock_ext_no_translatable_en',
    ];
    foreach ($always_valid_ids as $entity_id) {
      $entity = \Drupal::entityTypeManager()->getStorage('lock_test_always_valid')->load($entity_id);
      $this->assertNotNull($entity, "Entity $entity_id should exist.");
      $this->assertSame('xx', $entity->get('langcode'), "Entity $entity_id langcode.");
    }

    $maybe_invalid_ids = [
      'lock_ext_maybe_en',
      'lock_ext_maybe_missing',
      'lock_ext_maybe_other',
      'lock_ext_maybe_invalid',
      'lock_ext_maybe_empty',
    ];
    foreach ($maybe_invalid_ids as $entity_id) {
      $entity = \Drupal::entityTypeManager()->getStorage('lock_test_maybe_invalid')->load($entity_id);
      $this->assertNotNull($entity, "Entity $entity_id should exist.");
      $this->assertSame('xx', $entity->get('langcode'), "Entity $entity_id langcode.");
    }
  }

}
