<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldWidget;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\custom_field\Plugin\CustomFieldWidgetBase;
use Drupal\custom_field\Time;

/**
 * Plugin implementation of the 'time_range' custom field widget.
 */
#[CustomFieldWidget(
  id: 'time_range',
  label: new TranslatableMarkup('Time range'),
  category: new TranslatableMarkup('Time'),
  field_types: [
    'time_range',
  ],
)]
class TimeRangeWidget extends CustomFieldWidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'start_label' => 'Start time',
      'end_label' => 'End time',
      'time_end_required' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widgetSettingsForm($form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();

    $element['start_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Start time label'),
      '#default_value' => $settings['start_label'],
      '#description' => $this->t('The label for the start time field.'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $element['end_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('End time label'),
      '#default_value' => $settings['end_label'],
      '#description' => $this->t('The label for the end time field.'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $element['time_end_required'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require end time'),
      '#description' => $this->t('If checked, the end time field will be required if the start time is provided.'),
      '#default_value' => $settings['time_end_required'],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $field_settings = $field->getFieldSettings();
    $settings = $this->getSettings() + static::defaultSettings();
    // Determine if we're showing seconds in the widget.
    $show_seconds = (bool) $field_settings['seconds_enabled'];
    $item = $items[$delta];
    $name = $field->getName();
    $time_start = $item->{$name} ?? NULL;
    $time_end = $item->{$name . '__end'} ?? NULL;

    $element['#theme'] = 'custom_field_time_range';
    $element['#theme_wrappers'] = ['fieldset', 'container'];
    $element['#element_validate'][] = [$this, 'validateStartEnd'];

    $element['value']['#title'] = $this->t('@label', ['@label' => $settings['start_label']]);
    $element['value']['#description'] = NULL;
    $element['value'] += [
      '#type' => 'time_cf',
      '#default_value' => Time::createFromTimestamp($time_start)?->formatForWidget($show_seconds),
    ];
    if ($show_seconds) {
      // Add the step attribute if we're showing seconds in the widget.
      $element['value']['#attributes']['step'] = $field_settings['seconds_step'];
      // Set a property to determine the format in TimeElement::preRenderTime().
      $element['value']['#show_seconds'] = TRUE;
    }

    $element['end_value'] = [
      '#title' => $this->t('@label', ['@label' => $settings['end_label']]),
      '#default_value' => $time_end ? Time::createFromTimestamp($time_end)?->formatForWidget($show_seconds) : NULL,
    ] + $element['value'];

    return $element;
  }

  /**
   * An #element_validate callback to ensure the start time < the end time.
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
    $require_end_time = $this->getSetting('time_end_required');

    $start_value = $element['value']['#value'];
    $end_value = $element['end_value']['#value'];

    // If both values are empty, just return.
    if (Time::isEmpty($start_value) && Time::isEmpty($end_value)) {
      return;
    }

    try {
      $start_time = Time::createFromTimestamp($start_value);
      $end_time = Time::createFromTimestamp($end_value);
    }
    catch (\InvalidArgumentException $exception) {
      $start_time = FALSE;
      $end_time = FALSE;
    }

    // Validate the end time is after the start time.
    if ($start_time && $end_time) {
      $start_timestamp = $start_time->getTimestamp();
      $end_timestamp = $end_time->getTimestamp();
      if ($start_timestamp >= $end_timestamp) {
        $form_state->setError($element, t('The @title end time must be after the start time.', ['@title' => $element['#title']]));
      }
    }

    // Validate the end time is required.
    elseif ($start_time && $require_end_time && !$end_time) {
      $form_state->setError($element['end_value'], $this->t('The @title end time is required.', ['@title' => $element['#title']]));
    }
    // Validate the end date requires a start date.
    elseif ($end_time && !$start_time) {
      $form_state->setError($element['value'], $this->t('The @title cannot have an end time with no start time', ['@title' => $element['#title']]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValue(mixed $value, $column): ?array {
    $start = $value['value'] ?? NULL;
    $end = $value['end_value'] ?? NULL;
    if (!is_numeric($start)) {
      return NULL;
    }

    return [
      'value' => (int) $start,
      'end_value' => is_numeric($end) ? (int) $end : NULL,
    ];
  }

}
