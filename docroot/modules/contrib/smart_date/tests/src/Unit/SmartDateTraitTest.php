<?php

namespace Drupal\Tests\smart_date\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for SmartDateTrait static utility methods.
 *
 * These tests cover pure-PHP logic that requires no Drupal bootstrap:
 *   - isAllDay()
 *   - formatDurationTime()
 *   - settingsFormatNoDate / settingsFormatNoTime / settingsFormatNoTz.
 *
 * @coversDefaultClass \Drupal\smart_date\SmartDateTrait
 * @group smart_date
 */
#[Group('smart_date')]
class SmartDateTraitTest extends UnitTestCase {

  // ---------------------------------------------------------------------------
  // isAllDay
  // ---------------------------------------------------------------------------

  /**
   * @covers ::isAllDay
   * @dataProvider isAllDayProvider
   */
  #[DataProvider('isAllDayProvider')]
  public function testIsAllDay(int $start_ts, int $end_ts, $timezone, bool $expected): void {
    $this->assertSame($expected, SmartDateTraitTestDouble::isAllDay($start_ts, $end_ts, $timezone));
  }

  /**
   * Data provider for testIsAllDay.
   *
   * All timestamps are anchored to UTC to keep results timezone-independent.
   */
  public static function isAllDayProvider(): array {
    $tz       = 'UTC';
    $midnight = (new \DateTimeImmutable('2024-01-15 00:00:00', new \DateTimeZone($tz)))->getTimestamp();
    $end_day  = (new \DateTimeImmutable('2024-01-15 23:59:00', new \DateTimeZone($tz)))->getTimestamp();
    $noon     = (new \DateTimeImmutable('2024-01-15 12:00:00', new \DateTimeZone($tz)))->getTimestamp();
    $one_pm   = (new \DateTimeImmutable('2024-01-15 13:00:00', new \DateTimeZone($tz)))->getTimestamp();
    // Note: isAllDay() uses H:i (no seconds), so 23:59:xx still counts as
    // all-day. Use 23:00 to unambiguously test a non-all-day end time.
    $eleven_pm = (new \DateTimeImmutable('2024-01-15 23:00:00', new \DateTimeZone($tz)))->getTimestamp();

    return [
      'all day UTC string tz'     => [$midnight, $end_day, $tz, TRUE],
      'all day UTC object tz'     => [$midnight, $end_day, new \DateTimeZone($tz), TRUE],
      'start not midnight'        => [$noon, $end_day, $tz, FALSE],
      'end not 23:59 (23:00)'     => [$midnight, $eleven_pm, $tz, FALSE],
      'same time (zero duration)' => [$noon, $noon, $tz, FALSE],
      'normal timed range'        => [$noon, $one_pm, $tz, FALSE],
    ];
  }

  // ---------------------------------------------------------------------------
  // formatDurationTime
  // ---------------------------------------------------------------------------

  /**
   * @covers ::formatDurationTime
   * @dataProvider formatDurationTimeProvider
   */
  #[DataProvider('formatDurationTimeProvider')]
  public function testFormatDurationTime(string $input, string $expected): void {
    $this->assertSame($expected, SmartDateTraitTestDouble::exposedFormatDurationTime($input));
  }

  /**
   * Data provider for testFormatDurationTime.
   */
  public static function formatDurationTimeProvider(): array {
    return [
      'empty string'          => ['', ''],
      '1 hour'                => ['1 hour', 'P1H'],
      '2 hours 30 minutes'    => ['2 hours 30 minutes', 'P2H30M'],
      '1 day 3 hours'         => ['1 day 3 hours', 'P1D3H'],
      '5 minutes'             => ['5 minutes', 'P5M'],
      '1 year 2 days'         => ['1 year 2 days', 'P1Y2D'],
      'singular minute'       => ['1 minute', 'P1M'],
      'hours and minutes'     => ['3 hours 15 minutes', 'P3H15M'],
    ];
  }

  // ---------------------------------------------------------------------------
  // settingsFormatNoDate
  // ---------------------------------------------------------------------------

  /**
   * @covers ::settingsFormatNoDate
   */
  public function testSettingsFormatNoDateClearsDateFormat(): void {
    $settings = ['date_format' => 'F j, Y', 'time_format' => 'g:ia'];
    $result = SmartDateTraitTestDouble::settingsFormatNoDate($settings);
    $this->assertSame('', $result['date_format']);
    $this->assertSame('g:ia', $result['time_format'], 'time_format should be untouched');
  }

  /**
   * @covers ::settingsFormatNoDate
   */
  public function testSettingsFormatNoDateNoKey(): void {
    $settings = ['time_format' => 'g:ia'];
    $result = SmartDateTraitTestDouble::settingsFormatNoDate($settings);
    $this->assertArrayNotHasKey('date_format', $result);
  }

  // ---------------------------------------------------------------------------
  // settingsFormatNoTime
  // ---------------------------------------------------------------------------

  /**
   * @covers ::settingsFormatNoTime
   */
  public function testSettingsFormatNoTimeClearsTimeFormat(): void {
    $settings = ['date_format' => 'F j, Y', 'time_format' => 'g:ia'];
    $result = SmartDateTraitTestDouble::settingsFormatNoTime($settings);
    $this->assertSame('', $result['time_format']);
    $this->assertSame('F j, Y', $result['date_format'], 'date_format should be untouched');
  }

  /**
   * @covers ::settingsFormatNoTime
   */
  public function testSettingsFormatNoTimeNoKey(): void {
    $settings = ['date_format' => 'F j, Y'];
    $result = SmartDateTraitTestDouble::settingsFormatNoTime($settings);
    $this->assertArrayNotHasKey('time_format', $result);
  }

  // ---------------------------------------------------------------------------
  // settingsFormatNoTz
  // ---------------------------------------------------------------------------

  /**
   * @covers ::settingsFormatNoTz
   * @dataProvider settingsFormatNoTzProvider
   */
  #[DataProvider('settingsFormatNoTzProvider')]
  public function testSettingsFormatNoTz(array $input, string $expected_time_format): void {
    $result = SmartDateTraitTestDouble::settingsFormatNoTz($input);
    $this->assertSame($expected_time_format, $result['time_format']);
  }

  /**
   * Data provider for testSettingsFormatNoTz.
   */
  public static function settingsFormatNoTzProvider(): array {
    return [
      'T token stripped'          => [['time_format' => 'g:ia T'], 'g:ia'],
      'e token stripped'          => [['time_format' => 'g:ia e'], 'g:ia'],
      'O token stripped'          => [['time_format' => 'g:ia O'], 'g:ia'],
      'Z token stripped'          => [['time_format' => 'g:ia Z'], 'g:ia'],
      'P token stripped'          => [['time_format' => 'g:ia P'], 'g:ia'],
      'no tz token unchanged'     => [['time_format' => 'g:ia'], 'g:ia'],
      'escaped T not stripped'    => [['time_format' => 'g:ia \T'], 'g:ia \T'],
    ];
  }

  /**
   * @covers ::settingsFormatNoTz
   */
  public function testSettingsFormatNoTzAlsoStripsHourFormat(): void {
    $settings = [
      'time_format'      => 'g:ia T',
      'time_hour_format' => 'ga T',
    ];
    $result = SmartDateTraitTestDouble::settingsFormatNoTz($settings);
    $this->assertSame('g:ia', $result['time_format']);
    $this->assertSame('ga', $result['time_hour_format']);
  }

  /**
   * @covers ::settingsFormatNoTz
   */
  public function testSettingsFormatNoTzNoTimeFormatKey(): void {
    $settings = ['date_format' => 'F j, Y'];
    $result = SmartDateTraitTestDouble::settingsFormatNoTz($settings);
    $this->assertArrayNotHasKey('time_format', $result);
    $this->assertSame('F j, Y', $result['date_format']);
  }

}
