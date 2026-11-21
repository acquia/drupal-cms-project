<?php

namespace Drupal\geofield\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a GeofieldBackend attribute object.
 *
 * @ingroup geofield_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class GeofieldBackend extends Plugin {

  /**
   * Constructs a GeofieldBackend attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $admin_label
   *   The administrative label of the geofield backend.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   The description of the geofield backend.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $admin_label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly ?string $deriver = NULL,
  ) {}

}
