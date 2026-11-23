<?php

declare(strict_types=1);

namespace Drupal\custom_field\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a CustomFieldFeedsType attribute.
 *
 * Additional attribute keys for feeds types can be defined in
 * hook_custom_field_feeds_info_alter().
 *
 * @ingroup field_types
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class CustomFieldFeedsType extends Plugin {

  /**
   * Constructs a CustomFieldFeedsType attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the feed type.
   * @param bool $mark_unique
   *   (optional) A boolean stating that field of this feed type can be marked
   *   unique.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly bool $mark_unique = FALSE,
  ) {
  }

}
