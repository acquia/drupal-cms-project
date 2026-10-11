<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FeedsType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldFeedsType;

/**
 * Plugin implementation of the 'map' feeds type.
 */
#[CustomFieldFeedsType(
  id: 'map',
  label: new TranslatableMarkup('Serialized - Key/Value'),
  mark_unique: FALSE,
)]
class MapTarget extends BaseTarget {

  /**
   * {@inheritdoc}
   */
  public function prepareValue(mixed $value, array $configuration, string $langcode): ?array {
    $decoded = json_decode($value, TRUE);
    if (is_array($decoded) && !empty($decoded)) {
      return $decoded;
    }

    return NULL;
  }

}
