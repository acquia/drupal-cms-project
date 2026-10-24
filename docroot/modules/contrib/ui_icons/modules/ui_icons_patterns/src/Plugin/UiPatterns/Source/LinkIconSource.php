<?php

declare(strict_types=1);

namespace Drupal\ui_icons_patterns\Plugin\UiPatterns\Source;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\SourcePluginPropValue;

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'link_icon',
  label: new TranslatableMarkup('Icon (Link type field)'),
  description: new TranslatableMarkup('Provides the link icon data as source.'),
  prop_types: ['icon'],
)]
class LinkIconSource extends SourcePluginPropValue {

  /**
   * {@inheritdoc}
   */
  public function defaultSettings(): array {
    return [
      'icon' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): array {
    $field_items = $this->getContextValue('ui_patterns:field:items');
    $field_index = $this->getContextValue('ui_patterns:field:index');

    if (!$field_items instanceof FieldItemListInterface || NULL === $field_index) {
      return [];
    }

    $icon_value = $field_items->getValue();
    $target_id = $icon_value[$field_index]['options']['icon']['target_id'] ?? NULL;

    if (NULL === $target_id) {
      return [];
    }

    $icon_data = IconDefinition::getIconDataFromId($target_id);

    if (!$icon_data) {
      return [];
    }

    $icon_data['settings'] = $icon_value[$field_index]['options']['icon']['settings'] ?? [];

    return $icon_data;
  }

}
