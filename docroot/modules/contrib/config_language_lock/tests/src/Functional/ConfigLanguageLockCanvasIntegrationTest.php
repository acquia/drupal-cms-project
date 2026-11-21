<?php

declare(strict_types=1);

namespace Drupal\Tests\config_language_lock\Functional;

use Drupal\Core\Url;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Canvas integration protections.
 *
 * Verifies that when Canvas is installed:
 * 1. The settings form forces follow-site-default and disables language choice.
 * 2. The language overview form blocks site default changes when pages exist.
 * 3. Runtime requirements show an error when config language mismatches.
 */
#[Group('config_language_lock')]
#[RunTestsInSeparateProcesses]
class ConfigLanguageLockCanvasIntegrationTest extends ConfigLanguageLockTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'language',
    'config_language_lock',
    'config_language_lock_test_canvas',
  ];

  /**
   * Tests that the settings form is locked when Canvas is installed.
   */
  public function testSettingsFormLockedWithCanvas(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('de');

    $this->drupalGet(Url::fromRoute('config_language_lock.settings'));
    $this->assertSession()->pageTextContains('Configuration language should be locked to the site default language because Drupal Canvas is installed.');

    // The follow_site_default checkbox should be checked and required.
    $checkbox = $this->assertSession()->fieldExists('follow_site_default');
    $this->assertTrue($checkbox->isChecked());
    $this->assertTrue($checkbox->hasAttribute('required'));

    // The language select should not be visible.
    $this->assertSession()->fieldNotExists('locked_langcode');
  }

  /**
   * Tests that submitting the form sets lock to site default with Canvas.
   */
  public function testSettingsFormSubmitSetsLockToSiteDefault(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('de');

    // No lock configured yet. Submit the form to set it up.
    $this->drupalGet(Url::fromRoute('config_language_lock.settings'));
    $this->submitForm([
      'confirm_lock_change' => 1,
    ], 'Save configuration');

    $this->rebuildContainer();
    $settings = $this->config('config_language_lock.settings');
    $this->assertTrue((bool) $settings->get('follow_site_default'));
    $this->assertSame('en', $settings->get('locked_langcode'));
  }

  /**
   * Tests that submitting repairs a mismatch.
   */
  public function testSettingsFormRepairsMismatch(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('de');

    // Simulate a pre-existing lock to a non-default language.
    $this->config('config_language_lock.settings')
      ->set('locked_langcode', 'de')
      ->set('follow_site_default', FALSE)
      ->save();

    // Submit the form — Canvas forces follow_site_default and site default.
    $this->drupalGet(Url::fromRoute('config_language_lock.settings'));
    $this->submitForm([
      'confirm_lock_change' => 1,
    ], 'Save configuration');

    $this->rebuildContainer();
    $settings = $this->config('config_language_lock.settings');
    $this->assertTrue((bool) $settings->get('follow_site_default'));
    $this->assertSame('en', $settings->get('locked_langcode'));
  }

  /**
   * Tests that site default change is blocked when Canvas pages exist.
   */
  public function testSiteDefaultBlockedWithCanvasPages(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('de');

    // Set up the lock following site default.
    $this->config('config_language_lock.settings')
      ->set('locked_langcode', 'en')
      ->set('follow_site_default', TRUE)
      ->save();

    // Create a canvas_page entity.
    $storage = \Drupal::entityTypeManager()->getStorage('canvas_page');
    $storage->create(['title' => 'Test page'])->save();
    \Drupal::service('cache_tags.invalidator')->invalidateTags(['canvas_page_list']);

    // Visit language overview — the "Default language" fieldset explains the
    // restriction in place, not as a page-level warning message.
    $this->drupalGet('admin/config/regional/language');
    $this->assertSession()->elementTextContains(
      'css',
      'fieldset#edit-default-language',
      'The site default language cannot be changed because Drupal Canvas requires all Canvas pages to be in the site default language',
    );
    $this->assertSession()->elementNotExists('css', '.messages--warning');

    // A lone page that is not the front page links straight to its delete form.
    $this->assertSession()->elementExists(
      'css',
      'fieldset#edit-default-language a[href$="/canvas-test-page/1/delete"]',
    );

    // The site default select list should be disabled. Core's radio buttons
    // are gone from the table.
    $select = $this->assertSession()->elementExists('css', 'select[name="site_default_language"]');
    $this->assertTrue($select->hasAttribute('disabled'));
    $this->assertSession()->elementNotExists('css', 'input[name="site_default_language"]');
  }

  /**
   * Tests the two steps offered when the front page is a Canvas page.
   */
  public function testSiteDefaultRestrictionWhenFrontPageIsCanvasPage(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('de');

    $storage = \Drupal::entityTypeManager()->getStorage('canvas_page');
    $page = $storage->create(['title' => 'Front page']);
    $page->save();
    $this->config('system.site')
      ->set('page.front', '/canvas-test-page/' . $page->id())
      ->save();
    \Drupal::service('cache_tags.invalidator')->invalidateTags(['canvas_page_list']);

    $this->drupalGet('admin/config/regional/language');
    $this->assertSession()->pageTextContains('It is the front page, so it cannot be deleted yet.');

    // Step one changes the front page, step two deletes the pages.
    $steps = $this->cssSelect('fieldset#edit-default-language ol li a');
    $this->assertCount(2, $steps);
    $this->assertStringEndsWith('/admin/config/system/site-information', $steps[0]->getAttribute('href'));
    $this->assertStringEndsWith('/admin/content/pages', $steps[1]->getAttribute('href'));
  }

  /**
   * Tests that site default can be changed after Canvas pages are removed.
   */
  public function testSiteDefaultAllowedAfterCanvasPagesRemoved(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('de');

    $this->config('config_language_lock.settings')
      ->set('locked_langcode', 'en')
      ->set('follow_site_default', TRUE)
      ->save();

    // Create and then delete a canvas page.
    $storage = \Drupal::entityTypeManager()->getStorage('canvas_page');
    $page = $storage->create(['title' => 'Test page']);
    $page->save();
    $page->delete();

    // Now the language overview should allow changes — no restriction message
    // and the select list is enabled again.
    $this->drupalGet('admin/config/regional/language');
    $this->assertSession()->pageTextNotContains('The site default language cannot be changed because Drupal Canvas requires');
    $select = $this->assertSession()->elementExists('css', 'select[name="site_default_language"]');
    $this->assertFalse($select->hasAttribute('disabled'));
  }

  /**
   * Tests runtime requirements error when config language mismatches.
   */
  public function testRuntimeRequirementsWithMismatch(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('de');

    // Set lock to a non-default language.
    $this->config('config_language_lock.settings')
      ->set('locked_langcode', 'de')
      ->set('follow_site_default', FALSE)
      ->save();

    // Check the status report for the error.
    $this->drupalGet('admin/reports/status');
    $this->assertSession()->pageTextContains('Misconfigured for Drupal Canvas');
    $this->assertSession()->pageTextContains('Drupal Canvas requires the configuration language to be locked to the site default language.');
  }

  /**
   * Tests no runtime requirements error when properly configured.
   */
  public function testRuntimeRequirementsNoErrorWhenMatched(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);

    // Set lock to site default properly.
    $this->config('config_language_lock.settings')
      ->set('locked_langcode', 'en')
      ->set('follow_site_default', TRUE)
      ->save();

    $this->drupalGet('admin/reports/status');
    $this->assertSession()->pageTextNotContains('Misconfigured for Drupal Canvas');
  }

}
