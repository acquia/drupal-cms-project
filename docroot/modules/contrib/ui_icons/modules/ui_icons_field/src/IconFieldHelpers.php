<?php

declare(strict_types=1);

namespace Drupal\ui_icons_field;

/**
 * Helper methods for icon field.
 */
class IconFieldHelpers {

  /**
   * Filter settings to be saved from a settingsForm.
   *
   * All extractor settings form values are serialized in a single declared
   * icon_settings form key.
   * This form can be included in different forms: Field UI, Views UI, Layout
   * Builder... to avoid an implementation for each structure we try to be
   * generic by looking for 'icon_settings' key, when encountered it means we
   * are at the level of the settings array to save, ie:
   * foo
   *   bar
   *     settings:
   *       pack_id_1: settings array
   *       pack_id_2: settings array
   *       icon_settings: ... this element key
   * This method isolate the 'settings', remove icon_settings part and save it
   * by setting it as value to the element.
   *
   * @param array $element
   *   The element being processed.
   * @param array $values
   *   The form values.
   *
   * @return array
   *   The filtered values.
   */
  public static function validateSettings(array $element, array $values): array {
    $find_icon_settings = function ($elem) use (&$find_icon_settings) {
      if (!is_array($elem)) {
        return FALSE;
      }

      if (isset($elem['icon_settings'])) {
        return $elem;
      }

      foreach ($elem as $value) {
        $result = $find_icon_settings($value);
        if ($result !== FALSE) {
          return $result;
        }
      }

      return FALSE;
    };

    $settings = array_filter($values, function ($elem) use ($find_icon_settings) {
      return $find_icon_settings($elem) !== FALSE;
    });

    // Extract the value excluding 'icon_settings' key.
    $filtered_values = array_map(function ($elem) use ($find_icon_settings) {
      $found = $find_icon_settings($elem);
      return array_filter($found, function ($key) {
        return $key !== 'icon_settings';
      }, ARRAY_FILTER_USE_KEY);
    }, $settings);

    if (!$filtered_values) {
      return [];
    }

    // Clean some icon values.
    unset($filtered_values['icon_display'], $filtered_values['fields']['icon_display']);

    return reset($filtered_values);
  }

}
