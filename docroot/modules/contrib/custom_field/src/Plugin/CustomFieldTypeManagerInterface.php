<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Defines an interface for custom field Type plugins.
 */
interface CustomFieldTypeManagerInterface extends PluginManagerInterface {

  /**
   * Get custom field plugin items from an array of custom field settings.
   *
   * @param array<string, mixed> $settings
   *   The array of Drupal\custom_field\Plugin\Field\FieldType\CustomItem
   *   settings.
   *
   * @return \Drupal\custom_field\Plugin\CustomFieldTypeInterface[]
   *   The array of custom field plugin items to return.
   */
  public function getCustomFieldItems(array $settings): array;

  /**
   * Builds options for a select list based on field types.
   *
   * @return array<string, mixed>
   *   An array of options suitable for a select list.
   */
  public function fieldTypeOptions(): array;

  /**
   * Create the configuration settings for instantiating a field.
   *
   * @param array<string, mixed> $settings
   *   The field settings for this field.
   * @param array<string, mixed> $column
   *   The column settings for this field.
   *
   * @return array<string, mixed>
   *   The array of options to pass to field instance.
   */
  public function createOptionsForInstance(array $settings, array $column): array;

}
