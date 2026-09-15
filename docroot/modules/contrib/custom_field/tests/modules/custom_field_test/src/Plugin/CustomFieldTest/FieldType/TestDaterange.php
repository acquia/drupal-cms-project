<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Plugin\CustomFieldTest\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field_test\Attribute\TestFieldType;
use Drupal\custom_field_test\Plugin\FieldTypeTestBase;

/**
 * Plugin implementation of the 'daterange' field type test.
 */
#[TestFieldType(
  id: 'daterange',
  label: new TranslatableMarkup('Date range'),
)]
class TestDaterange extends FieldTypeTestBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultWidget(): array {
    return [
      'id' => 'daterange_default',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldWidget\DateRangeDefaultWidget',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormatter(): array {
    return [
      'id' => 'daterange_default',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldFormatter\DateRangeDefaultFormatter',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function testCases(string $name, array $settings): array {
    $timezones = \DateTimeZone::listIdentifiers();
    $timezone1 = (int) array_rand($timezones);
    $timezone2 = (int) array_rand($timezones);
    $properties = [
      $name,
      $name . '__end',
      $name . '__timezone',
      $name . '__duration',
    ];
    return [
      $this->buildTestCase($properties, [
        $properties[0] => '2014-01-01T20:00:00',
        $properties[1] => '2015-01-01T20:00:00',
        $properties[2] => $timezones[$timezone1],
      ]),
      $this->buildTestCase($properties, [
        $properties[0] => '2016-11-04T00:21:00',
        $properties[0] => '2017-01-01T20:00:00',
        $properties[2] => $timezones[$timezone2],
      ]),
      // Test for invalid time zone.
      $this->buildTestCase($properties, [
        $properties[0] => '2016-11-04T00:21:00',
        $properties[2] => 'invalid',
      ], TRUE, 'The value you selected is not a valid choice.'),
      // Test for no dates but extra properties.
      $this->buildTestCase($properties, [
        $properties[2] => $timezones[$timezone1],
        $properties[3] => 3600,
      ]),
    ];
  }

}
