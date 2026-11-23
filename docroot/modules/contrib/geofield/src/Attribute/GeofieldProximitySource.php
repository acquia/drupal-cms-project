<?php

namespace Drupal\geofield\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Geofield Proximity Source attribute object.
 *
 * @see \Drupal\geofield\Plugin\GeofieldProximitySourceManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class GeofieldProximitySource extends Plugin {

  /**
   * Constructs a GeofieldProximitySource attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The label of the plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   A short description of the plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $exposedDescription
   *   (optional) A description to show in the exposed form.
   * @param string[]|null $context
   *   (optional) The view handler plugin types (contexts) the plugin works
   *   for: filter, sort, field. An empty array means all of them. The plugin
   *   is not offered in any context when this is NULL.
   * @param bool|null $exposedOnly
   *   (optional) Whether the plugin works only in an exposed filter.
   * @param bool|null $no_ui
   *   (optional) Whether the plugin is left out of the source select. The
   *   manager reads this key, and an annotation could set it.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly ?TranslatableMarkup $exposedDescription = NULL,
    public readonly ?array $context = NULL,
    public readonly ?bool $exposedOnly = NULL,
    public readonly ?bool $no_ui = NULL,
    public readonly ?string $deriver = NULL,
  ) {}

}
