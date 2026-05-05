<?php

declare(strict_types=1);

namespace Drupal\drupical;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\Utility\Error;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Service to fetch events from the external Drupal.org feed.
 *
 * @internal
 */
final class EventsFetcher {

  /**
   * The configuration settings of this module.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The tempstore service.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected KeyValueStoreInterface $tempStore;

  /**
   * Construct an EventsFetcher service.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The http client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory service.
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $temp_store
   *   The tempstore factory service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param string $feedUrl
   *   The feed url path.
   * @param \Drupal\Core\Locale\CountryManagerInterface $countryManager
   *   The country manager service.
   */
  public function __construct(
    protected ClientInterface $httpClient,
    ConfigFactoryInterface $config,
    KeyValueExpirableFactoryInterface $temp_store,
    protected LoggerInterface $logger,
    protected string $feedUrl,
    protected CountryManagerInterface $countryManager,
  ) {
    $this->config = $config->get('drupical.settings');
    $this->tempStore = $temp_store->get('drupical');
  }

  /**
   * Fetches events from the feed, stopping at first past event.
   *
   * @param bool $force
   *   Whether to force refresh cache.
   *
   * @return \Drupal\drupical\Event[]
   *   Array of Event objects.
   *
   * @throws \Exception
   */
  public function fetch(bool $force = FALSE): array {
    $events = $this->tempStore->get('events');

    if ($force || $events === NULL) {
      $events = [];
      $page = 0;
      $current_time = time();
      $found_past_event = FALSE;

      // Iterate through pages until we find a past event.
      while (!$found_past_event) {
        try {
          $page_url = $this->feedUrl . '&page=' . $page;
          $response = $this->httpClient->get($page_url);
          $feed_content = (string) $response->getBody();
          $data = Json::decode($feed_content);

          if (empty($data['list'])) {
            // No more items.
            break;
          }

          foreach ($data['list'] as $item) {
            // Check if event has ended.
            $end_timestamp = (int) ($item['field_date_of_event']['value2'] ?? 0);

            if ($end_timestamp < $current_time) {
              // Found first past event, stop fetching.
              $found_past_event = TRUE;
              break;
            }

            // Parse and add event.
            $event = $this->parseEvent($item);
            if ($event) {
              $events[] = $event;
            }
          }

          $page++;
        }
        catch (\Exception $e) {
          $this->logger->error(Error::DEFAULT_ERROR_MESSAGE, Error::decodeException($e));
          throw $e;
        }
      }

      // Sort events: 1. featured first, 2. by date ascending (earliest first)
      usort($events, function ($a, $b) {
        // Featured events first.
        if ($a['featured'] !== $b['featured']) {
          return $b['featured'] <=> $a['featured'];
        }
        // Then by date ascending (soonest first)
        return strtotime($a['date_start']) <=> strtotime($b['date_start']);
      });

      // Save the raw array to temp store.
      $this->tempStore->setWithExpire('events', $events, $this->config->get('max_age') ?? 86400);
    }

    // Map the array into an array of Event objects.
    $events = array_map(function ($event) {
      return new Event(
        id: $event['id'],
        title: $event['title'],
        url: $event['url'],
        date_start: $event['date_start'],
        date_end: $event['date_end'],
        location: $event['location'],
        type: $event['type'],
        featured: $event['featured'],
      );
    }, $events);

    return $events;
  }

  /**
   * Parse a single event item from the feed.
   *
   * @param array $item
   *   Raw event data from API.
   *
   * @return array|null
   *   Event data array or NULL if parsing fails.
   */
  protected function parseEvent(array $item): ?array {
    try {
      // Convert timestamps to DATE_ATOM format (with timezone)
      $start_timestamp = (int) ($item['field_date_of_event']['value'] ?? 0);
      $end_timestamp = (int) ($item['field_date_of_event']['value2'] ?? 0);

      // Get event type - first type from array.
      $event_types = $item['field_event_type'] ?? [];
      $type = self::parseType($event_types);

      // Check if DrupalCon event.
      $is_featured = in_array('drupalcon', $event_types, TRUE);

      return [
        'id' => (string) $item['nid'],
        'title' => $item['title'] ?? 'Untitled Event',
        'url' => $item['url'] ?? '',
        'date_start' => gmdate(DATE_ATOM, $start_timestamp),
        'date_end' => gmdate(DATE_ATOM, $end_timestamp),
        'location' => $this->parseLocation($item),
        'type' => $type,
        'featured' => $is_featured,
      ];
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to parse event: @message', [
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Gets the event type mapping.
   *
   * @return array
   *   Array of event type keys and labels.
   */
  public static function getEventTypes(): array {
    return [
      'drupalcon' => 'DrupalCon',
      'training' => 'Training',
      'drupalinitiativemeeting' => 'Initiative meeting',
      'drupalfest' => 'Ceremony/awards',
      'localmeetup' => 'Meetup/interest group',
      'external' => 'External technology event',
      'drupalcamp' => 'Camp',
      'contribution' => 'Contribution',
    ];
  }

  /**
   * Parse event type from field_event_type array.
   *
   * @param array $event_types
   *   Array of event type values.
   *
   * @return string
   *   Mapped event type label.
   */
  public static function parseType(array $event_types): string {
    $type_map = self::getEventTypes();
    $mapped_types = array_map(fn($type) => $type_map[$type] ?? $type, $event_types);
    return implode(', ', $mapped_types);
  }

  /**
   * Parse location from event data.
   *
   * @param array $item
   *   Raw event data from API.
   *
   * @return string
   *   Location string.
   */
  protected function parseLocation(array $item): string {
    $format = $item['field_event_format'] ?? [];

    if (in_array('in_person', $format, TRUE)) {
      // In-person event - show address.
      $locality = $item['field_event_address']['locality'] ?? '';
      $country_code = $item['field_event_address']['country'] ?? '';

      // Convert country code to full name.
      $country_name = '';
      if ($country_code) {
        $countries = $this->countryManager->getList();
        $country_name = $countries[$country_code] ?? $country_code;
      }

      $location_parts = array_filter([$locality, $country_name]);
      return implode(', ', $location_parts);
    }
    elseif (in_array('online', $format, TRUE)) {
      // Online event.
      return 'Online/Virtual';
    }

    return '';
  }

  /**
   * Gets total count of cached events.
   *
   * @return int
   *   Number of events.
   */
  public function getTotalCount(): int {
    $events = $this->fetch();
    return count($events);
  }

  /**
   * Gets events with pagination.
   *
   * @param int $offset
   *   Offset to start from.
   * @param int $limit
   *   Number of events to return.
   *
   * @return \Drupal\drupical\Event[]
   *   Array of Event objects.
   */
  public function getEvents(int $offset, int $limit): array {
    $events = $this->fetch();
    return array_slice($events, $offset, $limit);
  }

}
