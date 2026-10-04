<?php

declare(strict_types=1);

namespace Drupal\drupical;

use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Object containing a single event from the feed.
 *
 * @internal
 */
final class Event {

  /**
   * Construct an Event object.
   *
   * @param string $id
   *   Unique identifier of the event.
   * @param string $title
   *   Title of the event.
   * @param string $url
   *   URL where the event can be seen.
   * @param string $date_start
   *   When the event starts.
   * @param string $date_end
   *   When the event ends.
   * @param string $location
   *   Location of the event.
   * @param string $type
   *   Type of the event (Meetup, WordCamp, DrupalCon, etc).
   * @param bool $featured
   *   Whether this event is featured or not.
   */
  public function __construct(
    public readonly string $id,
    public readonly string $title,
    public readonly string $url,
    public readonly string $date_start,
    public readonly string $date_end,
    public readonly string $location,
    public readonly string $type,
    public readonly bool $featured,
  ) {
  }

  /**
   * Gets the start date in timestamp format.
   *
   * @return int
   *   Date start timestamp.
   */
  public function getDateStartTimestamp(): int {
    return DrupalDateTime::createFromFormat(DATE_ATOM, $this->date_start)->getTimestamp();
  }

  /**
   * Gets the end date in timestamp format.
   *
   * @return int
   *   Date end timestamp.
   */
  public function getDateEndTimestamp(): int {
    return DrupalDateTime::createFromFormat(DATE_ATOM, $this->date_end)->getTimestamp();
  }

}
