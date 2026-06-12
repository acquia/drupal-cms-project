<?php

namespace Drupal\modeler_api\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Modeler attribute for plugin discovery.
 *
 * @see \Drupal\modeler_api\Plugin\ModelerPluginManager
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Modeler extends Plugin {

  /**
   * Constructs a Modeler attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   (optional) The human-readable name of the plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) A brief description of the plugin.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
  ) {}

}
