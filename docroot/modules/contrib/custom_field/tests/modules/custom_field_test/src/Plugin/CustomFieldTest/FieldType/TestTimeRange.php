<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Plugin\CustomFieldTest\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Time;
use Drupal\custom_field_test\Attribute\TestFieldType;
use Drupal\custom_field_test\Plugin\FieldTypeTestBase;

/**
 * Plugin implementation of the 'time_range' field type test.
 */
#[TestFieldType(
  id: 'time_range',
  label: new TranslatableMarkup('Time range'),
)]
class TestTimeRange extends FieldTypeTestBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultWidget(): array {
    return [
      'id' => 'time_range',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldWidget\TimeRangeWidget',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormatter(): array {
    return [
      'id' => 'time_range_default',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldFormatter\TimeRangeFormatter',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function testCases(string $name, array $settings): array {
    $properties = [
      $name,
      $name . '__end',
      $name . '__duration',
    ];
    $start_time = new Time(13, 40, 30);
    $end_time = new Time(14, 40, 30);
    $start_time2 = new Time(6, 40, 30);
    $end_time2 = new Time(8, 40, 30);
    return [
      $this->buildTestCase($properties, [
        $properties[0] => $start_time->getTimestamp(),
        $properties[1] => $end_time->getTimestamp(),
        $properties[2] => $end_time->getTimestamp() - $start_time->getTimestamp(),
      ]),
      $this->buildTestCase($properties, [
        $properties[0] => $start_time2->getTimestamp(),
        $properties[1] => $end_time2->getTimestamp(),
        $properties[2] => $end_time2->getTimestamp() - $start_time2->getTimestamp(),
      ]),
      $this->buildTestCase($properties, [
        $properties[2] => $start_time->getTimestamp(),
      ]),
    ];
  }

}
