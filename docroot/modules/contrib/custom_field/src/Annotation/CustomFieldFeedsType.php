<?php

namespace Drupal\custom_field\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Custom field feeds type annotation object.
 *
 * @see \Drupal\custom_field\Plugin\CustomFieldFeedsManager
 * @see plugin_api
 *
 * @Annotation
 */
class CustomFieldFeedsType extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public string $id;

  /**
   * The human-readable name of the feed type.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The default value for the mark unique field setting.
   *
   * @var bool
   */
  public $mark_unique = FALSE;

}
