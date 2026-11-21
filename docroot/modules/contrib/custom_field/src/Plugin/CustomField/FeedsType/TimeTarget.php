<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FeedsType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldFeedsType;
use Drupal\custom_field\Time;

/**
 * Plugin implementation of the 'time' feeds type.
 */
#[CustomFieldFeedsType(
  id: 'time',
  label: new TranslatableMarkup('Time'),
  mark_unique: TRUE,
)]
class TimeTarget extends BaseTarget {

  /**
   * {@inheritdoc}
   */
  public function prepareValue(mixed $value, array $configuration, string $langcode): ?int {
    if (is_string($value)) {
      $value = Time::createFromHtml5Format($value);
    }

    if ($value instanceof Time) {
      return $value->getTimestamp();
    }

    return NULL;
  }

}
