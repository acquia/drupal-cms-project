<?php

namespace Drupal\custom_field\Plugin\CustomField\FieldType;

use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_field\Plugin\CustomFieldTypeBase;

/**
 * Base class for numeric custom field types.
 */
class NumericTypeBase extends CustomFieldTypeBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings(): array {
    return [
      'min' => '',
      'max' => '',
      'prefix' => '',
      'suffix' => '',
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array &$form, FormStateInterface $form_state): array {
    $element = parent::fieldSettingsForm($form, $form_state);
    $settings = $this->getFieldSettings();
    $unsigned = $this->getSetting('unsigned');

    $element['min'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum'),
      '#default_value' => $settings['min'],
      '#min' => $unsigned ? 0 : NULL,
      '#description' => $this->t('The minimum value that should be allowed in this field. Leave blank for no minimum.'),
    ];

    $element['max'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum'),
      '#default_value' => $settings['max'],
      '#min' => $unsigned ? 0 : NULL,
      '#description' => $this->t('The maximum value that should be allowed in this field. Leave blank for no maximum.'),
    ];

    $element['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prefix'),
      '#default_value' => $settings['prefix'],
      '#size' => 60,
      '#description' => $this->t("Define a string that should be prefixed to the value, like '$ ' or '&euro; '. Leave blank for none. Separate singular and plural values with a pipe ('pound|pounds')."),
    ];

    $element['suffix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Suffix'),
      '#default_value' => $settings['suffix'],
      '#size' => 60,
      '#description' => $this->t("Define a string that should be suffixed to the value, like ' m', ' kb/s'. Leave blank for none. Separate singular and plural values with a pipe ('pound|pounds')."),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints(): array {
    $settings = $this->getSettings();
    $field_settings = $this->getFieldSettings();
    $constraints = [];
    // To prevent a PDO exception from occurring, restrict values in the range
    // allowed by databases.
    $type = $settings['type'];
    $min = $type !== 'float' ? $this->getDefaultMinValue($settings) : NULL;
    $max = $type !== 'float' ? $this->getDefaultMaxValue($settings) : NULL;

    // Handle range constraints.
    $min_set = isset($field_settings['min']) && $field_settings['min'] !== '';
    $max_set = isset($field_settings['max']) && $field_settings['max'] !== '';

    if ($min_set) {
      $min = $field_settings['min'];
    }
    if ($max_set) {
      $max = $field_settings['max'];
    }

    if ($min) {
      $constraints['Range']['min'] = $min;
    }
    if ($max) {
      $constraints['Range']['max'] = $max;
    }

    // Determine appropriate message.
    $params = [
      '%name' => $settings['name'],
      '%min' => $min,
      '%max' => $max,
    ];
    $messages = [
      'notInRangeMessage' => $this->t('%name: the value must be between %min and %max.', $params),
      'minMessage' => $this->t('%name: the value may be no less than %min.', $params),
      'maxMessage' => $this->t('%name: the value may be no greater than %max.', $params),
    ];

    $message_type = NULL;
    if ($min_set && $max_set) {
      $message_type = 'notInRangeMessage';
    }
    elseif ($min_set) {
      $message_type = ($min && $max) ? 'notInRangeMessage' : 'minMessage';
    }
    elseif ($max_set) {
      $message_type = ($min && $max) ? 'notInRangeMessage' : 'maxMessage';
    }

    if (!is_null($message_type)) {
      $constraints['Range'][$message_type] = $messages[$message_type];
    }

    return $constraints;
  }

  /**
   * Helper method to get the min value restricted by databases.
   *
   * @param array $settings
   *   An array of field settings.
   *
   * @return int|float
   *   The minimum value allowed by the database.
   */
  protected static function getDefaultMinValue(array $settings): int|float {
    if (!empty($settings['unsigned'])) {
      return 0;
    }

    // Each value is - (2 ^ (8 * bytes - 1)).
    $size_map = [
      'normal' => -2147483648,
      'tiny' => -128,
      'small' => -32768,
      'medium' => -8388608,
      'big' => -9223372036854775808,
    ];
    $size = $settings['size'] ?? 'normal';

    return $size_map[(string) $size];
  }

  /**
   * Helper method to get the max value restricted by databases.
   *
   * @param array $settings
   *   An array of field settings.
   *
   * @return int
   *   The maximum value allowed by the database.
   */
  protected static function getDefaultMaxValue(array $settings): int {
    if (!empty($settings['unsigned'])) {
      // Each value is (2 ^ (8 * bytes) - 1).
      $size_map = [
        'normal' => 4294967295,
        'tiny' => 255,
        'small' => 65535,
        'medium' => 16777215,
        'big' => PHP_INT_MAX,
      ];
    }
    else {
      // Each value is (2 ^ (8 * bytes - 1) - 1).
      $size_map = [
        'normal' => 2147483647,
        'tiny' => 127,
        'small' => 32767,
        'medium' => 8388607,
        'big' => PHP_INT_MAX,
      ];
    }
    $size = $settings['size'] ?? 'normal';

    return $size_map[(string) $size];
  }

  /**
   * Helper method to truncate a decimal number to a given number of decimals.
   *
   * @param float $decimal
   *   Decimal number to truncate.
   * @param int $num
   *   Number of digits the output will have.
   *
   * @return float
   *   Decimal number truncated.
   */
  protected static function truncateDecimal(float $decimal, int $num): float {
    $factor = pow(10, $num);
    return floor($decimal * $factor) / $factor;
  }

  /**
   * Helper method to get the number of decimal digits out of a decimal number.
   *
   * @param float|int $decimal
   *   The number to calculate the number of decimals digits from.
   *
   * @return int
   *   The number of decimal digits.
   */
  protected static function getDecimalDigits(float|int $decimal): int {
    $digits = 0;
    while ($decimal - round($decimal)) {
      $decimal *= 10;
      $digits++;
    }

    return $digits;
  }

}
