<?php

namespace Drupal\custom_field\Annotation;

use Drupal\Core\TypedData\Annotation\DataType;

/**
 * Defines a Prop widget annotation object.
 *
 * @Annotation
 */
class PropWidget extends DataType {

  /**
   * The prop type the widget supports.
   *
   * @var string
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
  public string $prop_type;

  /**
   * The human-readable name of the widget type.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A short description of the widget type.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
