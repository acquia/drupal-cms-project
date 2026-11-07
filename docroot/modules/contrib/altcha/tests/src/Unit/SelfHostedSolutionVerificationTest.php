<?php

namespace Drupal\Tests\altcha\Unit;

use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\ChallengeOptions;
use AltchaOrg\Altcha\Hasher\Algorithm;
use Drupal\altcha\SecretManager;
use Drupal\altcha\SelfHostedSolutionVerification;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests one-time consumption of verified self-hosted solutions.
 *
 * @group altcha
 */
#[Group('altcha')]
class SelfHostedSolutionVerificationTest extends UnitTestCase {

  /**
   * The consumed-solution store.
   */
  protected KeyValueStoreExpirableInterface & MockObject $store;

  /**
   * The shared lock backend.
   */
  protected LockBackendInterface & MockObject $lock;

  /**
   * The verification service.
   */
  protected SelfHostedSolutionVerification $verification;

  /**
   * The request timestamp used to calculate record lifetimes.
   */
  protected int $requestTime;

  /**
   * Lock and storage operations performed while consuming a solution.
   *
   * @var string[]
   */
  protected array $consumptionOperations = [];

  /**
   * Whether the mock store contains the consumed solution record.
   */
  protected bool $solutionConsumed = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $secret_manager = $this->createMock(SecretManager::class);
    $secret_manager->method('getSecretKey')->willReturn('test-secret');
    $this->store = $this->createMock(KeyValueStoreExpirableInterface::class);
    $factory = $this->createMock(KeyValueExpirableFactoryInterface::class);
    $factory->expects($this->once())
      ->method('get')
      ->with('altcha_consumed_self_hosted_solutions')
      ->willReturn($this->store);
    $this->requestTime = time();
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn($this->requestTime);
    $this->lock = $this->createMock(LockBackendInterface::class);
    $this->verification = new SelfHostedSolutionVerification($secret_manager, $factory, $time, $this->lock);
  }

  /**
   * Tests that a solution is consumed under a lock and reuse is denied.
   *
   * @dataProvider expiryProvider
   */
  #[DataProvider('expiryProvider')]
  public function testSolutionIsConsumedOnce(?int $lifetime, int $expected_ttl): void {
    $data = $this->createPayload($lifetime === NULL ? NULL : $this->requestTime + $lifetime);
    $solution_id = $data['signature'];
    $key = hash('sha256', $solution_id);
    $lock_name = 'altcha:self_hosted_solution:' . $key;

    // Both submissions acquire and release the lock and check the store. Only
    // the first submission records the solution as consumed.
    $this->lock->expects($this->exactly(2))
      ->method('acquire')
      ->with($lock_name)
      ->willReturnCallback(function (): bool {
        $this->consumptionOperations[] = 'acquire';
        return TRUE;
      });
    $this->store->expects($this->exactly(2))
      ->method('has')
      ->with($key)
      ->willReturnCallback(function (): bool {
        $this->consumptionOperations[] = 'has';
        return $this->solutionConsumed;
      });
    $this->store->expects($this->once())
      ->method('setWithExpire')
      ->with($key, TRUE, $expected_ttl)
      ->willReturnCallback(function (): void {
        $this->consumptionOperations[] = 'setWithExpire';
        $this->solutionConsumed = TRUE;
      });
    $this->lock->expects($this->exactly(2))
      ->method('release')
      ->with($lock_name)
      ->willReturnCallback(function (): void {
        $this->consumptionOperations[] = 'release';
      });

    // The first submission consumes the solution.
    $this->assertTrue($this->verification->verify($data));
    $this->assertSame([
      'acquire',
      'has',
      'setWithExpire',
      'release',
    ], $this->consumptionOperations);

    // The second submission is rejected.
    $this->consumptionOperations = [];
    $this->assertFalse($this->verification->verify($data));
    $this->assertSame([
      'acquire',
      'has',
      'release',
    ], $this->consumptionOperations);
  }

  /**
   * Provides remaining challenge lifetimes and expected record lifetimes.
   */
  public static function expiryProvider(): array {
    return [
      'expiry plus grace period' => [300, 360],
      'long-lived challenge' => [3600, 3660],
      'no expiry uses fallback' => [NULL, 660],
    ];
  }

  /**
   * Tests that lock contention rejects consumption without accessing the store.
   */
  public function testLockContention(): void {
    $data = $this->createPayload();
    $lock_name = 'altcha:self_hosted_solution:' . hash('sha256', $data['signature']);
    $this->lock->expects($this->once())->method('acquire')->with($lock_name)->willReturn(FALSE);
    $this->lock->expects($this->never())->method('release');
    $this->store->expects($this->never())->method('has');
    $this->store->expects($this->never())->method('setWithExpire');

    $this->assertFalse($this->verification->verify($data));
  }

  /**
   * Tests that invalid signatures are never consumed.
   */
  public function testInvalidSignatureIsNotConsumed(): void {
    $data = $this->createPayload();
    $data['signature'] = str_repeat('0', 64);
    $this->lock->expects($this->never())->method('acquire');
    $this->lock->expects($this->never())->method('release');
    $this->store->expects($this->never())->method('has');
    $this->store->expects($this->never())->method('setWithExpire');

    $this->assertFalse($this->verification->verify($data));
  }

  /**
   * Tests that an incorrect solution number is never consumed.
   */
  public function testInvalidSolutionIsNotConsumed(): void {
    $payload = $this->createPayload();
    // Preserve the signed challenge and change only the submitted solution.
    $payload['number']++;
    $this->lock->expects($this->never())->method('acquire');
    $this->lock->expects($this->never())->method('release');
    $this->store->expects($this->never())->method('has');
    $this->store->expects($this->never())->method('setWithExpire');

    $this->assertFalse($this->verification->verify($payload));
  }

  /**
   * Creates a signed, solved challenge using inexpensive proof of work.
   *
   * @param int|null $expires_at
   *   The challenge expiration timestamp, or NULL for no expiration.
   *
   * @return array
   *   The decoded payload accepted by the verification service.
   */
  protected function createPayload(?int $expires_at = NULL): array {
    $altcha = new Altcha('test-secret');
    $challenge = $altcha->createChallenge(new ChallengeOptions(
      algorithm: Algorithm::SHA256,
      maxNumber: 1,
      expires: $expires_at === NULL ? NULL : new \DateTimeImmutable('@' . $expires_at),
    ));
    $solution = $altcha->solveChallenge($challenge->challenge, $challenge->salt, Algorithm::SHA256, 1);
    $this->assertNotNull($solution);
    return [
      'algorithm' => 'SHA-256',
      'challenge' => $challenge->challenge,
      'number' => $solution->number,
      'salt' => $challenge->salt,
      'signature' => $challenge->signature,
    ];
  }

}
