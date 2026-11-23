<?php

declare(strict_types=1);

namespace Drupal\ui_icons;

use Drupal\Core\Theme\Icon\IconDefinition;

/**
 * Restricts untrusted icon settings to what the icon pack declares.
 */
final class IconSettings {

  /**
   * Keeps the scalar settings declared by the icon pack.
   *
   * The icon element merges settings over the template variables, so an
   * undeclared key such as `attributes` would replace the Attribute object
   * with a string printed as is.
   *
   * @param string $icon_full_id
   *   The icon full id, `pack_id:icon_id`.
   * @param mixed $settings
   *   The settings to filter, anything but an array gives none.
   *
   * @return array
   *   The settings declared by the icon pack, scalars only.
   *
   * @see \Drupal\Core\Render\Element\Icon::preRenderIcon()
   */
  public static function filter(string $icon_full_id, mixed $settings): array {
    $icon_data = IconDefinition::getIconDataFromId($icon_full_id);
    if (!$icon_data || !is_array($settings)) {
      return [];
    }

    $definition = \Drupal::service('plugin.manager.icon_pack')->getDefinition($icon_data['pack_id'], FALSE);
    $settings = array_intersect_key($settings, $definition['settings'] ?? []);

    // Icon settings are scalars only, as defined by the Icon API.
    return array_filter($settings, is_scalar(...));
  }

}
