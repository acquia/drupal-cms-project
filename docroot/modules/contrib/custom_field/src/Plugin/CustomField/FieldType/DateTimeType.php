<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldType;

use Drupal\Core\Datetime\TimeZoneFormHelper;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\custom_field\Attribute\CustomFieldType;
use Drupal\custom_field\Plugin\CustomFieldTypeBase;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'datetime' field type.
 */
#[CustomFieldType(
  id: 'datetime',
  label: new TranslatableMarkup('Date'),
  description: new TranslatableMarkup('A field containing a Date.'),
  category: new TranslatableMarkup('Date/Time'),
  default_widget: 'datetime_default',
  default_formatter: 'datetime_default',
)]
class DateTimeType extends CustomFieldTypeBase implements DateTimeTypeInterface {

  /**
   * Value for the 'datetime_type' setting: store only a date.
   */
  const DATETIME_TYPE_DATE = 'date';

  /**
   * Value for the 'datetime_type' setting: store a date and time.
   */
  const DATETIME_TYPE_DATETIME = 'datetime';

  /**
   * {@inheritdoc}
   */
  public static function schema(array $settings): array {
    ['name' => $name] = $settings;

    $columns[$name] = [
      'type' => 'varchar',
      'length' => 20,
    ];
    $columns[$name . self::SEPARATOR . 'timezone'] = [
      'description' => 'The preferred timezone.',
      'type' => 'varchar',
      'length' => 32,
    ];

    return $columns;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(array $settings): array {
    ['name' => $name, 'datetime_type' => $datetime_type] = $settings;
    $date = $name . self::SEPARATOR . 'date';
    $timezone = $name . self::SEPARATOR . 'timezone';
    $timezones = \DateTimeZone::listIdentifiers();
    array_unshift($timezones, '');

    $properties[$name] = DataDefinition::create('custom_field_datetime')
      ->setLabel(new TranslatableMarkup('@name', ['@name' => $name]))
      ->setSetting('datetime_type', $datetime_type)
      ->setRequired(FALSE);

    $properties[$date] = DataDefinition::create('any')
      ->setLabel(new TranslatableMarkup('@name computed date', ['@name' => $name]))
      ->setDescription(new TranslatableMarkup('The computed DateTime object.'))
      ->setComputed(TRUE)
      ->setClass('\Drupal\custom_field\Plugin\CustomField\DateTimeComputed')
      ->setSettings(['datetime_type' => $datetime_type, 'date source' => $name]);

    $properties[$timezone] = DataDefinition::create('string')
      ->setLabel(t('Timezone'))
      ->setDescription(t('The timezone of this date.'))
      ->setSetting('max_length', 32)
      ->setRequired(FALSE)
      ->setInternal(TRUE)
      // @todo Define this via an options provider once
      // https://www.drupal.org/node/2329937 is completed.
      ->addConstraint('AllowedValues', $timezones);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings(): array {
    return [
      'timezone_enabled' => FALSE,
      'timezone_options' => [],
      'seconds_enabled' => TRUE,
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array &$form, FormStateInterface $form_state): array {
    $element = parent::fieldSettingsForm($form, $form_state);
    $settings = $this->getFieldSettings();
    $date_time_type = $this->getDatetimeType();

    if ($date_time_type == self::DATETIME_TYPE_DATETIME) {
      $element['seconds_enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Add seconds parameter to input widget'),
        '#default_value' => $settings['seconds_enabled'],
      ];
      $timezone_options = TimeZoneFormHelper::getOptionsListByRegion();
      $element['timezone_enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable time zone selection'),
        '#description' => $this->t('Allows users to set a time zone for the date value.'),
        '#default_value' => $settings['timezone_enabled'],
      ];
      $element['timezone_options'] = [
        '#type' => 'select',
        '#multiple' => TRUE,
        '#title' => $this->t('Time zone options'),
        '#description' => $this->t('Select one or more time zones to display as options in the widget.<br />Hold down Ctrl (Windows) or Command (Mac) to select multiple.'),
        '#description_display' => 'before',
        '#options' => $timezone_options,
        '#default_value' => $settings['timezone_options'],
        '#states' => [
          'visible' => [
            ':input[name="settings[field_settings][' . $this->getName() . '][timezone_enabled]"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(CustomFieldTypeInterface $field, string $target_entity_type): string|array {
    $datetime_type = $field->getDatetimeType();
    $timestamp = \Drupal::time()->getRequestTime() - mt_rand(0, 86400 * 365);
    if ($datetime_type == self::DATETIME_TYPE_DATE) {
      $value = gmdate(static::DATE_STORAGE_FORMAT, $timestamp);
    }
    else {
      $value = gmdate(static::DATETIME_STORAGE_FORMAT, $timestamp);
    }

    return $value;
  }

  /**
   * Get the Unix timestamp from the stored datetime value.
   *
   * @return int|null
   *   The Unix timestamp, or NULL if the value is invalid.
   */
  public function getTimestamp(FieldItemInterface $item): ?int {
    $value = $this->value($item);

    // Ensure the value is not empty and is in the correct ISO 8601 format.
    if (!empty($value)) {
      try {
        $datetime = new \DateTime($value);
        return $datetime->getTimestamp();
      }
      catch (\Exception $e) {
        // Handle invalid datetime formats gracefully.
        return NULL;
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function onChange(string $property_name, bool $notify, FieldItemInterface $item): void {
    // Enforce that the computed date is recalculated.
    $item->set($property_name . '__date', NULL);
    parent::onChange($property_name, $notify, $item);
  }

}
