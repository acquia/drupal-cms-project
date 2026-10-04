<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Plugin\CustomFieldTest\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field_test\Attribute\TestFieldType;
use Drupal\custom_field_test\Plugin\FieldTypeTestBase;

/**
 * Plugin implementation of the 'integer' field type test.
 */
#[TestFieldType(
  id: 'integer',
  label: new TranslatableMarkup('Integer'),
)]
class TestInteger extends FieldTypeTestBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultWidget(): array {
    return [
      'id' => 'integer',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldWidget\IntegerWidget',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormatter(): array {
    return [
      'id' => 'number_integer',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldFormatter\IntegerFormatter',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function testCases(string $name, array $settings): array {
    $integer_max = 2147483647;
    $integer_min = -2147483648;
    $violation_min = $integer_min - 1;
    $violation_max = $integer_max + 1;
    $violation_message = $this->t('This value should be between @min and @max.', [
      '@min' => $integer_min,
      '@max' => $integer_max,
    ]);
    // Test min/max settings.
    $settings['min'] = 500;
    $settings['max'] = 600;
    $violation_min_max_message = (string) $this->t('integer: the value must be between @min and @max.', [
      '@min' => $settings['min'],
      '@max' => $settings['max'],
    ]);
    return [
      $this->buildTestCase($name, $violation_max, TRUE, (string) $violation_message),
      $this->buildTestCase($name, $violation_min, TRUE, (string) $violation_message),
      $this->buildTestCase($name, rand(0, 10)),
      $this->buildTestCase($name, rand(11, 20)),
      $this->buildTestCase($name, 499, TRUE, $violation_min_max_message, $settings),
      $this->buildTestCase($name, 601, TRUE, $violation_min_max_message, $settings),
    ];
  }

}
