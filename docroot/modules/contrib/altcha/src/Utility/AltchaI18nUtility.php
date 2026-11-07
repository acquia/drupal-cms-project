<?php

namespace Drupal\altcha\Utility;

use Drupal\altcha\Form\AltchaSettingsForm;

/**
 * Provides ALTCHA i18n helper methods.
 */
final class AltchaI18nUtility {

  /**
   * Builds the ALTCHA i18n library attachments.
   *
   * @param array $labels
   *   The configured ALTCHA labels keyed by their widget i18n keys.
   *
   * @return array
   *   A tuple containing the widget language and i18n attachments.
   */
  public static function getI18nAttachment(array $labels): array {
    $config = \Drupal::configFactory()->get('altcha.settings');
    $i18n_method = $config->get('i18n_method');

    $drupal_langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $altcha_langcode = $i18n_method === 'drupal' ? 'en' : self::getI18nLanguage($drupal_langcode);

    $i18n_library = [];

    // No need to add the entire i18n library in the case of EN site language,
    // since English is bundled with the ALTCHA widget by default.
    if ($i18n_method === 'altcha' && $altcha_langcode !== 'en') {
      $i18n_library = altcha_get_attached_library('altcha/altcha-i18n-all', 'i18n_library_override');
    }

    // Pass manually set configuration values via drupalSettings. The
    // missing labels will be filled by ALTCHA (EN) as a fallback.
    if ($i18n_method === 'drupal') {
      $labels = array_filter(array_map(function ($altcha_info) use ($config) {
        return $config->get($altcha_info['altcha_key']) ?: NULL;
      }, AltchaSettingsForm::getLabelMap()));
    }

    // Provide the ability to override specific strings in both i18n modes.
    $i18n_library = array_merge_recursive($i18n_library, [
      'library' => ['altcha/altcha-i18n-custom'],
      'drupalSettings' => [
        'altcha' => [
          'i18n' => [
            'currentLanguage' => $altcha_langcode,
            'labels' => $labels,
          ],
        ],
      ],
    ]);

    return [$altcha_langcode, $i18n_library];
  }

  /**
   * Maps Drupal language codes to ALTCHA i18n language codes.
   *
   * ALTCHA registers some locale-specific language variants only. For those
   * languages, map Drupal's generic or core-specific language code to the
   * bundled ALTCHA translation key.
   *
   * @param string $langcode
   *   The Drupal language code.
   *
   * @return string
   *   The ALTCHA i18n language code.
   */
  public static function getI18nLanguage(string $langcode): string {
    $language_map = [
      'es' => 'es-es',
      'fr' => 'fr-fr',
      'pt' => 'pt-pt',
      'zh' => 'zh-cn',
      'zh-hans' => 'zh-cn',
      'zh-hant' => 'zh-tw',
    ];

    return $language_map[strtolower($langcode)] ?? $langcode;
  }

}
