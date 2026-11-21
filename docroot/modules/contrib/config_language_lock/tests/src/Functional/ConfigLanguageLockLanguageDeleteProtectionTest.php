<?php

declare(strict_types=1);

namespace Drupal\Tests\config_language_lock\Functional;

use Drupal\Core\Url;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests UI protection for the locked language.
 *
 * Without a lock, all languages can be deleted and no special annotation
 * is shown. With a lock set, the locked language gets a "(Configuration
 * language)" label annotation on the languages list and its delete link is
 * hidden; direct access to the delete form returns 403. Switching the lock
 * to a different language moves the annotation and protection to the new
 * locked language, while the previously locked language becomes deletable.
 */
#[Group('config_language_lock')]
#[RunTestsInSeparateProcesses]
class ConfigLanguageLockLanguageDeleteProtectionTest extends ConfigLanguageLockTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'config_language_lock',
  ];

  /**
   * Tests locked language label annotation and delete protection.
   */
  public function testLockedLanguageCannotBeDeletedAndIsAnnotated(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('xx', 'yy');

    $this->assertNoLockedLanguageUiOrDeleteProtectionWhenUnlocked('xx', 'yy');

    $this->setLockedLanguage('xx');
    $this->assertLockedLanguageUiAndDeleteAccess('xx', 'yy');
    $this->setLockedLanguage('yy');
    $this->assertLockedLanguageUiAndDeleteAccess('yy', 'xx');
  }

  /**
   * Asserts locked-language note and delete access behavior.
   */
  protected function assertLockedLanguageUiAndDeleteAccess(string $locked_langcode, string $other_langcode): void {
    $this->drupalGet('admin/config/regional/language');
    $this->assertSession()->elementTextContains(
      'xpath',
      "//tr[td[contains(., '" . strtoupper($locked_langcode) . " (Configuration language)')]]",
      strtoupper($locked_langcode) . ' (Configuration language)',
    );

    $locked_delete_url = Url::fromRoute('entity.configurable_language.delete_form', ['configurable_language' => $locked_langcode])->toString();
    $other_delete_url = Url::fromRoute('entity.configurable_language.delete_form', ['configurable_language' => $other_langcode])->toString();

    // Delete link for locked language should not be shown, but should exist for
    // another language.
    $this->assertSession()->responseNotContains($locked_delete_url);
    $this->assertSession()->responseContains($other_delete_url);

    // Direct delete access should be denied for locked language.
    $this->drupalGet($locked_delete_url);
    $this->assertSession()->statusCodeEquals(403);

    // Direct delete access should remain possible for other language.
    $this->drupalGet($other_delete_url);
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Asserts default behavior before a lock language is configured.
   */
  protected function assertNoLockedLanguageUiOrDeleteProtectionWhenUnlocked(string $first_langcode, string $second_langcode): void {
    $this->drupalGet('admin/config/regional/language');
    $this->assertSession()->pageTextNotContains('(Configuration language)');

    $first_delete_url = Url::fromRoute('entity.configurable_language.delete_form', ['configurable_language' => $first_langcode])->toString();
    $second_delete_url = Url::fromRoute('entity.configurable_language.delete_form', ['configurable_language' => $second_langcode])->toString();

    // Direct delete access should remain possible for first language.
    $this->assertSession()->responseContains($first_delete_url);
    $this->drupalGet($first_delete_url);
    $this->assertSession()->statusCodeEquals(200);

    // Direct delete access should remain possible for second language.
    $this->drupalGet('admin/config/regional/language');
    $this->assertSession()->responseContains($second_delete_url);
    $this->drupalGet($second_delete_url);
    $this->assertSession()->statusCodeEquals(200);
  }

}
