<?php

declare(strict_types=1);

namespace Drupal\custom_field;

/**
 * Gathers and provides the tags that can be used to wrap fields.
 */
interface TagManagerInterface {

  /**
   * The stored value representing "no markup".
   */
  public const NO_MARKUP_VALUE = 'none';

  /**
   * Get the tags that can wrap fields.
   *
   * @param string[] $tags
   *   An optional array of tags to filter by.
   *
   * @return array<string, string[]|string>
   *   An array of tag options, where keys are group names or special values
   *   (e.g., 'none'), and values are either translated strings or nested arrays
   *   of tag IDs to labels.
   */
  public function getTagOptions(array $tags = []): array;

}
