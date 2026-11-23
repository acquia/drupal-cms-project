<?php

declare(strict_types=1);

namespace Drupal\google_tag\EventSubscriber;

use Drupal\Core\Render\Element;
use Drupal\google_tag\EventCollectorInterface;
use Drupal\search_api\Event\ProcessingResultsEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Search API subscriber.
 */
final class SearchApiSubscriber implements EventSubscriberInterface {

  /**
   * Event Collector Service.
   *
   * @var \Drupal\google_tag\EventCollectorInterface
   */
  private EventCollectorInterface $collector;

  /**
   * Search terms already pushed as a search event, keyed by term.
   *
   * Views pages can render multiple displays that each execute a Search
   * API query against the same keys (e.g. attachment/block displays on a
   * combined search results page), each dispatching
   * SearchApiEvents::PROCESSING_RESULTS separately. Without this guard a
   * single user search would push one 'search' event per display.
   *
   * @var array<string, true>
   */
  private array $searchTerms = [];

  /**
   * SearchApiSubscriber constructor.
   *
   * @param \Drupal\google_tag\EventCollectorInterface $collector
   *   Collector service.
   */
  public function __construct(EventCollectorInterface $collector) {
    $this->collector = $collector;
  }

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::PROCESSING_RESULTS => 'onSearch',
    ];
  }

  /**
   * Fires an event on search.
   *
   * @param \Drupal\search_api\Event\ProcessingResultsEvent $event
   *   Event object.
   */
  public function onSearch(ProcessingResultsEvent $event) {
    $keys = $event->getResults()->getQuery()->getKeys();

    if ($keys !== NULL) {
      $search_term = implode(' ', $this->flattenKeys($keys));
      if (isset($this->searchTerms[$search_term])) {
        return;
      }
      $this->searchTerms[$search_term] = TRUE;
      $this->collector->addEvent('search', [
        'search_term' => $search_term,
      ]);
    }
  }

  /**
   * Flattens parsed search keys into a list of search terms.
   *
   * Parsed keys can nest groups of keys to any depth. Properties such as
   * "#conjunction" and "#negation" are skipped.
   *
   * @param mixed $keys
   *   The search keys, as returned by QueryInterface::getKeys(), or a single
   *   key or group of keys within them.
   *
   * @return string[]
   *   The search terms.
   */
  private function flattenKeys(mixed $keys): array {
    if (!is_array($keys)) {
      return [(string) $keys];
    }
    $terms = [];
    foreach ($keys as $key => $value) {
      if (Element::child($key)) {
        array_push($terms, ...$this->flattenKeys($value));
      }
    }
    return $terms;
  }

}
