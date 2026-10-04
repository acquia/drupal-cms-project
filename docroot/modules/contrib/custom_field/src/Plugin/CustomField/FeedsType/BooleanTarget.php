<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FeedsType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldFeedsType;

/**
 * Plugin implementation of the 'boolean' feeds type.
 */
#[CustomFieldFeedsType(
  id: 'boolean',
  label: new TranslatableMarkup('Boolean'),
)]
class BooleanTarget extends BaseTarget {

  /**
   * {@inheritdoc}
   */
  public function prepareValue(mixed $value, array $configuration, string $langcode): ?bool {
    if (is_bool($value)) {
      return $value;
    }
    if (is_string($value)) {
      return (bool) trim($value);
    }
    if (is_scalar($value)) {
      return (bool) $value;
    }
    if (empty($value)) {
      return FALSE;
    }
    if (is_array($value)) {
      $value = current($value);
      return $this->prepareValue($value, $configuration, $langcode);
    }

    $value = @(string) $value;

    return $this->prepareValue($value, $configuration, $langcode);
  }

}
