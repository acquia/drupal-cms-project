<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'datetime_default' formatter.
 */
#[FieldFormatter(
  id: 'datetime_default',
  label: new TranslatableMarkup('Default'),
  field_types: [
    'datetime',
  ],
)]
class DateTimeDefaultFormatter extends DateTimeFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'format_type' => 'medium',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  protected function formatDate($date, ?string $timezone): string {
    $format_type = $this->getSetting('format_type');
    if ($this->getSetting('timezone_override')) {
      $timezone = $this->getSetting('timezone_override');
    }
    if (empty($timezone)) {
      $timezone = $date->getTimezone()->getName();
    }
    return $this->dateFormatter->format($date->getTimestamp(), $format_type, '', $timezone != '' ? $timezone : NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $time = new DrupalDateTime();
    $format_types = $this->dateFormatStorage->loadMultiple();
    $options = [];
    foreach ($format_types as $type => $type_info) {
      $format = $this->dateFormatter->format($time->getTimestamp(), $type);
      $options[$type] = $this->t('@label (@format)', [
        '@label' => $type_info->label(),
        '@format' => $format,
      ]);
    }

    $elements['format_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Date format'),
      '#description' => $this->t('Choose a format for displaying the date. Be sure to set a format appropriate for the field, i.e. omitting time for a field that only has a date.'),
      '#options' => $options,
      '#default_value' => $this->getSetting('format_type'),
    ];

    return $elements;
  }

}
