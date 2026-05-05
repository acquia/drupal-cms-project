<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Plugin\CustomFieldTest\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field_test\Attribute\TestFieldType;
use Drupal\custom_field_test\Plugin\FieldTypeTestBase;

/**
 * Plugin implementation of the 'map' field type test.
 */
#[TestFieldType(
  id: 'map',
  label: new TranslatableMarkup('Map'),
)]
class TestMap extends FieldTypeTestBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultWidget(): array {
    return [
      'id' => 'map_key_value',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldWidget\MapKeyValueWidget',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormatter(): array {
    return [
      'id' => 'string',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldFormatter\StringFormatter',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function testCases(string $name, array $settings): array {
    $map = [
      ['key' => 'Key1', 'value' => 'Value1'],
      ['key' => 'Key2', 'value' => 'Value2'],
    ];
    $new_map = [
      ['key' => 'New Key1', 'value' => 'New Value1'],
      ['key' => 'New Key2', 'value' => 'New Value2'],
      ['key' => 'New Key3', 'value' => 'New Value3'],
    ];
    return [
      $this->buildTestCase($name, $map),
      $this->buildTestCase($name, $new_map),
    ];
  }

}
