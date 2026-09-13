<?php

namespace Drupal\Tests\eca\Unit\Token;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\TempStore\SharedTempStore;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\eca\PluginManager\Event as EventPluginManager;
use Drupal\eca\ProcessDebugger;
use Drupal\eca\Token\Browser;
use Drupal\eca\Token\TokenServices;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests that the token Browser no longer expands deduplication markers.
 *
 * Covers issue #3590332: ECA's debug/replay token data is normalized with the
 * dedup markers '@ref' (TOKEN_DATA_REF) and '@prev' (TOKEN_DATA_PREV) to stay
 * compact. The Browser used to expand those markers server-side before the
 * data crossed the API to the Workflow Modeler, re-inflating it to full size
 * (a 70MB+ payload that crashed the client). The fix defers expansion to the
 * modeler frontend: the read paths must now return the compact,
 * marker-bearing data so the markers survive across the API and into exported
 * JSON.
 *
 * This test proves that:
 *   - getHistoryByHash(), getHistoryByEvent() and pollTesting() return data
 *     that STILL CONTAINS the '@ref' / '@prev' markers (i.e. NOT expanded),
 *     and
 *   - the spurious empty-string token key is suppressed during normalization.
 *
 * @see \Drupal\eca\Token\Browser::getHistoryByHash()
 * @see \Drupal\eca\Token\Browser::getHistoryByEvent()
 * @see \Drupal\eca\Token\Browser::pollTesting()
 * @see \Drupal\eca\Token\Browser::normalizedTokenData()
 */
#[Group('eca')]
#[Group('eca_core')]
class BrowserDeferExpansionTest extends TestCase {

  /**
   * Prefix for compressed data, mirroring Browser::COMPRESS_PREFIX.
   */
  private const string COMPRESS_PREFIX = "\x1f\x9d";

  /**
   * Compresses data the same way Browser::compress() does.
   *
   * The Browser stores history in the temp store as a marker-prefixed,
   * gzip-compressed JSON string. The read paths under test decompress that
   * format, so the fixtures must be built identically.
   *
   * @param array $data
   *   The data to compress.
   *
   * @return string
   *   The compressed, prefixed string.
   */
  private function compress(array $data): string {
    return self::COMPRESS_PREFIX . gzcompress(json_encode($data, JSON_THROW_ON_ERROR));
  }

  /**
   * Builds a history array that contains both '@ref' and '@prev' markers.
   *
   * @return array
   *   The marker-bearing history fixture.
   */
  private function markerBearingHistory(): array {
    return [
      [
        'type' => 'started',
        'id' => 'event',
        'data' => [
          'node' => [
            'label' => 'Node',
            'token' => 'node',
            'data' => ['title' => ['label' => 'Title', 'value' => 'Hello']],
          ],
          // A second token pointing at the same entity is stored as a
          // reference marker rather than a full copy.
          'entity' => [
            'label' => 'Entity',
            'token' => 'entity',
            ProcessDebugger::TOKEN_DATA_REF => 'node',
          ],
        ],
      ],
      [
        'type' => 'execute',
        'id' => 'action',
        // Unchanged token data is stored as a "previous" marker.
        'data' => ProcessDebugger::TOKEN_DATA_PREV,
      ],
    ];
  }

  /**
   * Asserts that a history array still contains the dedup markers.
   *
   * @param array $history
   *   The history array returned by a Browser read path.
   */
  private function assertMarkersPreserved(array $history): void {
    $encoded = json_encode($history, JSON_THROW_ON_ERROR);
    $this->assertStringContainsString(
      ProcessDebugger::TOKEN_DATA_REF,
      $encoded,
      'The @ref marker must survive (data must not be expanded server-side).',
    );
    $this->assertStringContainsString(
      ProcessDebugger::TOKEN_DATA_PREV,
      $encoded,
      'The @prev marker must survive (data must not be expanded server-side).',
    );

    // Be explicit about the structure too: the '@prev' entry must still be the
    // raw marker string, not an expanded data array.
    $this->assertSame(
      ProcessDebugger::TOKEN_DATA_PREV,
      $history[1]['data'],
      'The @prev entry must remain the marker string, not an expanded array.',
    );
    // And the '@ref' entry must still carry the reference key, not an
    // inlined copy of the referenced data.
    $this->assertArrayHasKey(
      ProcessDebugger::TOKEN_DATA_REF,
      $history[0]['data']['entity'],
      'The @ref entry must remain a reference, not an expanded copy.',
    );
    $this->assertArrayNotHasKey(
      'data',
      $history[0]['data']['entity'],
      'The @ref entry must not have been expanded into a data sub-tree.',
    );
  }

  /**
   * Tests that getHistoryByHash() returns compact, marker-bearing data.
   */
  public function testGetHistoryByHashKeepsMarkers(): void {
    $hash = 'abc123';
    $compressed = $this->compress($this->markerBearingHistory());

    $privateStore = $this->createStub(PrivateTempStore::class);
    $privateStore->method('get')->willReturnCallback(
      static fn(string $key) => $key === $hash ? $compressed : NULL,
    );

    $privateFactory = $this->createStub(PrivateTempStoreFactory::class);
    $privateFactory->method('get')->willReturn($privateStore);

    $browser = $this->createBrowser($privateFactory, $this->createStub(SharedTempStoreFactory::class));
    $history = $browser->getHistoryByHash($hash);

    $this->assertMarkersPreserved($history);
  }

  /**
   * Tests that getHistoryByEvent() returns compact, marker-bearing data.
   */
  public function testGetHistoryByEventKeepsMarkers(): void {
    $event = 'eca_model::event';
    $compressed = $this->compress($this->markerBearingHistory());

    $sharedStore = $this->createStub(SharedTempStore::class);
    $sharedStore->method('get')->willReturnCallback(
      static fn(string $key) => $key === $event ? $compressed : NULL,
    );

    $sharedFactory = $this->createStub(SharedTempStoreFactory::class);
    $sharedFactory->method('get')->willReturn($sharedStore);

    $browser = $this->createBrowser($this->createStub(PrivateTempStoreFactory::class), $sharedFactory);
    $history = $browser->getHistoryByEvent($event);

    $this->assertMarkersPreserved($history);
  }

  /**
   * Tests that pollTesting() returns compact, marker-bearing data.
   */
  public function testPollTestingKeepsMarkers(): void {
    $jobId = 'job-xyz';
    $event = 'eca_model::event';
    $compressed = $this->compress(['history' => $this->markerBearingHistory()]);

    $sharedStore = $this->createStub(SharedTempStore::class);
    $sharedStore->method('get')->willReturnCallback(
      static function (string $key) use ($jobId, $event, $compressed) {
        return match ($key) {
          'jobid::testing::' . $jobId => $event,
          'testing::' . $event => $compressed,
          default => NULL,
        };
      }
    );

    $sharedFactory = $this->createStub(SharedTempStoreFactory::class);
    $sharedFactory->method('get')->willReturn($sharedStore);

    $browser = $this->createBrowser($this->createStub(PrivateTempStoreFactory::class), $sharedFactory);
    $history = $browser->pollTesting($jobId);

    $this->assertIsArray($history);
    $this->assertMarkersPreserved($history);
  }

  /**
   * Tests that the spurious empty-string token key is suppressed.
   */
  public function testEmptyStringTokenKeySuppressed(): void {
    $tokenServices = $this->createStub(TokenServices::class);
    $tokenServices->method('getInfo')->willReturn(['types' => [], 'tokens' => []]);
    $tokenServices->method('getTokenData')->willReturnCallback(
      static function (?string $key = NULL) {
        if ($key === NULL) {
          // The empty-string key carries a bulky, unusable value tree; the
          // 'message' key is a normal scalar token.
          return ['' => ['huge' => 'tree'], 'message' => 'Hello world'];
        }
        return NULL;
      }
    );
    $tokenServices->method('hasTokenData')->willReturnCallback(
      static fn(?string $key = NULL): bool => $key === NULL || $key === 'message',
    );
    $tokenServices->method('getDataProviders')->willReturn([]);

    $browser = $this->createBrowser(
      $this->createStub(PrivateTempStoreFactory::class),
      $this->createStub(SharedTempStoreFactory::class),
      $tokenServices,
    );
    $result = $browser->normalizedTokenData('test_event');

    $this->assertArrayNotHasKey('', $result, 'The empty-string token key must be suppressed.');
    $this->assertArrayHasKey('message', $result, 'Regular tokens must still be normalized.');
  }

  /**
   * Creates a Browser with stubbed dependencies.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $privateFactory
   *   The private temp store factory.
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $sharedFactory
   *   The shared temp store factory.
   * @param \Drupal\eca\Token\TokenServices|null $tokenServices
   *   The token services, or NULL to use a bare stub.
   *
   * @return \Drupal\eca\Token\Browser
   *   The browser instance.
   */
  private function createBrowser(PrivateTempStoreFactory $privateFactory, SharedTempStoreFactory $sharedFactory, ?TokenServices $tokenServices = NULL): Browser {
    $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
    $eventDispatcher->method('getListeners')->willReturn([]);

    $eventPluginManager = $this->createStub(EventPluginManager::class);
    $eventPluginManager->method('getPluginIdForSystemEvent')
      ->willReturn('test_event_plugin');
    $eventPluginManager->method('getDefinition')
      ->willThrowException(new PluginNotFoundException('test_event_plugin'));

    $state = $this->createStub(StateInterface::class);
    $state->method('get')->willReturnCallback(
      static fn(string $key, $default = NULL) => $default,
    );

    return new Browser(
      $eventDispatcher,
      $tokenServices ?? $this->createStub(TokenServices::class),
      $eventPluginManager,
      $privateFactory,
      $sharedFactory,
      $this->createStub(AccountProxyInterface::class),
      $this->createStub(RequestStack::class),
      $this->createStub(TimeInterface::class),
      $state,
    );
  }

}
