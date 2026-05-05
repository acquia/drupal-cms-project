<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines an interface for custom field formatter plugins.
 */
interface CustomFieldFormatterManagerInterface extends PluginManagerInterface {

  /**
   * Helper function to create options for plugin manager getInstance() method.
   *
   * @param \Drupal\custom_field\Plugin\CustomFieldTypeInterface $custom_item
   *   The custom field definition.
   * @param string $format_type
   *   The format type.
   * @param array<string, mixed> $formatter_settings
   *   The formatter settings.
   * @param string $view_mode
   *   The view mode.
   *
   * @return array<string, mixed>
   *   The array of options.
   */
  public function createOptionsForInstance(CustomFieldTypeInterface $custom_item, string $format_type, array $formatter_settings, string $view_mode): array;

  /**
   * Returns the default settings of a custom_field formatter.
   *
   * @param string $type
   *   A custom_field formatter type name.
   *
   * @return array<string, mixed>
   *   The formatter type's default settings, as provided by the plugin
   *   definition, or an empty array if type or settings are undefined.
   */
  public function getDefaultSettings(string $type): array;

  /**
   * Return the value keys in formatter settings form for format_type selection.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $field_name
   *   The field name of the parent custom field.
   *
   * @return array<int, string>
   *   An array of value keys.
   */
  public function getFormatterValueKeys(FormStateInterface $form_state, string $field_name): array;

  /**
   * Return the input path structure in formatter settings form for states api.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $field_name
   *   The field name of the parent custom field.
   * @param string $property
   *   The property name of the custom field.
   * @param bool $is_views_subfield
   *   A boolean to indicate if the settings form is an individual views
   *   subfield.
   * @param string $wrapper
   *   The wrapper of the settings form. Either 'fields' or 'slots'.
   *
   * @return string
   *   The input path.
   */
  public function getInputPathForStatesApi(FormStateInterface $form_state, string $field_name, string $property, bool $is_views_subfield, string $wrapper): string;

  /**
   * Gets an instance of a formatter plugin.
   *
   * @param array<string, mixed> $options
   *   An array of options to build the plugin.
   *
   * @return \Drupal\custom_field\Plugin\CustomFieldFormatterInterface|null
   *   A formatter object or NULL when plugin is not found.
   */
  public function getInstance(array $options): ?CustomFieldFormatterInterface;

  /**
   * Return the available formatter plugins as an array keyed by plugin_id.
   *
   * @param \Drupal\custom_field\Plugin\CustomFieldTypeInterface $custom_field
   *   The custom field associated with the formatter.
   *
   * @return string[]
   *   The array of formatter options.
   */
  public function getOptions(CustomFieldTypeInterface $custom_field): array;

  /**
   * Merges default values for formatter configuration.
   *
   * @param string $field_type
   *   The field type.
   * @param array<string, mixed> $configuration
   *   An array of formatter configuration.
   *
   * @return array<string, mixed>
   *   The display properties with defaults added.
   */
  public function prepareConfiguration(string $field_type, array $configuration): array;

}
