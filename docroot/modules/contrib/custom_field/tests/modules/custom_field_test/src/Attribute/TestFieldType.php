<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a TestFieldType attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class TestFieldType extends Plugin {

  /**
   * Constructs a CustomFieldTypeTest attribute object.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the field type.
   * @param array $config_dependencies
   *   (optional) An array of configuration dependencies.
   * @param string|null $module
   *   The name of the module providing the field type test plugin.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly array $config_dependencies = [],
    public readonly ?string $module = NULL,
  ) {
  }

}
