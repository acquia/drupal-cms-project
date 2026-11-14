<?php

namespace Drupal\custom_field_test\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for custom field type test plugins.
 */
interface FieldTypeTestInterface extends PluginInspectionInterface {

  /**
   * The default widget to test for.
   *
   * @return array{id: string, class: string}
   *   An array containing the id and class as keys.
   */
  public function getDefaultWidget(): array;

  /**
   * The default formatter to test for.
   *
   * @return array{id: string, class: string}
   *   An array containing the id and class as keys.
   */
  public function getDefaultFormatter(): array;

  /**
   * The test cases to run.
   *
   * @param string $name
   *   The sub-field name.
   * @param array $settings
   *   The field definition settings.
   *
   * @return array{property: string, value: mixed, violation: boolean}
   *   An array of test cases.
   */
  public function testCases(string $name, array $settings): array;

}
