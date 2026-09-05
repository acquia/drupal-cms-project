<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldType;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\custom_field\Attribute\CustomFieldType;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'decimal' field type.
 */
#[CustomFieldType(
  id: "decimal",
  label: new TranslatableMarkup("Number (decimal)"),
  description: [
    new TranslatableMarkup("Ideal for exact counts and measures (prices, temperatures, distances, volumes, etc.)"),
    new TranslatableMarkup("Stores a number in the database in a fixed decimal format"),
    new TranslatableMarkup("For example, 12.34 km or € when used for further detailed calculations (such as summing many of these)"),
  ],
  category: new TranslatableMarkup("Number"),
  default_widget: "decimal",
  default_formatter: "number_decimal",
)]
class DecimalType extends NumericTypeBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(array $settings): array {
    ['name' => $name] = $settings;

    $columns[$name] = [
      'type' => 'numeric',
      'precision' => $settings['precision'] ?? 10,
      'scale' => $settings['scale'] ?? 2,
      'unsigned' => $settings['unsigned'] ?? FALSE,
    ];

    return $columns;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(array $settings): array {
    ['name' => $name] = $settings;

    $properties[$name] = DataDefinition::create('decimal')
      ->setLabel(new TranslatableMarkup('@name', ['@name' => $name]))
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array &$form, FormStateInterface $form_state): array {
    $element = parent::fieldSettingsForm($form, $form_state);
    $scale = $this->getScale();

    $element['min']['#step'] = pow(0.1, $scale);
    $element['max']['#step'] = pow(0.1, $scale);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(CustomFieldTypeInterface $field, string $target_entity_type): float {
    $field_settings = $field->getFieldSettings();
    $precision = $field->getPrecision() ?: 10;
    $scale = $field->getScale() ?: 2;

    $default_min = $field->isUnsigned() ? 0 : -pow(10, ($precision - $scale)) + 1;
    $default_max = pow(10, ($precision - $scale)) - 1;
    $min = isset($field_settings['min']) && is_numeric($field_settings['min']) ? $field_settings['min'] : $default_min;
    $max = isset($field_settings['max']) && is_numeric($field_settings['max']) ? $field_settings['max'] : $default_max;

    // Get the number of decimal digits for the $max.
    $decimal_digits = self::getDecimalDigits($max);
    // Do the same for the min and keep the higher number of decimal
    // digits.
    $decimal_digits = max(self::getDecimalDigits($min), $decimal_digits);

    // If $min = 1.234 and $max = 1.33 then $decimal_digits = 3.
    $scale = rand($decimal_digits, $scale);

    // Generate random decimal and truncate to scale.
    $random_decimal = $min + mt_rand() / mt_getrandmax() * ($max - $min);

    return self::truncateDecimal($random_decimal, $scale);
  }

}
