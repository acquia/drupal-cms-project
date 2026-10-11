<?php

declare(strict_types=1);

namespace Drupal\Tests\config_language_lock\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests content type form saves use the locked language over request language.
 *
 * Without a lock, submitting the content type add form via a URL-prefix
 * request saves the type with the request language as its langcode. With a
 * lock set, the saved langcode is always the locked language regardless of
 * which URL-prefix language was active when the form was submitted.
 */
#[Group('config_language_lock')]
#[RunTestsInSeparateProcesses]
class ConfigLanguageLockRequestLanguageContentTypeTest extends ConfigLanguageLockTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'node',
    'config_language_lock',
  ];

  /**
   * Tests content type save uses prefixed request language without a lock.
   */
  public function testContentTypeSaveFollowsRequestLanguageWhenUnlocked(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer content types',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('xx', 'yy');
    $this->enableLanguageUrlPrefixNegotiation();

    foreach (['xx' => 'lock_type_xx', 'yy' => 'lock_type_yy'] as $prefix => $type_id) {
      $this->drupalGet($prefix . '/admin/structure/types/add');
      $this->submitForm([
        'name' => 'Type ' . strtoupper($prefix) . ' ' . $this->randomMachineName(6),
        'type' => $type_id,
      ], 'Save');

      $content_type = \Drupal::entityTypeManager()->getStorage('node_type')->load($type_id);
      $this->assertNotNull($content_type);
      $this->assertSame($prefix, $content_type->get('langcode'));
    }
  }

  /**
   * Tests content type save ignores prefixed request language.
   */
  public function testContentTypeSaveIgnoresPrefixedRequestLanguage(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer content types',
    ]);
    $this->drupalLogin($admin_user);

    $this->createLanguages('de', 'xx', 'yy');
    $this->enableLanguageUrlPrefixNegotiation();
    $this->setLockedLanguage('de');

    foreach (['xx' => 'lock_type_xx', 'yy' => 'lock_type_yy'] as $prefix => $type_id) {
      $this->drupalGet($prefix . '/admin/structure/types/add');
      $this->submitForm([
        'name' => 'Type ' . strtoupper($prefix) . ' ' . $this->randomMachineName(6),
        'type' => $type_id,
      ], 'Save');

      $content_type = \Drupal::entityTypeManager()->getStorage('node_type')->load($type_id);
      $this->assertNotNull($content_type);
      $this->assertSame('de', $content_type->get('langcode'));
    }
  }

}
