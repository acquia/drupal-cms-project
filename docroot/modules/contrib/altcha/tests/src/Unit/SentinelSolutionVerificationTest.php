<?php

namespace Drupal\Tests\altcha\Unit;

use Drupal\altcha\SentinelSolutionVerification;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests one-time consumption of verified Sentinel solutions.
 *
 * @group altcha
 */
#[Group('altcha')]
class SentinelSolutionVerificationTest extends UnitTestCase {

  /**
   * The consumed-solution store.
   */
  protected KeyValueStoreExpirableInterface&MockObject $store;

  /**
   * The shared lock backend.
   */
  protected LockBackendInterface&MockObject $lock;

  /**
   * The verification service.
   */
  protected SentinelSolutionVerification $verification;

  /**
   * The request timestamp used to calculate record lifetimes.
   */
  protected int $requestTime;

  /**
   * Whether the mock store contains the consumed solution record.
   */
  protected bool $solutionConsumed = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $config = $this->createMock(Config::class);
    $config->method('get')->with('sentinel_api_secret')->willReturn('test-secret');
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('altcha.settings')->willReturn($config);
    $this->store = $this->createMock(KeyValueStoreExpirableInterface::class);
    $factory = $this->createMock(KeyValueExpirableFactoryInterface::class);
    $factory->expects($this->once())
      ->method('get')
      ->with('altcha_consumed_sentinel_solutions')
      ->willReturn($this->store);
    $this->requestTime = time();
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn($this->requestTime);
    $this->lock = $this->createMock(LockBackendInterface::class);
    $this->verification = new SentinelSolutionVerification($config_factory, $factory, $time, $this->lock);
  }

  /**
   * Tests that a Sentinel solution is consumed once under a lock.
   *
   * @dataProvider expiryProvider
   */
  #[DataProvider('expiryProvider')]
  public function testSolutionIsConsumedOnce(?int $lifetime, int $expected_ttl): void {
    $data = $this->createResult($lifetime === NULL ? NULL : $this->requestTime + $lifetime);
    $solution_id = $data['signature'];
    $key = hash('sha256', $solution_id);
    $lock_name = 'altcha:sentinel_solution:' . $key;
    $this->lock->expects($this->exactly(2))->method('acquire')->with($lock_name)->willReturn(TRUE);
    $this->lock->expects($this->exactly(2))->method('release')->with($lock_name);
    $this->store->expects($this->exactly(2))->method('has')->with($key)
      ->willReturnCallback(fn (): bool => $this->solutionConsumed);
    $this->store->expects($this->once())->method('setWithExpire')->with($key, TRUE, $expected_ttl)
      ->willReturnCallback(function (): void {
        $this->solutionConsumed = TRUE;
      });

    $this->assertTrue($this->verification->verify($data));
    $this->assertFalse($this->verification->verify($data));
  }

  /**
   * Provides solution lifetimes and expected stored lifetimes.
   */
  public static function expiryProvider(): array {
    return [
      'expiry plus grace period' => [300, 360],
      'long-lived solution' => [3600, 3660],
    ];
  }

  /**
   * Tests that lock contention rejects consumption without reading the store.
   */
  public function testLockContention(): void {
    $data = $this->createResult($this->requestTime + 300);
    $lock_name = 'altcha:sentinel_solution:' . hash('sha256', $data['signature']);
    $this->lock->expects($this->once())->method('acquire')->with($lock_name)->willReturn(FALSE);
    $this->lock->expects($this->never())->method('release');
    $this->store->expects($this->never())->method('has');
    $this->store->expects($this->never())->method('setWithExpire');

    $this->assertFalse($this->verification->verify($data));
  }

  /**
   * Tests that an invalid signature is never consumed.
   */
  public function testInvalidSolutionIsNotConsumed(): void {
    $data = $this->createResult($this->requestTime + 300);
    $data['signature'] = str_repeat('0', 64);
    $this->lock->expects($this->never())->method('acquire');
    $this->lock->expects($this->never())->method('release');
    $this->store->expects($this->never())->method('has');
    $this->store->expects($this->never())->method('setWithExpire');

    $this->assertFalse($this->verification->verify($data));
  }

  /**
   * Tests that the legacy library rejects results without a future expiry.
   *
   * @dataProvider invalidExpiryProvider
   */
  #[DataProvider('invalidExpiryProvider')]
  public function testInvalidExpiryIsNotConsumed(?int $lifetime): void {
    $data = $this->createResult($lifetime === NULL ? NULL : $this->requestTime + $lifetime);
    $this->lock->expects($this->never())->method('acquire');
    $this->lock->expects($this->never())->method('release');
    $this->store->expects($this->never())->method('has');
    $this->store->expects($this->never())->method('setWithExpire');

    $this->assertFalse($this->verification->verify($data));
  }

  /**
   * Provides missing and expired Sentinel expiry values.
   */
  public static function invalidExpiryProvider(): array {
    return [
      'missing expiry' => [NULL],
      'expired result' => [-60],
    ];
  }

  /**
   * Creates a Sentinel solution accepted by the verification service.
   */
  protected function createResult(?int $expires_at = NULL): array {
    $verification_data = 'verified=true';
    if ($expires_at !== NULL) {
      $verification_data .= '&expire=' . $expires_at;
    }
    $hash = hash('sha256', $verification_data, TRUE);
    return [
      'algorithm' => 'SHA-256',
      'verificationData' => $verification_data,
      'verified' => TRUE,
      'signature' => bin2hex(hash_hmac('sha256', $hash, 'test-secret', TRUE)),
    ];
  }

}
