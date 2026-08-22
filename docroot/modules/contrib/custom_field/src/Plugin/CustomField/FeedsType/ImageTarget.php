<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FeedsType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldFeedsType;

/**
 * Plugin implementation of the 'image' feeds type.
 */
#[CustomFieldFeedsType(
  id: 'image',
  label: new TranslatableMarkup('Image'),
  mark_unique: FALSE,
)]
class ImageTarget extends FileTarget {

}
