<?php

declare(strict_types=1);

// cspell:ignore corge quux
namespace Drupal\Tests\ui_icons\Unit\Controller;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\Core\Theme\Icon\IconDefinitionInterface;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\Tests\Core\Theme\Icon\IconTestTrait;
use Drupal\ui_icons\IconSearch;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Test the IconSearch class.
 *
 * @internal
 */
#[CoversClass(IconSearch::class)]
#[Group('ui_icons')]
class IconSearchTest extends TestCase {

  use IconTestTrait;

  /**
   * The container.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  private ContainerBuilder $container;

  /**
   * The icon pack manager.
   *
   * @var \Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface
   */
  private IconPackManagerInterface $iconPackManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  private RendererInterface $renderer;

  /**
   * The icon search service.
   *
   * @var \Drupal\ui_icons\IconSearch
   */
  private IconSearch $iconSearch;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container = new ContainerBuilder();
    \Drupal::setContainer($this->container);

    $this->iconPackManager = $this->createMock(IconPackManagerInterface::class);
    $this->renderer = $this->createMock(RendererInterface::class);
    $this->renderer
      ->method('renderInIsolation')
      ->willReturn(new Markup('_rendered_'));

    $this->iconSearch = new IconSearch(
      $this->iconPackManager,
      $this->renderer,
      // @todo test the cache.
      $this->createMock(CacheBackendInterface::class),
    );
  }

  /**
   * Test the _construct method.
   */
  public function testConstructor(): void {
    $iconSearch = new IconSearch(
      $this->createMock(IconPackManagerInterface::class),
      $this->createMock(RendererInterface::class),
      $this->createMock(CacheBackendInterface::class)
    );

    $this->assertInstanceOf(IconSearch::class, $iconSearch);
  }

  /**
   * Provide data for testSearch.
   *
   * @return \Generator
   *   The test cases.
   */
  public static function searchDataProviderId(): iterable {
    yield 'empty' => [
      'query' => '',
    ];

    yield 'space' => [
      'query' => ' ',
    ];

    yield 'one char' => [
      'query' => 'a',
    ];

    yield 'one char spaces' => [
      'query' => ' a ',
    ];

    yield 'no words' => [
      'query' => '?!%$*+',
    ];

    // Test the id based search.
    yield 'id exact full' => [
      'query' => '_pack_match_:_match_',
      'allowed_icon_pack' => [],
      'icons' => [
        'foo:bar',
        '_pack_match_:_match_',
        'baz:corge',
      ],
      'expected' => [
        '_pack_match_:_match_',
      ],
    ];

    yield 'id exact full wrong pack' => [
      'query' => '_match_:_match_',
      'allowed_icon_pack' => ['other'],
      'icons' => [
        'foo:bar',
        '_match_:_match_',
        'baz:corge',
      ],
      'expected' => NULL,
    ];

    yield 'id exact full good pack' => [
      'query' => '_match_pack_:_match_',
      'allowed_icon_pack' => ['_match_pack_'],
      'icons' => [
        'foo:bar',
        '_match_pack_:_match_',
        'baz:corge',
      ],
      'expected' => [
        '_match_pack_:_match_',
      ],
    ];

    yield 'id exact' => [
      'query' => '_match_',
      'allowed_icon_pack' => [],
      'icons' => [
        'bar:_match_',
        'baz:corge',
        'foo:_match_',
      ],
      'expected' => [
        'bar:_match_',
        'foo:_match_',
      ],
    ];

    yield 'id exact wrong pack' => [
      'query' => '_match_',
      'allowed_icon_pack' => ['other'],
      'icons' => [
        'bar:_match_',
        'baz:corge',
        'foo:_match_',
      ],
      'expected' => NULL,
    ];

    yield 'id exact good pack' => [
      'query' => '_match_',
      'allowed_icon_pack' => ['_match_pack_'],
      'icons' => [
        'bar:_match_',
        'baz:corge',
        '_match_pack_:_match_',
      ],
      'expected' => [
        '_match_pack_:_match_',
      ],
    ];

    yield 'id exact multiple results' => [
      'query' => '_match_',
      'allowed_icon_pack' => [],
      'icons' => [
        'foo:_match_',
        'bar:_match_',
        'bar:bar',
        'baz:_match_',
        'baz:baz',
        'corge:_match_',
      ],
      'expected' => [
        'foo:_match_',
        'bar:_match_',
        'baz:_match_',
        'corge:_match_',
      ],
    ];

    yield 'id exact multiple results wrong pack' => [
      'query' => '_match_',
      'allowed_icon_pack' => ['other'],
      'icons' => [
        'foo:_match_',
        'bar:_match_',
        'bar:bar',
        'baz:_match_',
        'baz:baz',
        'corge:_match_',
      ],
      'expected' => NULL,
    ];

    yield 'id exact multiple results good packs' => [
      'query' => '_match_',
      'allowed_icon_pack' => ['_match_pack_', '_match_pack_2_'],
      'icons' => [
        'bar:_match_',
        'bar:bar',
        '_match_pack_:_match_',
        'baz:_match_',
        'baz:baz',
        '_match_pack_2_:_match_',
      ],
      'expected' => [
        '_match_pack_:_match_',
        '_match_pack_2_:_match_',
      ],
    ];
  }

  /**
   * Provide data for testSearch.
   *
   * @return \Generator
   *   The test cases.
   */
  public static function searchDataProviderWord(): iterable {
    // Test words search.
    yield 'word result' => [
      'query' => 'fo',
      'allowed_icon_pack' => [],
      'icons' => [
        'bar:foo',
        'bar:bar',
        'baz: this is foo',
        'baz:baz',
        'corge:bar',
        'corge:foo or what? ',
        'qux:bar',
      ],
      'expected' => [
        'bar:foo',
        'baz: this is foo',
        'corge:foo or what? ',
      ],
    ];

    yield 'words with one match' => [
      'query' => 'part bar',
      'allowed_icon_pack' => [],
      'icons' => [
        'foo:_partial_barista_',
        'qux:quux',
        'baz:corge',
        'baz:baz',
      ],
      'expected' => [
        'foo:_partial_barista_',
      ],
    ];

    yield 'words with one match wrong pack' => [
      'query' => 'part bar',
      'allowed_icon_pack' => ['other'],
      'icons' => [
        'foo:_partial_barista_',
        'qux:quux',
        'baz:corge',
        'baz:baz',
      ],
      'expected' => NULL,
    ];

    yield 'words with one match good pack' => [
      'query' => 'part bar',
      'allowed_icon_pack' => ['_match_pack_'],
      'icons' => [
        '_match_pack_:_partial_barista_',
        'qux:quux',
        'baz:corge',
        'baz:baz',
      ],
      'expected' => [
        '_match_pack_:_partial_barista_',
      ],
    ];

    yield 'words with multiple match' => [
      'query' => 'foo bar',
      'allowed_icon_pack' => [],
      'icons' => [
        'foo:_partial_barista_',
        'qux:quux',
        'baz: some foolish',
        'baz:baz',
      ],
      'expected' => [
        'foo:_partial_barista_',
        'baz: some foolish',
      ],
    ];

    yield 'words with multiple match and one pack' => [
      'query' => 'foo bar',
      'allowed_icon_pack' => ['foo'],
      'icons' => [
        'foo:_partial_barista_',
        'qux:quux',
        'baz: some foolish',
        'baz:baz',
      ],
      'expected' => [
        'foo:_partial_barista_',
      ],
    ];

    yield 'words with multiple match and multiple pack' => [
      'query' => 'part bar',
      'allowed_icon_pack' => ['_match_pack_', '_other_match_pack'],
      'icons' => [
        '_match_pack_:_partial_barista_',
        'qux:quux',
        '_match_pack_: some foolish',
        'baz:baz',
      ],
      'expected' => [
        '_match_pack_:_partial_barista_',
      ],
    ];

    yield 'words specials chars ignored with multiple match' => [
      'query' => '!foo? !ùµ$::!bar.çà',
      'allowed_icon_pack' => [],
      'icons' => [
        'foo:_partial_barista_',
        'qux:quux',
        'baz: some foolish',
        'baz:baz',
      ],
      'expected' => [
        'foo:_partial_barista_',
        'baz: some foolish',
      ],
    ];
  }

  /**
   * Tests the search method of the IconSearch.
   *
   * @param string $query
   *   The search query to test.
   * @param array $allowed_icon_pack
   *   The limited allowed icon pack to test.
   * @param array $icons
   *   The icons returned by IconPackManager::getIcons().
   * @param array|null $expected
   *   The expected result values.
   */
  #[DataProvider('searchDataProviderId')]
  #[DataProvider('searchDataProviderWord')]
  public function testSearch(string $query, array $allowed_icon_pack = [], array $icons = [], ?array $expected = NULL): void {
    $this->preparePackManagerMock($icons, $allowed_icon_pack);
    $result = $this->iconSearch->search(
      $query,
      $allowed_icon_pack,
      5,
      [ResultCallback::class, 'testCreateResultEntry'],
    );

    if (NULL === $expected) {
      $this->assertEmpty($result);
      return;
    }

    $this->assertCount(count($expected), $result);

    foreach ($expected as $index => $expected_icon_id) {
      $this->assertArrayHasKey('_test_callback_', $result[$index]);
      $this->assertEquals($expected_icon_id, $result[$index]['_test_callback_']);
    }
  }

  /**
   * Helper to mock the iconPackManager getIcon() and getIcons() method.
   *
   * @param array $icons
   *   The icons returned by IconPackManager::getIcons().
   * @param array $allowed_icon_pack
   *   The limited allowed icon pack to test.
   */
  private function preparePackManagerMock(array $icons = [], array $allowed_icon_pack = []): void {
    if (!empty($allowed_icon_pack)) {
      foreach ($icons as $key => $icon_full_id) {
        [$pack_id] = explode(IconDefinition::ICON_SEPARATOR, $icon_full_id);
        if (!in_array($pack_id, $allowed_icon_pack)) {
          unset($icons[$key]);
        }
      }
    }

    $this->iconPackManager
      ->method('getIcons')
      ->with($allowed_icon_pack)
      ->willReturn(array_flip($icons));

    $icons_returned_map = [];
    foreach ($icons as $icon_full_id) {
      [$pack_id, $icon_id] = explode(IconDefinition::ICON_SEPARATOR, $icon_full_id);
      $icons_returned_map[] = [
        $icon_full_id,
        $this->createTestIcon([
          'pack_id' => $pack_id,
          'icon_id' => $icon_id,
        ]),
      ];
    }

    $this->iconPackManager
      ->method('getIcon')
      ->willReturnMap($icons_returned_map);
  }

}

/**
 * Test callback class.
 */
class ResultCallback {

  /**
   * Test callback icon result.
   *
   * @param \Drupal\Core\Theme\Icon\IconDefinitionInterface $icon
   *   The icon to process.
   * @param \Drupal\Core\Render\Markup $renderable
   *   The icon preview renderable.
   *
   * @return array
   *   The icon result with key '_test_callback_'.
   */
  // @phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
  public static function testCreateResultEntry(IconDefinitionInterface $icon, Markup $renderable): ?array {
    return ['_test_callback_' => $icon->getId()];
  }

}
