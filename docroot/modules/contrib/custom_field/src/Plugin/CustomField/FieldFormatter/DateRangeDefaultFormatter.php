<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Enum\DateTimeRangeDisplayOptions;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateRangeType;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeType;
use Drupal\custom_field\Trait\DateRangeTrait;

/**
 * Plugin implementation of the 'Default' formatter for 'daterange' fields.
 *
 * This formatter renders the data range using <time> elements, with
 * configurable date formats (from the list of configured formats) and a
 * separator.
 */
#[FieldFormatter(
  id: 'daterange_default',
  label: new TranslatableMarkup('Default'),
  field_types: [
    'daterange',
  ],
)]
class DateRangeDefaultFormatter extends DateTimeAdvancedFormatter {

  use DateRangeTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'from_to' => DateTimeRangeDisplayOptions::Both->value,
      'separator' => ' - ',
      'end_date_fallback_text' => 'TBD',
      'all_day_label' => 'All day',
      'all_day_separator' => ' | ',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);
    $parents = $form['#field_parents'];
    $visibility_path = $form['#visibility_path'];
    $datetime_type = $this->customFieldDefinition->getDatetimeType();
    $elements['from_to'] = [
      '#type' => 'select',
      '#title' => $this->t('Display'),
      '#options' => $this->getFromToOptions(),
      '#default_value' => $this->getSetting('from_to'),
    ];
    $elements['separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Date separator'),
      '#description' => $this->t('The string to separate the start and end dates'),
      '#default_value' => $this->getSetting('separator'),
      '#states' => [
        'visible' => [
          'select[name="' . $visibility_path . '[from_to]"]' => ['value' => DateTimeRangeDisplayOptions::Both->value],
        ],
      ],
    ];
    $elements['end_date_fallback_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('End date fallback text'),
      '#description' => $this->t('Optional text to display when the end date is empty. Leave blank to only show start date.'),
      '#default_value' => $this->getSetting('end_date_fallback_text'),
      '#states' => [
        'visible' => [
          'select[name="' . $visibility_path . '[from_to]"]' => ['value' => DateTimeRangeDisplayOptions::Both->value],
        ],
      ],
    ];
    if (in_array($datetime_type, [DateTimeType::DATETIME_TYPE_DATETIME, DateRangeType::DATETIME_TYPE_ALLDAY])) {
      $elements['all_day'] = [
        '#type' => 'details',
        '#title' => $this->t('All day settings'),
        '#open' => FALSE,
        '#states' => [
          'visible' => [
            'select[name="' . $visibility_path . '[from_to]"]' => ['value' => DateTimeRangeDisplayOptions::Both->value],
          ],
        ],
      ];
      $elements['all_day']['all_day_label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('All day label'),
        '#description' => $this->t('The string to output when date range has been set to run all day. Leave blank to only show the dates.'),
        '#default_value' => $this->getSetting('all_day_label'),
        '#parents' => [...$parents, 'all_day_label'],
      ];
      $elements['all_day']['all_day_separator'] = [
        '#type' => 'textfield',
        '#title' => $this->t('All day separator'),
        '#description' => $this->t('The string to separate the <em>All day label</em> from the dates.'),
        '#default_value' => $this->getSetting('all_day_separator'),
        '#parents' => [...$parents, 'all_day_separator'],
      ];
    }

    return $elements;
  }

  /**
   * Returns a list of possible values for the 'from_to' setting.
   *
   * @return array
   *   A list of 'from_to' options.
   */
  protected function getFromToOptions(): array {
    return [
      DateTimeRangeDisplayOptions::Both->value => $this->t('Display both start and end dates'),
      DateTimeRangeDisplayOptions::StartDate->value => $this->t('Display start date only'),
      DateTimeRangeDisplayOptions::EndDate->value => $this->t('Display end date only'),
    ];
  }

  /**
   * Gets whether the start date should be displayed.
   *
   * @return bool
   *   True if the start date should be displayed. False otherwise.
   */
  protected function startDateIsDisplayed(): bool {
    switch ($this->getSetting('from_to')) {
      case DateTimeRangeDisplayOptions::Both->value:
      case DateTimeRangeDisplayOptions::StartDate->value:
        return TRUE;
    }

    return FALSE;
  }

  /**
   * Gets whether the end date should be displayed.
   *
   * @return bool
   *   True if the end date should be displayed. False otherwise.
   */
  protected function endDateIsDisplayed(): bool {
    switch ($this->getSetting('from_to')) {
      case DateTimeRangeDisplayOptions::Both->value:
      case DateTimeRangeDisplayOptions::EndDate->value:
        return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): ?array {
    $separator = $this->getSetting('separator');
    $start_date = $value['start_date'];
    $end_date = $value['end_date'];
    if (!$start_date instanceof DrupalDateTime) {
      return NULL;
    }

    $end_date_fallback = $this->getSetting('end_date_fallback_text');
    $timezone = $this->getSetting('timezone_stored') && !empty($value['timezone']) ? $value['timezone'] : NULL;
    if ($this->getSetting('timezone_override')) {
      $timezone = $this->getSetting('timezone_override');
    }
    $is_all_day = FALSE;

    if ($end_date instanceof DrupalDateTime && ($start_date->getTimestamp() !== $end_date->getTimestamp())) {
      $is_all_day = $this->isAllDay($start_date, $end_date);
      $render = $this->renderStartEndWithIsoAttribute($start_date, $separator, $end_date, $timezone);
      $default = $this->renderStartEndWithIsoAttribute($start_date, $separator, $end_date);
    }
    else {
      $render = $this->buildDateWithIsoAttribute($start_date, TRUE, $timezone);
      $default = $this->buildDateWithIsoAttribute($start_date);
      $both_displayed = $this->startDateIsDisplayed() && $this->endDateIsDisplayed();
      // Render the fallback text if applicable.
      if (!$end_date instanceof DrupalDateTime && $both_displayed && !empty($end_date_fallback)) {
        $fallback_markup = [
          '#type' => 'markup',
          '#markup' => ' ' . $end_date_fallback,
        ];
        $render = [
          DateTimeRangeDisplayOptions::StartDate->value => $render,
          'separator' => ['#markup' => $separator],
          DateTimeRangeDisplayOptions::EndDate->value => $fallback_markup,
        ];
        $default = [
          DateTimeRangeDisplayOptions::StartDate->value => $default,
          'separator' => ['#markup' => $separator],
          DateTimeRangeDisplayOptions::EndDate->value => $fallback_markup,
        ];
      }
    }

    if (!$is_all_day && $this->getSetting('user_timezone') && !empty($timezone) && $timezone !== date_default_timezone_get()) {
      return [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#items' => [
          $render,
          $default,
        ],
      ];
    }

    return $render;
  }

  /**
   * Creates a render array with ISO attributes given start/end dates.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $start_date
   *   The start date to be rendered.
   * @param string $separator
   *   The separator string.
   * @param \Drupal\Core\Datetime\DrupalDateTime $end_date
   *   The end date to be rendered.
   * @param string|null $timezone
   *   The stored timezone.
   *
   * @return array
   *   A renderable array for a single date time range.
   */
  protected function renderStartEndWithIsoAttribute(DrupalDateTime $start_date, string $separator, DrupalDateTime $end_date, ?string $timezone = NULL): array {
    $element = [];
    $datetime_type = $this->customFieldDefinition->getDatetimeType();
    $all_day_format = '';
    $time_format = '';
    $date_format_parts = $this->getFilteredDateParts();
    $time_format_parts = $this->getFilteredTimeParts();
    $ampm_format = $time_format_parts['am_pm']['format'] ?? NULL;

    // Build format for date.
    if (!empty($date_format_parts)) {
      foreach ($date_format_parts as $value) {
        $all_day_format .= $value['format'];
        if ($value['suffix'] != '') {
          $all_day_format .= PlainTextOutput::renderFromHtml((string) $value['suffix']);
        }
      }
    }

    // Build format for time.
    if (!empty($time_format_parts)) {
      foreach ($time_format_parts as $value) {
        $time_format .= $value['format'];
        if ($value['suffix'] != '') {
          $time_format .= PlainTextOutput::renderFromHtml((string) $value['suffix']);
        }
      }
    }

    $is_all_day = $this->startDateIsDisplayed() && $this->endDateIsDisplayed() && $this->isAllDay($start_date, $end_date);
    if ($is_all_day) {
      $timezone = 'UTC';
      $start_date->setTimezone(timezone_open('UTC'));
      $end_date->setTimezone(timezone_open('UTC'));
    }
    if ($this->startDateIsDisplayed()) {
      $element[DateTimeRangeDisplayOptions::StartDate->value] = $this->buildDateWithIsoAttribute($start_date, FALSE, $timezone);
    }
    if ($this->startDateIsDisplayed() && $this->endDateIsDisplayed()) {
      $element['separator'] = [
        '#markup' => $separator,
      ];
    }
    if ($this->endDateIsDisplayed()) {
      $element[DateTimeRangeDisplayOptions::EndDate->value] = $this->buildDateWithIsoAttribute($end_date, FALSE, $timezone);
      if ($this->startDateIsDisplayed()) {
        if ($is_all_day) {
          $all_day_label = $this->getSetting('all_day_label') ?? '';
          $all_day_separator = $this->getSetting('all_day_separator') ?? '';
          // Format the start date without time.
          $element[DateTimeRangeDisplayOptions::StartDate->value]['#text'] = $this->dateFormatter->format($start_date->getTimestamp(), 'custom', $all_day_format, $timezone);
          $end_date_text = $this->dateFormatter->format($end_date->getTimestamp(), 'custom', $all_day_format, $timezone);
          if (!empty($all_day_label)) {
            if (!empty($all_day_separator)) {
              // Account for possible html entities as separator.
              $all_day_separator = PlainTextOutput::renderFromHtml((string) $all_day_separator);
            }
            if ($this->isSameDay($start_date, $end_date, $timezone)) {
              $element[DateTimeRangeDisplayOptions::StartDate->value]['#text'] .= $all_day_separator;
              // Replace visual end date with the all day label.
              $end_date_text = $all_day_label;
              // The separator between dates is irrelevant.
              $element['separator'] = NULL;
            }
            else {
              // Append visual end date with the all day label.
              $end_date_text .= $all_day_separator . $all_day_label;
            }
          }
          $element[DateTimeRangeDisplayOptions::EndDate->value]['#text'] = $end_date_text;
        }
        elseif ($this->isSameDay($start_date, $end_date, $timezone)) {
          if (!empty($time_format)) {
            if (!empty($ampm_format)) {
              $start_ampm = $start_date->format($ampm_format);
              $end_ampm = $end_date->format($ampm_format);
              // Simplify the start date am/pm part if same as end date.
              if ($start_ampm === $end_ampm) {
                $start_date_text = $element[DateTimeRangeDisplayOptions::StartDate->value]['#text'];
                // Strip out redundant string from start date.
                $element[DateTimeRangeDisplayOptions::StartDate->value]['#text'] = str_replace($start_ampm, '', $start_date_text);
              }
            }
            // Display just the time for the end date.
            $element[DateTimeRangeDisplayOptions::EndDate->value]['#text'] = $end_date->format($time_format);
          }
        }
      }
    }

    // Append timezone string if applicable.
    if (!$is_all_day && $datetime_type === DateTimeType::DATETIME_TYPE_DATETIME) {
      if ($this->getSetting('display_timezone')) {
        end($element);
        $last_key = key($element);
        $timezone_date = $this->startDateIsDisplayed() ? $start_date : $end_date;
        $element[(string) $last_key]['#suffix'] = ' ' . $this->formatTimezoneDisplay($timezone_date);
      }
    }

    return $element;
  }

}
