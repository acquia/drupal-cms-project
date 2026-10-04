<?php

namespace Drupal\custom_field\Plugin;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Defines an interface for custom field Type plugins.
 */
interface CustomFieldWidgetManagerInterface extends PluginManagerInterface {

  /**
   * Returns an array of widget types supported for a particular field.
   *
   * @param string $type
   *   The column type or plugin id of the field.
   *
   * @return string[]
   *   The array of widget type plugin ids.
   */
  public function getWidgetsForField(string $type): array;

  /**
   * Helper function to create options for plugin manager getInstance() method.
   *
   * @param string $field_name
   *   The field definition name.
   * @param \Drupal\custom_field\Plugin\CustomFieldTypeInterface $custom_item
   *   The custom field definition.
   * @param string $widget_type
   *   The format type.
   * @param array<string, mixed> $widget_settings
   *   The formatter settings.
   * @param string $view_mode
   *   The view mode.
   *
   * @return array<string, mixed>
   *   The array of options.
   */
  public function createOptionsForInstance(string $field_name, CustomFieldTypeInterface $custom_item, string $widget_type, array $widget_settings, string $view_mode): array;

  /**
   * Merges default values for widget configuration.
   *
   * @param string $field_type
   *   The field type.
   * @param array<string, mixed> $configuration
   *   An array of widget configuration.
   *
   * @return array<string, mixed>
   *   The display properties with defaults added.
   */
  public function prepareConfiguration(string $field_type, array $configuration): array;

}
