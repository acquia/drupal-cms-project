<?php

namespace Drupal\custom_field\PluginManager;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\custom_field\Plugin\PropWidgetInterface;

/**
 * Defines an interface for prop widget plugins.
 */
interface PropWidgetManagerInterface extends PluginManagerInterface {

  const CANVAS_IMAGE = 'json-schema-definitions://canvas.module/image';

  /**
   * Helper function to create options for plugin manager getInstance() method.
   *
   * @param string $widget_type
   *   The widget type.
   * @param array<string, mixed> $widget_settings
   *   The widget settings.
   *
   * @return array<string, mixed>
   *   The array of options.
   */
  public function createOptionsForInstance(string $widget_type, array $widget_settings): array;

  /**
   * Merges default values for widget configuration.
   *
   * @param array<string, mixed> $configuration
   *   An array of widget configuration.
   *
   * @return array<string, mixed>
   *   The display properties with defaults added.
   */
  public function prepareConfiguration(array $configuration): array;

  /**
   * Returns the default settings for a given widget type.
   *
   * @param string $type
   *   The widget type.
   *
   * @return array
   *   The default settings.
   */
  public function getDefaultSettings(string $type): array;

  /**
   * Returns a PropWidgetInterface object for the given property info.
   *
   * @param array $property_info
   *   The property info array.
   *
   * @return \Drupal\custom_field\Plugin\PropWidgetInterface|null
   *   The PropWidgetInterface object or NULL if not found.
   */
  public function getPropWidget(array $property_info): ?PropWidgetInterface;

}
