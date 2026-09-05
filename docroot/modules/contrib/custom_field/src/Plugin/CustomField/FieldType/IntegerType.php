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
 * Plugin implementation of the 'integer' field type.
 */
#[CustomFieldType(
  id: 'integer',
  label: new TranslatableMarkup('Number (integer)'),
  description: new TranslatableMarkup('This field stores a number in the database as an integer.'),
  category: new TranslatableMarkup('Number'),
  default_widget: 'integer',
  default_formatter: 'number_integer',
)]
class IntegerType extends NumericTypeBase {

  use OptionsTrait;

  /**
   * {@inheritdoc}
   */
  public static function schema(array $settings): array {
    ['name' => $name] = $settings;

    $columns[$name] = [
      'type' => 'int',
      'size' => $settings['size'] ?? 'normal',
      'unsigned' => $settings['unsigned'] ?? FALSE,
    ];

    return $columns;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(array $settings): array {
    ['name' => $name] = $settings;

    $properties[$name] = DataDefinition::create('integer')
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
    $unsigned = $this->getSetting('unsigned');
    if ($unsigned) {
      $element['min']['#min'] = 0;
      $element['min']['#default_value'] = $settings['min'];
      $element['min']['#description'] = $this->t('The minimum value that should be allowed in this field.');
    }

    $this->allowedValues($element, $form_state, $settings);

    $min = static::getDefaultMinValue($this->getSettings());
    $max = static::getDefaultMaxValue($this->getSettings());
    foreach (Element::children($element['allowed_values']['table']) as $option) {
      $element['allowed_values']['table'][$option]['key']['#type'] = 'number';
      $element['allowed_values']['table'][$option]['key']['#min'] = $min;
      $element['allowed_values']['table'][$option]['key']['#max'] = $max;
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
    if (!preg_match('/^-?\d+$/', $value)) {
      $form_state->setError($element, new TranslatableMarkup('The allowed value %value must be an integer.', [
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
  public static function generateSampleValue(CustomFieldTypeInterface $field, string $target_entity_type): int {
    $field_settings = $field->getFieldSettings();
    if (!empty($field_settings['allowed_values'])) {
      return (int) static::getRandomOptions($field_settings['allowed_values']);
    }
    $default_min = static::getDefaultMinValue($field->getSettings());
    $default_max = static::getDefaultMaxValue($field->getSettings());

    $min = isset($field_settings['min']) && is_numeric($field_settings['min']) ? $field_settings['min'] : $default_min;
    $max = isset($field_settings['max']) && is_numeric($field_settings['max']) ? $field_settings['max'] : $default_max;

    return mt_rand((int) $min, (int) $max);
  }

}
