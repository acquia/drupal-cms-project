<?php

/**
 * @file
 * Helper functions for the theme colours features.
 */

/**
 * Retrieves a theme setting value by key.
 *
 * @param string $key
 *   The setting key to retrieve.
 *
 * @return mixed
 *   The setting value, or NULL if not found.
 */
function _get(string $key) {
  $themeSettingsProvider = \Drupal::service('Drupal\Core\Extension\ThemeSettingsProvider');
  return $themeSettingsProvider->getSetting($key);
}

/**
 * Parses skin definitions from a newline-separated string.
 *
 * @param string $value
 *   A newline-separated string of skin definitions in "key|Label" format.
 *
 * @return array
 *   An associative array of skins keyed by skin key, each containing
 *   'key' and 'label' values.
 */
function _get_skins(string $value): array {
  $raw_value = array_filter(preg_split('/\r\n|\r|\n/', $value));
  $format_value = [];
  foreach ($raw_value as $line) {
    $parts = explode('|', $line);
    $key = trim($parts[0]);
    $label = isset($parts[1]) ? trim($parts[1]) : ucfirst($key);
    $format_value[$key] = [
      'key' => $key,
      'label' => $label,
    ];
  }

  return $format_value;
}

/**
 * Converts a hex color string to an RGB array.
 *
 * @param string $hex
 *   A hex color string (3 or 6 characters, with or without leading #).
 *
 * @return array
 *   An associative array with 'r', 'g', 'b' keys, or an empty array
 *   if the hex string is invalid.
 */
function _hex_to_rgb(string $hex): array {
  $hex = ltrim($hex, '#');

  if (strlen($hex) === 3) {
    $r = hexdec(str_repeat($hex[0], 2));
    $g = hexdec(str_repeat($hex[1], 2));
    $b = hexdec(str_repeat($hex[2], 2));
  }
  elseif (strlen($hex) === 6) {
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
  }
  else {
    return [];
  }

  return ['r' => $r, 'g' => $g, 'b' => $b];
}
