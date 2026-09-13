<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\custom_field\Attribute\CustomFieldType;
use Drupal\custom_field\Plugin\CustomFieldTypeBase;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'string' field type.
 */
#[CustomFieldType(
  id: 'string',
  label: new TranslatableMarkup('Text (plain)'),
  description: new TranslatableMarkup('A field containing a plain string value.'),
  category: new TranslatableMarkup('Text'),
  default_widget: 'text',
  default_formatter: 'string',
)]
class StringType extends CustomFieldTypeBase {

  use OptionsTrait;

  /**
   * {@inheritdoc}
   */
  public static function schema(array $settings): array {
    ['name' => $name] = $settings;

    $columns[$name] = [
      'type' => 'varchar',
      'length' => $settings['max_length'] ?? $settings['length'] ?? self::MAX_LENGTH,
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
  public static function defaultFieldSettings(): array {
    return [
      'prefix' => '',
      'suffix' => '',
      'allowed_values' => [],
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array &$form, FormStateInterface $form_state): array {
    $element = parent::fieldSettingsForm($form, $form_state);
    $settings = $this->getFieldSettings();

    $element['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prefix'),
      '#default_value' => $settings['prefix'],
      '#size' => 60,
      '#description' => $this->t("Define a string that should be prefixed to the value, like '$ ' or '&euro; '. Leave blank for none."),
    ];

    $element['suffix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Suffix'),
      '#default_value' => $settings['suffix'],
      '#size' => 60,
      '#description' => $this->t("Define a string that should be suffixed to the value, like ' m', ' kb/s'. Leave blank for none."),
    ];

    $this->allowedValues($element, $form_state, $settings);

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
    $length = $column_settings['length'];
    $value_length = mb_strlen($value);

    if ($length && mb_strlen($value) > $length) {
      $form_state->setError($element, new TranslatableMarkup('The allowed value %value cannot be longer than %length characters but is currently %value_length characters long.', [
        '%value' => $value,
        '%length' => $length,
        '%value_length' => $value_length,
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints(): array {
    $constraints = [];
    $length = $this->getSetting('length');
    if ($length) {
      $name = $this->getName();
      $constraints['Length'] = [
        'max' => $length,
        'maxMessage' => $this->t('@name: may not be longer than @max characters.', [
          '@name' => $name,
          '@max' => $length,
        ]),
      ];
    }

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(CustomFieldTypeInterface $field, string $target_entity_type): string {
    $field_settings = $field->getFieldSettings();
    if (!empty($field_settings['allowed_values'])) {
      return (string) static::getRandomOptions($field_settings['allowed_values']);
    }
    $random = new Random();
    $max_length = $field->getMaxLength();

    // When the maximum length is less than 15 generate a random word using the
    // maximum length.
    if ($max_length <= 15) {
      return ucfirst($random->word($max_length));
    }

    // The minimum length is either 10% of the maximum length, or 15 characters
    // long, whichever is greater.
    $min_length = (int) max(ceil($max_length * 0.10), 15);

    // Reduce the max length to allow us to add a period.
    $max_length -= 1;

    // The random value is generated multiple times to create a slight
    // preference towards values that are closer to the minimum length of the
    // string. For values larger than 255 (which is the default maximum value),
    // the bias towards minimum length is increased. This is because the default
    // maximum length of 255 is often used for fields that include shorter
    // values (i.e. title).
    $length = mt_rand($min_length, mt_rand($min_length, $max_length >= 255 ? mt_rand($min_length, $max_length) : $max_length));

    $string = $random->sentences(1);
    while (mb_strlen($string) < $length) {
      $string .= " {$random->sentences(1)}";
    }

    if (mb_strlen($string) > $max_length) {
      $string = substr($string, 0, $length);
      $string = substr($string, 0, (int) strrpos($string, ' '));
    }

    $string = rtrim($string, ' .');

    // Ensure that the string ends with a full stop if there are multiple
    // sentences.
    return $string . (str_contains($string, '.') ? '.' : '');
  }

}
