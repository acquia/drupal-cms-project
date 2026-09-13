<?php

namespace Drupal\custom_field\Plugin\CustomField\FieldType;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\custom_field\Attribute\CustomFieldType;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\custom_field\TypedData\CustomFieldDataDefinition;

/**
 * Plugin implementation of the 'daterange' field type.
 */
#[CustomFieldType(
  id: 'daterange',
  label: new TranslatableMarkup('Date range'),
  description: [
    new TranslatableMarkup("Ideal for storing durations that consist of start and end dates (and times)"),
    new TranslatableMarkup("Choose between setting both date and time, or date only, for each duration"),
    new TranslatableMarkup("The system automatically validates that the end date (and time) is later than the start, and both fields are completed"),
  ],
  category: new TranslatableMarkup('Date/Time'),
  default_widget: 'daterange_default',
  default_formatter: 'daterange_default',
)]
class DateRangeType extends DateTimeType {

  use DurationOptionsTrait;

  /**
   * Value for the 'datetime_type' setting: store a date and time.
   */
  const DATETIME_TYPE_ALLDAY = 'allday';

  /**
   * {@inheritdoc}
   */
  public static function schema(array $settings): array {
    $columns = parent::schema($settings);
    ['name' => $name] = $settings;

    $columns[$name]['description'] = 'The start date value';
    $columns[$name . self::SEPARATOR . 'end'] = [
      'type' => 'varchar',
      'length' => 20,
      'description' => 'The end date value.',
    ];
    $columns[$name . self::SEPARATOR . 'duration'] = [
      'type' => 'int',
      'unsigned' => TRUE,
      'description' => 'The difference between start and end times in seconds.',
    ];

    return $columns;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(array $settings): array {
    ['name' => $name, 'datetime_type' => $datetime_type] = $settings;

    $end_value = $name . self::SEPARATOR . 'end';
    $start_date = $name . self::SEPARATOR . 'start_date';
    $end_date = $name . self::SEPARATOR . 'end_date';
    $timezone = $name . self::SEPARATOR . 'timezone';
    $duration = $name . self::SEPARATOR . 'duration';
    $timezones = \DateTimeZone::listIdentifiers();
    array_unshift($timezones, '');

    $properties[$name] = CustomFieldDataDefinition::create('custom_field_daterange')
      ->setLabel(new TranslatableMarkup('@name start date', ['@name' => $name]))
      ->setSetting('datetime_type', $datetime_type)
      ->setRequired(FALSE);

    $properties[$start_date] = DataDefinition::create('any')
      ->setLabel(new TranslatableMarkup('@name computed start date', ['@name' => $name]))
      ->setDescription(new TranslatableMarkup('The computed start DateTime object.'))
      ->setComputed(TRUE)
      ->setClass('\Drupal\custom_field\Plugin\CustomField\DateTimeComputed')
      ->setSettings(['datetime_type' => $datetime_type, 'date source' => $name]);

    $properties[$end_value] = DataDefinition::create('datetime_iso8601')
      ->setLabel(new TranslatableMarkup('@name end date', ['@name' => $name]))
      ->setRequired(FALSE)
      ->setInternal(TRUE);

    $properties[$end_date] = DataDefinition::create('any')
      ->setLabel(new TranslatableMarkup('@name computed end date', ['@name' => $name]))
      ->setDescription(new TranslatableMarkup('The computed end DateTime object.'))
      ->setComputed(TRUE)
      ->setClass('\Drupal\custom_field\Plugin\CustomField\DateTimeComputed')
      ->setSettings(['datetime_type' => $datetime_type, 'date source' => $end_value]);

    $properties[$timezone] = DataDefinition::create('string')
      ->setLabel(t('Timezone'))
      ->setDescription(t('The timezone of this date.'))
      ->setSetting('max_length', 32)
      ->setRequired(FALSE)
      ->setInternal(TRUE)
      // @todo Define this via an options provider once
      // https://www.drupal.org/node/2329937 is completed.
      ->addConstraint('AllowedValues', $timezones);

    $properties[$duration] = DataDefinition::create('integer')
      ->setLabel(t('Duration, in seconds'))
      ->setDescription(t('The difference between start and end times in seconds.'))
      ->setInternal(TRUE)
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings(): array {
    return [
      'duration_enabled' => FALSE,
      'duration_options' => [],
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array &$form, FormStateInterface $form_state): array {
    $element = parent::fieldSettingsForm($form, $form_state);
    $settings = $this->getFieldSettings();

    $element['duration_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable duration selection'),
      '#description' => $this->t('Calculates end date from duration selection.'),
      '#default_value' => $settings['duration_enabled'],
      '#element_validate' => [[static::class, 'validateDurationOptions']],
    ];

    $this->durationOptions($element, $form_state, $settings);
    $element['duration_options']['#states']['visible'] = [
      ':input[name="settings[field_settings][' . $this->getName() . '][duration_enabled]"]' => ['checked' => TRUE],
    ];

    return $element;
  }

  /**
   * Validates the duration options field is not empty.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateDurationOptions(array &$element, FormStateInterface $form_state): void {
    $duration_enabled = $element['#value'];
    if (!$duration_enabled) {
      return;
    }
    $parents = array_slice($element['#array_parents'], 0, -1);
    $duration_options = $form_state->getValue([...$parents, 'duration_options', 'table']);
    if (empty($duration_options)) {
      $form_state->setError($element, new TranslatableMarkup('The duration options field cannot be empty.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(CustomFieldTypeInterface $field, string $target_entity_type): array {
    $datetime_type = $field->getDatetimeType();
    $start = \Drupal::time()->getRequestTime() - mt_rand(0, 86400 * 365) - 86400;
    $end = $start + 86400;
    $format = $datetime_type == DateTimeType::DATETIME_TYPE_DATE ? DateTimeTypeInterface::DATE_STORAGE_FORMAT : DateTimeTypeInterface::DATETIME_STORAGE_FORMAT;

    return [
      'value' => gmdate($format, $start),
      'end_value' => gmdate($format, $end),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onChange(string $property_name, bool $notify, FieldItemInterface $item): void {
    // Enforce that the computed dates are recalculated.
    $item->set($property_name . '__start_date', NULL);
    $item->set($property_name . '__end_date', NULL);
    parent::onChange($property_name, $notify, $item);
  }

}
