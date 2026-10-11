<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FeedsType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldFeedsType;

/**
 * Plugin implementation of the 'decimal' feeds type.
 */
#[CustomFieldFeedsType(
  id: 'decimal',
  label: new TranslatableMarkup('Decimal'),
  mark_unique: TRUE,
)]
class DecimalTarget extends BaseTarget {

  /**
   * {@inheritdoc}
   */
  public function prepareValue(mixed $value, array $configuration, string $langcode): mixed {
    $value = parent::prepareValue($value, $configuration, $langcode);

    return is_numeric($value) ? (float) $value : NULL;
  }

}
