<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldType;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\custom_field\Attribute\CustomFieldType;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'float' field type.
 */
#[CustomFieldType(
  id: 'float',
  label: new TranslatableMarkup('Number (float)'),
  description: new TranslatableMarkup('This field stores a number in the database in a floating point format.'),
  category: new TranslatableMarkup('Number'),
  default_widget: 'float',
  default_formatter: 'number_decimal',
)]
class FloatType extends NumericTypeBase {

  use OptionsTrait;

  /**
   * {@inheritdoc}
   */
  public static function schema(array $settings): array {
    ['name' => $name] = $settings;

    $columns[$name] = [
      'type' => 'float',
      'unsigned' => $settings['unsigned'] ?? FALSE,
      'size' => $settings['size'] ?? 'normal',
    ];

    return $columns;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(array $settings): array {
    ['name' => $name] = $settings;

    $properties[$name] = DataDefinition::create('float')
      ->setLabel(new TranslatableMarkup('@name', ['@name' => $name]))
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings(): array {
    return [
      'allowed_values' => [],
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array &$form, FormStateInterface $form_state): array {
    $element = parent::fieldSettingsForm($form, $form_state);
    $settings = $this->getFieldSettings();

    $element['min']['#step'] = 'any';
    $element['max']['#step'] = 'any';

    $this->allowedValues($element, $form_state, $settings);

    foreach (Element::children($element['allowed_values']['table']) as $option) {
      $element['allowed_values']['table'][$option]['key']['#type'] = 'number';
      $element['allowed_values']['table'][$option]['key']['#step'] = 'any';
      if ($this->isUnsigned()) {
        $element['allowed_values']['table'][$option]['key']['#min'] = 0;
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function validateAllowedValue(array $element, FormStateInterface $form_state): void {
    $value = $element['#value'];
    $sliced_parents = array_slice($element['#parents'], 0, -4);
    $field_name = end($sliced_parents);
    $column_settings = $form_state->get(['current_settings', 'columns', $field_name]);
    $unsigned = $column_settings['unsigned'] ?? FALSE;
    if (!is_numeric($value)) {
      $form_state->setError($element, new TranslatableMarkup('The allowed value %value must be a valid integer or decimal.', [
        '%value' => $value,
      ]));
    }
    elseif ($unsigned && $value < 0) {
      $form_state->setError($element, new TranslatableMarkup('The allowed value %value must be a positive number.', [
        '%value' => $value,
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(CustomFieldTypeInterface $field, string $target_entity_type): float {
    $field_settings = $field->getFieldSettings();
    if (!empty($field_settings['allowed_values'])) {
      return static::getRandomOptions($field_settings['allowed_values']);
    }
    $precision = rand(10, 32);
    $scale = rand(0, 2);
    $default_min = $field->isUnsigned() ? 0 : -pow(10, ($precision - $scale)) + 1;
    $min = isset($field_settings['min']) && is_numeric($field_settings['min']) ? $field_settings['min'] : $default_min;
    $max = isset($field_settings['max']) && is_numeric($field_settings['max']) ? $field_settings['max'] : pow(10, ($precision - $scale)) - 1;
    $random_decimal = $min + mt_rand() / mt_getrandmax() * ($max - $min);

    return static::truncateDecimal((float) $random_decimal, $scale);
  }

}
