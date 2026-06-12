<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\DataType;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\Core\TypedData\TypedData;

/**
 * Base class for DataType plugins.
 */
abstract class CustomFieldDataTypeBase extends TypedData implements PrimitiveInterface {

  /**
   * The parent typed data object.
   *
   * @var \Drupal\Core\Field\FieldItemInterface|null
   */
  protected $parent;

  /**
   * The data value.
   *
   * @var mixed
   */
  protected $value;

  /**
   * Returns the parent field data structure.
   *
   * @return \Drupal\Core\Field\FieldItemInterface|null
   *   The field item.
   */
  public function getParent(): ?FieldItemInterface {
    return $this->parent;
  }

}
