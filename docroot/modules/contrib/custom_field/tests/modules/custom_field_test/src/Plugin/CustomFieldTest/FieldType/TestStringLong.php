<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Plugin\CustomFieldTest\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field_test\Attribute\TestFieldType;
use Drupal\custom_field_test\Plugin\FieldTypeTestBase;

/**
 * Plugin implementation of the 'string_long' field type test.
 */
#[TestFieldType(
  id: 'string_long',
  label: new TranslatableMarkup('String long'),
)]
class TestStringLong extends FieldTypeTestBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultWidget(): array {
    return [
      'id' => 'textarea',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldWidget\TextareaWidget',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormatter(): array {
    return [
      'id' => 'text_default',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldFormatter\TextDefaultFormatter',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function testCases(string $name, array $settings): array {
    return [
      $this->buildTestCase($name, $this->random->paragraphs(4)),
      $this->buildTestCase($name, $this->random->paragraphs(6)),
    ];
  }

}
