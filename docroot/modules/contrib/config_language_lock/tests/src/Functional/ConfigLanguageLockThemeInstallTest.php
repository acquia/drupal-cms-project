<?php

declare(strict_types=1);

namespace Drupal\Tests\config_language_lock\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests config langcode handling when themes are installed.
 *
 * Theme installs follow the same lock-vs-locale logic as module installs with
 * one critical difference: the standard theme install UI controller returns a
 * redirect without calling batch_process(), so locale's rewrite batch is queued
 * but never executed. This means non-en, invalid, and empty langcodes all stay
 * as-is even with locale enabled — locale never gets a chance to rewrite them.
 *
 * Experimental themes go through ThemeExperimentalConfirmForm before install.
 * Because that is a real form submission, FormSubmitter calls batch_process()
 * and locale's batch runs normally — rewriting en/missing to the site default,
 * but leaving non-en, invalid, and empty langcodes untouched.
 *
 * With a lock set, the lock's own batch runs regardless of the install path and
 * normalizes all langcodes to the lock language.
 *
 * Uses both lock_test_always_valid (FullyValidatable) and
 * lock_test_maybe_invalid (no FullyValidatable) entity types. Like module
 * install, the theme installer
 * does not run FullyValidatable validation, so invalid and empty langcodes
 * install without errors in the no-lock case.
 */
#[Group('config_language_lock')]
#[RunTestsInSeparateProcesses]
class ConfigLanguageLockThemeInstallTest extends ConfigLanguageLockTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'config_language_lock',
    'config_language_lock_test_types',
  ];

  /**
   * Tests theme install keeps original langcodes without a lock or locale.
   *
   * Without a locked language or locale, Drupal installs config as-is:
   * - shipped en stays en
   * - missing langcode key defaults to en
   * - non-en valid langcode (xx) stays xx
   * - invalid (zz) and empty ('') langcodes ship as-is with no validation
   *   error.
   *
   * This applies equally to always_valid and maybe_invalid entities.
   */
  public function testThemeInstallKeepsOriginalLangcodesWhenUnlocked(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer themes',
    ]);
    $this->drupalLogin($admin_user);

    // Set de as the site default to confirm that without locale enabled, the
    // site default has no effect on installed config langcodes.
    $this->createLanguages('de', 'xx');
    $this->config('system.site')->set('default_langcode', 'de')->save();

    $this->installTestTheme();

    $expected_always_valid = [
      'lock_theme_en' => 'en',
      'lock_theme_missing' => 'en',
      'lock_theme_other' => 'xx',
      'lock_theme_invalid' => 'zz',
      'lock_theme_empty' => '',
    ];
    foreach ($expected_always_valid as $entity_id => $expected_langcode) {
      $entity = \Drupal::entityTypeManager()->getStorage('lock_test_always_valid')->load($entity_id);
      $this->assertNotNull($entity, "Entity $entity_id should exist.");
      $this->assertSame($expected_langcode, $entity->get('langcode'), "Entity $entity_id langcode.");
    }

    $expected_maybe_invalid = [
      'lock_theme_maybe_en' => 'en',
      'lock_theme_maybe_missing' => 'en',
      'lock_theme_maybe_other' => 'xx',
      'lock_theme_maybe_invalid' => 'zz',
      'lock_theme_maybe_empty' => '',
    ];
    foreach ($expected_maybe_invalid as $entity_id => $expected_langcode) {
      $entity = \Drupal::entityTypeManager()->getStorage('lock_test_maybe_invalid')->load($entity_id);
      $this->assertNotNull($entity, "Entity $entity_id should exist.");
      $this->assertSame($expected_langcode, $entity->get('langcode'), "Entity $entity_id langcode.");
    }
  }

  /**
   * Tests theme install keeps all langcodes even with locale enabled.
   *
   * Unlike module install, the theme install UI controller returns a redirect
   * without calling batch_process(). Locale's updateDefaultConfigLangcodes()
   * batch is queued by hook_themes_installed but never executed, so even en
   * langcodes are not rewritten to the site default.
   *
   * Non-en, invalid, and empty langcodes are also left as-is, the same as the
   * no-locale case — locale's batch never runs to touch any of them.
   */
  public function testThemeInstallKeepsLangcodesWhenUnlockedWithLocale(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer themes',
    ]);
    $this->drupalLogin($admin_user);

    $this->container->get('module_installer')->install(['locale']);
    $this->rebuildContainer();

    // Set de as the site default so locale would rewrite en configs to de —
    // but the theme install controller never processes the batch locale queues,
    // so the rewrite does not actually happen for any langcode.
    $this->createLanguages('de', 'xx');
    $this->config('system.site')->set('default_langcode', 'de')->save();

    $this->installTestTheme();

    // Locale's batch was queued but not processed; all langcodes stay as
    // installed regardless of the site default.
    $expected_always_valid = [
      'lock_theme_en' => 'en',
      'lock_theme_missing' => 'en',
      'lock_theme_other' => 'xx',
      'lock_theme_invalid' => 'zz',
      'lock_theme_empty' => '',
    ];
    foreach ($expected_always_valid as $entity_id => $expected_langcode) {
      $entity = \Drupal::entityTypeManager()->getStorage('lock_test_always_valid')->load($entity_id);
      $this->assertNotNull($entity, "Entity $entity_id should exist.");
      $this->assertSame($expected_langcode, $entity->get('langcode'), "Entity $entity_id langcode.");
    }

    $expected_maybe_invalid = [
      'lock_theme_maybe_en' => 'en',
      'lock_theme_maybe_missing' => 'en',
      'lock_theme_maybe_other' => 'xx',
      'lock_theme_maybe_invalid' => 'zz',
      'lock_theme_maybe_empty' => '',
    ];
    foreach ($expected_maybe_invalid as $entity_id => $expected_langcode) {
      $entity = \Drupal::entityTypeManager()->getStorage('lock_test_maybe_invalid')->load($entity_id);
      $this->assertNotNull($entity, "Entity $entity_id should exist.");
      $this->assertSame($expected_langcode, $entity->get('langcode'), "Entity $entity_id langcode.");
    }
  }

  /**
   * Tests theme install normalizes all config to lock language, without locale.
   *
   * With a lock set, all installed config is rewritten to the lock language
   * regardless of shipped langcode — including en, missing, non-en, invalid,
   * and empty. This applies to both always_valid and maybe_invalid entities.
   */
  public function testThemeInstallImportUsesLockedLanguage(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer themes',
    ]);
    $this->drupalLogin($admin_user);

    $this->assertThemeInstallUsesLockLanguage();
  }

  /**
   * Tests theme install normalizes all config to lock language, with locale.
   *
   * With locale enabled, this module takes ownership of the theme install hook
   * and normalizes all config to the lock language. Locale's own hook is
   * removed. The site default (de) is different from the lock (xx) and from
   * the shipped langcodes to confirm the lock — not the site default — wins.
   */
  public function testThemeInstallImportUsesLockedLanguageWithLocale(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer themes',
    ]);
    $this->drupalLogin($admin_user);

    $this->container->get('module_installer')->install(['locale']);
    $this->rebuildContainer();

    $this->assertThemeInstallUsesLockLanguage();
  }

  /**
   * Asserts all theme install entities were normalized to the lock language.
   */
  protected function assertThemeInstallUsesLockLanguage(): void {
    // Site default de and shipped non-en xx are both distinct from lock xx to
    // show the lock wins over everything.
    $this->createLanguages('de', 'xx');
    $this->config('system.site')->set('default_langcode', 'de')->save();
    $this->setLockedLanguage('xx');

    $this->installTestTheme();

    $always_valid_ids = [
      'lock_theme_en',
      'lock_theme_missing',
      'lock_theme_other',
      'lock_theme_invalid',
      'lock_theme_empty',
    ];
    foreach ($always_valid_ids as $entity_id) {
      $entity = \Drupal::entityTypeManager()->getStorage('lock_test_always_valid')->load($entity_id);
      $this->assertNotNull($entity, "Entity $entity_id should exist.");
      $this->assertSame('xx', $entity->get('langcode'), "Entity $entity_id langcode.");
    }

    $maybe_invalid_ids = [
      'lock_theme_maybe_en',
      'lock_theme_maybe_missing',
      'lock_theme_maybe_other',
      'lock_theme_maybe_invalid',
      'lock_theme_maybe_empty',
    ];
    foreach ($maybe_invalid_ids as $entity_id) {
      $entity = \Drupal::entityTypeManager()->getStorage('lock_test_maybe_invalid')->load($entity_id);
      $this->assertNotNull($entity, "Entity $entity_id should exist.");
      $this->assertSame('xx', $entity->get('langcode'), "Entity $entity_id langcode.");
    }
  }

  /**
   * Tests experimental theme install rewrites en langcodes to site default.
   *
   * An experimental theme install goes through ThemeExperimentalConfirmForm
   * before the actual install. Because it is a real form submission,
   * FormSubmitter calls batch_process() automatically, so locale's batch runs.
   * It rewrites en/missing langcodes to the site default but leaves non-en,
   * invalid, and
   * empty langcodes untouched — the same selective behavior as module install.
   */
  public function testExperimentalThemeInstallRewritesEnLangcodeToSiteDefaultWithLocale(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer themes',
    ]);
    $this->drupalLogin($admin_user);

    $this->container->get('module_installer')->install(['locale']);
    $this->rebuildContainer();

    // Set de as the site default so locale rewrites en/missing configs to de.
    $this->createLanguages('de', 'xx');
    $this->config('system.site')->set('default_langcode', 'de')->save();

    // The experimental theme confirm form is a real form submission, so
    // FormSubmitter processes the locale batch and rewrites en langcodes.
    $this->drupalGet('admin/appearance');
    $this->getSession()->getPage()->clickLink('Install Config language lock test theme (experimental) theme');
    $this->submitForm([], 'Continue');
    $this->rebuildContainer();

    // Locale rewrites en/missing to the site default (de). Empty langcode is
    // normalized to 'en' by getDefaultConfigLangcode() and also rewritten.
    // Non-en and invalid langcodes are left as-is.
    $expected_langcodes = [
      'lock_theme_exp_en' => 'de',
      'lock_theme_exp_missing' => 'de',
      'lock_theme_exp_other' => 'xx',
      'lock_theme_exp_invalid' => 'zz',
      'lock_theme_exp_empty' => 'de',
    ];
    foreach ($expected_langcodes as $entity_id => $expected_langcode) {
      $entity = \Drupal::entityTypeManager()->getStorage('lock_test_always_valid')->load($entity_id);
      $this->assertNotNull($entity, "Entity $entity_id should exist.");
      $this->assertSame($expected_langcode, $entity->get('langcode'), "Entity $entity_id langcode.");
    }
  }

  /**
   * Installs the config_language_lock_test_theme via the Appearance UI.
   */
  protected function installTestTheme(): void {
    $this->drupalGet('admin/appearance');
    $this->getSession()->getPage()->clickLink('Install Config language lock test theme theme');
    $this->rebuildContainer();
  }

}
