<?php

declare(strict_types=1);

namespace Drupal\drupical;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Service to render events from the feed.
 *
 * @internal
 */
final class EventsRenderer {

  use StringTranslationTrait;

  /**
   * Constructs an EventsRenderer object.
   *
   * @param \Drupal\drupical\EventsFetcher $eventsFetcher
   *   The EventsFetcher service.
   * @param string $organizeLink
   *   The URL for organizing events.
   * @param string $drupalconLink
   *   The URL for DrupalCon events.
   * @param string $campsLink
   *   The URL for Drupal camps.
   * @param string $drupicalLink
   *   The URL for Drupical.
   * @param string $addEventLink
   *   The URL for adding events.
   */
  public function __construct(
    protected EventsFetcher $eventsFetcher,
    protected string $organizeLink,
    protected string $drupalconLink,
    protected string $campsLink,
    protected string $drupicalLink,
    protected string $addEventLink,
  ) {
  }

  /**
   * Generates the events feed render array.
   *
   * @param int $limit
   *   Maximum number of events to display.
   * @param int $offset
   *   Number of events to skip.
   *
   * @return array
   *   Render array containing the events feed.
   */
  public function render(int $limit = 5, int $offset = 0): array {
    try {
      $total_count = $this->getTotalCount();
      $events = $this->getEvents($offset, $limit);
    }
    catch (\Exception) {
      return [
        '#markup' => $this->t('An error occurred while fetching the events feed, check the logs for more information.'),
      ];
    }

    $displayed_count = count($events);
    $has_more = ($offset + $displayed_count) < $total_count;

    // Events are already sorted by fetcher.
    $featured = [];
    $standard = [];
    foreach ($events as $event) {
      if ($event->featured) {
        $featured[] = $event;
      }
      else {
        $standard[] = $event;
      }
    }

    $build = [
      '#theme' => 'drupical',
      '#featured' => $featured,
      '#standard' => $standard,
      '#count' => $displayed_count,
      '#total_count' => $total_count,
      '#has_more' => $has_more,
      '#offset' => $offset,
      '#limit' => $limit,
      '#organize_link' => $this->organizeLink,
      '#drupalcon_link' => $this->drupalconLink,
      '#camps_link' => $this->campsLink,
      '#drupical_link' => $this->drupicalLink,
      '#add_event_link' => $this->addEventLink,
      '#attached' => [
        'library' => [
          'drupical/drupical',
        ],
      ],
      '#cache' => [
        'max-age' => 3600,
      ],
    ];

    return $build;
  }

  /**
   * Gets total count of events.
   *
   * @return int
   *   Total number of events.
   */
  public function getTotalCount(): int {
    return $this->eventsFetcher->getTotalCount();
  }

  /**
   * Gets events with pagination.
   *
   * @param int $offset
   *   Number of events to skip.
   * @param int $limit
   *   Maximum number of events to return.
   *
   * @return \Drupal\drupical\Event[]
   *   Array of Event objects.
   */
  public function getEvents(int $offset, int $limit): array {
    return $this->eventsFetcher->getEvents($offset, $limit);
  }

  /**
   * Returns all events (for backward compatibility).
   *
   * @return \Drupal\drupical\Event[]
   *   Array of all events.
   */
  public function getAllEvents(): array {
    return $this->eventsFetcher->fetch();
  }

}
