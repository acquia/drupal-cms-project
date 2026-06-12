<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Plugin\CustomFieldTest\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field_test\Attribute\TestFieldType;
use Drupal\custom_field_test\Plugin\FieldTypeTestBase;

/**
 * Plugin implementation of the 'float' field type test.
 */
#[TestFieldType(
  id: 'float',
  label: new TranslatableMarkup('Float'),
)]
class TestFloat extends FieldTypeTestBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultWidget(): array {
    return [
      'id' => 'float',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldWidget\FloatWidget',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormatter(): array {
    return [
      'id' => 'number_decimal',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldFormatter\DecimalFormatter',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function testCases(string $name, array $settings): array {
    $float = 3.14;
    $new_float = rand(1001, 2000) / 100;
    return [
      $this->buildTestCase($name, $float),
      $this->buildTestCase($name, $new_float),
    ];
  }

}
