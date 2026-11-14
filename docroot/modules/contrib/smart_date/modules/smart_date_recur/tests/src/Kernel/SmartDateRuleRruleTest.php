<?php

declare(strict_types=1);

namespace Drupal\Tests\smart_date_recur\Kernel;

use PHPUnit\Framework\Attributes\Group;
use Drupal\KernelTests\KernelTestBase;
use Drupal\smart_date_recur\Entity\SmartDateRule;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that SmartDateRule generates RFC 5545-compliant UNTIL values.
 *
 * Verifies that the RRULE string contains no hyphens in the date portion of
 * UNTIL, and that all-day events use DATE format while timed events use
 * DATE-TIME format.
 *
 * @group smart_date_recur
 * @see https://www.drupal.org/project/smart_date/issues/3557685
 */
#[RunTestsInSeparateProcesses]
#[Group('smart_date_recur')]
class SmartDateRuleRruleTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'datetime',
    'smart_date',
    'smart_date_recur',
  ];

  /**
   * A timed (non-all-day) start timestamp: 2026-04-15 10:00:00 UTC.
   */
  private int $timedStart;

  /**
   * A timed end timestamp: 2026-04-15 11:00:00 UTC.
   */
  private int $timedEnd;

  /**
   * An all-day start timestamp: 2026-04-15 00:00:00 UTC.
   */
  private int $allDayStart;

  /**
   * An all-day end timestamp: 2026-04-15 23:59:00 UTC.
   *
   * IsAllDay() checks date('H:i') == '23:59', so seconds are irrelevant.
   */
  private int $allDayEnd;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('smart_date_rule');
    $this->installEntitySchema('smart_date_override');
    $this->installConfig(['system']);

    $utc               = new \DateTimeZone('UTC');
    $this->timedStart  = (new \DateTime('2026-04-15 10:00:00', $utc))->getTimestamp();
    $this->timedEnd    = (new \DateTime('2026-04-15 11:00:00', $utc))->getTimestamp();
    $this->allDayStart = (new \DateTime('2026-04-15 00:00:00', $utc))->getTimestamp();
    $this->allDayEnd   = (new \DateTime('2026-04-15 23:59:00', $utc))->getTimestamp();
  }

  /**
   * Builds an in-memory SmartDateRule with its timezone pinned to UTC.
   *
   * Pinning to UTC ensures isAllDay() is evaluated consistently regardless of
   * the PHP process timezone.
   */
  private function createRule(int $start, int $end, string $limit): SmartDateRule {
    $rule = SmartDateRule::create([
      'freq'        => 'WEEKLY',
      'start'       => $start,
      'end'         => $end,
      'limit'       => $limit,
      'entity_type' => 'node',
      'bundle'      => 'article',
      'field_name'  => 'field_date',
    ]);
    $rule->setTimezone('UTC');
    return $rule;
  }

  /**
   * Calls the private makeRuleFromParts() and returns the assembled string.
   */
  private function assembleRuleString(SmartDateRule $rule): string {
    $method = new \ReflectionMethod($rule, 'makeRuleFromParts');
    return (string) $method->invoke($rule);
  }

  /**
   * UNTIL for a timed event must use compact date-time format with no hyphens.
   */
  public function testUntilTimedEventFormat(): void {
    $rule = $this->createRule($this->timedStart, $this->timedEnd, 'UNTIL=2026-04-24');
    $rule_string = $this->assembleRuleString($rule);

    $this->assertStringContainsString('UNTIL=20260424T235959', $rule_string);
    $this->assertStringNotContainsString('UNTIL=2026-04-24', $rule_string);
  }

  /**
   * UNTIL for an all-day event must use compact DATE-only format (no time).
   */
  public function testUntilAllDayEventFormat(): void {
    $rule = $this->createRule($this->allDayStart, $this->allDayEnd, 'UNTIL=2026-04-24');
    $rule_string = $this->assembleRuleString($rule);

    $this->assertStringContainsString('UNTIL=20260424', $rule_string);
    $this->assertStringNotContainsString('UNTIL=20260424T', $rule_string);
    $this->assertStringNotContainsString('UNTIL=2026-04-24', $rule_string);
  }

  /**
   * COUNT limits must pass through unchanged.
   */
  public function testCountLimitIsPreserved(): void {
    $rule = $this->createRule($this->timedStart, $this->timedEnd, 'COUNT=5');
    $rule_string = $this->assembleRuleString($rule);

    $this->assertStringContainsString('COUNT=5', $rule_string);
  }

  /**
   * GetAllProperties() must return compact date-time UNTIL for timed events.
   */
  public function testGetAllPropertiesTimedUntil(): void {
    $rule = $this->createRule($this->timedStart, $this->timedEnd, 'UNTIL=2026-04-24');
    $props = $rule->getAllProperties();

    $this->assertSame('UNTIL', $props['limit']);
    $this->assertSame('20260424T235959', $props['limit_val']);
  }

  /**
   * GetAllProperties() must return compact DATE-only UNTIL for all-day events.
   */
  public function testGetAllPropertiesAllDayUntil(): void {
    $rule = $this->createRule($this->allDayStart, $this->allDayEnd, 'UNTIL=2026-04-24');
    $props = $rule->getAllProperties();

    $this->assertSame('UNTIL', $props['limit']);
    $this->assertSame('20260424', $props['limit_val']);
  }

}
