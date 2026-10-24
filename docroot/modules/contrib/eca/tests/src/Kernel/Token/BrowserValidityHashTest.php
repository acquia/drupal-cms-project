<?php

namespace Drupal\Tests\eca\Kernel\Token;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\eca\Kernel\Token\Fixtures\CountingDataProvider;
use Drupal\Tests\eca\Kernel\Token\Fixtures\FreshDtoDataProvider;
use Drupal\Tests\eca\Kernel\Token\Fixtures\UnserializableDataProvider;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Token\Browser;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests what the normalized value cache hashes to decide it is still valid.
 *
 * Covers issue #3590431. The validity hash used to be taken over the raw value
 * stored in the token data array. For a token contributed by a
 * DataProviderInterface that value is the #[Token] attribute object that
 * declared it, not the data it resolves to. The attribute is content-identical
 * on every pass, so the hash never changed while the underlying data did, and
 * the very first normalization stayed pinned in place for the lifetime of the
 * cache.
 *
 * The fix hashes the resolved data instead. What gets *stored* in the token
 * data array is deliberately unchanged, because ::normalizeValue() reads the
 * declared property tree off the attribute and swapping in the resolved value
 * would change the shape of the inspector output.
 *
 * @see \Drupal\eca\Token\Browser::normalizedTokenData()
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class BrowserValidityHashTest extends KernelTestBase {

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
   * Tests that a provider token follows the data it resolves to.
   *
   * The provider hands out a different account on the second pass while the
   * #[Token] attribute stored in the token data array stays exactly the same
   * object. Only a hash taken over the resolved data can tell the two passes
   * apart.
   */
  public function testProviderTokenFollowsTheResolvedValue(): void {
    $alice = User::create(['name' => 'alice', 'mail' => 'alice@example.com']);
    $alice->save();
    $bob = User::create(['name' => 'bob', 'mail' => 'bob@example.com']);
    $bob->save();

    $provider = new CountingDataProvider($alice);
    $this->tokenServices->addTokenDataProvider($provider);

    $first = $this->browser->normalizedTokenData('test_event');
    $this->assertSame(
      'alice',
      $first[CountingDataProvider::KEY]['data']['name']['value'],
      'The first pass must reflect the account the provider resolves to.',
    );

    $provider->setEntity($bob);
    $second = $this->browser->normalizedTokenData('test_event');
    $this->assertSame(
      'bob',
      $second[CountingDataProvider::KEY]['data']['name']['value'],
      'A provider token must follow the data it resolves to, not the #[Token] attribute that declared it.',
    );
  }

  /**
   * Tests that hashing the resolved value costs no extra resolution.
   *
   * ::hasTokenData() is a NULL check on ::getTokenData(), so the existence
   * check in ::normalizedTokenData() already resolved the provider data. The
   * fix captures that result instead of throwing it away, which is what keeps
   * the resolution count flat.
   *
   * A warm pass is what isolates the hash. It reuses the cached normalization,
   * so the only resolutions left are the existence check and the entity dedup
   * lookup - two calls, each costing two resolutions because ::hasData()
   * resolves as well. A hash that resolved for itself would show up here
   * immediately as two more. The cold count is deliberately not pinned: it
   * includes the whole normalization walk, whose size depends on how many
   * tokens the resolved entity type declares.
   *
   * @see \Drupal\eca\Token\TokenDecoratorTrait::hasTokenData()
   */
  public function testHashingTheResolvedValueCostsNoExtraResolution(): void {
    $user = User::create(['name' => 'counted_user', 'mail' => 'counted@example.com']);
    $user->save();

    $provider = new CountingDataProvider($user);
    $this->tokenServices->addTokenDataProvider($provider);

    $provider->resolutions = 0;
    $this->browser->normalizedTokenData('test_event');
    $cold = $provider->resolutions;

    $provider->resolutions = 0;
    $this->browser->normalizedTokenData('test_event');
    $warm = $provider->resolutions;

    $this->assertSame(
      4,
      $warm,
      'A warm pass must resolve only for the existence check and the entity dedup. The validity hash has to reuse the value captured by the existence check instead of resolving again.',
    );
    $this->assertGreaterThan(
      $warm,
      $cold,
      'A cold pass additionally walks the normalization, so it must resolve more often than a warm one.',
    );
  }

  /**
   * Tests that an unserializable resolution degrades instead of blowing up.
   *
   * Hashing the resolved value runs serialize() over arbitrary provider data.
   * When that fails, the pass must fall back to a unique hash that forces
   * re-normalization, which is exactly what an unserializable plain token
   * value already did.
   */
  public function testUnserializableResolutionForcesReNormalization(): void {
    $this->tokenServices->addTokenDataProvider(new UnserializableDataProvider());

    $first = $this->browser->normalizedTokenData('test_event');
    $this->assertArrayHasKey(
      UnserializableDataProvider::KEY,
      $first,
      'An unserializable resolution must not abort the normalization pass.',
    );
    $firstHash = $this->processedValues()[UnserializableDataProvider::KEY]['hash'];

    $this->browser->normalizedTokenData('test_event');
    $secondHash = $this->processedValues()[UnserializableDataProvider::KEY]['hash'];

    $this->assertNotSame(
      $firstHash,
      $secondHash,
      'A value that cannot be hashed must be re-normalized on every pass rather than cached under a stale hash.',
    );
  }

  /**
   * Tests that a freshly built, content-identical DTO still hits the cache.
   *
   * HtmxRequestDataProvider returns a brand new DataTransferObject on every
   * resolution, and DataTransferObject declares no __serialize()/__sleep(), so
   * serialize() walks the whole TypedData graph including the parent and the
   * definition. If two content-identical instances did not serialize equal,
   * hashing the resolved value would trade the old false cache hit for a false
   * cache miss and re-normalize that token on every single debug step.
   *
   * @see \Drupal\eca_htmx\Token\HtmxRequestDataProvider::getData()
   */
  public function testFreshlyBuiltIdenticalDtoStillHitsTheCache(): void {
    // First the raw property this relies on, so a failure points straight at
    // serialize() rather than at the browser.
    $values = ['alpha' => 'one', 'beta' => 'two'];
    $this->assertSame(
      md5(serialize(DataTransferObject::create($values))),
      md5(serialize(DataTransferObject::create($values))),
      'Two content-identical DataTransferObject instances must serialize equal.',
    );

    $provider = new FreshDtoDataProvider($values);
    $this->tokenServices->addTokenDataProvider($provider);

    $this->browser->normalizedTokenData('test_event');
    $firstHash = $this->processedValues()[FreshDtoDataProvider::KEY]['hash'];

    $this->browser->normalizedTokenData('test_event');
    $secondHash = $this->processedValues()[FreshDtoDataProvider::KEY]['hash'];

    $this->assertSame(
      $firstHash,
      $secondHash,
      'A provider that rebuilds an unchanged DTO must still hit the cache.',
    );

    // And the cache must still notice when the content genuinely changes.
    $provider->setValues(['alpha' => 'changed', 'beta' => 'two']);
    $this->browser->normalizedTokenData('test_event');
    $this->assertNotSame(
      $secondHash,
      $this->processedValues()[FreshDtoDataProvider::KEY]['hash'],
      'A DTO whose content changed must invalidate the cache.',
    );
  }

  /**
   * Tests that a plain token value keeps hashing exactly as it did.
   *
   * Only values stored as a #[Token] attribute change behavior. Anything put
   * into the token data array directly must keep its old hashing, so an
   * unchanged value still hits the cache and a mutated one still misses it.
   */
  public function testPlainTokenValuesKeepTheirHashing(): void {
    $user = User::create(['name' => 'plain', 'mail' => 'plain@example.com']);
    $user->save();
    $this->tokenServices->addTokenData('account', $user);

    $this->browser->normalizedTokenData('test_event');
    $firstHash = $this->processedValues()['account']['hash'];

    $this->browser->normalizedTokenData('test_event');
    $this->assertSame(
      $firstHash,
      $this->processedValues()['account']['hash'],
      'An unchanged plain token value must still hit the cache.',
    );

    $user->setUsername('plain_changed');
    $this->browser->normalizedTokenData('test_event');
    $this->assertNotSame(
      $firstHash,
      $this->processedValues()['account']['hash'],
      'A mutated plain token value must still miss the cache.',
    );
  }

}
