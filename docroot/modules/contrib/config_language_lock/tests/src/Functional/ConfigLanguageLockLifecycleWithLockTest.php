<?php

declare(strict_types=1);

namespace Drupal\Tests\config_language_lock\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests config langcode lifecycle as the locked language is switched.
 *
 * Covers the full EN->XX->YY->EN round-trip both with and without locale.
 * Without locale only the langcode is rewritten; no config values change.
 * With locale, .po-based translation overrides are swapped during each switch.
 * The translate_english variant also covers the symmetric EN override path.
 * Both extension-managed config and UI-created config follow the lock at every
 * step. UI-created config translation swapping is tested via schema-based
 * extraction (getTranslatableDataFromTypedConfig fallback).
 * Module installs while a lock is active are covered with and without locale.
 */
#[Group('config_language_lock')]
#[RunTestsInSeparateProcesses]
class ConfigLanguageLockLifecycleWithLockTest extends ConfigLanguageLockLifecycleTestBase {

  /**
   * Tests lock-language switching across three languages.
   */
  public function testLockedLanguageSwitchLifecycle(): void {
    $this->container->get('module_installer')->install(['locale']);
    $this->rebuildContainer();

    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer content types',
      'translate interface',
    ]);
    $this->drupalLogin($admin_user);

    $first_langcode = 'xx';
    $second_langcode = 'yy';
    $xx_name = 'XX content type name';
    $yy_name = 'YY content type name';
    $content_type_id = 'lock_lifecycle';
    $content_type_config_name = "node.type.$content_type_id";

    $original_active = $this->setUpTranslationsViaPoImport(
      $first_langcode,
      $second_langcode,
      $content_type_config_name,
    );

    // Create a content type via the UI before any lock is set. The site default
    // is en and the request language is en, so the new type gets langcode 'en'.
    $ui_type_id = 'lock_lifecycle_ui';
    $ui_type_config_name = "node.type.$ui_type_id";
    $this->createContentTypeViaUi($ui_type_id, 'Lock lifecycle UI', 'en');
    $ui_type_original_name = \Drupal::service('config.storage')->read($ui_type_config_name)['name'];

    // Add translations for UI-created config via language overrides.
    // These demonstrate that UI config translations are also swapped correctly.
    $lm = \Drupal::languageManager();
    $lm->getLanguageConfigOverride($first_langcode, $ui_type_config_name)
      ->set('name', 'UI XX')
      ->save();
    $lm->getLanguageConfigOverride($second_langcode, $ui_type_config_name)
      ->set('name', 'UI YY')
      ->save();

    // Step 1: EN -> XX.
    // Active config gets xx name from override. xx-override is removed.
    // Old en name cannot be saved as en-override because EN override storage is
    // NullStorage when translate_english is off. yy-override is untouched.
    // The UI-created type also follows the lock: langcode changes and xx
    // translation is swapped into active (schema-based extraction handles UI
    // config).
    $this->setLockedLanguage($first_langcode);
    $this->rebuildContainer();
    $lm = \Drupal::languageManager();
    $active_content_type = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame($first_langcode, $this->config('config_language_lock.settings')->get('locked_langcode'));
    $this->assertSame($first_langcode, $active_content_type['langcode']);
    $this->assertSame($xx_name, $active_content_type['name']);
    $this->assertNull($lm->getLanguageConfigOverride('en', $content_type_config_name)->get('name'));
    $this->assertNull($lm->getLanguageConfigOverride($first_langcode, $content_type_config_name)->get('name'));
    $this->assertSame($yy_name, $lm->getLanguageConfigOverride($second_langcode, $content_type_config_name)->get('name'));
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $this->assertSame($first_langcode, $ui_type['langcode']);
    $this->assertSame('UI XX', $ui_type['name'], 'UI config translation should be swapped (schema-based extraction).');
    // UI config en override cannot be saved when translate_english is off
    // (NullStorage).
    $this->assertNull($lm->getLanguageConfigOverride('en', $ui_type_config_name)->get('name'), 'UI config en override uses NullStorage (translate_english off).');
    $this->assertNull($lm->getLanguageConfigOverride($first_langcode, $ui_type_config_name)->get('name'), 'UI config xx override consumed.');
    $this->assertSame('UI YY', $lm->getLanguageConfigOverride($second_langcode, $ui_type_config_name)->get('name'), 'UI config yy override remains.');

    // Step 2: XX -> YY.
    // Active config gets yy name from override. yy-override is removed.
    // Old xx name is saved as xx-override. en-override remains absent.
    // UI-created type: yy translation swapped in, xx translation saved as
    // override.
    $this->setLockedLanguage($second_langcode);
    $this->rebuildContainer();
    $lm = \Drupal::languageManager();
    $active_content_type = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame($second_langcode, $this->config('config_language_lock.settings')->get('locked_langcode'));
    $this->assertSame($second_langcode, $active_content_type['langcode']);
    $this->assertSame($yy_name, $active_content_type['name']);
    $this->assertNull($lm->getLanguageConfigOverride('en', $content_type_config_name)->get('name'));
    $this->assertSame($xx_name, $lm->getLanguageConfigOverride($first_langcode, $content_type_config_name)->get('name'));
    $this->assertNull($lm->getLanguageConfigOverride($second_langcode, $content_type_config_name)->get('name'));
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $this->assertSame($second_langcode, $ui_type['langcode']);
    $this->assertSame('UI YY', $ui_type['name'], 'UI config yy translation should be swapped.');
    $this->assertSame('UI XX', $lm->getLanguageConfigOverride($first_langcode, $ui_type_config_name)->get('name'), 'UI config xx translation saved as override.');
    $this->assertNull($lm->getLanguageConfigOverride($second_langcode, $ui_type_config_name)->get('name'), 'UI config yy override consumed.');

    // Step 3: YY -> EN.
    // Active config restored to shipped default (no en-override to read;
    // module falls back to extractDefaultTranslatableData source strings).
    // Old yy name is saved as yy-override. xx-override is untouched.
    // UI-created type: no en-override exists (NullStorage when
    // translate_english is off), so the active translated value remains.
    $this->setLockedLanguage('en');
    $this->rebuildContainer();
    $lm = \Drupal::languageManager();
    $active_content_type = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame('en', $this->config('config_language_lock.settings')->get('locked_langcode'));
    $this->assertSame('en', $active_content_type['langcode']);
    $this->assertSame($original_active['name'], $active_content_type['name']);
    $this->assertNull($lm->getLanguageConfigOverride('en', $content_type_config_name)->get('name'));
    $this->assertSame($xx_name, $lm->getLanguageConfigOverride($first_langcode, $content_type_config_name)->get('name'));
    $this->assertSame($yy_name, $lm->getLanguageConfigOverride($second_langcode, $content_type_config_name)->get('name'));
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $this->assertSame('en', $ui_type['langcode']);
    $this->assertSame('UI YY', $ui_type['name'], 'UI config keeps latest translated value when no en override exists.');
    $this->assertSame('UI XX', $lm->getLanguageConfigOverride($first_langcode, $ui_type_config_name)->get('name'), 'UI config xx override remains.');
    $this->assertSame('UI YY', $lm->getLanguageConfigOverride($second_langcode, $ui_type_config_name)->get('name'), 'UI config yy override remains.');
  }

  /**
   * Tests lifecycle with English translation enabled.
   *
   * When locale's "translate English" setting is on, EN gets a real override
   * storage instead of a NullStorage, so it behaves symmetrically with all
   * other languages and the EN override round-trip can be asserted.
   */
  public function testLockedLanguageSwitchLifecycleWithTranslateEnglish(): void {
    $this->container->get('module_installer')->install(['locale']);
    $this->rebuildContainer();

    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer content types',
      'translate interface',
    ]);
    $this->drupalLogin($admin_user);

    $first_langcode = 'xx';
    $second_langcode = 'yy';
    $xx_name = 'XX content type name';
    $yy_name = 'YY content type name';
    $content_type_id = 'lock_lifecycle';
    $content_type_config_name = "node.type.$content_type_id";

    // Enable EN translation so EN gets real override storage.
    $this->config('locale.settings')->set('translate_english', 1)->save();
    $this->rebuildContainer();

    // Confirm EN overrides now persist (translate_english on): saving a value
    // and reading it back returns the saved value, enabling en-override
    // round-trips in the assertions below.
    \Drupal::languageManager()
      ->getLanguageConfigOverride('en', $content_type_config_name)
      ->set('name', 'sentinel')
      ->save();
    $this->assertSame(
      'sentinel',
      \Drupal::languageManager()
        ->getLanguageConfigOverride('en', $content_type_config_name)
        ->get('name'),
    );
    \Drupal::languageManager()
      ->getLanguageConfigOverride('en', $content_type_config_name)
      ->delete();

    $original_active = $this->setUpTranslationsViaPoImport(
      $first_langcode,
      $second_langcode,
      $content_type_config_name,
    );

    // Create a content type via the UI before any lock is set. The site default
    // is en and the request language is en, so the new type gets langcode 'en'.
    $ui_type_id = 'lock_lifecycle_ui';
    $ui_type_config_name = "node.type.$ui_type_id";
    $this->createContentTypeViaUi($ui_type_id, 'Lock lifecycle UI', 'en');
    $ui_type_original_name = \Drupal::service('config.storage')->read($ui_type_config_name)['name'];

    // Add translations for UI-created config via language overrides.
    \Drupal::languageManager()->getLanguageConfigOverride($first_langcode, $ui_type_config_name)
      ->set('name', 'UI XX te')
      ->save();
    \Drupal::languageManager()->getLanguageConfigOverride($second_langcode, $ui_type_config_name)
      ->set('name', 'UI YY te')
      ->save();

    // Step 1: EN -> XX.
    // Active config gets xx name from override. xx-override is removed.
    // Old en name is saved as en-override (translate_english is on, so EN has
    // real storage). yy-override is untouched.
    // UI-created type: xx translation swapped in, en translation saved
    // (translate_english enables this).
    $this->setLockedLanguage($first_langcode);
    $this->rebuildContainer();
    $active_content_type = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame($first_langcode, $this->config('config_language_lock.settings')->get('locked_langcode'));
    $this->assertSame($first_langcode, $active_content_type['langcode']);
    $this->assertSame($xx_name, $active_content_type['name']);
    $this->assertSame($original_active['name'], \Drupal::languageManager()->getLanguageConfigOverride('en', $content_type_config_name)->get('name'));
    $this->assertNull(\Drupal::languageManager()->getLanguageConfigOverride($first_langcode, $content_type_config_name)->get('name'));
    $this->assertSame($yy_name, \Drupal::languageManager()->getLanguageConfigOverride($second_langcode, $content_type_config_name)->get('name'));
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $this->assertSame($first_langcode, $ui_type['langcode']);
    $this->assertSame('UI XX te', $ui_type['name'], 'UI config xx translation swapped (locale translation).');
    $this->assertSame($ui_type_original_name, \Drupal::languageManager()->getLanguageConfigOverride('en', $ui_type_config_name)->get('name'), 'UI config en value saved as en override (translate_english on).');
    $this->assertNull(\Drupal::languageManager()->getLanguageConfigOverride($first_langcode, $ui_type_config_name)->get('name'), 'UI config xx override consumed.');
    $this->assertSame('UI YY te', \Drupal::languageManager()->getLanguageConfigOverride($second_langcode, $ui_type_config_name)->get('name'), 'UI config yy override remains.');

    // Step 2: XX -> YY.
    // Active config gets yy name from override. yy-override is removed.
    // Old xx name is saved as xx-override. en-override is untouched.
    // UI-created type: yy translation swapped, xx translation saved as
    // override.
    $this->setLockedLanguage($second_langcode);
    $this->rebuildContainer();
    $active_content_type = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame($second_langcode, $this->config('config_language_lock.settings')->get('locked_langcode'));
    $this->assertSame($second_langcode, $active_content_type['langcode']);
    $this->assertSame($yy_name, $active_content_type['name']);
    $this->assertSame($original_active['name'], \Drupal::languageManager()->getLanguageConfigOverride('en', $content_type_config_name)->get('name'));
    $this->assertSame($xx_name, \Drupal::languageManager()->getLanguageConfigOverride($first_langcode, $content_type_config_name)->get('name'));
    $this->assertNull(\Drupal::languageManager()->getLanguageConfigOverride($second_langcode, $content_type_config_name)->get('name'));
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $this->assertSame($second_langcode, $ui_type['langcode']);
    $this->assertSame('UI YY te', $ui_type['name'], 'UI config yy translation swapped.');
    $this->assertSame($ui_type_original_name, \Drupal::languageManager()->getLanguageConfigOverride('en', $ui_type_config_name)->get('name'), 'UI config en override preserved.');
    $this->assertSame('UI XX te', \Drupal::languageManager()->getLanguageConfigOverride($first_langcode, $ui_type_config_name)->get('name'), 'UI config xx translation saved as override.');
    $this->assertNull(\Drupal::languageManager()->getLanguageConfigOverride($second_langcode, $ui_type_config_name)->get('name'), 'UI config yy override consumed.');

    // Step 3: YY -> EN.
    // Active config restored from en-override. en-override is removed.
    // Old yy name is saved as yy-override. xx-override is untouched.
    // UI-created type: restored from en-override (translate_english enables
    // recovery).
    $this->setLockedLanguage('en');
    $this->rebuildContainer();
    $active_content_type = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame('en', $this->config('config_language_lock.settings')->get('locked_langcode'));
    $this->assertSame('en', $active_content_type['langcode']);
    $this->assertSame($original_active['name'], $active_content_type['name']);
    $this->assertNull(\Drupal::languageManager()->getLanguageConfigOverride('en', $content_type_config_name)->get('name'));
    $this->assertSame($xx_name, \Drupal::languageManager()->getLanguageConfigOverride($first_langcode, $content_type_config_name)->get('name'));
    $this->assertSame($yy_name, \Drupal::languageManager()->getLanguageConfigOverride($second_langcode, $content_type_config_name)->get('name'));
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $this->assertSame('en', $ui_type['langcode']);
    $this->assertSame($ui_type_original_name, $ui_type['name'], 'UI config restored from en-override (translate_english on).');
    $this->assertNull(\Drupal::languageManager()->getLanguageConfigOverride('en', $ui_type_config_name)->get('name'), 'UI config en override consumed.');
    $this->assertSame('UI XX te', \Drupal::languageManager()->getLanguageConfigOverride($first_langcode, $ui_type_config_name)->get('name'), 'UI config xx override remains.');
    $this->assertSame('UI YY te', \Drupal::languageManager()->getLanguageConfigOverride($second_langcode, $ui_type_config_name)->get('name'), 'UI config yy override remains.');
  }

  /**
   * Tests lock-language switching rewrites only the langcode without locale.
   *
   * Without locale there are no translation overrides to swap. Each switch
   * updates the langcode to the new lock language; all other config values
   * (e.g. the content type name) stay unchanged throughout the round-trip.
   *
   * Also tests that UI-created config (not in locale's install storage)
   * still has its langcode rewritten, demonstrating that the lock handles
   * config that locale would ignore.
   */
  public function testLockedLanguageSwitchLifecycleWithoutLocale(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer content types',
    ]);
    $this->drupalLogin($admin_user);

    $content_type_config_name = 'node.type.lock_lifecycle';

    $this->createLanguages('xx', 'yy');

    // Baseline: shipped langcode is en, name is unchanged.
    $active = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame('en', $active['langcode']);
    $original_name = $active['name'];

    // Create a UI content type while no lock is set. The request language is
    // en, so it gets langcode 'en'.
    $ui_type_config_name = 'node.type.lock_lifecycle_ui';
    $this->drupalCreateContentType([
      'type' => 'lock_lifecycle_ui',
      'name' => 'Lock lifecycle UI',
    ]);
    $this->rebuildContainer();
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $this->assertSame('en', $ui_type['langcode']);

    // Step 1: EN → XX. Only the langcode changes; name stays at original.
    $this->setLockedLanguage('xx');
    $this->rebuildContainer();
    $active = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame('xx', $active['langcode']);
    $this->assertSame($original_name, $active['name']);
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $this->assertSame('xx', $ui_type['langcode']);

    // Step 2: XX → YY. Only the langcode changes again; name still unchanged.
    $this->setLockedLanguage('yy');
    $this->rebuildContainer();
    $active = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame('yy', $active['langcode']);
    $this->assertSame($original_name, $active['name']);
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $this->assertSame('yy', $ui_type['langcode']);

    // Step 3: YY → EN. Langcode returns to en; name still the original.
    $this->setLockedLanguage('en');
    $this->rebuildContainer();
    $active = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame('en', $active['langcode']);
    $this->assertSame($original_name, $active['name']);
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $this->assertSame('en', $ui_type['langcode']);
  }

  /**
   * Tests UI-created config translation swapping via schema-based extraction.
   *
   * Without locale module, the schema-based fallback
   * (getTranslatableDataFromTypedConfig) extracts which fields are
   * translatable from config schema. This test verifies that translation
   * swapping works for UI-created config that has language overrides
   * (translations) but no entry in locale's install storage.
   */
  public function testUiCreatedConfigTranslationSwappingWithoutLocale(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer content types',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('xx', 'yy');

    // Create UI-created content type with langcode en.
    $ui_type_id = 'schema_swap_ui';
    $ui_type_config_name = "node.type.$ui_type_id";
    $this->createContentTypeViaUi($ui_type_id, 'Schema Swap Test', 'en');
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $this->assertSame('en', $ui_type['langcode']);
    $original_name = $ui_type['name'];

    // Add translations via language overrides (no locale needed for this).
    // Note: Without locale module, the language module still provides real
    // override storage for 'en'. There is no translate_english gate because
    // locale isn't installed. So en overrides persist normally, which is what
    // we want for this test. This differs from locale's behavior where
    // translate_english controls whether 'en' overrides use real or null
    // storage.
    \Drupal::languageManager()->getLanguageConfigOverride('xx', $ui_type_config_name)
      ->set('name', 'XX schema swap')
      ->save();
    \Drupal::languageManager()->getLanguageConfigOverride('yy', $ui_type_config_name)
      ->set('name', 'YY schema swap')
      ->save();

    // Set lock to xx. Overrides should be swapped using schema-based
    // extraction.
    $this->setLockedLanguage('xx');
    $this->rebuildContainer();
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $lm = \Drupal::languageManager();
    $this->assertSame('xx', $ui_type['langcode']);
    $this->assertSame('XX schema swap', $ui_type['name'], 'XX override should be swapped into active config.');
    $this->assertSame($original_name, $lm->getLanguageConfigOverride('en', $ui_type_config_name)->get('name'), 'Original en value should be saved as en override.');
    $this->assertNull($lm->getLanguageConfigOverride('xx', $ui_type_config_name)->get('name'), 'xx override should be consumed.');
    $this->assertSame('YY schema swap', $lm->getLanguageConfigOverride('yy', $ui_type_config_name)->get('name'), 'yy override should remain untouched.');

    // Switch to yy. Should swap yy override, preserve en, and save xx as
    // override.
    $this->setLockedLanguage('yy');
    $this->rebuildContainer();
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $lm = \Drupal::languageManager();
    $this->assertSame('yy', $ui_type['langcode']);
    $this->assertSame('YY schema swap', $ui_type['name'], 'YY override should be swapped into active config.');
    $this->assertSame($original_name, $lm->getLanguageConfigOverride('en', $ui_type_config_name)->get('name'), 'en override should remain.');
    $this->assertSame('XX schema swap', $lm->getLanguageConfigOverride('xx', $ui_type_config_name)->get('name'), 'Old xx value should be saved as xx override.');
    $this->assertNull($lm->getLanguageConfigOverride('yy', $ui_type_config_name)->get('name'), 'yy override should be consumed.');

    // Return to en. Should restore from en override.
    $this->setLockedLanguage('en');
    $this->rebuildContainer();
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $lm = \Drupal::languageManager();
    $this->assertSame('en', $ui_type['langcode']);
    $this->assertSame($original_name, $ui_type['name'], 'Should restore from en override.');
    $this->assertNull($lm->getLanguageConfigOverride('en', $ui_type_config_name)->get('name'), 'en override should be consumed.');
    $this->assertSame('XX schema swap', $lm->getLanguageConfigOverride('xx', $ui_type_config_name)->get('name'), 'xx override should remain.');
    $this->assertSame('YY schema swap', $lm->getLanguageConfigOverride('yy', $ui_type_config_name)->get('name'), 'yy override should remain.');
  }

  /**
   * Tests module installs with an active lock normalize to the lock language.
   *
   * The lock's own hook_modules_installed batch runs independently of locale.
   * Newly installed config is normalized to the lock language regardless of the
   * site default. Existing config is also rewritten when the lock is set.
   */
  public function testModuleInstallUsesLockLanguageWithoutLocale(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer modules',
    ]);
    $this->drupalLogin($admin_user);

    $content_type_config_name = 'node.type.lock_lifecycle';

    $this->createLanguages('de', 'xx');

    // Set site default to de to confirm the lock — not the site default —
    // determines installed config langcodes.
    $this->drupalGet('admin/config/regional/language');
    $this->submitForm(['site_default_language' => 'de'], 'Save configuration');
    $this->rebuildContainer();

    // Set lock to xx. The lock switch batch rewrites all existing config to xx.
    $this->setLockedLanguage('xx');
    $active = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame('xx', $active['langcode']);

    // Install step 1. The lock batch normalizes newly installed config to xx.
    $this->drupalGet('admin/modules');
    $this->submitForm(['modules[config_language_lock_test_lifecycle_step1][enable]' => TRUE], 'Install');
    $this->rebuildContainer();
    $active = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame('xx', $active['langcode']);
    $step1 = \Drupal::service('config.storage')->read('node.type.lock_lifecycle_step1');
    $this->assertSame('xx', $step1['langcode']);

    // Install step 2. Same result regardless of site default.
    $this->drupalGet('admin/modules');
    $this->submitForm(['modules[config_language_lock_test_lifecycle_step2][enable]' => TRUE], 'Install');
    $this->rebuildContainer();
    $step2 = \Drupal::service('config.storage')->read('node.type.lock_lifecycle_step2');
    $this->assertSame('xx', $step2['langcode']);
  }

}
