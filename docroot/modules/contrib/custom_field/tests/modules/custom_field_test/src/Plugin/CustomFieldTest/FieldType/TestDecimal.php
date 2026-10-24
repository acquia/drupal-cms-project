<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Plugin\CustomFieldTest\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field_test\Attribute\TestFieldType;
use Drupal\custom_field_test\Plugin\FieldTypeTestBase;

/**
 * Plugin implementation of the 'decimal' field type test.
 */
#[TestFieldType(
  id: 'decimal',
  label: new TranslatableMarkup('Decimal'),
)]
class TestDecimal extends FieldTypeTestBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultWidget(): array {
    return [
      'id' => 'decimal',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldWidget\DecimalWidget',
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
    $violation_message = 'This value should be a valid number.';
    return [
      $this->buildTestCase($name, '20-40', TRUE, $violation_message),
      $this->buildTestCase($name, 3.14),
      $this->buildTestCase($name, '18.2'),
    ];
  }

}
