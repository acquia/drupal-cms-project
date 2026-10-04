<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Plugin\CustomFieldTest\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field_test\Attribute\TestFieldType;
use Drupal\custom_field_test\Plugin\FieldTypeTestBase;

/**
 * Plugin implementation of the 'viewfield' field type test.
 */
#[TestFieldType(
  id: 'viewfield',
  label: new TranslatableMarkup('Viewfield'),
)]
class TestViewfield extends FieldTypeTestBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultWidget(): array {
    return [
      'id' => 'viewfield_select',
      'class' => 'Drupal\custom_field_viewfield\Plugin\CustomField\FieldWidget\ViewfieldSelectWidget',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormatter(): array {
    return [
      'id' => 'viewfield_default',
      'class' => 'Drupal\custom_field_viewfield\Plugin\CustomField\FieldFormatter\ViewfieldDefaultFormatter',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function testCases(string $name, array $settings): array {
    $properties = [
      $name,
      $name . '__display',
      $name . '__arguments',
      $name . '__items',
    ];
    return [
      $this->buildTestCase($properties, [
        $properties[0] => 'custom_field_test',
        $properties[1] => 'block_1',
        $properties[2] => $this->random->machineName(),
        $properties[3] => 10,
      ]),
      $this->buildTestCase($properties, [
        $properties[0] => 'custom_field_test_2',
        $properties[1] => 'default',
        $properties[2] => $this->random->machineName(),
        $properties[3] => 5,
      ]),
      // Test extra properties when main property is NULL.
      $this->buildTestCase($properties, [
        $properties[1] => 'block_1',
        $properties[2] => $this->random->machineName(),
        $properties[3] => 10,
      ]),
    ];
  }

}
