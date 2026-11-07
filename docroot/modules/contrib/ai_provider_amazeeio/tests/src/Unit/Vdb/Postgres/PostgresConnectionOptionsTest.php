<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_provider_amazeeio\Unit\Vdb\Postgres;

use Drupal\ai_provider_amazeeio\Vdb\Postgres\PostgresPgvectorClient;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the libpq options built from the configured run-time parameters.
 *
 * @internal This class is not part of the module's public programming API.
 */
#[CoversClass(PostgresPgvectorClient::class)]
final class PostgresConnectionOptionsTest extends UnitTestCase {

  /**
   * Provides run-time parameters and the options keyword they produce.
   *
   * @return array<string, array{array<string, scalar>, string}>
   *   Test cases keyed by name, each holding parameters and expected options.
   */
  public static function optionsProvider(): array {
    return [
      'none' => [[], ''],
      'single' => [
        ['random_page_cost' => 1.1],
        ";options='-c random_page_cost=1.1'",
      ],
      'multiple' => [
        ['random_page_cost' => '1.1', 'hnsw.ef_search' => 100],
        ";options='-c random_page_cost=1.1 -c hnsw.ef_search=100'",
      ],
      'boolean' => [
        ['enable_seqscan' => FALSE],
        ";options='-c enable_seqscan=off'",
      ],
      'space, quote and backslash' => [
        ['application_name' => "it's a\\b"],
        ";options='-c application_name=it\\'s\\\\ a\\\\\\\\b'",
      ],
    ];
  }

  /**
   * Tests the options keyword appended to the connection string.
   *
   * @param array<string, scalar> $parameters
   *   The run-time parameters.
   * @param string $expected
   *   The expected options keyword.
   */
  #[DataProvider('optionsProvider')]
  public function testBuildOptions(array $parameters, string $expected): void {
    self::assertSame($expected, $this->buildOptions($parameters));
  }

  /**
   * Provides run-time parameters that must be rejected.
   *
   * @return array<string, array{array<string, scalar>}>
   *   Test cases keyed by name, each holding invalid run-time parameters.
   */
  public static function invalidParametersProvider(): array {
    return [
      'name with space' => [['foo bar' => '1']],
      'name injection' => [["x' sslmode=disable" => '1']],
      'name with semicolon' => [['x;drop' => '1']],
      'name with leading digit' => [['1cost' => '1']],
      'value with semicolon' => [['application_name' => 'a;host=evil']],
    ];
  }

  /**
   * Tests that invalid run-time parameters are rejected.
   *
   * @param array<string, scalar> $parameters
   *   The run-time parameters.
   */
  #[DataProvider('invalidParametersProvider')]
  public function testInvalidParametersThrow(array $parameters): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->buildOptions($parameters);
  }

  /**
   * Calls the protected buildOptions() for the given run-time parameters.
   *
   * @param array<string, scalar> $parameters
   *   The run-time parameters.
   */
  private function buildOptions(array $parameters): string {
    $client = new PostgresPgvectorClient(
      $this->createMock(FieldsHelperInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      $parameters,
    );
    return (new \ReflectionMethod($client, 'buildOptions'))->invoke($client);
  }

}
