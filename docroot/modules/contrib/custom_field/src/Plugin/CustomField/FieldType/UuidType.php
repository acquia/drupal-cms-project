<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\custom_field\Attribute\CustomFieldType;
use Drupal\custom_field\Plugin\CustomFieldTypeBase;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'uuid' field type.
 *
 * The main purpose of this field is to be able to identify a specific
 * custom field item without having to rely on any of the exposed fields which
 * could change at any given time (i.e. content is updated, or delta is changed
 * with a manual reorder).
 */
#[CustomFieldType(
  id: 'uuid',
  label: new TranslatableMarkup('UUID'),
  description: new TranslatableMarkup('A field containing a UUID.'),
  category: new TranslatableMarkup('General'),
  default_widget: 'uuid',
  default_formatter: 'string',
  never_check_empty: TRUE,
)]
class UuidType extends CustomFieldTypeBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(array $settings): array {
    ['name' => $name] = $settings;

    $columns[$name] = [
      'type' => 'varchar_ascii',
      'length' => 128,
    ];

    return $columns;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(array $settings): array {
    ['name' => $name] = $settings;

    $properties[$name] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('@name', ['@name' => $name]))
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(CustomFieldTypeInterface $field, string $target_entity_type): mixed {
    return \Drupal::service('uuid')->generate();
  }

}
