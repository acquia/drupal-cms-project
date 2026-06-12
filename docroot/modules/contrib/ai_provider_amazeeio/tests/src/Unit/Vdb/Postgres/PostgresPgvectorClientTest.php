<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_provider_amazeeio\Unit\Vdb\Postgres;

use Drupal\ai_provider_amazeeio\Vdb\Postgres\PostgresPgvectorClient;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests PostgresPgvectorClient::shouldHaveColumn().
 *
 * @internal This class is not part of the module's public programming API.
 */
#[CoversClass(PostgresPgvectorClient::class)]
final class PostgresPgvectorClientTest extends UnitTestCase {

  /**
   * Tests that columns are only created for "Filterable attributes" fields.
   */
  #[DataProvider('indexingOptionsProvider')]
  public function testShouldHaveColumn(?string $indexing_option, bool $expected): void {
    $field = $this->createMock(FieldInterface::class);
    $field->method('getFieldIdentifier')->willReturn('some_field');

    $index = $this->createMock(IndexInterface::class);
    $index->method('id')->willReturn('test_index');
    $field->method('getIndex')->willReturn($index);

    $raw_config = [];
    if ($indexing_option !== NULL) {
      $raw_config['indexing_options']['some_field']['indexing_option'] = $indexing_option;
    }
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('getRawData')->willReturn($raw_config);

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')
      ->with('ai_search.index.test_index')
      ->willReturn($config);

    $client = new PostgresPgvectorClient(
      $this->createMock(FieldsHelperInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $config_factory,
    );

    self::assertSame($expected, $client->shouldHaveColumn($field));
  }

  /**
   * Tests that a field with no attached index never gets a column.
   */
  public function testShouldHaveColumnReturnsFalseWhenFieldHasNoIndex(): void {
    $field = $this->createMock(FieldInterface::class);
    $field->method('getIndex')->willReturn(NULL);

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->expects(self::never())->method('get');

    $client = new PostgresPgvectorClient(
      $this->createMock(FieldsHelperInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $config_factory,
    );

    self::assertFalse($client->shouldHaveColumn($field));
  }

  /**
   * Data provider for testShouldHaveColumn.
   *
   * @return array<string, array{?string, bool}>
   *   Each row maps a case label to [indexing option value or NULL, expected].
   */
  public static function indexingOptionsProvider(): array {
    return [
      'attributes get a column' => ['attributes', TRUE],
      'main content does not' => ['main_content', FALSE],
      'contextual content does not' => ['contextual_content', FALSE],
      'ignore does not' => ['ignore', FALSE],
      'missing entry does not' => [NULL, FALSE],
    ];
  }

}
