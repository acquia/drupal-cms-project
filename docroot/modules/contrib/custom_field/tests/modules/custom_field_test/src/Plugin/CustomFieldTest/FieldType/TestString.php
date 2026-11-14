<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Plugin\CustomFieldTest\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field_test\Attribute\TestFieldType;
use Drupal\custom_field_test\Plugin\FieldTypeTestBase;

/**
 * Plugin implementation of the 'string' field type test.
 */
#[TestFieldType(
  id: 'string',
  label: new TranslatableMarkup('String'),
)]
class TestString extends FieldTypeTestBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultWidget(): array {
    return [
      'id' => 'text',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldWidget\TextWidget',
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
    $violation_message = (string) $this->t('@name: may not be longer than @max characters.', [
      '@name' => $name,
      '@max' => 255,
    ]);
    return [
      $this->buildTestCase($name, $this->random->word(256), TRUE, $violation_message),
      $this->buildTestCase($name, $this->random->word(mt_rand(10, 255))),
    ];
  }

}
