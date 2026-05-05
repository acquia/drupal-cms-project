<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'custom_table' formatter.
 */
#[FieldFormatter(
  id: 'custom_table',
  label: new TranslatableMarkup('Table'),
  description: new TranslatableMarkup('Formats the custom field items as html table.'),
  field_types: [
    'custom',
  ],
  weight: 2,
)]
class CustomTableFormatter extends BaseFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'sort_by' => '_delta',
      'sort_order' => 'asc',
      'hide_empty' => FALSE,
      'hide_header' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();

    // Add sorting settings to summary.
    $sort_by = $this->getSetting('sort_by');
    $sort_order = $this->getSetting('sort_order');
    $sort_by_label = $this->t('@label', ['@label' => $sort_by === '_delta' ? 'Original order' : $sort_by]);
    $sort_order_label = $sort_order === 'asc' ? $this->t('Ascending') : $this->t('Descending');

    $summary[] = $this->t('Sort by: @sort_by (@order)', [
      '@sort_by' => $sort_by_label,
      '@order' => $sort_order_label,
    ]);
    $summary[] = $this->t('Hide columns with empty rows: @hide_empty', ['@hide_empty' => $this->getSetting('hide_empty') ? 'Yes' : 'No']);

    // Add header visibility to summary.
    $summary[] = $this->t('Hide table header: @hide_header', [
      '@hide_header' => $this->getSetting('hide_header') ? 'Yes' : 'No',
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $sortable = $this->getSortableFields();
    // Initialize sort_by options with weight option.
    $sort_by_options = [
      '_delta' => $this->t('Original order (by weight)'),
    ];

    $form['sort_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort by'),
      '#options' => $sort_by_options,
      '#default_value' => $this->getSetting('sort_by'),
      '#weight' => -10,
    ];

    $form['sort_order'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort order'),
      '#options' => [
        'asc' => $this->t('Ascending'),
        'desc' => $this->t('Descending'),
      ],
      '#default_value' => $this->getSetting('sort_order'),
      '#weight' => -9,
    ];

    $form['hide_empty'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide columns with empty rows'),
      '#default_value' => $this->getSetting('hide_empty'),
    ];

    // Hide table header.
    $form['hide_header'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide table header'),
      '#default_value' => $this->getSetting('hide_header'),
      '#description' => $this->t('If checked, the table header row will not be displayed.'),
    ];

    // Add available subfields to sort options and process field settings.
    foreach ($this->getCustomFieldItems() as $name => $custom_item) {
      // Add this subfield to the sort_by options.
      if (in_array($custom_item->getDataType(), $sortable)) {
        $form['sort_by']['#options'][$name] = $custom_item->getLabel();
      }
      // Remove non-applicable settings.
      unset($form['fields'][$name]['content']['formatter_settings']['label_display']);
      unset($form['fields'][$name]['content']['wrappers']['label_tag']);
      unset($form['fields'][$name]['content']['wrappers']['label_classes']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    if (!$items->isEmpty()) {
      $component = Html::cleanCssIdentifier($this->fieldDefinition->getName());
      $settings = $this->getSetting('fields') ?? [];
      $hide_empty = $this->getSetting('hide_empty') ?? FALSE;
      $hide_header = $this->getSetting('hide_header') ?? FALSE;
      $sort_by = $this->getSetting('sort_by') ?? '_delta';
      $sort_order = $this->getSetting('sort_order') ?? 'asc';
      $custom_items = $this->sortFields($settings);
      $sortable = $this->getSortableFields();

      // Track columns with non-empty values.
      $column_has_values = [];
      $header = [];
      $valid_columns = [];

      // Get the data type for the sort field if it's not delta.
      $sort_field_type = NULL;
      if ($sort_by !== '_delta' && isset($custom_items[$sort_by])) {
        $sort_field_type = $custom_items[$sort_by]->getDataType();
      }

      foreach ($custom_items as $name => $custom_item) {
        $setting = $settings[$name] ?? [];
        if (($setting['format_type'] ?? '') === 'hidden') {
          continue;
        }
        $formatter_settings = $setting['formatter_settings'] ?? [];
        $field_label = $formatter_settings['field_label'] ?? NULL;
        $header[$name] = $field_label ?: $custom_item->getLabel();
        $column_has_values[$name] = FALSE;
        $valid_columns[$name] = TRUE;
      }

      // Fallback: if the sort field is not sortable, use the original order.
      if ($sort_by !== '_delta' && ($sort_field_type === NULL || !in_array($sort_field_type, $sortable))) {
        // Fall back to delta sorting for excluded types.
        $sort_by = '_delta';
      }

      // Define which types should be compared numerically.
      $numeric_types = [
        'boolean',
        'daterange',
        'datetime',
        'decimal',
        'duration',
        'float',
        'integer',
        'time',
        'time_range',
      ];

      $is_numeric_sort = in_array($sort_field_type, $numeric_types);

      // Initialize rows and collect data for sorting.
      $rows_data = [];
      foreach ($items as $delta => $item) {
        $values = $this->getFormattedValues($item, $langcode);

        // Get the sort value for this row based on the data type.
        if ($sort_by === '_delta') {
          $sort_value = $delta;
        }
        elseif (isset($values[$sort_by])) {
          $raw_value = $item->get($sort_by)->getValue() ?? '';
          if ($is_numeric_sort) {
            $sort_value = $this->extractNumericSortValue($raw_value, $sort_field_type);
          }
          else {
            // Text-based: use displayed markup if available, fallback to raw.
            $sort_value = $values[$sort_by]['value']['#markup'] ?? (string) $raw_value;
          }
        }
        else {
          // Missing field, sort to end.
          $sort_value = NULL;
        }

        $row_data = [
          'delta' => $delta,
          'sort_value' => $sort_value,
          'class' => [$component . '__item'],
          'data' => [],
          'values' => $values,
        ];

        foreach ($custom_items as $name => $custom_item) {
          if (!isset($valid_columns[$name])) {
            continue;
          }
          $value = $values[$name] ?? NULL;
          $output = NULL;
          if ($value !== NULL) {
            $column_has_values[$name] = TRUE;
            $output = [
              '#theme' => 'custom_field_item',
              '#field_name' => $name,
              '#name' => $value['name'],
              '#value' => $value['value'],
              '#label' => $value['label'],
              '#label_display' => 'hidden',
              '#type' => $value['type'],
              '#wrappers' => $value['wrappers'],
              '#entity_type' => $value['entity_type'],
              '#lang_code' => $langcode,
            ];
          }
          $row_data['data'][$name] = [
            'data' => $output ?: '',
            'class' => [$component . '__' . Html::cleanCssIdentifier((string) $name)],
          ];
        }

        $rows_data[$delta] = $row_data;
      }

      // Sort only if needed.
      $needs_sorting = !($sort_by === '_delta' && $sort_order === 'asc');
      if ($needs_sorting) {
        uasort($rows_data, function ($a, $b) use ($sort_order, $is_numeric_sort) {
          $a_val = $a['sort_value'];
          $b_val = $b['sort_value'];

          // Nulls go last (consistent across asc/desc).
          if ($a_val === NULL) {
            return $b_val === NULL ? 0 : 1;
          }
          if ($b_val === NULL) {
            return -1;
          }

          if ($is_numeric_sort) {
            // All numeric types are converted to int/float/timestamp.
            $comparison = $a_val <=> $b_val;
          }
          else {
            $comparison = strnatcasecmp((string) $a_val, (string) $b_val);
          }

          return $sort_order === 'desc' ? -$comparison : $comparison;
        });
      }

      // Build final rows array from sorted data.
      $rows = [];
      foreach ($rows_data as $row_data) {
        $rows[] = [
          'class' => $row_data['class'],
          'data' => $row_data['data'],
        ];
      }

      // Filter headers and rows based on the table_empty setting.
      $filtered_header = [];
      $filtered_rows = [];
      foreach ($valid_columns as $name => $is_valid) {
        if (!$hide_empty || $column_has_values[$name]) {
          $filtered_header[] = $header[$name];
          foreach ($rows as $delta => $row) {
            $filtered_rows[$delta]['class'] = $row['class'];
            $filtered_rows[$delta]['data'][] = $row['data'][$name];
          }
        }
      }

      if (!empty($filtered_header)) {
        $elements[0] = [
          '#theme' => 'table',
          '#header' => $hide_header ? [] : $filtered_header,
          '#attributes' => [
            'class' => [$component],
          ],
          '#rows' => $filtered_rows,
        ];
      }
    }

    return $elements;
  }

  /**
   * Convert time string to seconds since midnight for sorting.
   *
   * @param string $time
   *   Time string in format 'H:i:s'.
   *
   * @return int
   *   Seconds since midnight.
   */
  protected function timeToSeconds(string $time): int {
    $parts = explode(':', $time);
    $hours = isset($parts[0]) ? (int) $parts[0] : 0;
    $minutes = isset($parts[1]) ? (int) $parts[1] : 0;
    $seconds = isset($parts[2]) ? (int) $parts[2] : 0;
    return $hours * 3600 + $minutes * 60 + $seconds;
  }

  /**
   * Get a list of field types that can be sorted.
   *
   * @return array
   *   An array of field types that can be sorted.
   */
  protected function getSortableFields(): array {
    return [
      'boolean',
      'daterange',
      'datetime',
      'decimal',
      'duration',
      'email',
      'float',
      'integer',
      'string',
      'telephone',
      'time',
      'time_range',
    ];
  }

  /**
   * Extract numeric sort value based on a field type.
   *
   * @param mixed $raw_value
   *   The raw value to extract from.
   * @param string $type
   *   The field type.
   *
   * @return float|int
   *   The extracted numeric value or 0 if the value is not numeric.
   */
  private function extractNumericSortValue(mixed $raw_value, string $type): float|int {
    if ($raw_value === '' || $raw_value === NULL) {
      return 0;
    }

    switch ($type) {
      case 'decimal':
      case 'float':
        if (is_numeric($raw_value)) {
          return (float) $raw_value;
        }
        $clean = preg_replace('/[^\d.-]/', '', (string) $raw_value);
        return is_numeric($clean) ? (float) $clean : 0.0;

      case 'integer':
      case 'boolean':
      case 'duration':
        if (is_numeric($raw_value)) {
          return (int) $raw_value;
        }
        $clean = preg_replace('/[^\d.-]/', '', (string) $raw_value);
        return is_numeric($clean) ? (int) $clean : 0;

      case 'datetime':
      case 'daterange':
        return !empty($raw_value) ? (strtotime($raw_value) ?: 0) : 0;

      case 'time':
      case 'time_range':
        return !empty($raw_value) ? ($this->timeToSeconds($raw_value) ?: 0) : 0;

      default:
        return 0;
    }
  }

}
