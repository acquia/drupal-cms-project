<?php

declare(strict_types=1);

namespace Drupal\Tests\config_language_lock\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the follow-site-default option.
 *
 * When follow_site_default is enabled, changing the site default language on
 * the language overview form automatically updates locked_langcode and runs
 * the rewrite batch. The test uses three distinct languages: de (initial lock
 * and site default), xx (request language via URL prefix, never the lock), and
 * yy (the new site default we switch to). This ensures the lock follows the
 * site default — not the request language — at every step.
 */
#[Group('config_language_lock')]
#[RunTestsInSeparateProcesses]
class ConfigLanguageLockFollowSiteDefaultTest extends ConfigLanguageLockTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'locale',
    'node',
    'config_language_lock',
    'config_language_lock_test_types',
  ];

  /**
   * Tests that changing the site default updates the lock and rewrites config.
   *
   * Verifies that:
   * 1. The lock follows the site default change (not the request language).
   * 2. Existing config is rewritten by the batch on site default change.
   * 3. New config after the change uses the new lock language.
   */
  public function testSiteDefaultChangeUpdatesLockAndBatch(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer content types',
      'administer modules',
    ]);
    $this->drupalLogin($admin_user);

    // Create languages; set de as site default, xx as request language prefix.
    $this->createLanguages('de', 'xx', 'yy');
    $this->config('system.site')->set('default_langcode', 'de')->save();
    $this->enableLanguageUrlPrefixNegotiation();

    // Set lock to de and enable follow.
    $this->setLockedLanguage('de');
    $this->enableFollowSiteDefault();

    // Install a test module to get a known config entity in de.
    $this->drupalGet('admin/modules');
    $this->submitForm(
      ['modules[config_language_lock_test_lifecycle_step1][enable]' => TRUE],
      'Install',
    );
    $this->rebuildContainer();

    $step1 = \Drupal::service('config.storage')->read('node.type.lock_lifecycle_step1');
    $this->assertSame('de', $step1['langcode'], 'Installed config has lock langcode de.');

    // Create a content type via UI. Even though the request language might
    // differ, hook_entity_presave enforces the lock. Use URL prefix xx to show
    // the lock wins over the request language.
    $base_url = $this->baseUrl;
    $this->drupalGet($base_url . '/xx/admin/structure/types/add');
    $this->submitForm(
      [
        'name' => 'Follow test type',
        'type' => 'follow_test_type',
        'title_label' => 'Title',
      ],
      'Save',
    );
    $this->rebuildContainer();

    $ui_type = \Drupal::service('config.storage')->read('node.type.follow_test_type');
    $this->assertSame('de', $ui_type['langcode'], 'UI-created content type has lock langcode de.');

    // Change the site default to yy on the language admin overview form.
    $this->drupalGet('admin/config/regional/language');
    $this->submitForm(['site_default_language' => 'yy'], 'Save configuration');
    $this->rebuildContainer();

    // The site default must now be yy.
    $site_default = $this->config('system.site')->get('default_langcode');
    $this->assertSame('yy', $site_default, 'Site default language changed to yy.');

    // The lock must now be yy.
    $locked = $this->config('config_language_lock.settings')->get('locked_langcode');
    $this->assertSame('yy', $locked, 'Lock langcode updated to new site default yy.');

    // Previously installed config must have been rewritten to yy by the batch.
    $step1 = \Drupal::service('config.storage')->read('node.type.lock_lifecycle_step1');
    $this->assertSame('yy', $step1['langcode'], 'Existing config rewritten to yy.');
    $ui_type = \Drupal::service('config.storage')->read('node.type.follow_test_type');
    $this->assertSame('yy', $ui_type['langcode'], 'UI-created type rewritten to yy.');

    // New config created after the change must use lock yy, not request xx.
    $this->drupalGet($base_url . '/xx/admin/structure/types/add');
    $this->submitForm(
      [
        'name' => 'Follow test type 2',
        'type' => 'follow_test_type_2',
        'title_label' => 'Title',
      ],
      'Save',
    );
    $this->rebuildContainer();

    $ui_type2 = \Drupal::service('config.storage')->read('node.type.follow_test_type_2');
    $this->assertSame('yy', $ui_type2['langcode'], 'New content type after change uses lock yy.');
  }

}
