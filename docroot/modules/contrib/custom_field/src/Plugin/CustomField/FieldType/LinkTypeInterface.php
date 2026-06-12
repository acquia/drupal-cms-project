<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldType;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Url;

/**
 * Interface definition for Datetime subfields.
 */
interface LinkTypeInterface {

  /**
   * Specifies whether the field supports only internal URLs.
   */
  const LINK_INTERNAL = 0x01;

  /**
   * Specifies whether the field supports only external URLs.
   */
  const LINK_EXTERNAL = 0x10;

  /**
   * Specifies whether the field supports both internal and external URLs.
   */
  const LINK_GENERIC = 0x11;

  /**
   * Returns Url object for a field.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   A field.
   *
   * @return \Drupal\Core\Url
   *   The Url object.
   */
  public function getUrl(FieldItemInterface $item): Url;

  /**
   * Determines if a link is external.
   *
   * @return bool
   *   TRUE if the link is external, FALSE otherwise.
   */
  public function isExternal(FieldItemInterface $item): bool;

}
