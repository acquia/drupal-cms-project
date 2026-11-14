<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Plugin\CustomFieldTest\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field_test\Attribute\TestFieldType;
use Drupal\custom_field_test\Plugin\FieldTypeTestBase;

/**
 * Plugin implementation of the 'datetime' field type test.
 */
#[TestFieldType(
  id: 'datetime',
  label: new TranslatableMarkup('Datetime'),
)]
class TestDatetime extends FieldTypeTestBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultWidget(): array {
    return [
      'id' => 'datetime_default',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldWidget\DateTimeDefaultWidget',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormatter(): array {
    return [
      'id' => 'datetime_default',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldFormatter\DateTimeDefaultFormatter',
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
      $name . '__timezone',
    ];
    return [
      $this->buildTestCase($properties, [
        $properties[0] => '2014-01-01T20:00:00',
        $properties[1] => $timezones[$timezone1],
      ]),
      $this->buildTestCase($properties, [
        $properties[0] => '2016-11-04T00:21:00',
        $properties[1] => $timezones[$timezone2],
      ]),
      // Test for invalid time zone.
      $this->buildTestCase($properties, [
        $properties[0] => '2016-11-04T00:21:00',
        $properties[1] => 'invalid',
      ], TRUE, 'The value you selected is not a valid choice.'),
      // Test for time zone only.
      $this->buildTestCase($properties, [
        $properties[1] => $timezones[$timezone1],
      ]),
    ];
  }

}
