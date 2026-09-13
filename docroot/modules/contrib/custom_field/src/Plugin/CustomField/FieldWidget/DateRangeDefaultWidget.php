<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldWidget;

/**
 * Plugin implementation of the 'daterange_default' widget.
 */
#[CustomFieldWidget(
  id: 'daterange_default',
  label: new TranslatableMarkup('Date range'),
  category: new TranslatableMarkup('Date'),
  field_types: [
    'daterange',
  ],
)]
class DateRangeDefaultWidget extends DateRangeWidgetBase {
}
