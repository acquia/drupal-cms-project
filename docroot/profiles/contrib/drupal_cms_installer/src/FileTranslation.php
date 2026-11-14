<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_installer;

use Composer\InstalledVersions;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\Translator\FileTranslation as CoreFileTranslation;

/**
 * Decorates the file-based string translation service to use dynamic prefixing.
 *
 * @internal
 *   Everything in the Drupal CMS installer is internal and may be changed or
 *   removed at any time without warning. External code should not interact
 *   with this class.
 */
final class FileTranslation extends CoreFileTranslation {

  /**
   * Returns the version of the translation file for the installer.
   *
   * @return string
   *   The version of the translation file.
   */
  public static function version(): string {
    $version = InstalledVersions::getPrettyVersion('drupal/drupal_cms_installer');
    // The translation server can handle dev versions, just not with the `-dev`
    // suffix used by Composer.
    return str_ends_with($version, '-dev') ? substr($version, 0, -4) : $version;
  }

  /**
   * Returns the name of the translation file for the installer.
   *
   * @param string|null $language
   *   (optional) The language code of the translation.
   *
   * @return string
   *   The name of the necessary translation file in the given language.
   */
  public static function getFileName(?string $language = NULL): string {
    return 'drupal_cms_installer-' . self::version() . '.' . $language . '.po';
  }

  /**
   * {@inheritdoc}
   */
  protected function getTranslationFilesPattern($langcode = LanguageInterface::VALID_LANGCODE_REGEX): string {
    return '!' . self::getFileName($langcode) . '!';
  }

}
