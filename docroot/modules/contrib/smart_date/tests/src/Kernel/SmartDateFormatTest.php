<?php

namespace Drupal\Tests\smart_date\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\smart_date\Unit\SmartDateTraitTestDouble;

/**
 * Tests SmartDateTrait formatting behavior with Drupal services.
 *
 * @group smart_date
 */
#[Group('smart_date')]
#[RunTestsInSeparateProcesses]
class SmartDateFormatTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'datetime',
    'smart_date',
  ];

  /**
   * Formatter settings used by the tests.
   *
   * @var array
   */
  protected array $settings = [
    'date_format' => 'Y-m-d',
    'time_format' => 'H:i',
    'time_hour_format' => 'H:i',
    'allday_label' => 'All day',
    'separator' => ' to ',
    'join' => ' ',
    'ampm_reduce' => FALSE,
    'date_first' => TRUE,
    'site_time_toggle' => FALSE,
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->config('system.date')
      ->set('timezone.default', 'UTC')
      ->save();
  }

  /**
   * Tests same-day range formatting.
   */
  public function testFormatSmartDateSameDayRange(): void {
    $start = strtotime('2024-01-15T10:00:00+00:00');
    $end = strtotime('2024-01-15T11:00:00+00:00');

    $this->assertSame(
      '2024-01-15 10:00 to 11:00',
      SmartDateTraitTestDouble::formatSmartDate($start, $end, $this->settings, 'UTC', 'string')
    );
  }

  /**
   * Tests all-day range formatting.
   */
  public function testFormatSmartDateAllDayRange(): void {
    $start = strtotime('2024-01-15T00:00:00+00:00');
    $end = strtotime('2024-01-15T23:59:00+00:00');

    $this->assertSame(
      '2024-01-15 All day',
      SmartDateTraitTestDouble::formatSmartDate($start, $end, $this->settings, 'UTC', 'string')
    );
  }

  /**
   * Tests date-only multi-day range formatting.
   */
  public function testFormatSmartDateDateOnlyRange(): void {
    $settings = $this->settings;
    $settings['time_format'] = '';

    $start = strtotime('2024-01-15T10:00:00+00:00');
    $end = strtotime('2024-01-17T11:00:00+00:00');

    $this->assertSame(
      '2024-01-15 to 2024-01-17',
      SmartDateTraitTestDouble::formatSmartDate($start, $end, $settings, 'UTC', 'string')
    );
  }

}
