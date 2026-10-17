<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\DataType;

use Drupal\Core\TypedData\PrimitiveInterface;

/**
 * Interface for custom_field_string_long custom field data.
 *
 * The "custom_field_string_long" data type provides a mechanism to return the
 * processed value for "string_long" custom_field types that we can normalize
 * for jsonapi.
 */
interface CustomFieldStringLongInterface extends PrimitiveInterface {

  /**
   * Returns the processed text or original value if not formatted.
   *
   * @return \Drupal\Component\Render\MarkupInterface|mixed|string
   *   The filtered markup or string if the field is not formatted.
   */
  public function getProcessed(): mixed;

}
