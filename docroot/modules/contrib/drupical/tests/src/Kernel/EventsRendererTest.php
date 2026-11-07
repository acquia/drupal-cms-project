<?php

declare(strict_types=1);

namespace Drupal\Tests\drupical\Kernel;

use GuzzleHttp\Psr7\Response;

/**
 * @coversDefaultClass \Drupal\drupical\EventsRenderer
 *
 * @group drupical
 */
class EventsRendererTest extends DrupicalTestBase {

  /**
   * Tests rendered output when something goes wrong.
   */
  public function testRendererException(): void {
    $this->setTestFeedResponses([
      new Response(403),
    ]);
    $render = $this->container->get('drupical.renderer')->render();
    $this->assertArrayHasKey('#markup', $render);
    $this->assertStringContainsString('error occurred', (string) $render['#markup']);
  }

  /**
   * Tests rendered valid content.
   */
  public function testRendererContent(): void {
    $future_timestamp = time() + 86400;
    $past_timestamp = time() - 86400;

    $featured_event = [
      'nid' => '1001',
      'title' => 'DrupalCon Test',
      'url' => 'https://www.drupal.org/event/drupalcon',
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

    $regular_event = [
      'nid' => '1002',
      'title' => 'Regular Meetup',
      'url' => 'https://www.drupal.org/event/meetup',
      'field_date_of_event' => [
        'value' => $future_timestamp + 86400,
        'value2' => $future_timestamp + 90000,
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

    $this->setEventItems([[$featured_event, $regular_event, $past_event]]);
    $render = $this->container->get('drupical.renderer')->render();

    // Check render array structure.
    $this->assertEquals('drupical', $render['#theme']);
    $this->assertEquals(2, $render['#count']);
    $this->assertEquals(2, $render['#total_count']);

    // Check featured events.
    $this->assertCount(1, $render['#featured']);
    $this->assertEquals('1001', $render['#featured'][0]->id);
    $this->assertEquals('DrupalCon Test', $render['#featured'][0]->title);

    // Check standard events.
    $this->assertCount(1, $render['#standard']);
    $this->assertEquals('1002', $render['#standard'][0]->id);
    $this->assertEquals('Regular Meetup', $render['#standard'][0]->title);
  }

  /**
   * Tests pagination.
   */
  public function testRendererPagination(): void {
    $future_timestamp = time() + 86400;
    $past_timestamp = time() - 86400;

    $events = [];
    for ($i = 1; $i <= 10; $i++) {
      $events[] = [
        'nid' => (string) $i,
        'title' => 'Event ' . $i,
        'url' => 'https://www.drupal.org/event/' . $i,
        'field_date_of_event' => [
          'value' => $future_timestamp + ($i * 3600),
          'value2' => $future_timestamp + ($i * 3600) + 7200,
        ],
        'field_event_type' => ['localmeetup'],
        'field_event_format' => ['online'],
        'field_event_address' => [],
      ];
    }

    // Add past event to stop pagination.
    $events[] = [
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

    $this->setEventItems([$events]);

    // Render first 5 events.
    $render = $this->container->get('drupical.renderer')->render(5, 0);
    $this->assertEquals(5, $render['#count']);
    $this->assertEquals(10, $render['#total_count']);
    $this->assertTrue($render['#has_more']);

    // Render next 5 events.
    $render = $this->container->get('drupical.renderer')->render(5, 5);
    $this->assertEquals(5, $render['#count']);
    $this->assertEquals(10, $render['#total_count']);
    $this->assertFalse($render['#has_more']);
  }

}
