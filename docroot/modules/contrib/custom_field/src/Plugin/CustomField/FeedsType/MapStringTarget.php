<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FeedsType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldFeedsType;

/**
 * Plugin implementation of the 'map_string' feeds type.
 */
#[CustomFieldFeedsType(
  id: 'map_string',
  label: new TranslatableMarkup('Serialized - Text (plain)'),
  mark_unique: FALSE,
)]
class MapStringTarget extends MapTarget {

}
