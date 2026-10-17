<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Time;

/**
 * Plugin implementation of the 'time_range_default' formatter.
 */
#[FieldFormatter(
  id: 'time_range_default',
  label: new TranslatableMarkup('Default'),
  field_types: [
    'time_range',
  ],
)]
class TimeRangeFormatter extends TimeAdvancedFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'separator' => ' - ',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Time separator'),
      '#description' => $this->t('The string to separate the start and end times'),
      '#default_value' => $this->getSetting('separator'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): array {
    $start_time = Time::createFromTimestamp($value['start']);
    $end_time = Time::createFromTimestamp($value['end']);
    $separator = $this->getSetting('separator');
    $time_format_parts = $this->getFilteredTimeParts();
    $ampm_format = $time_format_parts['am_pm']['format'] ?? NULL;
    $aria_label_format = "g:i A";

    $element['start'] = $this->buildTimeWithIsoAttribute($start_time);
    $element['start']['#attributes']['aria-label'] = $this->t('Start time @time', ['@time' => $start_time->format($aria_label_format)]);
    if ($end_time) {
      $element['separator'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $separator,
        '#attributes' => [
          'aria-label' => 'to',
        ],
      ];
      if (!empty($ampm_format)) {
        $start_ampm = $start_time->format($ampm_format);
        $end_ampm = $end_time->format($ampm_format);
        if ($start_ampm === $end_ampm) {
          // Strip out redundant string from start time.
          $start_text = $element['start']['#text'];
          $element['start']['#text'] = str_replace($start_ampm, '', $start_text);
        }
      }
      $element['end'] = $this->buildTimeWithIsoAttribute($end_time);
      $element['end']['#attributes']['aria-label'] = $this->t('End time @time', ['@time' => $end_time->format($aria_label_format)]);
    }

    return $element;
  }

}
