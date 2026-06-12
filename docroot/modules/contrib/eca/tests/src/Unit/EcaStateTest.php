<?php

namespace Drupal\Tests\eca\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\eca\EcaState;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests to EcaState class.
 */
#[Group('eca')]
#[Group('eca_core')]
class EcaStateTest extends EcaUnitTestBase {

  private const TEST_KEY = 'test_key';

  /**
   * Key value factory service.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected KeyValueFactoryInterface $keyValueFactory;

  /**
   * Key value store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected KeyValueStoreInterface $keyValueStore;

  /**
   * The cache backend that should be used.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cache;

  /**
   * The lock backend that should be used.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected LockBackendInterface $lock;

  /**
   * Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->keyValueFactory = $this->createStub(KeyValueFactoryInterface::class);
    $this->keyValueStore = $this->createStub(KeyValueStoreInterface::class);
    $this->cache = $this->createStub(CacheBackendInterface::class);
    $this->lock = $this->createStub(LockBackendInterface::class);
    $this->time = $this->createStub(TimeInterface::class);
  }

  /**
   * Tests if the timestamp has expired.
   */
  public function testIfTimestampHasExpired(): void {
    // 2018/01/09 15:00:00
    $storedTimestamp = 1515506400;
    // 2018/01/09 16:00:00
    $currentTimestamp = 1515510000;
    $keyValueStore = $this->createMock(KeyValueStoreInterface::class);
    $keyValueStore->expects($this->once())->method('get')
      ->with('timestamp.' . self::TEST_KEY)->willReturn($storedTimestamp);
    $keyValueFactory = $this->createMock(KeyValueFactoryInterface::class);
    $keyValueFactory->expects($this->once())->method('get')
      ->with('eca')->willReturn($keyValueStore);

    $time = $this->createMock(TimeInterface::class);
    $time->expects($this->exactly(3))->method('getCurrentTime')
      ->willReturn($currentTimestamp);

    $ecaState = new EcaState($keyValueFactory, $this->cache, $this->lock, $time);
    $this->assertEquals($currentTimestamp, $ecaState->getCurrentTimestamp());
    $this->assertTrue($ecaState->hasTimestampExpired(self::TEST_KEY, 3599));
    $this->assertFalse($ecaState->hasTimestampExpired(self::TEST_KEY, 3600));
  }

  /**
   * Tests timestampKey method.
   *
   * @throws \ReflectionException
   */
  public function testTimestampKey(): void {
    $ecaState = new EcaState($this->keyValueFactory, $this->cache, $this->lock, $this->time);
    $result = $this->getPrivateMethod(EcaState::class, 'timestampKey')
      ->invokeArgs($ecaState, [self::TEST_KEY]);

    $this->assertEquals('timestamp.test_key', $result);
  }

  /**
   * Tests the get and set methods.
   */
  public function testGetterAndSetter(): void {
    // 2018/01/09 16:00:00
    $currentTimestamp = 1515510000;
    $time = $this->createMock(TimeInterface::class);
    $time->expects($this->once())->method('getCurrentTime')
      ->willReturn($currentTimestamp);
    $keyValueFactory = $this->createMock(KeyValueFactoryInterface::class);
    $keyValueFactory->expects($this->once())->method('get')
      ->with('eca')->willReturn($this->keyValueStore);
    $ecaState = new EcaState($keyValueFactory, $this->cache, $this->lock, $time);
    $ecaState->setTimestamp(self::TEST_KEY);
    $this->assertEquals($currentTimestamp, $ecaState->getTimestamp(self::TEST_KEY));
  }

}
