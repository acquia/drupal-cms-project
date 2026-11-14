<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Defines an interface for custom field feeds plugins.
 */
interface CustomFieldFeedsManagerInterface extends PluginManagerInterface {

  /**
   * Get custom field feeds plugin items from an array of custom field settings.
   *
   * @param array<string, mixed> $settings
   *   The array of Drupal\custom_field\Plugin\Field\FieldType\CustomItem
   *   settings.
   *
   * @return \Drupal\custom_field\Plugin\CustomFieldFeedsTypeInterface[]
   *   The array of custom field feeds plugin items to return.
   */
  public function getFeedsTargets(array $settings): array;

}
