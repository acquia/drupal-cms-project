<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FeedsType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldFeedsType;

/**
 * Plugin implementation of the 'daterange' feeds type.
 */
#[CustomFieldFeedsType(
  id: 'daterange',
  label: new TranslatableMarkup('Daterange'),
  mark_unique: TRUE,
)]
class DaterangeTarget extends DatetimeTarget {
}
