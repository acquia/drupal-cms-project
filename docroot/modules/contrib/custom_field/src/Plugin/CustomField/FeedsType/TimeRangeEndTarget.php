<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FeedsType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldFeedsType;

/**
 * Plugin implementation of the 'time_range_end' feeds type.
 */
#[CustomFieldFeedsType(
  id: 'time_range_end',
  label: new TranslatableMarkup('Time range end'),
  mark_unique: TRUE,
)]
class TimeRangeEndTarget extends TimeRangeTarget {
}
