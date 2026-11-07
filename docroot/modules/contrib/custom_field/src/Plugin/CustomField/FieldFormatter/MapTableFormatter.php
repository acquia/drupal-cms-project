<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Plugin\CustomFieldFormatterBase;

/**
 * Plugin implementation of the 'map_table' formatter.
 */
#[FieldFormatter(
  id: 'map_table',
  label: new TranslatableMarkup('Table'),
  field_types: [
    'map',
  ],
)]
class MapTableFormatter extends CustomFieldFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'key_label' => 'Key',
      'value_label' => 'Value',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements['key_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Key label'),
      '#description' => $this->t('The table header label for key column'),
      '#default_value' => $this->getSetting('key_label'),
      '#maxlength' => 128,
    ];
    $elements['value_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value label'),
      '#description' => $this->t('The table header label for value column'),
      '#default_value' => $this->getSetting('value_label'),
      '#maxlength' => 128,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): ?array {
    if (!is_array($value) || empty($value)) {
      return NULL;
    }

    $rows = [];
    foreach ($value as $mapping) {
      $rows[] = [
        $mapping['key'],
        $mapping['value'],
      ];
    }
    return [
      '#type' => 'table',
      '#header' => [
        'key' => (string) $this->getSetting('key_label'),
        'value' => (string) $this->getSetting('value_label'),
      ],
      '#rows' => $rows,
    ];
  }

}
