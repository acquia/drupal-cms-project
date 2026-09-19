<?php

namespace Drupal\custom_field\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a CustomFieldWidget attribute for plugin discovery.
 *
 * Plugin Namespace: Plugin\CustomField\FieldWidget.
 *
 * Widgets handle how fields are displayed in edit forms.
 *
 * Additional attribute keys for widgets can be defined in
 * hook_custom_field_widget_info_alter().
 *
 * @see \Drupal\Core\Field\WidgetPluginManager
 * @see \Drupal\Core\Field\WidgetInterface
 *
 * @ingroup field_widget
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class PropWidget extends Plugin {

  /**
   * Constructs a FieldWidget attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param string $prop_type
   *   The prop type the widget supports.
   * @param string[] $items_types
   *   (optional) The array types the widget supports for items.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   (optional) The human-readable name of the widget type.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) A short description of the widget type.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly string $prop_type,
    public readonly array $items_types = [],
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly ?string $deriver = NULL,
  ) {}

}
