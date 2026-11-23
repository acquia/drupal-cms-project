<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\Kernel\Events;

use Drupal\google_tag\Entity\TagContainer;
use Drupal\search_api\Entity\Index;
use Drupal\Tests\google_tag\Kernel\GoogleTagTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Search Api search event test.
 *
 * @group google_tag
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
final class SearchApiSearchEventTest extends GoogleTagTestCase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'search_api',
    'search_api_db',
    'search_api_test_db',
  ];

  /**
   * Tests search event.
   *
   * @param string|array $keys
   *   The search keys to set on the query.
   * @param string $expected
   *   The expected search term.
   *
   * @dataProvider providerKeys
   */
  #[DataProvider('providerKeys')]
  public function testEvent(string|array $keys, string $expected): void {
    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installEntitySchema('search_api_task');
    $this->installConfig(['search_api', 'search_api_test_db']);

    TagContainer::create([
      'id' => 'foo',
      'weight' => 10,
      'tag_container_ids' => ['G-XYZ1234'],
      'events' => ['search' => []],
    ])->save();

    $index = Index::load('database_search_index');
    $query = $index->query();
    $query->keys($keys);
    $query->execute();

    $collector = $this->container->get('google_tag.event_collector');
    $events = $collector->getEvents();
    self::assertCount(1, $events);
    self::assertEquals('search', $events[0]->getName());
    self::assertEquals([
      'search_term' => $expected,
    ], $events[0]->getData());
  }

  /**
   * Data provider for testEvent().
   *
   * @return array
   *   Search keys and the expected search term.
   */
  public static function providerKeys(): array {
    return [
      'string' => ['foo bar baz', 'foo bar baz'],
      'negated term' => ['foo -bar', 'foo bar'],
      // The test index's processors drop the short "ll" token.
      'negated contraction' => [
        "elvis presley -You'll Never Walk Alone",
        'elvis presley You Never Walk Alone',
      ],
      'flat array' => [
        ['#conjunction' => 'AND', 0 => 'foo', 1 => 'bar'],
        'foo bar',
      ],
      // Two fulltext filters with different conjunctions, as combined by
      // the Search API Views fulltext filter, nest groups more than one
      // level deep.
      'nested array' => [
        [
          '#conjunction' => 'AND',
          0 => 'foo',
          1 => [
            '#conjunction' => 'OR',
            0 => 'bar',
            1 => [
              '#conjunction' => 'AND',
              '#negation' => TRUE,
              0 => 'baz',
            ],
          ],
        ],
        'foo bar baz',
      ],
      'numeric term' => [
        ['#conjunction' => 'AND', 0 => 'foo', 1 => 42],
        'foo 42',
      ],
    ];
  }

  /**
   * Tests that repeated queries for the same keys push one search event.
   *
   * A Views page with attachment or block displays runs one Search API query
   * per display, each with the same keys.
   */
  public function testEventOncePerSearchTerm(): void {
    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installEntitySchema('search_api_task');
    $this->installConfig(['search_api', 'search_api_test_db']);

    TagContainer::create([
      'id' => 'foo',
      'weight' => 10,
      'events' => ['search' => []],
    ])->save();

    $index = Index::load('database_search_index');
    foreach (['foo bar', 'foo bar', 'baz', 'foo bar'] as $keys) {
      $query = $index->query();
      $query->keys($keys);
      $query->execute();
    }
    // A query without keys is not a search.
    $index->query()->execute();

    $events = $this->container->get('google_tag.event_collector')->getEvents();
    self::assertCount(2, $events);
    self::assertEquals(['search_term' => 'foo bar'], $events[0]->getData());
    self::assertEquals(['search_term' => 'baz'], $events[1]->getData());
  }

}
