<?php

declare(strict_types=1);

namespace Drupal\Tests\config_language_lock\Functional;

use Drupal\Core\Language\Language;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Base class for config_language_lock functional tests.
 */
abstract class ConfigLanguageLockTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Creates configurable languages by langcode.
   *
   * @param string ...$langcodes
   *   Langcodes to create. Labels default to the langcode in upper case.
   */
  protected function createLanguages(string ...$langcodes): void {
    foreach ($langcodes as $langcode) {
      if (!ConfigurableLanguage::load($langcode)) {
        ConfigurableLanguage::create([
          'id' => $langcode,
          'label' => strtoupper($langcode),
          'direction' => Language::DIRECTION_LTR,
        ])->save();
      }
    }
  }

  /**
   * Sets the locked language through the settings form with acknowledgement.
   *
   * @param string $langcode
   *   The langcode to lock configuration to.
   */
  protected function setLockedLanguage(string $langcode): void {
    $this->drupalGet(Url::fromRoute('config_language_lock.settings'));
    $this->submitForm(['locked_langcode' => $langcode], 'Save configuration');
    $this->assertSession()->fieldExists('edit-confirm-lock-change');
    $this->submitForm([
      'locked_langcode' => $langcode,
      'confirm_lock_change' => 1,
    ], 'Save configuration');

    $this->rebuildContainer();
    $this->assertSame($langcode, $this->config('config_language_lock.settings')->get('locked_langcode'));
  }

  /**
   * Enables follow-site-default on the settings form.
   *
   * Requires the confirm_lock_change checkbox because config will be rewritten
   * immediately when the option is saved.
   */
  protected function enableFollowSiteDefault(): void {
    $this->drupalGet(Url::fromRoute('config_language_lock.settings'));
    $this->submitForm([
      'follow_site_default' => 1,
      'confirm_lock_change' => 1,
    ], 'Save configuration');
    $this->rebuildContainer();
    $this->assertTrue(
      (bool) $this->config('config_language_lock.settings')->get('follow_site_default'),
    );
  }

  /**
   * Enables interface language negotiation by URL prefix.
   */
  protected function enableLanguageUrlPrefixNegotiation(): void {
    $this->drupalGet('admin/config/regional/language/detection');
    $this->submitForm([
      'language_interface[enabled][language-url]' => 1,
    ], 'Save settings');
  }

}
