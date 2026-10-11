<?php

declare(strict_types=1);

namespace Drupal\Tests\config_language_lock\Functional;

/**
 * Base class for lifecycle tests that combine locale and config language lock.
 */
abstract class ConfigLanguageLockLifecycleTestBase extends ConfigLanguageLockTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'node',
    'config_language_lock',
    'config_language_lock_test_default_content_type',
  ];

  /**
   * Sets up translations via locale .po import and returns original active.
   *
   * Asserts the fixture ships with langcode 'en', creates the two test
   * languages, then imports pre-built .po files through the locale import UI
   * so locale's string storage knows about both translations. This enables
   * locale to properly manage overrides (creating, updating, and cleaning them
   * up) throughout the lifecycle tests.
   *
   * @param string $first_langcode
   *   Language code for the first test language (e.g. 'xx').
   * @param string $second_langcode
   *   Language code for the second test language (e.g. 'yy').
   * @param string $content_type_config_name
   *   Config name to assert the baseline langcode on.
   *
   * @return array
   *   The original active config array before any lifecycle changes.
   */
  protected function setUpTranslationsViaPoImport(
    string $first_langcode,
    string $second_langcode,
    string $content_type_config_name,
  ): array {
    // Confirm the fixture ships in English so step 3 asserts a known baseline.
    $original_active = \Drupal::service('config.storage')
      ->read($content_type_config_name);
    $this->assertSame('en', $original_active['langcode']);

    // Import translations via the locale import UI so that locale's string
    // storage knows about these translations. This means locale can manage the
    // overrides properly (creating, updating, and cleaning them up) rather than
    // leaving them as unmanaged orphans when written directly via the API.
    $this->createLanguages($first_langcode, $second_langcode);
    $module_path = \Drupal::service('extension.list.module')
      ->getPath('config_language_lock_test_default_content_type');
    $translations_dir = DRUPAL_ROOT . '/' . $module_path . '/translations';
    $this->drupalGet('admin/config/regional/translate/import');
    $this->submitForm([
      'langcode' => $first_langcode,
      'files[file]' => "$translations_dir/config_language_lock_test_default_content_type.$first_langcode.po",
    ], 'Import');
    $this->drupalGet('admin/config/regional/translate/import');
    $this->submitForm([
      'langcode' => $second_langcode,
      'files[file]' => "$translations_dir/config_language_lock_test_default_content_type.$second_langcode.po",
    ], 'Import');
    $this->rebuildContainer();

    return $original_active;
  }

  /**
   * Creates a content type via the Drupal UI and asserts its saved langcode.
   *
   * @param string $type_id
   *   Machine name for the new content type.
   * @param string $type_label
   *   Human-readable label for the new content type.
   * @param string $expected_langcode
   *   The langcode expected on the saved config entity.
   */
  protected function createContentTypeViaUi(
    string $type_id,
    string $type_label,
    string $expected_langcode,
  ): void {
    $this->drupalCreateContentType([
      'type' => $type_id,
      'name' => $type_label,
    ]);
    $this->rebuildContainer();
    $config = \Drupal::service('config.storage')->read("node.type.$type_id");
    $this->assertSame($expected_langcode, $config['langcode']);
  }

}
