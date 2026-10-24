<?php

namespace Drupal\Tests\eca\Unit;

use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;
use Drupal\eca\Entity\EcaStorage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Tests EcaStorage::rebuildSubscribedEvents() retry logic.
 *
 * @see \Drupal\eca\Entity\EcaStorage::rebuildSubscribedEvents()
 */
#[Group('eca')]
#[Group('eca_core')]
class EcaStorageTest extends TestCase {

  /**
   * Creates an EcaStorage instance bypassing the constructor.
   *
   * EcaStorage extends ConfigEntityStorage which requires many constructor
   * dependencies. We bypass the constructor via reflection and inject only
   * the properties needed for rebuildSubscribedEvents().
   *
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend mock.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger mock.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state mock.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher mock.
   * @param \Symfony\Component\EventDispatcher\EventSubscriberInterface $eventSubscriber
   *   The event subscriber mock.
   *
   * @return \Drupal\eca\Entity\EcaStorage
   *   The storage instance with injected dependencies.
   */
  private function createStorage(
    LockBackendInterface $lock,
    LoggerChannelInterface $logger,
    StateInterface $state,
    EventDispatcherInterface $eventDispatcher,
    EventSubscriberInterface $eventSubscriber,
  ): EcaStorage {
    $reflector = new \ReflectionClass(EcaStorage::class);
    $storage = $reflector->newInstanceWithoutConstructor();
    $reflector->getProperty('lock')->setValue($storage, $lock);
    $reflector->getProperty('logger')->setValue($storage, $logger);
    $reflector->getProperty('state')->setValue($storage, $state);
    $reflector->getProperty('eventDispatcher')->setValue($storage, $eventDispatcher);
    $reflector->getProperty('eventSubscriber')->setValue($storage, $eventSubscriber);
    return $storage;
  }

  /**
   * Tests that the method gives up after exceeding the max retry limit.
   *
   * When the lock cannot be acquired after MAX_LOCK_RETRIES attempts, the
   * method should log a warning and return without performing the rebuild.
   */
  public function testRebuildGivesUpAfterMaxRetries(): void {
    $lock = $this->createMock(LockBackendInterface::class);
    // The lock is never acquired: initial attempt + 10 retries = 11 calls.
    $lock->expects($this->exactly(11))
      ->method('acquire')
      ->with('eca_rebuild_subscribed_events')
      ->willReturn(FALSE);
    // The lock should never be released since it was never acquired.
    $lock->expects($this->never())->method('release');

    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('warning')
      ->with(
        'Could not acquire lock for rebuilding ECA subscribed events after @retries attempts.',
        ['@retries' => 10],
      );

    $state = $this->createStub(StateInterface::class);
    $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
    $eventSubscriber = $this->createStub(EventSubscriberInterface::class);

    $storage = $this->createStorage($lock, $logger, $state, $eventDispatcher, $eventSubscriber);
    $storage->rebuildSubscribedEvents();
  }

  /**
   * Tests that the method acquires the lock and proceeds on the first attempt.
   *
   * When the lock is acquired immediately, the method should perform the
   * rebuild and release the lock without logging any warnings.
   */
  public function testRebuildSucceedsOnFirstAttempt(): void {
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())
      ->method('acquire')
      ->with('eca_rebuild_subscribed_events')
      ->willReturn(TRUE);
    $lock->expects($this->once())
      ->method('release')
      ->with('eca_rebuild_subscribed_events');

    $logger = $this->createMock(LoggerChannelInterface::class);
    // No warning should be logged since the lock was acquired immediately.
    $logger->expects($this->never())->method('warning');

    $state = $this->createStub(StateInterface::class);
    $state->method('get')
      ->with('eca.subscribed')
      ->willReturn([]);

    $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
    $eventSubscriber = $this->createStub(EventSubscriberInterface::class);

    // We need to mock the entity loading. Since the constructor was bypassed,
    // loadMultiple() will fail. We use a partial mock to stub the protected
    // methods that require full bootstrapping.
    $reflector = new \ReflectionClass(EcaStorage::class);
    $storage = $this->getMockBuilder(EcaStorage::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['doRebuildSubscribedEvents', 'doRebuildTemplates'])
      ->getMock();

    $reflector->getProperty('lock')->setValue($storage, $lock);
    $reflector->getProperty('logger')->setValue($storage, $logger);
    $reflector->getProperty('state')->setValue($storage, $state);
    $reflector->getProperty('eventDispatcher')->setValue($storage, $eventDispatcher);
    $reflector->getProperty('eventSubscriber')->setValue($storage, $eventSubscriber);

    $storage->expects($this->once())
      ->method('doRebuildSubscribedEvents')
      ->willReturn([]);
    $storage->expects($this->once())
      ->method('doRebuildTemplates');

    $storage->rebuildSubscribedEvents();
  }

  /**
   * Tests that the lock is acquired after a few retries.
   *
   * Simulates a scenario where the lock is held briefly and becomes
   * available after 3 failed attempts.
   */
  public function testRebuildSucceedsAfterRetries(): void {
    $lock = $this->createMock(LockBackendInterface::class);
    // Fail 3 times, then succeed on the 4th attempt.
    $lock->expects($this->exactly(4))
      ->method('acquire')
      ->with('eca_rebuild_subscribed_events')
      ->willReturnOnConsecutiveCalls(FALSE, FALSE, FALSE, TRUE);
    $lock->expects($this->once())
      ->method('release')
      ->with('eca_rebuild_subscribed_events');

    $logger = $this->createMock(LoggerChannelInterface::class);
    // No warning should be logged since the lock was eventually acquired.
    $logger->expects($this->never())->method('warning');

    $state = $this->createStub(StateInterface::class);
    $state->method('get')
      ->with('eca.subscribed')
      ->willReturn([]);

    $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
    $eventSubscriber = $this->createStub(EventSubscriberInterface::class);

    $reflector = new \ReflectionClass(EcaStorage::class);
    $storage = $this->getMockBuilder(EcaStorage::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['doRebuildSubscribedEvents', 'doRebuildTemplates'])
      ->getMock();

    $reflector->getProperty('lock')->setValue($storage, $lock);
    $reflector->getProperty('logger')->setValue($storage, $logger);
    $reflector->getProperty('state')->setValue($storage, $state);
    $reflector->getProperty('eventDispatcher')->setValue($storage, $eventDispatcher);
    $reflector->getProperty('eventSubscriber')->setValue($storage, $eventSubscriber);

    $storage->expects($this->once())
      ->method('doRebuildSubscribedEvents')
      ->willReturn([]);
    $storage->expects($this->once())
      ->method('doRebuildTemplates');

    $storage->rebuildSubscribedEvents();
  }

}
