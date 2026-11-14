<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FeedsType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldFeedsType;

/**
 * Plugin implementation of the 'time_range' feeds type.
 */
#[CustomFieldFeedsType(
  id: 'time_range',
  label: new TranslatableMarkup('Time range'),
  mark_unique: TRUE,
)]
class TimeRangeTarget extends TimeTarget {
}
