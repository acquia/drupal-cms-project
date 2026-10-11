<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FeedsType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldFeedsType;

/**
 * Plugin implementation of the 'string_long' feeds type.
 */
#[CustomFieldFeedsType(
  id: 'string_long',
  label: new TranslatableMarkup('String long'),
  mark_unique: TRUE,
)]
class StringLongTarget extends BaseTarget {

  /**
   * {@inheritdoc}
   */
  public function prepareValue(mixed $value, array $configuration, string $langcode): ?string {
    $value = parent::prepareValue($value, $configuration, $langcode);

    return !empty($value) ? $value : NULL;
  }

}
