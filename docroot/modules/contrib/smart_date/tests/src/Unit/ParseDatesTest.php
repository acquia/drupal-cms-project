<?php

namespace Drupal\Tests\smart_date\Unit;

use PHPUnit\Framework\Attributes\Group;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\Tests\UnitTestCase;
use Drupal\smart_date\Plugin\migrate\process\ParseDates;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Unit tests for ParseDates::transform() — non-rrule code paths.
 *
 * The rrule paths require SmartDateRule::create() / save(), which needs a full
 * kernel.  These tests cover:
 *   - D7-style integer timestamp arrays (single and multi-value)
 *   - Plain-text "START to END" strings
 *   - Pipe-delimited multiple values
 *   - Post-2038 timestamp handling.
 *
 * @coversDefaultClass \Drupal\smart_date\Plugin\migrate\process\ParseDates
 * @group smart_date
 */
#[Group('smart_date')]
class ParseDatesTest extends UnitTestCase {

  /**
   * Plugin under test.
   *
   * @var \Drupal\smart_date\Plugin\migrate\process\ParseDates
   */
  protected ParseDates $plugin;

  /**
   * Migrate executable stub.
   *
   * @var \Drupal\migrate\MigrateExecutableInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected MigrateExecutableInterface $migrateExecutable;

  /**
   * Migration row stub.
   *
   * @var \Drupal\migrate\Row|\PHPUnit\Framework\MockObject\MockObject
   */
  protected Row $row;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // ParseDates calls \Drupal::config('system.date')
    // ->get('timezone')['default'].
    $config_factory = $this->getConfigFactoryStub([
      'system.date' => ['timezone' => ['default' => 'UTC']],
    ]);
    $container = new ContainerBuilder();
    $container->set('config.factory', $config_factory);
    \Drupal::setContainer($container);

    // entity_type / bundle only used in the rrule path; pass empty strings.
    $this->plugin = new ParseDates(
      ['entity_type' => '', 'bundle' => ''],
      'parse_dates',
      []
    );
    $this->migrateExecutable = $this->createMock(MigrateExecutableInterface::class);
    $this->row = $this->createMock(Row::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    \Drupal::setContainer(new ContainerBuilder());
  }

  // ---------------------------------------------------------------------------
  // D7 integer array format
  // ---------------------------------------------------------------------------

  /**
   * @covers ::transform
   */
  public function testTransformD7IntegerArray(): void {
    // Timestamps with a known 60-minute (3600 s) spread.
    // 2024-01-15 10:00:00 UTC.
    $start_ts = 1705316400;
    // 2024-01-15 11:00:00 UTC
    $end_ts = 1705320000;

    $value  = [['value' => $start_ts, 'value2' => $end_ts]];
    $result = $this->plugin->transform($value, $this->migrateExecutable, $this->row, 'field_date');

    $this->assertCount(1, $result);
    $item = $result[0];
    $this->assertSame($start_ts, $item['value']);
    $this->assertSame($end_ts, $item['end_value']);
    $this->assertSame(60, $item['duration']);
    $this->assertSame('UTC', $item['timezone']);
    $this->assertArrayNotHasKey('rrule', $item, 'Non-repeating events must not have an rrule key.');
  }

  /**
   * @covers ::transform
   */
  public function testTransformD7MultipleIntegerItems(): void {
    $start1 = 1705316400;
    $end1   = 1705320000;
    // Next day, same time.
    $start2 = 1705402800;
    $end2   = 1705406400;

    $value  = [
      ['value' => $start1, 'value2' => $end1],
      ['value' => $start2, 'value2' => $end2],
    ];
    $result = $this->plugin->transform($value, $this->migrateExecutable, $this->row, 'field_date');

    $this->assertCount(2, $result);
    $this->assertSame($start1, $result[0]['value']);
    $this->assertSame($start2, $result[1]['value']);
  }

  // ---------------------------------------------------------------------------
  // Plain-text "START to END" format
  // ---------------------------------------------------------------------------

  /**
   * @covers ::transform
   */
  public function testTransformPlainTextFormat(): void {
    // ISO-8601 strings with UTC offset keep the test timezone-independent.
    $value = '2024-01-15T10:00:00+00:00 to 2024-01-15T11:00:00+00:00';

    $result = $this->plugin->transform($value, $this->migrateExecutable, $this->row, 'field_date');

    $this->assertCount(1, $result);
    $item = $result[0];
    $this->assertSame(strtotime('2024-01-15T10:00:00+00:00'), $item['value']);
    $this->assertSame(strtotime('2024-01-15T11:00:00+00:00'), $item['end_value']);
    $this->assertSame(60, $item['duration']);
    $this->assertSame('UTC', $item['timezone']);
  }

  /**
   * A single date with no end (value == value2) is valid.
   *
   * @covers ::transform
   */
  public function testTransformPlainTextSingleDate(): void {
    $value = '2024-03-20T09:00:00+00:00 to 2024-03-20T09:00:00+00:00';

    $result = $this->plugin->transform($value, $this->migrateExecutable, $this->row, 'field_date');

    $this->assertCount(1, $result);
    $this->assertSame(0, $result[0]['duration']);
  }

  // ---------------------------------------------------------------------------
  // Pipe-delimited multiple values
  // ---------------------------------------------------------------------------

  /**
   * @covers ::transform
   */
  public function testTransformPipeDelimitedString(): void {
    $v1 = '2024-01-15T10:00:00+00:00 to 2024-01-15T11:00:00+00:00';
    $v2 = '2024-01-16T14:00:00+00:00 to 2024-01-16T15:00:00+00:00';

    $result = $this->plugin->transform(
      $v1 . '|' . $v2,
      $this->migrateExecutable,
      $this->row,
      'field_date'
    );

    $this->assertCount(2, $result);
    $this->assertSame(strtotime('2024-01-15T10:00:00+00:00'), $result[0]['value']);
    $this->assertSame(strtotime('2024-01-16T14:00:00+00:00'), $result[1]['value']);
  }

  // ---------------------------------------------------------------------------
  // Post-2038 timestamp handling
  // ---------------------------------------------------------------------------

  /**
   * End values over INT_MAX must be retained.
   *
   * @covers ::transform
   */
  public function testTransformRetainsPost2038Values(): void {
    // 2038-01-19 03:13:20 UTC.
    $start_ts = 2147483600;
    // One hour later, beyond the signed 32-bit timestamp limit.
    $end_ts = 2147487200;
    $value = [
      ['value' => $start_ts, 'value2' => $end_ts],
    ];
    $result = $this->plugin->transform($value, $this->migrateExecutable, $this->row, 'field_date');

    $this->assertCount(1, $result,
      'Items whose end_value exceeds 2147483647 must be retained now that Smart Date uses BIGINT storage.');
    $this->assertSame($start_ts, $result[0]['value']);
    $this->assertSame($end_ts, $result[0]['end_value']);
    $this->assertSame(60, $result[0]['duration']);
  }

  /**
   * Values within the 32-bit range must also be retained.
   *
   * Uses a short (1-hour) event to avoid the multi-day expansion path.
   *
   * @covers ::transform
   */
  public function testTransformRetainsValuesWithinYear2038Limit(): void {
    // 2024-01-15 10:00:00 UTC
    $start_ts = 1705316400;
    // 2024-01-15 11:00:00 UTC — well below INT_MAX
    $end_ts = 1705320000;
    $value  = [['value' => $start_ts, 'value2' => $end_ts]];

    $result = $this->plugin->transform($value, $this->migrateExecutable, $this->row, 'field_date');

    $this->assertCount(1, $result,
      'Items whose end_value is within the 32-bit limit must be retained.');
    $this->assertSame($end_ts, $result[0]['end_value']);
  }

}
