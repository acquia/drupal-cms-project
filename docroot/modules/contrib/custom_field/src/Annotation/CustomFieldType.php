<?php

namespace Drupal\custom_field\Annotation;

use Drupal\Core\Field\Annotation\FieldType;

/**
 * Defines a Custom field Type item annotation object.
 *
 * @see \Drupal\custom_field\Plugin\CustomFieldTypeManager
 * @see plugin_api
 *
 * @Annotation
 */
class CustomFieldType extends FieldType {

  /**
   * The default value for the check empty field setting.
   *
   * @var bool
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
  public bool $check_empty = FALSE;

  /**
   * Flag to restrict this type from empty row checking.
   *
   * @var bool
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
  public bool $never_check_empty = FALSE;

}
