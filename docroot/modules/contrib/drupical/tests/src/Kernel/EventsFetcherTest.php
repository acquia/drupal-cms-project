<?php

declare(strict_types=1);

namespace Drupal\Tests\drupical\Kernel;

/**
 * @coversDefaultClass \Drupal\drupical\EventsFetcher
 *
 * @group drupical
 */
class EventsFetcherTest extends DrupicalTestBase {

  /**
   * Tests fetching events from the feed.
   */
  public function testFetchEvents(): void {
    // Create mock event data (future event).
    $future_timestamp = time() + 86400;
    $event_item = [
      'nid' => '12345',
      'title' => 'DrupalCon Test 2025',
      'url' => 'https://www.drupal.org/event/test',
      'field_date_of_event' => [
        'value' => $future_timestamp,
        'value2' => $future_timestamp + 7200,
      ],
      'field_event_type' => ['drupalcon'],
      'field_event_format' => ['in_person'],
      'field_event_address' => [
        'locality' => 'Test City',
        'country' => 'US',
      ],
    ];

    // Add past event to stop pagination.
    $past_timestamp = time() - 86400;
    $past_event = [
      'nid' => '99999',
      'title' => 'Past Event',
      'url' => 'https://www.drupal.org/event/past',
      'field_date_of_event' => [
        'value' => $past_timestamp,
        'value2' => $past_timestamp + 3600,
      ],
      'field_event_type' => ['localmeetup'],
      'field_event_format' => ['online'],
      'field_event_address' => [],
    ];

    // Set mock response with future event and past event to stop pagination.
    $this->setEventItems([[$event_item, $past_event]]);

    // Fetch events.
    $events = $this->container->get('drupical.fetcher')->fetch();

    // Assertions.
    $this->assertCount(1, $events);
    $this->assertSame('12345', $events[0]->id);
    $this->assertSame('DrupalCon Test 2025', $events[0]->title);
    $this->assertSame('https://www.drupal.org/event/test', $events[0]->url);
    $this->assertTrue($events[0]->featured);
    $this->assertSame('Test City, United States', $events[0]->location);
    $this->assertSame('DrupalCon', $events[0]->type);
  }

  /**
   * Tests that past events are skipped.
   */
  public function testSkipPastEvents(): void {
    $past_timestamp = time() - 86400;
    $future_timestamp = time() + 86400;

    $past_event = [
      'nid' => '11111',
      'title' => 'Past Event',
      'url' => 'https://www.drupal.org/event/past',
      'field_date_of_event' => [
        'value' => $past_timestamp,
        'value2' => $past_timestamp + 3600,
      ],
      'field_event_type' => ['localmeetup'],
      'field_event_format' => ['online'],
      'field_event_address' => [],
    ];

    $future_event = [
      'nid' => '22222',
      'title' => 'Future Event',
      'url' => 'https://www.drupal.org/event/future',
      'field_date_of_event' => [
        'value' => $future_timestamp,
        'value2' => $future_timestamp + 3600,
      ],
      'field_event_type' => ['localmeetup'],
      'field_event_format' => ['online'],
      'field_event_address' => [],
    ];

    // Set mock response with future event first, then past event.
    $this->setEventItems([[$future_event, $past_event]]);

    $events = $this->container->get('drupical.fetcher')->fetch();

    // Should only return the future event, stop when past event is found.
    $this->assertCount(1, $events);
    $this->assertSame('22222', $events[0]->id);
    $this->assertSame('Future Event', $events[0]->title);
  }

  /**
   * Tests that featured events are sorted first.
   */
  public function testFeaturedEventsSortedFirst(): void {
    $future_timestamp = time() + 86400;

    $regular_event = [
      'nid' => '11111',
      'title' => 'Regular Meetup',
      'url' => 'https://www.drupal.org/event/meetup',
      'field_date_of_event' => [
        'value' => $future_timestamp,
        'value2' => $future_timestamp + 3600,
      ],
      'field_event_type' => ['localmeetup'],
      'field_event_format' => ['online'],
      'field_event_address' => [],
    ];

    $featured_event = [
      'nid' => '22222',
      'title' => 'DrupalCon Featured',
      'url' => 'https://www.drupal.org/event/drupalcon',
      'field_date_of_event' => [
        'value' => $future_timestamp + 86400,
        'value2' => $future_timestamp + 90000,
      ],
      'field_event_type' => ['drupalcon'],
      'field_event_format' => ['in_person'],
      'field_event_address' => [
        'locality' => 'Test City',
        'country' => 'US',
      ],
    ];

    // Add past event to stop pagination.
    $past_timestamp = time() - 86400;
    $past_event = [
      'nid' => '99999',
      'title' => 'Past Event',
      'url' => 'https://www.drupal.org/event/past',
      'field_date_of_event' => [
        'value' => $past_timestamp,
        'value2' => $past_timestamp + 3600,
      ],
      'field_event_type' => ['localmeetup'],
      'field_event_format' => ['online'],
      'field_event_address' => [],
    ];

    // Set mock response with regular event first, then featured, then past.
    $this->setEventItems([[$regular_event, $featured_event, $past_event]]);

    $events = $this->container->get('drupical.fetcher')->fetch();

    // Featured event should be first despite being later in the feed.
    $this->assertCount(2, $events);
    $this->assertSame('22222', $events[0]->id);
    $this->assertTrue($events[0]->featured);
    $this->assertSame('11111', $events[1]->id);
    $this->assertFalse($events[1]->featured);
  }

  /**
   * Tests that events are cached and force refresh works.
   */
  public function testCaching(): void {
    $future_timestamp = time() + 86400;
    $past_timestamp = time() - 86400;

    $event = [
      'nid' => '12345',
      'title' => 'Cached Event',
      'url' => 'https://www.drupal.org/event/test',
      'field_date_of_event' => [
        'value' => $future_timestamp,
        'value2' => $future_timestamp + 7200,
      ],
      'field_event_type' => ['localmeetup'],
      'field_event_format' => ['online'],
      'field_event_address' => [],
    ];

    $past_event = [
      'nid' => '99999',
      'title' => 'Past Event',
      'url' => 'https://www.drupal.org/event/past',
      'field_date_of_event' => [
        'value' => $past_timestamp,
        'value2' => $past_timestamp + 3600,
      ],
      'field_event_type' => ['localmeetup'],
      'field_event_format' => ['online'],
      'field_event_address' => [],
    ];

    // Set up 3 mock responses (we'll need them for 3 fetches).
    $this->setEventItems([
      [$event, $past_event],
      [$event, $past_event],
      [$event, $past_event],
    ]);

    // First fetch - should make HTTP request.
    $events1 = $this->container->get('drupical.fetcher')->fetch();
    $this->assertCount(1, $events1);
    $this->assertSame('12345', $events1[0]->id);
    $this->assertCount(1, $this->history, 'First fetch should make 1 HTTP request');

    // Second fetch (without force) - should use cache, no new HTTP request.
    $events2 = $this->container->get('drupical.fetcher')->fetch();
    $this->assertCount(1, $events2);
    $this->assertSame('12345', $events2[0]->id);
    $this->assertCount(1, $this->history, 'Second fetch should use cache, still 1 HTTP request');

    // Third fetch with force=TRUE - should ignore cache and make new HTTP
    // request.
    $events3 = $this->container->get('drupical.fetcher')->fetch(TRUE);
    $this->assertCount(1, $events3);
    $this->assertSame('12345', $events3[0]->id);
    $this->assertCount(2, $this->history, 'Force refresh should make a new HTTP request (2 total)');
  }

}
