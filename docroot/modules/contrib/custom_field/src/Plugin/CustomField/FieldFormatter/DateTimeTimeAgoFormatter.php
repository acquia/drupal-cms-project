<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'datetime_time_ago' formatter.
 */
#[FieldFormatter(
  id: 'datetime_time_ago',
  label: new TranslatableMarkup('Time ago'),
  field_types: [
    'datetime',
  ],
)]
class DateTimeTimeAgoFormatter extends TimestampAgoFormatter {

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): ?array {
    /** @var \Drupal\Core\Datetime\DrupalDateTime $date */
    $date = $value['date'];

    if ($date === NULL) {
      return NULL;
    }

    return $this->formatDate($date);
  }

  /**
   * Formats a date/time as a time interval.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   A date/time object.
   *
   * @return array
   *   The formatted date/time string using the past or future format setting.
   */
  protected function formatDate(DrupalDateTime $date): array {
    return parent::formatTimestamp($date->getTimestamp());
  }

}
