<?php

declare(strict_types=1);

namespace Drupal\Tests\drupical\Unit;

use Drupal\drupical\EventsFetcher;
use Drupal\Tests\UnitTestCase;

/**
 * Simple test to ensure that asserts pass.
 *
 * @group drupical
 */
class EventsFetcherUnitTest extends UnitTestCase {

  /**
   * Test the getEventTypes() method.
   *
   * @covers \Drupal\drupical\EventsFetcher::getEventTypes
   */
  public function testGetEventTypes(): void {
    $types = EventsFetcher::getEventTypes();

    $this->assertIsArray($types);
    $this->assertNotEmpty($types);

    // Test specific mappings.
    $this->assertSame('DrupalCon', $types['drupalcon']);
    $this->assertSame('Training', $types['training']);
    $this->assertSame('Initiative meeting', $types['drupalinitiativemeeting']);
    $this->assertSame('Ceremony/awards', $types['drupalfest']);
    $this->assertSame('Meetup/interest group', $types['localmeetup']);
    $this->assertSame('External technology event', $types['external']);
    $this->assertSame('Camp', $types['drupalcamp']);
    $this->assertSame('Contribution', $types['contribution']);
  }

  /**
   * Test the parseType() method.
   *
   * @covers \Drupal\drupical\EventsFetcher::parseType
   *
   * @dataProvider typeProvider
   */
  public function testParseType(array $eventTypes, string $expected): void {
    $this->assertSame($expected, EventsFetcher::parseType($eventTypes));
  }

  /**
   * Data provider for testParseType().
   */
  public static function typeProvider(): array {
    return [
      'single known type' => [
        ['drupalcon'],
        'DrupalCon',
      ],
      'multiple known types' => [
        ['drupalcon', 'training'],
        'DrupalCon, Training',
      ],
      'unknown type' => [
        ['unknown_type'],
        'unknown_type',
      ],
      'mix of known and unknown' => [
        ['drupalcon', 'unknown_type', 'training'],
        'DrupalCon, unknown_type, Training',
      ],
      'empty array' => [
        [],
        '',
      ],
      'camp type' => [
        ['drupalcamp'],
        'Camp',
      ],
      'meetup type' => [
        ['localmeetup'],
        'Meetup/interest group',
      ],
      'all types' => [
        ['drupalcon', 'training', 'drupalcamp', 'localmeetup'],
        'DrupalCon, Training, Camp, Meetup/interest group',
      ],
    ];
  }

}
