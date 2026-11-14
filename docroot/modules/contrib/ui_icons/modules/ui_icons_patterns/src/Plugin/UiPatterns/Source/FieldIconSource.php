<?php

declare(strict_types=1);

namespace Drupal\ui_icons_patterns\Plugin\UiPatterns\Source;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\ui_icons_patterns\Plugin\Derivative\FieldIconSourceDeriver;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\FieldValueSourceBase;

/**
 * Plugin implementation of the prop source.
 */
#[Source(
  id: 'field_icon',
  label: new TranslatableMarkup('Field Icon'),
  description: new TranslatableMarkup('Field icon source plugin for props.'),
  deriver: FieldIconSourceDeriver::class
)]
class FieldIconSource extends FieldValueSourceBase {

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    // @todo get value from field to allow string
    $items = $this->getEntityFieldItemList();
    $delta = (isset($this->context['ui_patterns:field:index'])) ? $this->getContextValue('ui_patterns:field:index') : 0;
    $field_item_at_delta = $items->get($delta);

    $prop_type_plugin_id = $this->propDefinition['ui_patterns']['type_definition']->getPluginId();
    $is_prop_type_icon = ($prop_type_plugin_id === 'icon');

    if (!$field_item_at_delta) {
      return $is_prop_type_icon ? NULL : [];
    }

    $value = $field_item_at_delta->getValue();
    if (!$icon_data = IconDefinition::getIconDataFromId($value['target_id'] ?? $value['icon_id'] ?? '')) {
      return $is_prop_type_icon ? NULL : [];
    }

    $icon_settings = $value['settings'] ?? $value['icon_settings'] ?? [];
    $icon_data['settings'] = $icon_settings[$icon_data['pack_id']] ?? [];

    if ($prop_type_plugin_id === 'icon') {
      return $icon_data;
    }

    $full_icon_id = IconDefinition::createIconId($icon_data['pack_id'], $icon_data['icon_id']);
    return IconDefinition::getRenderable($full_icon_id, $icon_data['settings']);
  }

}
