<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Plugin\CustomFieldTest\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field_test\Attribute\TestFieldType;
use Drupal\custom_field_test\Plugin\FieldTypeTestBase;

/**
 * Plugin implementation of the 'boolean' field type test.
 */
#[TestFieldType(
  id: 'boolean',
  label: new TranslatableMarkup('Boolean'),
)]
class TestBoolean extends FieldTypeTestBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultWidget(): array {
    return [
      'id' => 'checkbox',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldWidget\CheckboxWidget',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormatter(): array {
    return [
      'id' => 'boolean',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldFormatter\BooleanFormatter',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function testCases(string $name, array $settings): array {
    return [
      $this->buildTestCase($name, '1'),
      $this->buildTestCase($name, 0),
    ];
  }

}
