<?php

declare(strict_types=1);

namespace Drupal\custom_field\Element;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\Element\Datetime;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a base class for custom field date elements.
 */
abstract class DatetimeBase extends Datetime {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $info = parent::getInfo();
    $info['#theme_wrappers'] = [];
    $info['#theme'] = NULL;
    $info['#timezone_element'] = FALSE;
    $info['#show_seconds'] = TRUE;
    $info['#field_parents'] = [];

    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    $element += ['#date_timezone' => date_default_timezone_get()];
    if ($input !== FALSE) {
      if ($element['#date_date_element'] === 'datetime-local' && !empty($input['date'])) {
        // With a datetime-local input, the date value is always normalized to
        // the format Y-m-d\TH:i.
        // @see https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/datetime-local
        // 'html_datetime' is not a valid format to pass to
        // DrupalDateTime::createFromFormat()
        $date_parts = explode('T', $input['date']);
        $date_input = $date_parts[0];
        $time_input = $date_parts[1] ?? NULL;
        $date_format = DateFormat::load('html_date')->getPattern();
        $time_format = DateFormat::load('html_time')->getPattern();
      }
      else {
        $date_format = $element['#date_date_format'] != 'none' ? static::getHtml5DateFormat($element) : '';
        $time_format = $element['#date_time_element'] != 'none' ? static::getHtml5TimeFormat($element) : '';
        $same_day = $form_state->getValue([...$element['#field_parents'], 'same_day']);
        if ($input instanceof DrupalDateTime) {
          $values = [
            'date' => $input->format($date_format),
            'time' => $input->format($time_format),
          ];
          $input = $values;
        }
        // Modify input for same-day checkbox.
        elseif ($same_day && end($element['#parents']) === 'end_value') {
          $start_date = $form_state->getValue([...$element['#field_parents'], 'value']);
          if (empty($start_date['date'])) {
            $input['time'] = '';
          }
          else {
            $input['date'] = $start_date['date'];
          }
        }

        $date_input = $element['#date_date_element'] != 'none' && !empty($input['date']) ? $input['date'] : '';
        $time_input = $element['#date_time_element'] != 'none' && !empty($input['time']) ? $input['time'] : '';
      }

      // Seconds will be omitted in a post in case there's no entry.
      if (!empty($time_input) && strlen($time_input) == 5) {
        $time_input .= ':00';
      }

      try {
        $date_time_format = trim($date_format . ' ' . $time_format);
        $date_time_input = trim($date_input . ' ' . $time_input);
        $date = DrupalDateTime::createFromFormat($date_time_format, $date_time_input, $element['#date_timezone']);
      }
      catch (\Exception) {
        $date = NULL;
      }
      $input = [
        'date'   => $date_input,
        'time'   => $time_input,
        'object' => $date,
      ];
    }
    else {
      $date = $element['#default_value'] ?? NULL;
      if ($date instanceof DrupalDateTime && !$date->hasErrors()) {
        $date->setTimezone(new \DateTimeZone($element['#date_timezone']));
        $input = [
          'date'   => $date->format($element['#date_date_format']),
          'time'   => $date->format($element['#date_time_format']),
          'object' => $date,
        ];
      }
      else {
        $input = [
          'date'   => '',
          'time'   => '',
          'object' => NULL,
        ];
      }
    }

    return $input;
  }

  /**
   * {@inheritdoc}
   */
  protected static function getHtml5DateFormat($element) {
    switch ($element['#date_date_element']) {
      case 'date':
        return DateFormat::load('html_date')->getPattern();

      case 'datetime':
        return DateFormat::load('html_datetime')->getPattern();

      case 'datetime-local':
        return 'Y-m-d\TH:i';

      default:
        return $element['#date_date_format'];
    }
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The processed element.
   */
  public static function processDatetime(&$element, FormStateInterface $form_state, &$complete_form): array {
    $element = parent::processDatetime($element, $form_state, $complete_form);
    $show_seconds = $element['#show_seconds'] ?? FALSE;
    if ($element['#date_date_element'] === 'datetime-local') {
      if ($show_seconds) {
        $element['date']['#attributes']['step'] = 1;
        $format = 'm/d/Y, h:i:s A';
      }
      else {
        $element['date']['#attributes']['step'] = 60;
        $format = 'm/d/Y, h:i A';
      }
      $element['date']['#attributes']['title'] = t('Enter a valid date and time - e.g. @format', [
        '@format' => (new \DateTime())->format($format),
      ]);
    }
    elseif ($element['#date_time_element'] !== 'none') {
      if ($show_seconds) {
        $element['time']['#attributes']['step'] = 1;
        $format = 'h:i:s A';
      }
      else {
        $element['time']['#attributes']['step'] = 60;
        $format = 'h:i A';
        // Remove the seconds from the time element.
        if (!empty($element['time']['#value'])) {
          $parts = explode(':', $element['time']['#value']);
          $parts = array_splice($parts, 0, 2);
          $element['time']['#value'] = implode(':', $parts);
        }
      }
      $element['time']['#attributes']['title'] = t('Enter a valid time - e.g. @format', [
        '@format' => (new \DateTime())->format($format),
      ]);
    }

    if ($element['#date_date_element'] != 'none') {
      // The value callback has populated the #value array.
      $same_day = $element['#same_day'] ?? FALSE;
      if ($same_day) {
        $element['date']['#type'] = 'hidden';
        $element['time']['#title'] = $element['#title'];
        $element['time']['#title_display'] = 'before';
      }
      $date = !empty($element['#value']['object']) ? $element['#value']['object'] : NULL;
      if (!$date instanceof DrupalDateTime) {
        $format_settings = [];
        $date_format = $element['#date_date_element'] != 'none' ? static::getHtml5DateFormat($element) : '';
        // With a datetime-local input, the date value is always normalized to
        // the format Y-m-d\TH:i.
        // @see https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/datetime-local
        // 'html_datetime' returned by static::getHtml5DateFormat($element) is
        // not a valid format.
        // @see https://www.drupal.org/project/drupal/issues/3505318
        if ($element['#date_date_element'] === 'datetime-local') {
          $date_format = DateFormat::load('html_date')->getPattern() . '\T' . DateFormat::load('html_time')->getPattern();
        }
        $range = static::datetimeRangeYears($element['#date_year_range']);
        $html5_min = DrupalDateTime::createFromFormat(DrupalDateTime::FORMAT, $range[0] . '-01-01 00:00:00');
        $html5_max = DrupalDateTime::createFromFormat(DrupalDateTime::FORMAT, $range[1] . '-12-31 23:59:59');
        $element['date']['#attributes']['min'] = $html5_min->format($date_format, $format_settings);
        $element['date']['#attributes']['max'] = $html5_max->format($date_format, $format_settings);
      }
    }

    return $element;
  }

}
