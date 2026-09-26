<?php

namespace Drupal\Tests\smart_date\Unit;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\smart_date\Plugin\Field\FieldType\SmartDateItem;

/**
 * Unit tests for SmartDateItem::schema().
 *
 * Schema() returns a static array and does not call any Drupal services, so
 * it can be exercised without a kernel bootstrap.
 *
 * @coversDefaultClass \Drupal\smart_date\Plugin\Field\FieldType\SmartDateItem
 * @group smart_date
 */
#[Group('smart_date')]
class SmartDateItemSchemaTest extends UnitTestCase {

  /**
   * Stub passed to schema(); the method is static and ignores its argument.
   *
   * @var \Drupal\Core\Field\FieldStorageDefinitionInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $fieldStorageDefinition;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->fieldStorageDefinition = $this->createMock(FieldStorageDefinitionInterface::class);
  }

  // ---------------------------------------------------------------------------
  // Column existence
  // ---------------------------------------------------------------------------

  /**
   * @covers ::schema
   */
  public function testSchemaHasRequiredColumns(): void {
    $schema = SmartDateItem::schema($this->fieldStorageDefinition);
    foreach (['value', 'end_value', 'duration', 'rrule', 'rrule_index', 'timezone'] as $column) {
      $this->assertArrayHasKey($column, $schema['columns'], "Expected column '$column' in schema.");
    }
  }

  // ---------------------------------------------------------------------------
  // Column types
  // ---------------------------------------------------------------------------

  /**
   * @covers ::schema
   */
  public function testValueColumnIsTimestampCompatible(): void {
    $schema = SmartDateItem::schema($this->fieldStorageDefinition);
    $this->assertSame('int', $schema['columns']['value']['type']);
    $this->assertSame('big', $schema['columns']['value']['size'],
      'value column must be a big int to accommodate 64-bit timestamps.');
  }

  /**
   * @covers ::schema
   */
  public function testEndValueColumnIsTimestampCompatible(): void {
    $schema = SmartDateItem::schema($this->fieldStorageDefinition);
    $this->assertSame('int', $schema['columns']['end_value']['type']);
    $this->assertSame('big', $schema['columns']['end_value']['size'],
      'end_value column must be a big int to accommodate 64-bit timestamps.');
  }

  /**
   * @covers ::schema
   */
  public function testDurationColumnIsInt(): void {
    $schema = SmartDateItem::schema($this->fieldStorageDefinition);
    $this->assertSame('int', $schema['columns']['duration']['type']);
    $this->assertArrayNotHasKey('size', $schema['columns']['duration'],
      'duration uses default int size — no size key expected.');
  }

  /**
   * @covers ::schema
   */
  public function testTimezoneColumnIsVarchar32(): void {
    $schema = SmartDateItem::schema($this->fieldStorageDefinition);
    $col = $schema['columns']['timezone'];
    $this->assertSame('varchar', $col['type']);
    $this->assertSame(32, $col['length'],
      'Timezone column must hold at least 32 characters (e.g. "America/Los_Angeles").');
  }

  // ---------------------------------------------------------------------------
  // Indexes
  // ---------------------------------------------------------------------------

  /**
   * @covers ::schema
   */
  public function testSchemaHasIndexes(): void {
    $schema = SmartDateItem::schema($this->fieldStorageDefinition);
    $this->assertArrayHasKey('indexes', $schema);
    foreach (['value', 'end_value', 'rrule', 'rrule_index'] as $index) {
      $this->assertArrayHasKey($index, $schema['indexes'],
        "Expected index '$index' to exist for query performance.");
    }
  }

  /**
   * @covers ::schema
   */
  public function testValueIndexPointsToValueColumn(): void {
    $schema = SmartDateItem::schema($this->fieldStorageDefinition);
    $this->assertContains('value', $schema['indexes']['value']);
  }

  // ---------------------------------------------------------------------------
  // isEmpty
  // ---------------------------------------------------------------------------

  /**
   * Verifies isEmpty() exists; full coverage belongs in a Kernel test.
   *
   * The method requires a bootstrapped field item to call $this->get(), so
   * only the contract is asserted here.
   *
   * @covers ::isEmpty
   */
  public function testIsEmptyMethodExists(): void {
    $this->assertTrue(
      method_exists(SmartDateItem::class, 'isEmpty'),
      'SmartDateItem must implement isEmpty().'
    );
  }

}
