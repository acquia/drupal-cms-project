<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FeedsType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldFeedsType;

/**
 * Plugin implementation of the 'telephone' feeds type.
 */
#[CustomFieldFeedsType(
  id: 'telephone',
  label: new TranslatableMarkup('Telephone'),
  mark_unique: TRUE,
)]
class TelephoneTarget extends StringTarget {

}
