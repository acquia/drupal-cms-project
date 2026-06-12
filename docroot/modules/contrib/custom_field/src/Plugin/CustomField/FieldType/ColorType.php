<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\custom_field\Attribute\CustomFieldType;
use Drupal\custom_field\Plugin\CustomFieldTypeBase;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'color' field type.
 */
#[CustomFieldType(
  id: 'color',
  label: new TranslatableMarkup('Color'),
  description: new TranslatableMarkup('A field containing a hexadecimal color value.'),
  category: new TranslatableMarkup('General'),
  default_widget: 'color',
  default_formatter: 'string',
)]
class ColorType extends CustomFieldTypeBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(array $settings): array {
    ['name' => $name] = $settings;

    $columns[$name] = [
      'type' => 'varchar',
      'length' => 7,
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
  public static function generateSampleValue(CustomFieldTypeInterface $field, string $target_entity_type): string {
    return static::generateRandomHexCode();
  }

  /**
   * Helper method to generate random hexadecimal color codes.
   *
   * @return string
   *   The generated hexadecimal code.
   */
  protected static function generateRandomHexCode(): string {
    $characters = '0123456789ABCDEF';
    $hexCode = '';

    for ($i = 0; $i < 6; $i++) {
      $hexCode .= $characters[rand(0, strlen($characters) - 1)];
    }

    return '#' . $hexCode;
  }

}
