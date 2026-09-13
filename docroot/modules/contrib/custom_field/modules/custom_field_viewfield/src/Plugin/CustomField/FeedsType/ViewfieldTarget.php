<?php

declare(strict_types=1);

namespace Drupal\custom_field_viewfield\Plugin\CustomField\FeedsType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldFeedsType;
use Drupal\custom_field\Plugin\CustomField\FeedsType\BaseTarget;

/**
 * Plugin implementation of the 'viewfield' feeds type.
 */
#[CustomFieldFeedsType(
  id: 'viewfield',
  label: new TranslatableMarkup('Viewfield'),
  mark_unique: TRUE,
)]
class ViewfieldTarget extends BaseTarget {

}
