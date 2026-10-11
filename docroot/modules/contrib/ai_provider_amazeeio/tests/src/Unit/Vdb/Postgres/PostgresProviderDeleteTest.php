<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_provider_amazeeio\Unit\Vdb\Postgres;

use Drupal\ai_provider_amazeeio\Vdb\Postgres\Plugin\VdbProvider\PostgresProvider;
use Drupal\ai_provider_amazeeio\Vdb\Postgres\PostgresPgvectorClient;
use Drupal\search_api\IndexInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the deletion pipeline of PostgresProvider.
 *
 * Regression tests for https://www.drupal.org/i/3586238: when multiple
 * Search API indexes share one pgvector collection, deleting items from one
 * index must not delete rows for the same drupal_entity_id belonging to
 * other indexes.
 *
 * @internal This class is not part of the module's public programming API.
 */
#[CoversClass(PostgresProvider::class)]
final class PostgresProviderDeleteTest extends UnitTestCase {

  /**
   * The backend configuration used by all tests.
   *
   * @var array
   */
  private array $configuration = [
    'database_settings' => [
      'collection' => 'test_collection',
      'database_name' => 'test_db',
    ],
  ];

  /**
   * Creates the provider under test wired to a recording fake client.
   *
   * The real getClient() resolves a service from the global \Drupal
   * container and getConnection() opens a pgsql connection, so both are
   * stubbed out. The parent plugin constructor is bypassed because none of
   * its dependencies are used by the deletion pipeline.
   */
  private function createProvider(DeleteTestPgvectorClient $client): DeleteTestPostgresProvider {
    /** @var \Drupal\Tests\ai_provider_amazeeio\Unit\Vdb\Postgres\DeleteTestPostgresProvider $provider */
    $provider = (new \ReflectionClass(DeleteTestPostgresProvider::class))->newInstanceWithoutConstructor();
    $provider->client = $client;
    return $provider;
  }

  /**
   * Tests that deleteIndexItems() scopes the deletion to the given index.
   */
  public function testDeleteIndexItemsFiltersByIndexId(): void {
    $client = new DeleteTestPgvectorClient();
    $client->querySearchRows = [['id' => '42']];
    $provider = $this->createProvider($client);

    $index = $this->createMock(IndexInterface::class);
    $index->method('id')->willReturn('index_a');

    $provider->deleteIndexItems($this->configuration, $index, ['entity:node/1:en']);

    self::assertSame(
      ["WHERE drupal_entity_id IN ('entity:node/1:en') AND index_id IN ('index_a')"],
      $client->querySearchFilters
    );
    self::assertSame([['42']], $client->deleteFromCollectionIds);
  }

  /**
   * Tests that deleteItems() keeps the unscoped entity id filter.
   */
  public function testDeleteItemsFiltersByEntityIdOnly(): void {
    $client = new DeleteTestPgvectorClient();
    $client->querySearchRows = [['id' => '42']];
    $provider = $this->createProvider($client);

    $provider->deleteItems($this->configuration, ['entity:node/1:en']);

    self::assertSame(
      ["WHERE drupal_entity_id IN ('entity:node/1:en')"],
      $client->querySearchFilters
    );
    self::assertSame([['42']], $client->deleteFromCollectionIds);
  }

  /**
   * Tests that getVdbIds() with no Drupal ids does not touch the database.
   */
  public function testGetVdbIdsWithEmptyDrupalIdsReturnsEmpty(): void {
    $client = new DeleteTestPgvectorClient();
    $provider = $this->createProvider($client);

    self::assertSame([], $provider->getVdbIds('test_collection', []));
    self::assertSame([], $client->querySearchFilters);
    self::assertSame([], $client->deleteFromCollectionIds);
  }

  /**
   * Tests that deleteAllIndexItems() deletes by index id on the client.
   */
  public function testDeleteAllIndexItemsDeletesByIndexId(): void {
    $client = new DeleteTestPgvectorClient();
    $provider = $this->createProvider($client);

    $index = $this->createMock(IndexInterface::class);
    $index->method('id')->willReturn('index_a');

    $provider->deleteAllIndexItems($this->configuration, $index);

    self::assertSame(
      [['test_collection', 'index_a']],
      $client->deleteByIndexIdCalls
    );
  }

}

/**
 * PostgresProvider double that avoids the global container and pgsql.
 */
final class DeleteTestPostgresProvider extends PostgresProvider {

  /**
   * The fake client returned by getClient().
   */
  public PostgresPgvectorClient $client;

  /**
   * {@inheritdoc}
   */
  public function getClient(): PostgresPgvectorClient {
    return $this->client;
  }

  /**
   * {@inheritdoc}
   */
  public function getConnection(string $database = 'default'): \PDO|false {
    // The fake client never uses the connection.
    return FALSE;
  }

}

/**
 * Recording fake for PostgresPgvectorClient.
 *
 * A hand-written fake instead of a PHPUnit mock because the real methods
 * type-hint \PDO, which requires a live connection to instantiate; the
 * overrides widen the parameter so the provider can pass FALSE.
 */
final class DeleteTestPgvectorClient extends PostgresPgvectorClient {

  /**
   * Filters strings received by querySearch(), in call order.
   *
   * @var string[]
   */
  public array $querySearchFilters = [];

  /**
   * Id arrays received by deleteFromCollection(), in call order.
   *
   * @var array<int, array>
   */
  public array $deleteFromCollectionIds = [];

  /**
   * Collection name and index id pairs received by deleteByIndexId().
   *
   * @var array<int, array{string, string}>
   */
  public array $deleteByIndexIdCalls = [];

  /**
   * Rows returned by querySearch().
   *
   * @var array<int, array>
   */
  public array $querySearchRows = [];

  public function __construct() {
    // Skip the parent dependencies; the fake never uses them.
  }

  /**
   * {@inheritdoc}
   */
  public function prepareStringArrayForSql(array $items, $connection): string {
    // Mimic the real output without needing a pgsql connection to escape.
    return "('" . implode("','", $items) . "')";
  }

  /**
   * {@inheritdoc}
   */
  public function querySearch(string $collection_name, array $output_fields, string $filters, int $limit, int $offset, $connection): array {
    $this->querySearchFilters[] = $filters;
    return $this->querySearchRows;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteFromCollection(string $collection_name, array $ids, $connection): void {
    $this->deleteFromCollectionIds[] = $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteByIndexId(string $collection_name, string $index_id, $connection): void {
    $this->deleteByIndexIdCalls[] = [$collection_name, $index_id];
  }

}
