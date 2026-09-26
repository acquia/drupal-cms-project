<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\TimeZoneFormHelper;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeType;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeTypeInterface;
use Drupal\custom_field\Plugin\CustomFieldFormatterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for 'DateTime custom field formatter' plugin implementations.
 */
abstract class DateTimeFormatterBase extends CustomFieldFormatterBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The date format entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $dateFormatStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->dateFormatStorage = $container->get('entity_type.manager')->getStorage('date_format');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'timezone_override' => '',
      'timezone_stored' => FALSE,
      'display_timezone' => FALSE,
      'timezone_format' => 'abbreviation',
      'user_timezone' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $visibility_path = $form['#visibility_path'];
    $parents = $form['#field_parents'];
    $elements['timezone'] = [
      '#type' => 'details',
      '#title' => $this->t('Time zone'),
    ];
    $elements['timezone']['timezone_override'] = [
      '#type' => 'select',
      '#title' => $this->t('Time zone override'),
      '#description' => $this->t('The time zone selected here will always be used'),
      '#options' => TimeZoneFormHelper::getOptionsListByRegion(TRUE),
      '#default_value' => $this->getSetting('timezone_override'),
      '#parents' => [...$parents, 'timezone_override'],
    ];
    if ($this->customFieldDefinition->getDatetimeType() === DateTimeType::DATETIME_TYPE_DATETIME) {
      $elements['timezone']['timezone_stored'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Use stored time zone'),
        '#description' => $this->t('If checked, the time zone of the field will be used if available instead of the time zone of the user.'),
        '#default_value' => $this->getSetting('timezone_stored'),
        '#states' => [
          'visible' => [
            ':input[name="' . $visibility_path . '[timezone_override]"]' => ['value' => ''],
          ],
        ],
        '#parents' => [...$parents, 'timezone_stored'],
      ];
      $elements['timezone']['display_timezone'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Display time zone'),
        '#description' => $this->t('If checked, the time zone of the field will be displayed along with the date/time.'),
        '#default_value' => $this->getSetting('display_timezone'),
        '#parents' => [...$parents, 'display_timezone'],
      ];
      $elements['timezone']['timezone_format'] = [
        '#type' => 'select',
        '#title' => $this->t('Time zone format'),
        '#description' => $this->t('The format of the time zone to display.'),
        '#options' => [
          'name' => $this->t('Name'),
          'offset' => $this->t('Offset'),
          'abbreviation' => $this->t('Abbreviation'),
        ],
        '#default_value' => $this->getSetting('timezone_format'),
        '#states' => [
          'visible' => [
            ':input[name="' . $visibility_path . '[display_timezone]"]' => ['checked' => TRUE],
          ],
        ],
        '#parents' => [...$parents, 'timezone_format'],
      ];
      $elements['timezone']['user_timezone'] = [
        '#type' => 'checkbox',
        '#title' => $this->t("Append date/time in user's time zone"),
        '#description' => $this->t("If checked, the date/time in the user's time zone will also be displayed."),
        '#default_value' => $this->getSetting('user_timezone'),
        '#parents' => [...$parents, 'user_timezone'],
      ];
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): ?array {
    /** @var \Drupal\Core\Datetime\DrupalDateTime $date */
    $date = $value['date'];

    if ($date === NULL) {
      return NULL;
    }

    $timezone = $this->getSetting('timezone_stored') ? $value['timezone'] : NULL;
    if ($this->getSetting('timezone_override')) {
      $timezone = $this->getSetting('timezone_override');
    }

    if ($this->getSetting('user_timezone') && (!empty($timezone))) {
      return [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#items' => [
          $this->buildDateWithIsoAttribute($date, TRUE, $timezone),
          $this->buildDateWithIsoAttribute($date),
        ],
      ];
    }

    return $this->buildDateWithIsoAttribute($date, TRUE, $timezone);
  }

  /**
   * Creates a formatted date value as a string.
   *
   * @param \Drupal\Component\Datetime\DateTimePlus $date
   *   A DrupalDateTime object.
   * @param string|null $timezone
   *   The stored timezone.
   *
   * @return string
   *   A formatted date string using the chosen format.
   */
  abstract protected function formatDate(DateTimePlus $date, ?string $timezone): string;

  /**
   * Sets the proper time zone on a DrupalDateTime object for the current user.
   *
   * A DrupalDateTime object loaded from the database will have the UTC time
   * zone applied to it.  This method will apply the time zone for the current
   * user, based on system and user settings.
   *
   * @param \Drupal\Component\Datetime\DateTimePlus $date
   *   A DrupalDateTime object.
   * @param string|null $timezone
   *   The stored timezone.
   */
  protected function setTimeZone(DateTimePlus $date, ?string $timezone = NULL): void {
    $datetime_type = $this->customFieldDefinition->getDatetimeType();
    if ($datetime_type === DateTimeType::DATETIME_TYPE_DATE) {
      // A date without time has no timezone conversion.
      $timezone = DateTimeTypeInterface::STORAGE_TIMEZONE;
    }
    elseif (empty($timezone)) {
      $timezone = date_default_timezone_get();
    }

    $date->setTimezone(timezone_open($timezone));
  }

  /**
   * Creates a render array from a date object.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   A date object.
   * @param string|null $timezone
   *   The stored timezone.
   *
   * @return array<string, mixed>
   *   A render array.
   */
  protected function buildDate(DrupalDateTime $date, ?string $timezone = NULL): array {
    $datetime_type = $this->customFieldDefinition->getDatetimeType();
    $data_type = $this->customFieldDefinition->getDataType();
    $this->setTimeZone($date, $timezone);
    $formatted_date = $this->formatDate($date, $timezone);

    if ($data_type === 'datetime' && $datetime_type === DateTimeType::DATETIME_TYPE_DATETIME && $this->getSetting('display_timezone')) {
      $formatted_date .= ' ' . $this->formatTimezoneDisplay($date);
    }
    return [
      '#markup' => $formatted_date,
      '#cache' => [
        'contexts' => [
          'timezone',
        ],
      ],
    ];
  }

  /**
   * Creates a render array from a date object with ISO date attribute.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   A date object.
   * @param bool $format_timezone
   *   A boolean indicating whether to format and append the timezone.
   * @param string|null $timezone
   *   The stored timezone.
   *
   * @return array<string, mixed>
   *   A render array.
   */
  protected function buildDateWithIsoAttribute(DrupalDateTime $date, bool $format_timezone = TRUE, ?string $timezone = NULL): array {
    $datetime_type = $this->customFieldDefinition->getDatetimeType();
    // Create the ISO date in Universal Time.
    $iso_date = $date->format("Y-m-d\TH:i:s") . 'Z';

    $this->setTimeZone($date, $timezone);
    $formatted_date = $this->formatDate($date, $timezone);

    if ($format_timezone && $datetime_type === DateTimeType::DATETIME_TYPE_DATETIME && $this->getSetting('display_timezone')) {
      $formatted_date .= ' ' . $this->formatTimezoneDisplay($date);
    }

    return [
      '#theme' => 'time',
      '#text' => $formatted_date,
      '#attributes' => [
        'datetime' => $iso_date,
      ],
      '#cache' => [
        'contexts' => [
          'timezone',
        ],
      ],
    ];
  }

  /**
   * Formats timezone display based on settings.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   The date object.
   *
   * @return string
   *   The formatted timezone.
   */
  protected function formatTimezoneDisplay(DrupalDateTime $date): string {
    $format = $this->getSetting('timezone_format');
    $timezone_name = $date->getTimezone()->getName();

    switch ($format) {
      case 'name':
        return '(' . $timezone_name . ')';

      case 'offset':
        return '(' . $date->format('P') . ')';

      default:
        // Default to abbreviation.
        return '(' . $date->format('T') . ')';
    }
  }

}
