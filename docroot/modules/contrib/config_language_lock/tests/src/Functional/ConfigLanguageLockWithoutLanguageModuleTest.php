<?php

declare(strict_types=1);

namespace Drupal\Tests\config_language_lock\Functional;

use Drupal\Core\Url;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the module works on sites without the language module.
 *
 * The language module is optional: config_language_lock does not depend on it
 * and all of its enforcement points work on a monolingual site. On such a site
 * the only selectable lock language is the site default, but locking is still
 * meaningful because shipped, recipe-imported and programmatically created
 * config can carry empty, missing, unknown or foreign langcodes.
 */
#[Group('config_language_lock')]
#[RunTestsInSeparateProcesses]
class ConfigLanguageLockWithoutLanguageModuleTest extends ConfigLanguageLockTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'config_language_lock',
    'config_language_lock_test_types',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The whole point of this test class: nothing here may pull in language.
    $module_handler = \Drupal::moduleHandler();
    $this->assertFalse($module_handler->moduleExists('language'), 'The language module is not installed.');
    $this->assertFalse($module_handler->moduleExists('locale'), 'The locale module is not installed.');
  }

  /**
   * Tests the settings form is reachable and usable without language module.
   *
   * The route is granted by this module's own 'administer configuration
   * language' permission, since 'administer languages' is only defined when
   * the language module is installed.
   */
  public function testSettingsFormWithoutLanguageModule(): void {
    $this->drupalLogin($this->createConfigLanguageAdmin());

    $this->drupalGet(Url::fromRoute('config_language_lock.settings'));
    $this->assertSession()->statusCodeEquals(200);

    // The selector must be a real select listing the site default language,
    // not core's language_select stub that silently returns 'und'.
    $this->assertSession()->fieldExists('locked_langcode');
    $this->assertSession()->optionExists('locked_langcode', 'English');
    $this->assertSession()->optionExists('locked_langcode', '- Do not lock configuration language -');
    $options = $this->getSession()->getPage()->findAll('css', 'select[name="locked_langcode"] option');
    $values = array_map(static fn ($option) => $option->getValue(), $options);
    $this->assertSame(['', 'en'], $values);

    $this->setLockedLanguage('en');
    $this->assertSame('en', $this->config('config_language_lock.settings')->get('locked_langcode'));
  }

  /**
   * Tests the settings route is protected without the language module.
   */
  public function testSettingsFormAccessWithoutLanguageModule(): void {
    $unprivileged = $this->drupalCreateUser(['access administration pages']);
    $this->drupalLogin($unprivileged);
    $this->drupalGet(Url::fromRoute('config_language_lock.settings'));
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->createConfigLanguageAdmin());
    $this->drupalGet(Url::fromRoute('config_language_lock.settings'));
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests langcode enforcement on save without the language module.
   *
   * With no lock the shipped langcode survives; with a lock every config
   * entity save is normalized, even when the assigned langcode is a language
   * the monolingual site knows nothing about.
   */
  public function testEntityPresaveEnforcementWithoutLanguageModule(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('lock_test_maybe_invalid');

    $storage->create([
      'id' => 'unlocked',
      'label' => 'Unlocked',
      'langcode' => 'xx',
    ])->save();
    $this->assertSame('xx', $storage->load('unlocked')->get('langcode'));

    $this->drupalLogin($this->createConfigLanguageAdmin());
    $this->setLockedLanguage('en');

    $storage = \Drupal::entityTypeManager()->getStorage('lock_test_maybe_invalid');
    $storage->create([
      'id' => 'locked',
      'label' => 'Locked',
      'langcode' => 'xx',
    ])->save();
    $this->assertSame('en', $storage->load('locked')->get('langcode'));

    // The settings form batch rewrote the pre-existing entity as well.
    $this->assertSame('en', $storage->load('unlocked')->get('langcode'));
  }

  /**
   * Tests module install rewrites config to the lock without language module.
   */
  public function testExtensionInstallUsesLockedLanguageWithoutLanguageModule(): void {
    $this->drupalLogin($this->createConfigLanguageAdmin(['administer modules']));
    $this->setLockedLanguage('en');

    $this->drupalGet('admin/modules');
    $this->submitForm(['modules[config_language_lock_test_extension_import][enable]' => TRUE], 'Install');
    $this->rebuildContainer();

    foreach (static::alwaysValidIds() as $entity_id) {
      $entity = \Drupal::entityTypeManager()->getStorage('lock_test_always_valid')->load($entity_id);
      $this->assertNotNull($entity, "Entity $entity_id should exist.");
      $this->assertSame('en', $entity->get('langcode'), "Entity $entity_id langcode.");
    }
    foreach (static::maybeInvalidIds() as $entity_id) {
      $entity = \Drupal::entityTypeManager()->getStorage('lock_test_maybe_invalid')->load($entity_id);
      $this->assertNotNull($entity, "Entity $entity_id should exist.");
      $this->assertSame('en', $entity->get('langcode'), "Entity $entity_id langcode.");
    }
  }

  /**
   * Tests the opt-in model still holds without the language module.
   *
   * Installing the module without configuring a lock must not change any
   * langcode, exactly as on a multilingual site.
   */
  public function testExtensionInstallUnlockedWithoutLanguageModule(): void {
    $this->drupalLogin($this->createConfigLanguageAdmin(['administer modules']));

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
  }

  /**
   * Tests follow-site-default works without the language module.
   */
  public function testFollowSiteDefaultWithoutLanguageModule(): void {
    $this->drupalLogin($this->createConfigLanguageAdmin());

    $storage = \Drupal::entityTypeManager()->getStorage('lock_test_maybe_invalid');
    $storage->create([
      'id' => 'follow_default',
      'label' => 'Follow default',
      'langcode' => 'xx',
    ])->save();

    $this->enableFollowSiteDefault();

    $settings = $this->config('config_language_lock.settings');
    $this->assertTrue((bool) $settings->get('follow_site_default'));
    $this->assertSame('en', $settings->get('locked_langcode'));
    $this->assertSame(
      'en',
      \Drupal::entityTypeManager()->getStorage('lock_test_maybe_invalid')->load('follow_default')->get('langcode'),
    );
  }

  /**
   * Tests the lock keeps working after the language module is added later.
   *
   * The language module is optional, not forbidden: a site that starts out
   * monolingual and becomes multilingual later must be able to lock to a
   * language other than the site default without reinstalling this module.
   */
  public function testLanguageModuleCanBeInstalledLater(): void {
    // 'administer languages' cannot be granted yet: the language module that
    // defines it is not installed. This module's own permission is enough.
    $this->drupalLogin($this->createConfigLanguageAdmin(['administer modules']));
    $this->setLockedLanguage('en');

    \Drupal::service('module_installer')->install(['language']);
    $this->rebuildContainer();

    $this->createLanguages('de', 'xx');
    $this->config('system.site')->set('default_langcode', 'de')->save();
    $this->setLockedLanguage('xx');

    // The selector now offers all configured languages.
    $this->drupalGet(Url::fromRoute('config_language_lock.settings'));
    $this->assertSession()->optionExists('locked_langcode', 'DE');
    $this->assertSession()->optionExists('locked_langcode', 'XX');

    $storage = \Drupal::entityTypeManager()->getStorage('lock_test_maybe_invalid');
    $storage->create([
      'id' => 'after_language_install',
      'label' => 'After language install',
      'langcode' => 'de',
    ])->save();
    $this->assertSame('xx', $storage->load('after_language_install')->get('langcode'));
  }

  /**
   * Creates a user allowed to administer the configuration language.
   *
   * @param array $extra_permissions
   *   Additional permissions to grant.
   *
   * @return \Drupal\user\UserInterface
   *   The created user.
   */
  protected function createConfigLanguageAdmin(array $extra_permissions = []): UserInterface {
    return $this->drupalCreateUser(array_merge([
      'administer configuration language',
      'access administration pages',
    ], $extra_permissions));
  }

  /**
   * Entity IDs shipped by the extension import test module, always valid type.
   */
  protected static function alwaysValidIds(): array {
    return [
      'lock_ext_en',
      'lock_ext_missing',
      'lock_ext_other',
      'lock_ext_invalid',
      'lock_ext_empty',
      'lock_ext_no_translatable_en',
    ];
  }

  /**
   * Entity IDs shipped by the extension import test module, maybe invalid type.
   */
  protected static function maybeInvalidIds(): array {
    return [
      'lock_ext_maybe_en',
      'lock_ext_maybe_missing',
      'lock_ext_maybe_other',
      'lock_ext_maybe_invalid',
      'lock_ext_maybe_empty',
    ];
  }

}
