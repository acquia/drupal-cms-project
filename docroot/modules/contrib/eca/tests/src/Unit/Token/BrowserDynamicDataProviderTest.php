<?php

namespace Drupal\Tests\eca\Unit\Token;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\eca\Token\Browser;
use Drupal\eca\Token\DataProviderInterface;
use Drupal\eca\Token\DynamicDataProviderInterface;
use Drupal\eca\Token\TokenServices;
use Drupal\eca\PluginManager\Event as EventPluginManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests that Browser::normalizedTokenData() discovers dynamic provider tokens.
 *
 * @see \Drupal\eca\Token\Browser::normalizedTokenData()
 * @see \Drupal\eca\Token\DynamicDataProviderInterface
 */
#[Group('eca')]
#[Group('eca_core')]
class BrowserDynamicDataProviderTest extends TestCase {

  /**
   * Creates a Browser with stubbed dependencies.
   *
   * The EventPluginManager is configured to throw PluginNotFoundException
   * so that getSupportedTokens() finds no #[Token] attributes from the
   * event plugin. This isolates the dynamic provider path.
   *
   * @param \Drupal\eca\Token\TokenServices $tokenServices
   *   The token services mock.
   *
   * @return \Drupal\eca\Token\Browser
   *   The browser instance.
   */
  private function createBrowser(TokenServices $tokenServices): Browser {
    $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
    $eventDispatcher->method('getListeners')->willReturn([]);

    $eventPluginManager = $this->createStub(EventPluginManager::class);
    $eventPluginManager->method('getPluginIdForSystemEvent')->willReturn('test_event_plugin');
    $eventPluginManager->method('getDefinition')->willThrowException(
      new PluginNotFoundException('test_event_plugin')
    );

    $state = $this->createStub(StateInterface::class);
    $state->method('get')->willReturn(5);

    return new Browser(
      $eventDispatcher,
      $tokenServices,
      $eventPluginManager,
      $this->createStub(PrivateTempStoreFactory::class),
      $this->createStub(SharedTempStoreFactory::class),
      $this->createStub(AccountProxyInterface::class),
      $this->createStub(RequestStack::class),
      $this->createStub(TimeInterface::class),
      $state,
    );
  }

  /**
   * Tests that dynamic provider tokens appear in normalizedTokenData().
   *
   * This is the core test for the fix: tokens from a
   * DynamicDataProviderInterface provider (like the queue Task) should be
   * included in the normalized output, even though they have no #[Token]
   * attributes.
   */
  public function testDynamicProviderTokensIncluded(): void {
    $dynamicProvider = $this->createStub(DynamicDataProviderInterface::class);
    $dynamicProvider->method('getAvailableKeys')
      ->willReturn(['entity', 'message']);

    $tokenServices = $this->createStub(TokenServices::class);
    // getTokenData() with no argument returns only "regular" tokens.
    $tokenServices->method('getTokenData')
      ->willReturnCallback(function (?string $key = NULL) {
        if ($key === NULL) {
          return ['env_name' => 'production'];
        }
        $map = [
          'entity' => 'node_object',
          'message' => 'Hello world',
        ];
        return $map[$key] ?? NULL;
      });
    $tokenServices->method('getDataProviders')
      ->willReturn([$dynamicProvider]);
    $tokenServices->method('hasTokenData')
      ->willReturnCallback(function (?string $key = NULL): bool {
        if ($key === NULL) {
          return TRUE;
        }
        return in_array($key, ['env_name', 'entity', 'message'], TRUE);
      });
    $tokenServices->method('getInfo')
      ->willReturn(['types' => [], 'tokens' => []]);

    $browser = $this->createBrowser($tokenServices);
    $result = $browser->normalizedTokenData('test_event');

    // All three tokens should be in the output.
    $this->assertArrayHasKey('env_name', $result);
    $this->assertArrayHasKey('entity', $result);
    $this->assertArrayHasKey('message', $result);

    // Verify the normalized structure.
    $this->assertSame('Env Name', $result['env_name']['label']);
    $this->assertSame('env_name', $result['env_name']['token']);
    $this->assertSame('Entity', $result['entity']['label']);
    $this->assertSame('entity', $result['entity']['token']);
    $this->assertSame('Message', $result['message']['label']);
    $this->assertSame('message', $result['message']['token']);
  }

  /**
   * Tests that regular DataProviderInterface (non-dynamic) is not queried.
   *
   * Providers that do not implement DynamicDataProviderInterface should be
   * ignored by the dynamic key discovery -- they rely on #[Token] attributes.
   */
  public function testNonDynamicProviderIgnored(): void {
    $staticProvider = $this->createStub(DataProviderInterface::class);
    // If the provider were queried, this would fail because
    // DataProviderInterface does not have getAvailableKeys().
    $tokenServices = $this->createStub(TokenServices::class);
    $tokenServices->method('getTokenData')
      ->willReturnCallback(function (?string $key = NULL) {
        if ($key === NULL) {
          return ['env_name' => 'production'];
        }
        return NULL;
      });
    $tokenServices->method('getDataProviders')
      ->willReturn([$staticProvider]);
    $tokenServices->method('hasTokenData')
      ->willReturnCallback(function (?string $key = NULL): bool {
        return $key === NULL || $key === 'env_name';
      });
    $tokenServices->method('getInfo')
      ->willReturn(['types' => [], 'tokens' => []]);

    $browser = $this->createBrowser($tokenServices);
    $result = $browser->normalizedTokenData('test_event');

    // Only the regular token should appear.
    $this->assertArrayHasKey('env_name', $result);
    $this->assertCount(1, $result);
  }

  /**
   * Tests that dynamic tokens do not override existing regular tokens.
   *
   * If a key from a dynamic provider already exists in the regular token
   * data, the regular token takes precedence.
   */
  public function testDynamicTokenDoesNotOverrideExisting(): void {
    $dynamicProvider = $this->createStub(DynamicDataProviderInterface::class);
    $dynamicProvider->method('getAvailableKeys')
      ->willReturn(['entity', 'message']);

    $tokenServices = $this->createStub(TokenServices::class);
    $tokenServices->method('getTokenData')
      ->willReturnCallback(function (?string $key = NULL) {
        if ($key === NULL) {
          // 'entity' already exists as a regular token.
          return ['entity' => 'regular_entity'];
        }
        $map = [
          'entity' => 'dynamic_entity',
          'message' => 'Hello world',
        ];
        return $map[$key] ?? NULL;
      });
    $tokenServices->method('getDataProviders')
      ->willReturn([$dynamicProvider]);
    $tokenServices->method('hasTokenData')
      ->willReturnCallback(function (?string $key = NULL): bool {
        return $key === NULL || in_array($key, ['entity', 'message'], TRUE);
      });
    $tokenServices->method('getInfo')
      ->willReturn(['types' => [], 'tokens' => []]);

    $browser = $this->createBrowser($tokenServices);
    $result = $browser->normalizedTokenData('test_event');

    // Both should be present.
    $this->assertArrayHasKey('entity', $result);
    $this->assertArrayHasKey('message', $result);

    // The regular 'entity' value should not be overridden: its normalized
    // value should come from the regular data ('regular_entity'), not from
    // the dynamic provider.
    $this->assertSame('regular_entity', $result['entity']['value']);
  }

}
