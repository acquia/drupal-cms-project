<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FeedsType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldFeedsType;

/**
 * Plugin implementation of the 'link' feeds type.
 */
#[CustomFieldFeedsType(
  id: 'link',
  label: new TranslatableMarkup('Link'),
  mark_unique: TRUE,
)]
class LinkTarget extends UriTarget {

}
