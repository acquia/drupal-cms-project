<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\TimeZoneFormHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeType;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeTypeInterface;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\custom_field\Plugin\CustomFieldWidgetBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base plugin class for datetime custom field widgets.
 */
class DateTimeWidgetBase extends CustomFieldWidgetBase {

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
      'year_range' => '1900:2050',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widgetSettingsForm($form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();
    $element['year_range'] = [
      '#type' => 'custom_field_date_year_range',
      '#title' => $this->t('Year range'),
      '#default_value' => $settings['year_range'],
    ];

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

    // Determine if we're showing seconds in the widget.
    $show_seconds = !empty($field_settings['seconds_enabled']);

    $date = [
      '#type' => 'custom_field_datetime',
      '#default_value' => NULL,
      '#date_increment' => 1,
      '#date_timezone' => date_default_timezone_get(),
      '#date_year_range' => $this->getSetting('year_range'),
      '#show_seconds' => $show_seconds,
    ];

    if ($datetime_type == DateTimeType::DATETIME_TYPE_DATE) {
      // A date-only field should have no timezone conversion performed, so
      // use the same timezone as for storage.
      $date['#date_timezone'] = DateTimeTypeInterface::STORAGE_TIMEZONE;
    }

    if ($date_object = $item->{$field->getName() . '__date'} ?? NULL) {
      $date['#default_value'] = $this->createDefaultValue($date_object, $date['#date_timezone'], $datetime_type, $show_seconds);
    }
    $element['value'] = $date + $element;
    if ($datetime_type === DateTimeType::DATETIME_TYPE_DATETIME && $field_settings['timezone_enabled']) {
      $timezone_options = !empty($field_settings['timezone_options']) ? array_combine($field_settings['timezone_options'], $field_settings['timezone_options']) : TimeZoneFormHelper::getOptionsListByRegion();
      $element['value']['#timezone_element'] = TRUE;
      $element['timezone'] = [
        '#type' => 'select',
        '#title' => $this->t('Time zone'),
        '#options' => $timezone_options,
        '#empty_option' => $this->t('- Select -'),
        '#default_value' => $item->{$field->getName() . '__timezone'},
      ];
    }
    return $element;
  }

  /**
   * Creates a date object for use as a default value.
   *
   * This will take a default value, apply the proper timezone for display in
   * a widget, and set the default time for date-only fields.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   The UTC default date.
   * @param string $timezone
   *   The timezone to apply.
   * @param string $datetime_type
   *   The type of date.
   * @param bool $show_seconds
   *   Whether to omit the seconds from the default value.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   A date object for use as a default value in a field widget.
   *
   * @throws \DateInvalidTimeZoneException
   */
  protected function createDefaultValue(DrupalDateTime $date, string $timezone, string $datetime_type, bool $show_seconds = FALSE): DrupalDateTime {
    // The date was created and verified during field_load(), so it is safe to
    // use without further inspection.
    $year = (int) $date->format('Y');
    $month = (int) $date->format('m');
    $day = (int) $date->format('d');
    $date->setTimezone(new \DateTimeZone($timezone));
    if ($datetime_type === DateTimeType::DATETIME_TYPE_DATE) {
      $date->setDefaultDateTime();
      // Reset the date to handle cases where the UTC offset is greater than
      // 12 hours.
      $date->setDate($year, $month, $day);
    }
    if ($datetime_type === DateTimeType::DATETIME_TYPE_DATETIME && !$show_seconds) {
      $date->setTime((int) $date->format('H'), (int) $date->format('i'));
    }
    return $date;
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
    $timezone = new \DateTimeZone(DateTimeTypeInterface::STORAGE_TIMEZONE);

    if (empty($value['value'])) {
      return NULL;
    }

    if ($value['value'] instanceof DrupalDateTime) {
      $date = $value['value'];

      // Adjust the date for storage.
      $value['value'] = $date->setTimezone($timezone)->format($storage_format);
    }

    return $value;
  }

}
