<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateRangeType;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeType;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeTypeInterface;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\custom_field\Trait\DateRangeTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base plugin class for daterange custom field widgets.
 */
class DateRangeWidgetBase extends DateTimeWidgetBase {

  use DateRangeTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'start_label' => 'Start date',
      'end_label' => 'End date',
      'year_range_end' => '1900:2050',
      'all_day_checkbox' => FALSE,
      'same_day_checkbox' => FALSE,
      'date_end_required' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widgetSettingsForm($form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();
    $element['year_range']['#title'] = $this->t('Year range start');
    $element['year_range_end'] = [
      '#type' => 'custom_field_date_year_range',
      '#title' => $this->t('Year range end'),
      '#default_value' => $settings['year_range_end'],
    ];
    $element['start_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Start date label'),
      '#default_value' => $settings['start_label'],
      '#description' => $this->t('The label for the start date field.'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $element['end_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('End date label'),
      '#default_value' => $settings['end_label'],
      '#description' => $this->t('The label for the end date field.'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $element['date_end_required'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require end date'),
      '#description' => $this->t('If checked, the end date field will be required if the start date is provided.'),
      '#default_value' => $settings['date_end_required'],
    ];
    if ($field->getDatetimeType() === DateTimeType::DATETIME_TYPE_DATETIME) {
      $element['all_day_checkbox'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('All day checkbox'),
        '#description' => $this->t('Provide a checkbox to make an event all day which will omit the time input elements.'),
        '#default_value' => $settings['all_day_checkbox'],
      ];
      $element['same_day_checkbox'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Same day checkbox'),
        '#description' => $this->t('Provide a checkbox to make an event same day which will simplify input of the end date.'),
        '#default_value' => $settings['same_day_checkbox'],
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $item = $items[$delta];
    $datetime_type = $field->getDatetimeType();
    $field_settings = $field->getFieldSettings();
    $settings = $this->getSettings() + static::defaultSettings();
    $duration_enabled = !empty($field_settings['duration_enabled']);
    $duration_options = $duration_enabled ? $field_settings['duration_options'] ?? [] : [];
    $start_date = $form_state->getValue([...$element['#field_parents'], 'value']) ?? $item->{$field->getName() . '__start_date'};
    $end_date = $form_state->getValue([...$element['#field_parents'], 'end_value']) ?? $item->{$field->getName() . '__end_date'};
    $all_day = $element['all_day']['#default_value'] ?? FALSE;
    $same_day = $element['same_day']['#default_value'] ?? FALSE;
    $duration_value = NULL;
    // Determine if we're showing seconds in the widget.
    $show_seconds = !empty($field_settings['seconds_enabled']);
    $date_element = $datetime_type === DateTimeType::DATETIME_TYPE_DATETIME ? 'datetime-local' : DateTimeType::DATETIME_TYPE_DATE;
    $format = $datetime_type === DateTimeType::DATETIME_TYPE_DATETIME ? DateTimeTypeInterface::DATETIME_STORAGE_FORMAT : DateTimeTypeInterface::DATE_STORAGE_FORMAT;
    $time_format = $show_seconds ? 'H:i:s' : 'H:i';
    $values = $form_state->getValues();

    $wrapper_id = $this->getUniqueElementId($form, $items->getName(), $delta, $field->getName());
    $element['#prefix'] = '<div id="' . $wrapper_id . '">';
    $element['#suffix'] = '</div>';
    $element['#theme'] = 'custom_field_daterange';
    $element['#theme_wrappers'] = ['fieldset', 'container'];
    $element['#element_validate'][] = [$this, 'validateStartEnd'];

    if ($datetime_type === DateTimeType::DATETIME_TYPE_DATETIME) {
      if ($settings['all_day_checkbox']) {
        $all_day = NestedArray::getValue($values, [...$element['#field_parents'], 'all_day']) ?? $this->isAllDay($start_date, $end_date);
      }
      if ($settings['same_day_checkbox']) {
        $same_day = NestedArray::getValue($values, [...$element['#field_parents'], 'same_day']) ?? !$all_day && $this->isSameDay($start_date, $end_date);
      }
      if ($settings['all_day_checkbox']) {
        $element['all_day'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('All day'),
          '#ajax' => [
            'callback' => [$this, 'ajaxUpdateDaterange'],
            'wrapper' => $wrapper_id,
          ],
          '#default_value' => $all_day,
          '#access' => !$same_day,
        ];
      }
      if ($settings['same_day_checkbox']) {
        $element['same_day'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Same day'),
          '#ajax' => [
            'callback' => [$this, 'ajaxUpdateDaterange'],
            'wrapper' => $wrapper_id,
          ],
          '#default_value' => $same_day,
          '#access' => !$all_day,
        ];
      }
      if ($all_day) {
        $format = DateTimeTypeInterface::DATE_STORAGE_FORMAT;
        // The timezone element is irrelevant for all-day date ranges.
        if (isset($element['timezone'])) {
          $element['timezone']['#access'] = FALSE;
        }
      }
      $user_timezone = new \DateTimeZone(date_default_timezone_get());
      $trigger = $form_state->getTriggeringElement();
      $triggers = ['all_day', 'same_day'];
      if ($trigger && in_array(end($trigger['#parents']), $triggers)) {
        $active_trigger = end($trigger['#parents']);
        $user_input = $form_state->getUserInput();
        $change_input = FALSE;
        if ($start_date instanceof DrupalDateTime) {
          $start_date->setTimezone($user_timezone);
          if ($active_trigger === 'all_day') {
            // Reset the time to work with the datetime-local element.
            if (!$all_day && $date_element === 'datetime-local') {
              $start_date->setTime(0, 0);
            }
            NestedArray::setValue($user_input, [
              ...$element['#field_parents'],
              'value',
              'date',
            ], $start_date->format($format));
            if ($end_date instanceof DrupalDateTime) {
              /* @phpstan-ignore-next-line */
              $end_date->setTimeZone($user_timezone);
              // Reset the time to work with the datetime-local element.
              if (!$all_day && $date_element === 'datetime-local') {
                $end_date->setTime(23, 59);
              }
              NestedArray::setValue($user_input, [
                ...$element['#field_parents'],
                'end_value',
                'date',
              ], $end_date->format($format));
            }
            $change_input = TRUE;
          }
          if ($active_trigger === 'same_day') {
            if ($same_day) {
              NestedArray::setValue($user_input, [...$element['#field_parents'], 'end_value', 'date'], $start_date->format('Y-m-d'));
              NestedArray::setValue($user_input, [...$element['#field_parents'], 'end_value', 'time'], NULL);
              $change_input = TRUE;
            }
          }
        }
        if ($change_input) {
          $form_state->setUserInput($user_input);
        }
      }
    }

    if (!empty($duration_options)) {
      $duration_value = NestedArray::getValue($values, [...$element['#field_parents'], 'duration']) ?? $item->{$field->getName() . '__duration'};
      // Calculate the duration from default value dates if available.
      if (empty($duration_value) && $start_date instanceof DrupalDateTime && $end_date instanceof DrupalDateTime) {
        $duration_calc = $end_date->getTimestamp() - $start_date->getTimestamp();
        if ($duration_calc > 0) {
          $duration_value = $duration_calc;
        }
      }
      $options = [];
      foreach ($duration_options as $option) {
        $options[$option['key']] = $option['label'];
      }
      if (empty($duration_value) || !array_key_exists($duration_value, $options)) {
        $duration_value = 'custom';
      }
      $options['custom'] = $this->t('Custom');
      $element['duration'] = [
        '#type' => 'select',
        '#title' => $this->t('Duration'),
        '#options' => $options,
        '#default_value' => array_key_exists($duration_value, $options) ? $duration_value : 'custom',
        '#ajax' => [
          'callback' => [$this, 'ajaxUpdateDaterange'],
          'wrapper' => $wrapper_id,
        ],
        '#weight' => -9,
        '#access' => !$all_day && !$same_day,
      ];
    }

    $element['value']['#title'] = $this->t('@label', ['@label' => $settings['start_label']]);
    $element['value']['#description'] = NULL;
    $element['value']['#type'] = 'custom_field_datetime_date';
    $element['value']['#timezone_element'] = FALSE;
    $element['value'] += [
      '#date_date_element' => $all_day ? 'date' : $date_element,
      '#date_date_format' => $format,
      '#date_time_format' => '',
      '#date_date_callbacks' => [],
      '#date_time_element' => 'none',
    ];
    if ($all_day) {
      $element['value']['#date_timezone'] = DateTimeTypeInterface::STORAGE_TIMEZONE;
    }

    $element['end_value'] = [
      '#title' => $this->t('@label', ['@label' => $settings['end_label']]),
      '#default_value' => NULL,
      '#date_year_range' => $this->getSetting('year_range_end'),
      '#access' => !$duration_enabled || $duration_value === 'custom' || $all_day || $same_day,
    ] + $element['value'];

    if ($same_day) {
      $element['end_value']['#date_date_element'] = 'date';
      $element['end_value']['#date_date_format'] = DateTimeTypeInterface::DATE_STORAGE_FORMAT;
      $element['end_value']['#date_time_element'] = 'time';
      $element['end_value']['#date_time_format'] = $time_format;
      $element['end_value']['#same_day'] = TRUE;
    }

    if ($all_day && $start_date instanceof DrupalDateTime && $end_date instanceof DrupalDateTime) {
      $start = clone $start_date;
      $end = clone $end_date;
      $start->setTimezone(timezone_open('UTC'));
      $end->setTimezone(timezone_open('UTC'));
      $start->setTime(0, 0);
      $diff = $start->diff($end);
      $start_date_str = $start->format('Y-m-d');
      $end_date_str = $end->format('Y-m-d');
      $days = floor(($end->getTimestamp() - $start->getTimestamp()) / 86400);
      if ($start_date_str === $end_date_str) {
        $days = 0;
      }
      elseif ($diff->h === 23 && $diff->i === 59) {
        $days = max(1, $days - 1);
      }
      $end = clone $start;
      if ($days >= 0) {
        $end->add(new \DateInterval("P{$days}D"));
      }

      $element['value']['#default_value'] = $start;
      $element['end_value']['#default_value'] = $end;
    }
    if (!$all_day && $start_date instanceof DrupalDateTime) {
      $timezone = (string) $element['value']['#date_timezone'];
      $element['value']['#default_value'] = $this->createDefaultValue($start_date, $timezone, $datetime_type, $show_seconds);
    }
    if (!$all_day && $end_date instanceof DrupalDateTime) {
      $timezone = (string) $element['end_value']['#date_timezone'];
      if ((!$duration_enabled || $duration_value === 'custom' || $same_day)) {
        $element['end_value']['#default_value'] = $this->createDefaultValue($end_date, $timezone, $datetime_type, $show_seconds);
      }
    }

    if (isset($element['timezone'])) {
      $element['timezone']['#weight'] = 10;
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValue(mixed $value, $column): mixed {
    // The widget form element type has transformed the value to a
    // DrupalDateTime object at this point. We need to convert it back to the
    // storage timezone and format.
    $datetime_type = $column['datetime_type'];
    $storage_format = $datetime_type === 'date' ? DateTimeTypeInterface::DATE_STORAGE_FORMAT : DateTimeTypeInterface::DATETIME_STORAGE_FORMAT;
    $storage_timezone = timezone_open(DateTimeTypeInterface::STORAGE_TIMEZONE);
    $user_timezone = timezone_open(date_default_timezone_get());
    $all_day_checkbox = $this->getSetting('all_day_checkbox');

    if (!is_array($value)) {
      return NULL;
    }

    $start_date = $value['value'] ?? NULL;
    $end_date = $value['end_value'] ?? NULL;
    $all_day = $value['all_day'] ?? FALSE;
    $same_day = $value['same_day'] ?? FALSE;
    $duration = $value['duration'] ?? NULL;

    if (!$start_date instanceof DrupalDateTime) {
      return NULL;
    }

    if ($all_day_checkbox && !$all_day && $end_date instanceof DrupalDateTime && $datetime_type === DateTimeType::DATETIME_TYPE_DATETIME) {
      $start = clone $start_date;
      $end = clone $end_date;
      $start->setTimezone($user_timezone);
      $end->setTimezone($user_timezone);
      $diff = $start->diff($end);
      if ($diff->h === 23 && $diff->i === 59) {
        $days = floor(($start->getTimestamp() - $end->getTimestamp()) / 86400);
        $start_date = DrupalDateTime::createFromFormat(
          $storage_format,
          $start_date->format(DateTimeTypeInterface::DATE_STORAGE_FORMAT) . 'T00:00:00',
          $storage_timezone
        );
        $end_date = DrupalDateTime::createFromFormat(
          $storage_format,
          $end_date->format(DateTimeTypeInterface::DATE_STORAGE_FORMAT) . 'T23:59:59',
          $storage_timezone
        );
        if ($days > 0) {
          $end_date->add(new \DateInterval("P{$days}D"));
        }
      }
    }
    if ($datetime_type === DateRangeType::DATETIME_TYPE_ALLDAY || $all_day) {
      $user_timezone = $storage_timezone;
      // All day fields start at midnight on the starting date, but are
      // stored like datetime fields, so we need to adjust the time.
      // This function is called twice, so to prevent a double conversion,
      // we need to explicitly set the timezone.
      /* @phpstan-ignore-next-line */
      $start_date->setTimeZone($user_timezone)->setTime(0, 0);
      if ($end_date instanceof DrupalDateTime) {
        /* @phpstan-ignore-next-line */
        $end_date->setTimeZone($user_timezone)->setTime(23, 59, 59);
      }
    }
    elseif (!$same_day && is_numeric($duration) && $duration > 0) {
      $end_date = clone $start_date;
      try {
        $interval = new \DateInterval('PT' . $duration . 'S');
        $end_date->add($interval);
      }
      catch (\Exception $e) {
        $end_date = NULL;
      }
    }

    // Adjust the date for storage.
    $value['value'] = $start_date->setTimezone($storage_timezone)->format($storage_format);
    if ($end_date instanceof DrupalDateTime) {
      $value['end_value'] = $end_date->setTimezone($storage_timezone)->format($storage_format);
    }

    return $value;
  }

  /**
   * An #element_validate callback to ensure the start date <= the end date.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public function validateStartEnd(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    // Only validate if the start and end date fields are present.
    $require_end_date = $this->getSetting('date_end_required');
    if (!isset($element['end_value']) || !$element['end_value']['#access']) {
      return;
    }

    $start_date = $element['value']['#value']['object'];
    $end_date = $element['end_value']['#value']['object'];
    $same_day = $element['same_day']['#value'] ?? FALSE;
    $all_day = $element['all_day']['#value'] ?? FALSE;

    // Validate the end date is after the start date.
    if ($start_date instanceof DrupalDateTime && $end_date instanceof DrupalDateTime) {
      $start_timestamp = $start_date->getTimestamp();
      $end_timestamp = $end_date->getTimestamp();
      if (!$all_day) {
        if ($start_timestamp >= $end_timestamp) {
          if ($same_day) {
            $error = $this->t('The @title end time must be after the start time', ['@title' => $element['#title']]);
          }
          else {
            $error = $this->t('The @title end date must be after the start date', ['@title' => $element['#title']]);
          }
          $form_state->setError($element['end_value'], $error);
        }
      }
      elseif ($start_timestamp !== $end_timestamp) {
        $interval = $start_date->diff($end_date);
        if ($interval->invert === 1) {
          $form_state->setError($element['end_value'], $this->t('The @title end date cannot be before the start date', ['@title' => $element['#title']]));
        }
      }
    }
    // Validate the end date is required.
    elseif ($start_date instanceof DrupalDateTime && $require_end_date && !$end_date instanceof DrupalDateTime) {
      $form_state->setError($element['end_value'], $this->t('The @title end date is required.', ['@title' => $element['#title']]));
    }
    // Validate the end date requires a start date.
    elseif ($end_date instanceof DrupalDateTime && !$start_date instanceof DrupalDateTime) {
      $form_state->setError($element['value'], $this->t('The @title cannot have an end date with no start date', ['@title' => $element['#title']]));
    }
  }

  /**
   * Ajax callback to trigger daterange changes.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function ajaxUpdateDaterange(array &$form, FormStateInterface $form_state): AjaxResponse {
    $triggering_element = $form_state->getTriggeringElement();
    $value = $triggering_element['#value'];
    $wrapper_id = $triggering_element['#ajax']['wrapper'];
    $array_parents = $triggering_element['#array_parents'];
    $key = end($array_parents);
    $form_state_keys = array_slice($array_parents, 0, -1);
    $updated_element = NestedArray::getValue($form, $form_state_keys);
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#' . $wrapper_id, $updated_element));
    if ($key === 'duration' && $value === 'custom' || $key === 'all_day' && $value === TRUE) {
      $focus_input = $updated_element['end_value']['date']['#name'];
      $response->addCommand(new InvokeCommand(':input[name="' . $focus_input . '"]', 'focus'));
    }

    return $response;
  }

}
