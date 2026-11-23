<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_provider_amazeeio\Unit\Vdb\Postgres;

use Drupal\ai_provider_amazeeio\Vdb\Postgres\Plugin\VdbProvider\PostgresProvider;
use Drupal\ai_provider_amazeeio\Vdb\Postgres\PostgresPgvectorClient;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Query\ConditionGroup;
use Drupal\search_api\ServerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests that filter values are quoted regardless of the field's type.
 *
 * @internal This class is not part of the module's public programming API.
 */
#[CoversClass(PostgresProvider::class)]
#[CoversClass(PostgresPgvectorClient::class)]
final class PostgresProviderFilterQuotingTest extends UnitTestCase {

  /**
   * Provides filter values, one plain and one with special characters.
   *
   * @return array<string, array{string}>
   *   Test cases keyed by name, each holding a single filter value.
   */
  public static function valueProvider(): array {
    return [
      'number' => ['150'],
      'special characters' => ['1)) OR (1=1) -- '],
    ];
  }

  /**
   * A numeric field's value must be quoted as a single SQL string literal.
   */
  #[DataProvider('valueProvider')]
  public function testNumericFilterValueIsQuoted(string $value): void {
    $client = new QuotingTestPgvectorClient();
    $provider = (new \ReflectionClass(QuotingTestPostgresProvider::class))
      ->newInstanceWithoutConstructor();
    $provider->client = $client;

    $field = $this->createMock(FieldInterface::class);
    $field->method('getType')->willReturn('integer');
    $field->method('getFieldIdentifier')->willReturn('field_price');

    $server = $this->createMock(ServerInterface::class);
    $server->method('getBackendConfig')->willReturn([
      'database_settings' => [
        'collection' => 'test_collection',
        'database_name' => 'test_db',
      ],
    ]);

    $index = $this->createMock(IndexInterface::class);
    $index->method('id')->willReturn('index_a');
    $index->method('getServerInstance')->willReturn($server);
    $index->method('getField')->with('field_price')->willReturn($field);

    $group = new ConditionGroup();
    $group->addCondition('field_price', $value, '=');

    [$filters] = $provider->exposedProcessConditionGroup($index, $group);

    // The value must appear wrapped as a single quoted SQL literal, so its
    // parentheses cannot alter the surrounding statement.
    self::assertStringContainsString("('" . $value . "')", $filters[0]);
  }

}

/**
 * Exposes the protected filter builder and skips the container / pgsql.
 */
final class QuotingTestPostgresProvider extends PostgresProvider {

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
    return FALSE;
  }

  /**
   * Public wrapper so the protected builder can be exercised directly.
   */
  public function exposedProcessConditionGroup(IndexInterface $index, ConditionGroup $group): array {
    return $this->processConditionGroup($index, $group);
  }

}

/**
 * Recording fake for PostgresPgvectorClient without a live pgsql connection.
 */
final class QuotingTestPgvectorClient extends PostgresPgvectorClient {

  public function __construct() {
    // Skip the parent dependencies; the fake never uses them.
  }

  /**
   * {@inheritdoc}
   */
  public function shouldHaveColumn(FieldInterface $field): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function escapeIdentifierForSql(string $identifier_to_escape, $connection): string {
    return '"' . $identifier_to_escape . '"';
  }

  /**
   * {@inheritdoc}
   */
  public function prepareStringArrayForSql(array $items, $connection): string {
    return "('" . implode("','", $items) . "')";
  }

}
