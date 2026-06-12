<?php

namespace Drupal\Tests\eca\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Entity\Objects\EcaEvent;
use Drupal\eca\Plugin\ECA\Event\EventInterface;
use Drupal\eca\PluginManager\Event as EventPluginManager;
use Drupal\eca\Processor;
use Drupal\eca\Token\Browser;
use Drupal\eca\Token\TokenInterface;
use Drupal\eca\Token\TokenServices;
use Drupal\modeler_api\TemplateTokenResolver;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for the ECA processor engine.
 */
#[Group('eca')]
#[Group('eca_core')]
class ProcessorTest extends EcaUnitTestBase {

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The Token browser.
   *
   * @var \Drupal\eca\Token\Browser
   */
  protected Browser $tokenBrowser;

  /**
   * The template token resolver.
   *
   * @var \Drupal\modeler_api\TemplateTokenResolver
   */
  protected TemplateTokenResolver $templateTokenResolver;

  /**
   * The Token services.
   *
   * @var \Drupal\eca\Token\TokenInterface
   */
  protected TokenInterface $tokenService;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The ECA event plugin manager.
   *
   * @var \Drupal\eca\PluginManager\Event
   */
  protected EventPluginManager $eventPluginManager;

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->logger = $this->createStub(LoggerChannelInterface::class);
    $this->tokenBrowser = new Browser(
      $this->createStub(EventDispatcherInterface::class),
      $this->createStub(TokenServices::class),
      $this->createStub(EventPluginManager::class),
      $this->createStub(PrivateTempStoreFactory::class),
      $this->createStub(SharedTempStoreFactory::class),
      $this->createStub(AccountProxyInterface::class),
      $this->createStub(RequestStack::class),
      $this->createStub(TimeInterface::class),
      $this->createStub(StateInterface::class),
    );
    $this->templateTokenResolver = $this->createStub(TemplateTokenResolver::class);
    $this->tokenService = $this->createStub(TokenInterface::class);
    $this->eventDispatcher = $this->createStub(EventDispatcherInterface::class);
    $this->eventPluginManager = $this->createStub(EventPluginManager::class);
    $this->state = $this->createStub(StateInterface::class);
    $this->currentUser = $this->createStub(AccountProxyInterface::class);
  }

  /**
   * Tests the recursionThresholdSurpassed without history.
   *
   * @throws \ReflectionException
   */
  public function testRecursionThresholdWithoutHistory(): void {
    $processor = new Processor($this->entityTypeManager, $this->logger, $this->eventDispatcher, $this->eventPluginManager, $this->state, $this->tokenBrowser, $this->templateTokenResolver, $this->currentUser, 3);
    $method = $this->getPrivateMethod(Processor::class, 'recursionThresholdSurpassed');
    $eca = $this->getEca('1');
    $result = $method->invokeArgs($processor, [
      $eca,
      $this->getEcaEvent($eca, '1'),
    ]);
    $this->assertFalse($result);
  }

  /**
   * Tests the recursionThreshold is surpassed.
   *
   * @throws \ReflectionException
   */
  public function testRecursionThresholdSurpassed(): void {
    $processor = new Processor($this->entityTypeManager, $this->logger, $this->eventDispatcher, $this->eventPluginManager, $this->state, $this->tokenBrowser, $this->templateTokenResolver, $this->currentUser, 2);
    $this->assertTrue($this->isThresholdComplied($processor));
  }

  /**
   * Tests the recursionThreshold is not surpassed.
   *
   * @throws \ReflectionException
   */
  public function testRecursionThresholdNotSurpassed(): void {
    $processor = new Processor($this->entityTypeManager, $this->logger, $this->eventDispatcher, $this->eventPluginManager, $this->state, $this->tokenBrowser, $this->templateTokenResolver, $this->currentUser, 3);
    $this->assertFalse($this->isThresholdComplied($processor));
  }

  /**
   * Check whether the threshold is complied.
   *
   * @param \Drupal\eca\Processor $processor
   *   The ECA processor service.
   *
   * @return bool
   *   Returns TRUE, if the recursion threshold got exceeded, FALSE otherwise.
   *
   * @throws \ReflectionException
   */
  private function isThresholdComplied(Processor $processor): bool {
    $method = $this->getPrivateMethod(Processor::class, 'recursionThresholdSurpassed');
    $executionHistory = $this->getPrivateProperty(Processor::class, 'executionHistory');
    $eca = $this->getEca('1');
    $ecaEvent = $this->getEcaEvent($eca, '1');
    $ecaEventHistory = [];
    $ecaEventHistory[] = $eca->id() . ':' . $ecaEvent->getId();
    $ecaEventHistory[] = $eca->id() . ':' . $this->getEcaEvent($eca, '2')->getId();
    $ecaEventHistory[] = $eca->id() . ':' . $this->getEcaEvent($eca, '3')->getId();
    $ecaEventHistory[] = $eca->id() . ':' . $ecaEvent->getId();
    $ecaEventHistory[] = $eca->id() . ':' . $ecaEvent->getId();
    $ecaEventHistory[] = $eca->id() . ':' . $ecaEvent->getId();
    $executionHistory->setValue($processor, $ecaEventHistory);
    return $method->invokeArgs($processor, [$eca, $ecaEvent]);
  }

  /**
   * Gets an ECA config entity initialized with mocks.
   *
   * @param string $id
   *   The ID of the ECA config entity.
   *
   * @return \Drupal\eca\Entity\Eca
   *   The mocked ECA config entity.
   */
  private function getEca(string $id): Eca {
    $eca = $this->createStub(Eca::class);
    $eca->set('id', $id);
    $eca->method('id')->willReturn($id);
    return $eca;
  }

  /**
   * Gets a EcaEvent initialized with mocks.
   *
   * @param \Drupal\eca\Entity\Eca $eca
   *   An ECA config entity.
   * @param string $id
   *   The ID of the event.
   *
   * @return \Drupal\eca\Entity\Objects\EcaEvent
   *   The mocked event.
   */
  private function getEcaEvent(Eca $eca, string $id): EcaEvent {
    $event = $this->createStub(EventInterface::class);
    return new EcaEvent($eca, $id, 'label', $event);
  }

}
