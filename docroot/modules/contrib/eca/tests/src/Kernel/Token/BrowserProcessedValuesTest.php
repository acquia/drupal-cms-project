<?php

namespace Drupal\Tests\eca\Kernel\Token;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Token\Browser;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the lifetime of the normalized value cache in the token Browser.
 *
 * Covers issue #3590396: Browser is a plain shared service, so a single
 * instance lives for the whole container lifetime. Its $processedValues cache
 * was keyed by token name only and never invalidated, which made it outlive
 * the execution chain it was built for. That is wrong on two counts:
 *
 * - Memory: in a long-running process (drush cron, a queue worker, a batch)
 *   the cache accumulates a fully normalized sub-tree per token key and is
 *   never released.
 * - Correctness: the cache validity hash was computed over the raw token
 *   *value*. For tokens contributed by a data provider the value stored in
 *   the token data array is the #[Token] attribute object, which never
 *   changes, so the hash never changed either while the data the attribute
 *   resolves to did.
 *
 * The cache is therefore scoped to a single execution chain: the Processor
 * clears it at the root execution boundary, the same place it already clears
 * its execution history.
 *
 * Issue #3590431 then fixed the validity hash itself, so the correctness
 * half above is history: the hash is taken over the resolved data and notices
 * a change wherever it happens, including within one chain. Scoping the cache
 * remains necessary for the memory half.
 *
 * @see \Drupal\eca\Token\Browser::normalizedTokenData()
 * @see \Drupal\eca\Token\Browser::resetProcessedValues()
 * @see \Drupal\eca\Processor::execute()
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class BrowserProcessedValuesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'modeler_api',
  ];

  /**
   * The token browser.
   *
   * @var \Drupal\eca\Token\Browser
   */
  protected Browser $browser;

  /**
   * The ECA token services.
   *
   * @var \Drupal\eca\Token\TokenInterface
   */
  protected $tokenServices;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'user']);
    $this->browser = \Drupal::service('eca.token_browser');
    $this->tokenServices = \Drupal::service('eca.token_services');
  }

  /**
   * Reads the private normalized value cache of the token browser.
   *
   * @return array
   *   The current content of Browser::$processedValues.
   */
  private function processedValues(): array {
    return (new \ReflectionProperty(Browser::class, 'processedValues'))
      ->getValue($this->browser);
  }

  /**
   * Tests that the cache is populated and that the reset empties it.
   *
   * This is what ::resetProcessedValues() is still for. Every cached entry
   * holds a fully normalized sub-tree, and the browser is a plain shared
   * service, so in a long-running single process nothing would ever release
   * them. Correctness no longer depends on the reset: since #3590431 the
   * validity hash is taken over the resolved data, so a changed value is
   * noticed whether or not the cache was cleared.
   *
   * @see \Drupal\Tests\eca\Kernel\Token\BrowserValidityHashTest
   */
  public function testResetClearsTheNormalizedValueCache(): void {
    $user = User::create([
      'name' => 'cached_user',
      'mail' => 'cached@example.com',
    ]);
    $user->save();
    $this->tokenServices->addTokenData('account', $user);

    $this->assertSame([], $this->processedValues(), 'The cache must start out empty.');

    $this->browser->normalizedTokenData('test_event');
    $this->assertArrayHasKey(
      'account',
      $this->processedValues(),
      'Normalizing token data must populate the cache.',
    );

    $this->browser->resetProcessedValues();
    $this->assertSame(
      [],
      $this->processedValues(),
      'Resetting must release every cached sub-tree.',
    );
  }

  /**
   * Tests that the cache survives repeated normalization within one chain.
   *
   * The cache exists so that the many debug steps of a single execution chain
   * do not each re-normalize the same unchanged token data. Only an explicit
   * reset may drop it, never a plain normalization pass, otherwise the whole
   * point of the cache is lost.
   */
  public function testCacheIsRetainedAcrossStepsWithinOneChain(): void {
    $user = User::create([
      'name' => 'step_user',
      'mail' => 'step@example.com',
    ]);
    $user->save();
    $this->tokenServices->addTokenData('account', $user);

    $first = $this->browser->normalizedTokenData('test_event');
    $cachedAfterFirst = $this->processedValues();
    $second = $this->browser->normalizedTokenData('test_event');

    $this->assertNotEmpty($cachedAfterFirst);
    $this->assertSame(
      $cachedAfterFirst,
      $this->processedValues(),
      'A second normalization pass must reuse, not discard, the cache.',
    );
    $this->assertEquals(
      $first['account'],
      $second['account'],
      'Unchanged token data must normalize identically across steps.',
    );
  }

  /**
   * Tests that a mutated entity is re-normalized rather than served stale.
   *
   * The issue reported that a value which changes and later reverts returns a
   * stale normalization. It does not: the cache keeps a single hash per token
   * key and replaces it on every change, so V1 -> V2 -> V1 mismatches on the
   * third pass and is re-normalized. This test pins that behavior down so the
   * scoping change cannot silently introduce the staleness that was reported
   * but never present.
   */
  public function testRevertedValueIsReNormalizedNotServedStale(): void {
    $user = User::create(['name' => 'v1', 'mail' => 'v1@example.com']);
    $user->save();
    $this->tokenServices->addTokenData('account', $user);

    $v1 = $this->browser->normalizedTokenData('test_event');
    $user->setUsername('v2');
    $v2 = $this->browser->normalizedTokenData('test_event');
    $user->setUsername('v1');
    $reverted = $this->browser->normalizedTokenData('test_event');

    $this->assertSame('v1', $v1['account']['data']['name']['value']);
    $this->assertSame('v2', $v2['account']['data']['name']['value']);
    $this->assertSame(
      'v1',
      $reverted['account']['data']['name']['value'],
      'A reverted value must be re-normalized, not served from the V2 cache.',
    );
    $this->assertEquals($v1['account'], $reverted['account']);
  }

  /**
   * Tests that data provider tokens stay current without any reset.
   *
   * The 'user' token is contributed by CurrentUserDataProvider, so the value
   * held in the token data array is the #[Token] attribute object rather than
   * the account. That object is identical on every pass, so a validity hash
   * taken over it can never notice that the account changed.
   *
   * This used to be documented here as intended behavior, with
   * ::resetProcessedValues() named as the remedy. It was not a remedy, only a
   * chain-wide bound on the damage: an account switch *within* one chain still
   * served the previous account. Issue #3590431 hashes the resolved data
   * instead, so the switch below is picked up with no reset in between.
   *
   * @see \Drupal\eca\Token\CurrentUserDataProvider
   * @see \Drupal\eca\Token\Browser::normalizedTokenData()
   */
  public function testDataProviderTokensStayCurrentWithinOneChain(): void {
    User::create(['uid' => 0, 'name' => ''])->save();
    $alice = User::create(['name' => 'alice', 'mail' => 'alice@example.com']);
    $alice->save();
    $bob = User::create(['name' => 'bob', 'mail' => 'bob@example.com']);
    $bob->save();

    /** @var \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher */
    $accountSwitcher = \Drupal::service('account_switcher');

    $accountSwitcher->switchTo($alice);
    $asAlice = $this->browser->normalizedTokenData('test_event');
    $accountSwitcher->switchBack();
    $this->assertSame(
      'alice',
      $asAlice['user']['data']['name']['value'],
      'The provider backed user token must resolve to the active account.',
    );

    // Deliberately no ::resetProcessedValues() here: this is the same chain.
    $accountSwitcher->switchTo($bob);
    $asBob = $this->browser->normalizedTokenData('test_event');
    $accountSwitcher->switchBack();
    $this->assertSame(
      'bob',
      $asBob['user']['data']['name']['value'],
      'A provider backed token must follow the current account without needing the cache to be reset.',
    );
  }

}
