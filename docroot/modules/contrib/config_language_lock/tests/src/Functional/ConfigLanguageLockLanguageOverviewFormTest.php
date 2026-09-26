<?php

declare(strict_types=1);

namespace Drupal\Tests\config_language_lock\Functional;

use Behat\Mink\Element\NodeElement;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the site default language selector on the language overview page.
 *
 * Core offers a radio button in every table row to pick the site default
 * language. This module replaces those with a select list in a "Default
 * language" fieldset below the table, and marks the site default language and
 * the configuration language in the table instead.
 */
#[Group('config_language_lock')]
#[RunTestsInSeparateProcesses]
class ConfigLanguageLockLanguageOverviewFormTest extends ConfigLanguageLockTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'config_language_lock',
  ];

  /**
   * Tests the select list and the table annotations.
   */
  public function testDefaultLanguageSelectAndAnnotations(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('de');

    $this->drupalGet('admin/config/regional/language');
    $assert = $this->assertSession();

    // The radio buttons and their table column are gone.
    $assert->elementNotExists('css', 'input[name="site_default_language"]');
    $assert->elementNotExists('xpath', '//table//th[normalize-space(.) = "Default"]');

    // The select list lives in its own fieldset and offers all languages.
    $fieldset = $assert->elementExists('css', 'fieldset#edit-default-language');
    $assert->elementTextContains('css', 'fieldset#edit-default-language legend', 'Default language');
    $select = $assert->elementExists('css', 'select[name="site_default_language"]', $fieldset);
    $this->assertFalse($select->hasAttribute('disabled'));
    $this->assertSame('en', $select->getValue());
    $option_values = array_map(fn (NodeElement $option) => $option->getValue(), $select->findAll('css', 'option'));
    sort($option_values);
    $this->assertSame(['de', 'en'], $option_values);

    // Without a lock, only the site default language is marked.
    $this->assertLanguageRowLabel('English (Site default language)');
    $this->assertLanguageRowLabel('DE');
    $assert->pageTextNotContains('(Configuration language)');

    // Locking configuration to German marks the two roles separately.
    $this->setLockedLanguage('de');
    $this->drupalGet('admin/config/regional/language');
    $this->assertLanguageRowLabel('English (Site default language)');
    $this->assertLanguageRowLabel('DE (Configuration language)');

    // The select list changes the site default language. German now plays
    // both roles and English none.
    $this->submitForm(['site_default_language' => 'de'], 'Save configuration');
    $this->rebuildContainer();
    $this->assertSame('de', $this->config('system.site')->get('default_langcode'));
    $select = $assert->elementExists('css', 'select[name="site_default_language"]');
    $this->assertSame('de', $select->getValue());
    $this->assertLanguageRowLabel('DE (Site default and configuration language)');
    $this->assertLanguageRowLabel('English');
    $assert->pageTextNotContains('English (Site default language)');
  }

  /**
   * Asserts that a language table row has exactly the given label cell text.
   *
   * @param string $label
   *   The expected text of the name cell, including any annotation.
   */
  protected function assertLanguageRowLabel(string $label): void {
    $this->assertSession()->elementExists(
      'xpath',
      '//table//tbody//tr/td[1][normalize-space(.) = "' . $label . '"]',
    );
  }

}
