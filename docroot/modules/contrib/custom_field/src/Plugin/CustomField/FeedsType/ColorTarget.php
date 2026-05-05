<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FeedsType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldFeedsType;

/**
 * Plugin implementation of the 'color' feeds type.
 */
#[CustomFieldFeedsType(
  id: 'color',
  label: new TranslatableMarkup('Color'),
  mark_unique: TRUE,
)]
class ColorTarget extends BaseTarget {

  /**
   * {@inheritdoc}
   */
  public function prepareValue(mixed $value, array $configuration, string $langcode): ?string {
    $value = is_string($value) ? trim($value) : trim((string) $value);

    if (str_starts_with($value, '#')) {
      $value = substr($value, 1);
    }

    $length = strlen($value);

    // Account for hexadecimal short notation.
    if ($length === 3) {
      $value .= $value;
    }

    // Make sure we have a valid hexadecimal color.
    return strlen($value) === 6 ? '#' . strtoupper($value) : NULL;
  }

}
