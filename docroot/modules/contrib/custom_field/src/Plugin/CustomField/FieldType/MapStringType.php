<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldType;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'map_string' field type.
 */
#[CustomFieldType(
  id: 'map_string',
  label: new TranslatableMarkup('Serialized - Text (plain)'),
  description: new TranslatableMarkup('A field for storing a serialized array of strings.'),
  category: new TranslatableMarkup('Map'),
  default_widget: 'map_text',
  default_formatter: 'map_list',
)]
class MapStringType extends MapType {

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(CustomFieldTypeInterface $field, string $target_entity_type): array {
    $random = new Random();
    $map_values = [];
    for ($i = 0; $i < 5; $i++) {
      $map_values[] = $random->word(mt_rand(10, 20));
    }

    return $map_values;
  }

}
