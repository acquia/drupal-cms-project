<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Plugin\CustomFieldTest\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field_test\Attribute\TestFieldType;
use Drupal\custom_field_test\Plugin\FieldTypeTestBase;

/**
 * Plugin implementation of the 'duration' field type test.
 */
#[TestFieldType(
  id: 'duration',
  label: new TranslatableMarkup('Duration'),
)]
class TestDuration extends FieldTypeTestBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultWidget(): array {
    return [
      'id' => 'duration',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldWidget\DurationWidget',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormatter(): array {
    return [
      'id' => 'duration',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldFormatter\DurationFormatter',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function testCases(string $name, array $settings): array {
    return [
      // 1 day in seconds.
      $this->buildTestCase($name, 86400),
      // 1 week in seconds.
      $this->buildTestCase($name, 604800),
      // 1 month in seconds.
      $this->buildTestCase($name, 2592000),
    ];
  }

}
