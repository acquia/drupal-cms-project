<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Plugin\CustomFieldTest\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field_test\Attribute\TestFieldType;
use Drupal\custom_field_test\Plugin\FieldTypeTestBase;

/**
 * Plugin implementation of the 'color' field type test.
 */
#[TestFieldType(
  id: 'color',
  label: new TranslatableMarkup('Color'),
)]
class TestColor extends FieldTypeTestBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultWidget(): array {
    return [
      'id' => 'color',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldWidget\ColorWidget',
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
    return [
      $this->buildTestCase($name, '#000000'),
      // cspell:disable-next-line
      $this->buildTestCase($name, '5bcefa'),
    ];
  }

}
