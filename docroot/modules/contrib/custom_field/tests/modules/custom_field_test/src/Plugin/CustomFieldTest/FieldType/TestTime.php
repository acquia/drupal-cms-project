<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Plugin\CustomFieldTest\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Time;
use Drupal\custom_field_test\Attribute\TestFieldType;
use Drupal\custom_field_test\Plugin\FieldTypeTestBase;

/**
 * Plugin implementation of the 'time' field type test.
 */
#[TestFieldType(
  id: 'time',
  label: new TranslatableMarkup('Time'),
)]
class TestTime extends FieldTypeTestBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultWidget(): array {
    return [
      'id' => 'time_widget',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldWidget\TimeWidget',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormatter(): array {
    return [
      'id' => 'time',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldFormatter\TimeFormatter',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function testCases(string $name, array $settings): array {
    $time = new Time(13, 40, 30);
    $new_time = new Time(6, 40, 30);
    // The max time in range is 86400.
    $invalid_time = 90400;
    $violation_message = $this->t('The value @time is not a valid time.', [
      '@time' => $invalid_time,
    ]);
    return [
      $this->buildTestCase($name, $time->getTimestamp()),
      $this->buildTestCase($name, $invalid_time, TRUE, (string) $violation_message),
      $this->buildTestCase($name, $new_time->getTimestamp()),
    ];
  }

}
