<?php

declare(strict_types=1);

namespace Drupal\Tests\config_language_lock\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests site default language change lifecycle with and without a lock.
 *
 * Without locale, the site default language is irrelevant — neither site
 * default changes nor module installs rewrite config langcodes. With locale,
 * module installs trigger locale's updateDefaultConfigLangcodes() batch:
 * - Step 1 (EN->XX): rewrites all existing en config to xx and newly
 *   installed config also gets xx.
 * - Step 2 (XX->YY): prior config is already xx so it is not rewritten;
 *   only newly installed config gets yy.
 * - Step 3 (YY->EN): locale skips the rewrite; new config stays en.
 *
 * With a lock, neither site default changes nor module installs change config
 * langcodes: the lock's own switch rewrites active config and locale's hooks
 * are removed. Site default changes are irrelevant once the lock is set.
 */
#[Group('config_language_lock')]
#[RunTestsInSeparateProcesses]
class ConfigLanguageLockLifecycleWithSiteDefaultTest extends ConfigLanguageLockLifecycleTestBase {

  /**
   * Tests site default language change lifecycle without a locked language.
   */
  public function testSiteDefaultLanguageChangeLifecycleWhenUnlocked(): void {
    $this->container->get('module_installer')->install(['locale']);
    $this->rebuildContainer();

    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer content types',
      'administer modules',
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

    // Create a content type via the UI before any site default change. The site
    // default is en and no lock is set, so the langcode selector defaults to
    // the request language (en). The new type gets langcode 'en'.
    // Unlike extension-managed config, UI-created config is NOT tracked in
    // locale's defaultConfigStorage (InstallStorage), so locale's
    // updateDefaultConfigLangcodes() never rewrites it — it stays 'en' through
    // all steps.
    $ui_type_id = 'lock_lifecycle_ui';
    $ui_type_config_name = "node.type.$ui_type_id";
    $this->createContentTypeViaUi($ui_type_id, 'Lock lifecycle UI', 'en');

    // Step 1: EN -> XX via site default change on the UI.
    // The default change alone does not touch active config.
    $this->drupalGet('admin/config/regional/language');
    $this->submitForm(['site_default_language' => $first_langcode], 'Save configuration');
    $this->rebuildContainer();
    $lm = \Drupal::languageManager();
    $active_content_type = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame('en', $active_content_type['langcode']);
    $this->assertSame($original_active['name'], $active_content_type['name']);
    $this->assertSame($xx_name, $lm->getLanguageConfigOverride($first_langcode, $content_type_config_name)->get('name'));
    $this->assertSame($yy_name, $lm->getLanguageConfigOverride($second_langcode, $content_type_config_name)->get('name'));
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $this->assertSame('en', $ui_type['langcode']);

    // Install the step 1 test module via the UI so the locale batch runs. Site
    // default is now xx. The batch runs updateDefaultConfigLangcodes() (sets
    // langcode to xx on all EXTENSION-MANAGED en config) followed by
    // updateConfigTranslations() which merges translations into active config
    // and refreshes overrides. The translated name is baked into active config
    // AND the xx-override is kept intact (redundantly containing the same
    // value). Newly installed step 1 config also gets xx langcode.
    // The UI-created type is NOT extension-managed, so it stays at 'en'.
    $this->drupalGet('admin/modules');
    $this->submitForm(['modules[config_language_lock_test_lifecycle_step1][enable]' => TRUE], 'Install');
    $this->rebuildContainer();
    $lm = \Drupal::languageManager();
    $active_content_type = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame($first_langcode, $active_content_type['langcode']);
    $this->assertSame($xx_name, $active_content_type['name']);
    $this->assertSame($xx_name, $lm->getLanguageConfigOverride($first_langcode, $content_type_config_name)->get('name'));
    $this->assertSame($yy_name, $lm->getLanguageConfigOverride($second_langcode, $content_type_config_name)->get('name'));
    $step1_type = \Drupal::service('config.storage')->read('node.type.lock_lifecycle_step1');
    $this->assertSame($first_langcode, $step1_type['langcode']);
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $this->assertSame('en', $ui_type['langcode']);

    // Step 2: XX -> YY via site default change on the UI.
    // The default change alone still does not touch active config.
    $this->drupalGet('admin/config/regional/language');
    $this->submitForm(['site_default_language' => $second_langcode], 'Save configuration');
    $this->rebuildContainer();
    $lm = \Drupal::languageManager();
    $active_content_type = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame($first_langcode, $active_content_type['langcode']);
    $this->assertSame($xx_name, $active_content_type['name']);
    $this->assertSame($xx_name, $lm->getLanguageConfigOverride($first_langcode, $content_type_config_name)->get('name'));
    $this->assertSame($yy_name, $lm->getLanguageConfigOverride($second_langcode, $content_type_config_name)->get('name'));
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $this->assertSame('en', $ui_type['langcode']);

    // Install the step 2 test module via the UI. Site default is now yy.
    // updateDefaultConfigLangcodes rewrites en config to yy, but the content
    // type langcode is xx — not en — so it is NOT rewritten. Only newly
    // installed step 2 config gets yy. Both overrides remain.
    // The UI-created type has langcode 'en' but is not extension-managed, so
    // it is NOT rewritten either.
    $this->drupalGet('admin/modules');
    $this->submitForm(['modules[config_language_lock_test_lifecycle_step2][enable]' => TRUE], 'Install');
    $this->rebuildContainer();
    $lm = \Drupal::languageManager();
    $active_content_type = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame($first_langcode, $active_content_type['langcode']);
    $this->assertSame($xx_name, $active_content_type['name']);
    $this->assertSame($xx_name, $lm->getLanguageConfigOverride($first_langcode, $content_type_config_name)->get('name'));
    $this->assertSame($yy_name, $lm->getLanguageConfigOverride($second_langcode, $content_type_config_name)->get('name'));
    $step2_type = \Drupal::service('config.storage')->read('node.type.lock_lifecycle_step2');
    $this->assertSame($second_langcode, $step2_type['langcode']);
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $this->assertSame('en', $ui_type['langcode']);

    // Step 3: YY -> EN via site default change on the UI.
    // The default change alone still does not touch active config.
    $this->drupalGet('admin/config/regional/language');
    $this->submitForm(['site_default_language' => 'en'], 'Save configuration');
    $this->rebuildContainer();
    $lm = \Drupal::languageManager();
    $active_content_type = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame($first_langcode, $active_content_type['langcode']);
    $this->assertSame($xx_name, $active_content_type['name']);
    $this->assertSame($xx_name, $lm->getLanguageConfigOverride($first_langcode, $content_type_config_name)->get('name'));
    $this->assertSame($yy_name, $lm->getLanguageConfigOverride($second_langcode, $content_type_config_name)->get('name'));
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $this->assertSame('en', $ui_type['langcode']);

    // Install the step 3 test module via the UI. Site default is en, so
    // updateDefaultConfigLangcodes skips the rewrite entirely (it only runs
    // when default is not en). New step 3 config stays en.
    $this->drupalGet('admin/modules');
    $this->submitForm(['modules[config_language_lock_test_lifecycle_step3][enable]' => TRUE], 'Install');
    $this->rebuildContainer();
    $lm = \Drupal::languageManager();
    $active_content_type = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame($first_langcode, $active_content_type['langcode']);
    $this->assertSame($xx_name, $active_content_type['name']);
    $this->assertSame($xx_name, $lm->getLanguageConfigOverride($first_langcode, $content_type_config_name)->get('name'));
    $this->assertSame($yy_name, $lm->getLanguageConfigOverride($second_langcode, $content_type_config_name)->get('name'));
    $step3_type = \Drupal::service('config.storage')->read('node.type.lock_lifecycle_step3');
    $this->assertSame('en', $step3_type['langcode']);
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $this->assertSame('en', $ui_type['langcode']);
  }

  /**
   * Tests site default language change lifecycle with a locked language.
   *
   * With a lock set to xx, locale's config language management is disabled.
   * All config is consistently created and kept in the locked language: the
   * lock switch rewrites existing active config to xx on lock, newly created
   * UI config is saved as xx, and newly installed extension config is saved as
   * xx. Site default changes have no effect at all. Everything stays at xx
   * throughout all site default changes and extension install steps.
   */
  public function testSiteDefaultLanguageChangeLifecycleWhenLocked(): void {
    $this->container->get('module_installer')->install(['locale']);
    $this->rebuildContainer();

    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer content types',
      'administer modules',
      'translate interface',
    ]);
    $this->drupalLogin($admin_user);

    $first_langcode = 'xx';
    $second_langcode = 'yy';
    $xx_name = 'XX content type name';
    $yy_name = 'YY content type name';
    $content_type_id = 'lock_lifecycle';
    $content_type_config_name = "node.type.$content_type_id";

    $this->setUpTranslationsViaPoImport(
      $first_langcode,
      $second_langcode,
      $content_type_config_name,
    );

    // Lock to xx. All active config is rewritten to xx by the lock switch.
    // The content type gets langcode xx and the xx translation baked in.
    $this->setLockedLanguage($first_langcode);
    $this->rebuildContainer();
    $lm = \Drupal::languageManager();
    $active_content_type = \Drupal::service('config.storage')
      ->read($content_type_config_name);
    $this->assertSame($first_langcode, $active_content_type['langcode']);
    $this->assertSame($xx_name, $active_content_type['name']);
    $this->assertNull(
      $lm->getLanguageConfigOverride($first_langcode, $content_type_config_name)
        ->get('name'),
    );
    $this->assertSame(
      $yy_name,
      $lm->getLanguageConfigOverride($second_langcode, $content_type_config_name)
        ->get('name'),
    );

    // Create a content type via the UI while the lock is xx. The lock enforces
    // xx regardless of the request language, so the new type gets xx.
    $ui_type_id = 'lock_lifecycle_ui';
    $ui_type_config_name = "node.type.$ui_type_id";
    $this->createContentTypeViaUi(
      $ui_type_id,
      'Lock lifecycle UI',
      $first_langcode,
    );

    // Step 1: EN -> XX site default change. The lock is already xx, so this
    // changes nothing: active config stays xx, overrides stay put.
    $this->drupalGet('admin/config/regional/language');
    $this->submitForm(
      ['site_default_language' => $first_langcode],
      'Save configuration',
    );
    $this->rebuildContainer();
    $lm = \Drupal::languageManager();
    $active_content_type = \Drupal::service('config.storage')
      ->read($content_type_config_name);
    $this->assertSame($first_langcode, $active_content_type['langcode']);
    $this->assertSame($xx_name, $active_content_type['name']);
    $this->assertNull(
      $lm->getLanguageConfigOverride($first_langcode, $content_type_config_name)
        ->get('name'),
    );
    $this->assertSame(
      $yy_name,
      $lm->getLanguageConfigOverride($second_langcode, $content_type_config_name)
        ->get('name'),
    );
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $this->assertSame($first_langcode, $ui_type['langcode']);

    // Install step 1 module via the UI. The lock disables locale's config
    // language management, so existing config is untouched. Newly installed
    // step 1 config is saved as xx because the lock is active.
    $this->drupalGet('admin/modules');
    $this->submitForm(
      ['modules[config_language_lock_test_lifecycle_step1][enable]' => TRUE],
      'Install',
    );
    $this->rebuildContainer();
    $lm = \Drupal::languageManager();
    $active_content_type = \Drupal::service('config.storage')
      ->read($content_type_config_name);
    $this->assertSame($first_langcode, $active_content_type['langcode']);
    $this->assertSame($xx_name, $active_content_type['name']);
    $this->assertNull(
      $lm->getLanguageConfigOverride($first_langcode, $content_type_config_name)
        ->get('name'),
    );
    $this->assertSame(
      $yy_name,
      $lm->getLanguageConfigOverride($second_langcode, $content_type_config_name)
        ->get('name'),
    );
    $step1_type = \Drupal::service('config.storage')
      ->read('node.type.lock_lifecycle_step1');
    $this->assertSame($first_langcode, $step1_type['langcode']);
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $this->assertSame($first_langcode, $ui_type['langcode']);

    // Step 2: XX -> YY site default change. The lock is still xx, so nothing
    // changes: all config stays at xx regardless of the new site default.
    $this->drupalGet('admin/config/regional/language');
    $this->submitForm(
      ['site_default_language' => $second_langcode],
      'Save configuration',
    );
    $this->rebuildContainer();
    $lm = \Drupal::languageManager();
    $active_content_type = \Drupal::service('config.storage')
      ->read($content_type_config_name);
    $this->assertSame($first_langcode, $active_content_type['langcode']);
    $this->assertSame($xx_name, $active_content_type['name']);
    $this->assertNull(
      $lm->getLanguageConfigOverride($first_langcode, $content_type_config_name)
        ->get('name'),
    );
    $this->assertSame(
      $yy_name,
      $lm->getLanguageConfigOverride($second_langcode, $content_type_config_name)
        ->get('name'),
    );
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $this->assertSame($first_langcode, $ui_type['langcode']);

    // Install step 2 module via the UI. The lock disables locale's config
    // language management, so existing config is untouched regardless of the
    // site default. Newly installed step 2 config is saved as xx.
    $this->drupalGet('admin/modules');
    $this->submitForm(
      ['modules[config_language_lock_test_lifecycle_step2][enable]' => TRUE],
      'Install',
    );
    $this->rebuildContainer();
    $lm = \Drupal::languageManager();
    $active_content_type = \Drupal::service('config.storage')
      ->read($content_type_config_name);
    $this->assertSame($first_langcode, $active_content_type['langcode']);
    $this->assertSame($xx_name, $active_content_type['name']);
    $this->assertNull(
      $lm->getLanguageConfigOverride($first_langcode, $content_type_config_name)
        ->get('name'),
    );
    $this->assertSame(
      $yy_name,
      $lm->getLanguageConfigOverride($second_langcode, $content_type_config_name)
        ->get('name'),
    );
    $step2_type = \Drupal::service('config.storage')
      ->read('node.type.lock_lifecycle_step2');
    $this->assertSame($first_langcode, $step2_type['langcode']);
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $this->assertSame($first_langcode, $ui_type['langcode']);

    // Step 3: YY -> EN site default change. The lock is still xx; no changes.
    $this->drupalGet('admin/config/regional/language');
    $this->submitForm(['site_default_language' => 'en'], 'Save configuration');
    $this->rebuildContainer();
    $lm = \Drupal::languageManager();
    $active_content_type = \Drupal::service('config.storage')
      ->read($content_type_config_name);
    $this->assertSame($first_langcode, $active_content_type['langcode']);
    $this->assertSame($xx_name, $active_content_type['name']);
    $this->assertNull(
      $lm->getLanguageConfigOverride($first_langcode, $content_type_config_name)
        ->get('name'),
    );
    $this->assertSame(
      $yy_name,
      $lm->getLanguageConfigOverride($second_langcode, $content_type_config_name)
        ->get('name'),
    );
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $this->assertSame($first_langcode, $ui_type['langcode']);

    // Install step 3 module via the UI. The lock disables locale's config
    // language management. Newly installed step 3 config is saved as xx.
    $this->drupalGet('admin/modules');
    $this->submitForm(
      ['modules[config_language_lock_test_lifecycle_step3][enable]' => TRUE],
      'Install',
    );
    $this->rebuildContainer();
    $active_content_type = \Drupal::service('config.storage')
      ->read($content_type_config_name);
    $this->assertSame($first_langcode, $active_content_type['langcode']);
    $this->assertSame($xx_name, $active_content_type['name']);
    $step3_type = \Drupal::service('config.storage')
      ->read('node.type.lock_lifecycle_step3');
    $this->assertSame($first_langcode, $step3_type['langcode']);
    $ui_type = \Drupal::service('config.storage')->read($ui_type_config_name);
    $this->assertSame($first_langcode, $ui_type['langcode']);
  }

  /**
   * Tests site default changes and module installs do not affect langcodes.
   *
   * Without locale, changing the site default language and installing modules
   * both leave config langcodes exactly as shipped. All step modules install
   * with langcode 'en' regardless of the site default at the time.
   */
  public function testSiteDefaultAndModuleInstallKeepShippedLangcodesWhenUnlocked(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer modules',
    ]);
    $this->drupalLogin($admin_user);

    $content_type_config_name = 'node.type.lock_lifecycle';

    // Baseline: shipped as 'en'.
    $active = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame('en', $active['langcode']);

    $this->createLanguages('de', 'xx');

    // Change site default to de. No locale hooks run; active config unchanged.
    $this->drupalGet('admin/config/regional/language');
    $this->submitForm(['site_default_language' => 'de'], 'Save configuration');
    $this->rebuildContainer();
    $active = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame('en', $active['langcode']);

    // Install step 1 while site default = de. No locale batch; stays 'en'.
    $this->drupalGet('admin/modules');
    $this->submitForm(['modules[config_language_lock_test_lifecycle_step1][enable]' => TRUE], 'Install');
    $this->rebuildContainer();
    $active = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame('en', $active['langcode']);
    $step1 = \Drupal::service('config.storage')->read('node.type.lock_lifecycle_step1');
    $this->assertSame('en', $step1['langcode']);

    // Change site default to xx. Still no effect.
    $this->drupalGet('admin/config/regional/language');
    $this->submitForm(['site_default_language' => 'xx'], 'Save configuration');
    $this->rebuildContainer();
    $active = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame('en', $active['langcode']);

    // Install step 2 while site default = xx. No locale batch; stays 'en'.
    $this->drupalGet('admin/modules');
    $this->submitForm(['modules[config_language_lock_test_lifecycle_step2][enable]' => TRUE], 'Install');
    $this->rebuildContainer();
    $active = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame('en', $active['langcode']);
    $step2 = \Drupal::service('config.storage')->read('node.type.lock_lifecycle_step2');
    $this->assertSame('en', $step2['langcode']);
  }

}
