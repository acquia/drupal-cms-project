<?php

declare(strict_types=1);

namespace Drupal\ui_icons_patterns\Plugin\UIPatterns\SettingType;

use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\ui_patterns_settings\Definition\PatternDefinitionSetting;
use Drupal\ui_patterns_settings\Plugin\PatternSettingTypeBase;

/**
 * Icon setting type.
 *
 * @UiPatternsSettingType(
 *   id = "icon",
 *   label = @Translation("Icon")
 * )
 */
class IconSettingType extends PatternSettingTypeBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, $value, PatternDefinitionSetting $def, $form_type) {
    $value = $this->getValue($value);
    $form[$def->getName()] = [
      '#type' => 'icon_autocomplete',
      '#title' => $def->getLabel(),
      '#default_value' => $value['target_id'] ?? '',
      '#default_settings' => $value['settings'] ?? [],
      '#show_settings' => TRUE,
      '#return_id' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess($value, array $context) {
    if (!is_array($value)) {
      return [
        'pack_id' => '',
        'icon_id' => '',
        'settings' => [],
      ];
    }
    // Value not coming from ::settingsForm(), like component definition's
    // preview, has an already resolved flat structure with primitive only.
    if (isset($value['icon_id']) && is_string($value['icon_id']) && isset($value['pack_id'])) {
      return $value;
    }
    // Data coming from ::settingsForm() have an IconDefinition objects.
    if (!$icon_data = IconDefinition::getIconDataFromId($value['target_id'])) {
      return [
        'pack_id' => '',
        'icon_id' => '',
        'settings' => [],
      ];
    }

    return [
      'pack_id' => $icon_data['pack_id'],
      'icon_id' => $icon_data['icon_id'],
      'settings' => $value['settings'][$icon_data['pack_id']] ?? [],
    ];
  }

}
