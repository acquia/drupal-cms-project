<?php

namespace Drupal\Tests\smart_date\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use Drupal\smart_date\Plugin\Field\FieldType\SmartDateFieldItemList;

/**
 * Tests smartdate field item storage and default value behavior.
 *
 * @group smart_date
 */
#[Group('smart_date')]
#[RunTestsInSeparateProcesses]
class SmartDateItemTest extends FieldKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'datetime',
    'options',
    'smart_date',
  ];

  /**
   * Field name used by the tests.
   *
   * @var string
   */
  protected string $fieldName = 'field_smart_date';

  /**
   * The field storage used by the tests.
   *
   * @var \Drupal\field\Entity\FieldStorageConfig
   */
  protected FieldStorageConfig $fieldStorage;

  /**
   * The field config used by the tests.
   *
   * @var \Drupal\field\Entity\FieldConfig
   */
  protected FieldConfig $field;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->fieldStorage = FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'type' => 'smartdate',
      'entity_type' => 'entity_test',
    ]);
    $this->fieldStorage->save();

    $this->field = FieldConfig::create([
      'field_storage' => $this->fieldStorage,
      'bundle' => 'entity_test',
      'label' => 'Smart date',
    ]);
    $this->field->save();
  }

  /**
   * Tests that post-2038 values can be saved and reloaded.
   */
  public function testPost2038ValuesSaveReloadAndComputeDates(): void {
    // 2040-01-01 10:00:00 UTC.
    $start = 2209024800;
    $end = $start + 5400;

    $entity = EntityTest::create([
      'name' => $this->randomMachineName(),
      $this->fieldName => [
        [
          'value' => $start,
          'end_value' => $end,
          'duration' => 90,
          'timezone' => 'America/New_York',
        ],
      ],
    ]);
    $this->entityValidateAndSave($entity);

    $reloaded = EntityTest::load($entity->id());
    $item = $reloaded->{$this->fieldName}->first();

    $this->assertSame($start, (int) $item->value);
    $this->assertSame($end, (int) $item->end_value);
    $this->assertSame(90, (int) $item->duration);
    $this->assertSame('America/New_York', $item->timezone);
    $this->assertSame($start, $item->start_time->getTimestamp());
    $this->assertSame($end, $item->end_time->getTimestamp());
  }

  /**
   * Tests SmartDateItem::isEmpty().
   */
  public function testIsEmptyRequiresBothStartAndEndToBeEmpty(): void {
    $entity = EntityTest::create([
      'name' => $this->randomMachineName(),
    ]);

    $empty_item = $entity->{$this->fieldName}->appendItem([
      'value' => NULL,
      'end_value' => NULL,
    ]);
    $this->assertTrue($empty_item->isEmpty());

    $start_only_item = $entity->{$this->fieldName}->appendItem([
      'value' => 1705316400,
      'end_value' => NULL,
    ]);
    $this->assertFalse($start_only_item->isEmpty());

    $end_only_item = $entity->{$this->fieldName}->appendItem([
      'value' => NULL,
      'end_value' => 1705320000,
    ]);
    $this->assertFalse($end_only_item->isEmpty());
  }

  /**
   * Tests default value processing calculates timestamps and duration.
   */
  public function testProcessDefaultValueCalculatesRangeAndRoundsToNextMinute(): void {
    $entity = EntityTest::create([
      'name' => $this->randomMachineName(),
    ]);

    $default_value = [
      [
        'default_date_type' => 'fixed',
        'default_date' => '2040-01-01 10:00:30',
        'default_duration' => 90,
        'default_duration_increments' => "30\n60\n90\ncustom",
        'min' => '',
        'max' => '',
      ],
    ];

    $processed = SmartDateFieldItemList::processDefaultValue($default_value, $entity, $this->field);
    $expected_start = (new \DateTimeImmutable('2040-01-01 10:01:00', new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE)))->getTimestamp();

    $this->assertCount(1, $processed);
    $this->assertSame($expected_start, $processed[0]['value']);
    $this->assertSame($expected_start + 90 * 60, $processed[0]['end_value']);
    $this->assertInstanceOf(DrupalDateTime::class, $processed[0]['date']);
  }

}
