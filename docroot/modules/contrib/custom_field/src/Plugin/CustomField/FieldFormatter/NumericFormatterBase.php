<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_field\Plugin\CustomFieldFormatterBase;

/**
 * Parent plugin for decimal and integer formatters.
 */
abstract class NumericFormatterBase extends CustomFieldFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'thousand_separator' => '',
      'decimal_separator' => '.',
      'scale' => 2,
      'prefix_suffix' => TRUE,
      'key_label' => 'label',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $visible = $form['#visibility_path'];
    $options = [
      ''  => $this->t('- None -'),
      '.' => $this->t('Decimal point'),
      ',' => $this->t('Comma'),
      ' ' => $this->t('Space'),
      chr(8201) => $this->t('Thin space'),
      "'" => $this->t('Apostrophe'),
    ];
    $elements['thousand_separator'] = [
      '#type' => 'select',
      '#title' => $this->t('Thousand marker'),
      '#options' => $options,
      '#default_value' => $this->getSetting('thousand_separator') ?? ',',
      '#weight' => 0,
      '#states' => [
        'visible' => [
          ['select[name="' . $visible . '[format_type]"]' => ['value' => 'number_decimal']],
          ['select[name="' . $visible . '[format_type]"]' => ['value' => 'number_integer']],
        ],
      ],
    ];
    $elements['key_label'] = [
      '#type' => 'radios',
      '#title' => $this->t('Display'),
      '#description' => $this->t('Select the output when values are restricted in field widget.'),
      '#options' => [
        'key' => $this->t('Key'),
        'label' => $this->t('Label'),
      ],
      '#default_value' => $this->getSetting('key_label'),
    ];
    $elements['prefix_suffix'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display prefix and suffix'),
      '#default_value' => $this->getSetting('prefix_suffix'),
      '#weight' => 10,
    ];

    return $elements;
  }

  /**
   * Formats a number.
   *
   * @param mixed $number
   *   The numeric value.
   *
   * @return string
   *   The formatted number.
   */
  abstract protected function numberFormat(mixed $number): string;

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): mixed {
    $field_settings = $this->customFieldDefinition->getFieldSettings();
    $allowed_values = $field_settings['allowed_values'] ?? [];
    $output = $this->numberFormat((float) $value);
    if (!empty($allowed_values) && $this->getSetting('key_label') == 'label') {
      $index = array_search($output, array_column($allowed_values, 'key'));
      $output = $index !== FALSE ? $allowed_values[$index]['label'] : $output;
    }
    elseif ($this->getSetting('prefix_suffix')) {
      $prefixes = isset($field_settings['prefix']) ? array_map([
        'Drupal\Core\Field\FieldFilteredMarkup',
        'create',
      ], explode('|', $field_settings['prefix'])) : [''];
      $suffixes = isset($field_settings['suffix']) ? array_map([
        'Drupal\Core\Field\FieldFilteredMarkup',
        'create',
      ], explode('|', $field_settings['suffix'])) : [''];
      $prefix = (count($prefixes) > 1) ? $this->formatPlural($value, $prefixes[0], $prefixes[1]) : $prefixes[0];
      $suffix = (count($suffixes) > 1) ? $this->formatPlural($value, $suffixes[0], $suffixes[1]) : $suffixes[0];
      $output = $prefix . $output . $suffix;
    }

    return $output;
  }

}
