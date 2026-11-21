<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'number_unformatted' formatter.
 */
#[FieldFormatter(
  id: 'number_unformatted',
  label: new TranslatableMarkup('Unformatted'),
  field_types: [
    'integer',
    'decimal',
    'float',
  ],
)]
class NumericUnformattedFormatter extends DecimalFormatter {

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): mixed {
    return $value;
  }

}
