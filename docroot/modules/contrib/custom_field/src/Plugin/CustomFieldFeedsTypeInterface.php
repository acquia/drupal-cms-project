<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for custom field Type plugins.
 */
interface CustomFieldFeedsTypeInterface extends PluginInspectionInterface {

  /**
   * Returns a value after any conversions needed.
   *
   * @param mixed $value
   *   The raw value prior to conversions.
   * @param array $configuration
   *   The feeds configuration array.
   * @param string $langcode
   *   The feeds language code.
   *
   * @return mixed
   *   Prepares the value for feeds import.
   */
  public function prepareValue(mixed $value, array $configuration, string $langcode): mixed;

  /**
   * Returns a default configuration array for the custom field.
   *
   * @return array
   *   The default configuration for the custom field.
   */
  public function defaultConfiguration(): array;

  /**
   * Returns a configuration form array for the custom field.
   *
   * @param int $delta
   *   The delta of the field.
   * @param array $configuration
   *   The feeds configuration array.
   *
   * @return mixed
   *   The configuration form array.
   */
  public function buildConfigurationForm(int $delta, array $configuration): mixed;

  /**
   * Returns a summary array for custom fields.
   *
   * @param array $configuration
   *   The feeds configuration array.
   *
   * @return array
   *   The summary array.
   */
  public function getSummary(array $configuration): array;

}
