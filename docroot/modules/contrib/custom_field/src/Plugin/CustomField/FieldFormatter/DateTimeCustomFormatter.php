<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeTypeInterface;

/**
 * Plugin implementation of the 'datetime_custom' formatter.
 */
#[FieldFormatter(
  id: 'datetime_custom',
  label: new TranslatableMarkup('Custom'),
  field_types: [
    'datetime',
  ],
)]
class DateTimeCustomFormatter extends DateTimeFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'date_format' => DateTimeTypeInterface::DATETIME_STORAGE_FORMAT,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['date_format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Date/time format'),
      '#description' => $this->t('See <a href=":url" target="_blank">the documentation for PHP date formats</a>.', [
        ':url' => 'https://www.php.net/manual/datetime.format.php#refsect1-datetime.format-parameters',
      ]),
      '#default_value' => $this->getSetting('date_format'),
    ];

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
    if ($this->getSetting('user_timezone') && !empty($timezone)) {
      return [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#items' => [
          $this->buildDate($date, $timezone),
          $this->buildDate($date),
        ],
      ];
    }

    return $this->buildDate($date, $timezone);
  }

  /**
   * {@inheritdoc}
   */
  protected function formatDate(object $date, ?string $timezone): string {
    $format = $this->getSetting('date_format');
    if ($this->getSetting('timezone_override')) {
      $timezone = $this->getSetting('timezone_override');
    }
    if (empty($timezone)) {
      $timezone = $date->getTimezone()->getName();
    }
    return $this->dateFormatter->format($date->getTimestamp(), 'custom', $format, $timezone != '' ? $timezone : NULL);
  }

}
