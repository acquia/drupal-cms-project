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
use PHPUnit\Framework\Attributes\DataProvider;
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
   * The number of nesting levels that the recursion simulation walks through.
   *
   * A correctly detected repetition is recognized at a nesting level far below
   * this, so reaching this level means the pattern is not detected at all and
   * would keep nesting until PHP runs out of memory or stack.
   */
  private const int MAX_SIMULATED_NESTING = 200;

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
   * Tests recursion detection for a repeating block of more than one event.
   *
   * The three tests above all put the checked event at the very end of the
   * history, which makes the repeating block exactly one entry long. This test
   * covers the multi-entry block instead, where a whole sequence of events
   * repeats itself, as it happens when two ECA models trigger each other.
   *
   * @param int $threshold
   *   The configured maximum allowed level of recursion.
   * @param array $history
   *   The event IDs to place into the execution history, in execution order.
   * @param bool $expected
   *   Whether the recursion threshold is expected to be surpassed.
   *
   * @throws \ReflectionException
   */
  #[DataProvider('repeatedBlockProvider')]
  public function testRecursionThresholdWithRepeatedBlock(int $threshold, array $history, bool $expected): void {
    $processor = new Processor($this->entityTypeManager, $this->logger, $this->eventDispatcher, $this->eventPluginManager, $this->state, $this->tokenBrowser, $this->templateTokenResolver, $this->currentUser, $threshold);
    $method = $this->getPrivateMethod(Processor::class, 'recursionThresholdSurpassed');
    $executionHistory = $this->getPrivateProperty(Processor::class, 'executionHistory');
    $eca = $this->getEca('1');
    $ecaEvent = $this->getEcaEvent($eca, '1');
    $ecaEventHistory = [];
    foreach ($history as $event_id) {
      $ecaEventHistory[] = $eca->id() . ':' . $this->getEcaEvent($eca, $event_id)->getId();
    }
    $executionHistory->setValue($processor, $ecaEventHistory);
    $this->assertSame($expected, $method->invokeArgs($processor, [$eca, $ecaEvent]));
  }

  /**
   * Provides histories whose repeating block spans more than one event.
   *
   * The checked event is always "1", and it is never the last entry of the
   * history, so the repeating block is always longer than a single entry.
   *
   * @return array
   *   The test cases, keyed by a description of the scenario.
   */
  public static function repeatedBlockProvider(): array {
    return [
      // The block "1, 2" starts to repeat, but only for the first time. With a
      // threshold of 1 that single repetition is still allowed.
      'one repetition of a two event block' => [
        1,
        ['1', '2'],
        FALSE,
      ],
      // The block "1, 2" is now directly preceded by an identical block, which
      // surpasses a threshold of 1.
      'two repetitions of a two event block' => [
        1,
        ['1', '2', '1', '2'],
        TRUE,
      ],
      // The same history with a higher threshold still passes.
      'two repetitions below a higher threshold' => [
        2,
        ['1', '2', '1', '2'],
        FALSE,
      ],
      // Three repetitions surpass a threshold of 2.
      'three repetitions of a two event block' => [
        2,
        ['1', '2', '1', '2', '1', '2'],
        TRUE,
      ],
      // A block of three events repeating twice surpasses a threshold of 1.
      'two repetitions of a three event block' => [
        1,
        ['1', '2', '3', '1', '2', '3'],
        TRUE,
      ],
      // The preceding block differs in its last entry, so it is no repetition
      // and the threshold is not surpassed.
      'preceding block differs' => [
        1,
        ['1', '2', '3', '1', '2', '4'],
        FALSE,
      ],
      // A non repeating prefix must not be mistaken for a repetition, even
      // though the history is long enough to hold one.
      'unrelated prefix before the block' => [
        1,
        ['4', '5', '1', '2'],
        FALSE,
      ],
      // Neither the block "1, 3" opened by the last occurrence of the checked
      // event nor the block "1, 1, 3" opened by the earlier one is preceded by
      // an identical block, so nothing repeats.
      'no occurrence opens a repeating block' => [
        1,
        ['2', '1', '1', '3'],
        FALSE,
      ],
    ];
  }

  /**
   * Tests that a repeating pattern of nested events is always detected.
   *
   * The execution history is a stack of the event executions that are
   * currently nested inside each other, and recursion means that this stack
   * grows by the same pattern of events over and over again. Whatever that
   * pattern is, the processor must recognize it at some nesting level, because
   * otherwise nesting continues until PHP runs out of memory or stack.
   *
   * This simulates the processor pushing a periodic pattern onto the execution
   * history, asking it before every push whether the recursion threshold is
   * surpassed, and records the nesting level at which it says yes.
   *
   * @param int $threshold
   *   The configured maximum allowed level of recursion.
   * @param array $pattern
   *   The event IDs that the execution history repeats, in execution order.
   * @param int $expected_level
   *   The nesting level at which the repetition must be detected.
   *
   * @throws \ReflectionException
   */
  #[DataProvider('repeatingPatternProvider')]
  public function testRepeatingPatternIsDetected(int $threshold, array $pattern, int $expected_level): void {
    $processor = new Processor($this->entityTypeManager, $this->logger, $this->eventDispatcher, $this->eventPluginManager, $this->state, $this->tokenBrowser, $this->templateTokenResolver, $this->currentUser, $threshold);
    $level = $this->firstDetectedNestingLevel($processor, $pattern);
    $description = implode(', ', $pattern);
    $this->assertNotNull($level, sprintf('The repeating pattern "%s" was not detected as recursion within %d levels of nesting, so it would nest until PHP runs out of memory.', $description, self::MAX_SIMULATED_NESTING));
    $this->assertSame($expected_level, $level, sprintf('The repeating pattern "%s" must be detected at nesting level %d.', $description, $expected_level));
  }

  /**
   * Provides repeating patterns of nested events and their detection level.
   *
   * Every pattern here describes an execution history that keeps growing by
   * the same sequence of events, which is exactly what infinite recursion
   * looks like from the point of view of the processor. The expected level is
   * the number of nested executions that are allowed to happen before the
   * repetition is recognized, which grows with the configured threshold and
   * with the length of the repeating pattern.
   *
   * @return array
   *   The test cases, keyed by a description of the scenario.
   */
  public static function repeatingPatternProvider(): array {
    return [
      // A single event that keeps triggering itself. The threshold allows it
      // to be nested that many times before the next one is blocked.
      'one event, threshold 1' => [1, ['A'], 2],
      'one event, threshold 2' => [2, ['A'], 3],
      'one event, threshold 3' => [3, ['A'], 4],
      // Two events triggering each other, the classic ping pong cycle.
      'two alternating events, threshold 1' => [1, ['A', 'B'], 4],
      'two alternating events, threshold 2' => [2, ['A', 'B'], 6],
      // Three events forming a cycle.
      'three cycling events' => [1, ['A', 'B', 'C'], 6],
      // A repeating block that is not aligned with the last occurrence of the
      // event that is about to be executed. The block that truly repeats is
      // "A, A, B, B", while the last occurrence of "A" only opens the shorter
      // and misaligned block "A, B, B", which never repeats itself.
      'block of two pairs, threshold 1' => [1, ['A', 'A', 'B', 'B'], 8],
      'block of two pairs, threshold 2' => [2, ['A', 'A', 'B', 'B'], 12],
      'block of two pairs, threshold 3' => [3, ['A', 'A', 'B', 'B'], 16],
      // The same pattern entered at its other three nesting levels, which is
      // what happens when the recursion starts at a different event.
      'block of two pairs, rotated once' => [1, ['A', 'B', 'B', 'A'], 8],
      'block of two pairs, rotated twice' => [1, ['B', 'A', 'A', 'B'], 8],
      'block of two pairs, rotated thrice' => [1, ['B', 'B', 'A', 'A'], 8],
      // A misaligned block over three events.
      'palindromic block of three events' => [
        1,
        ['A', 'B', 'C', 'C', 'B', 'A'],
        12,
      ],
      // A pattern that the last occurrence does find, but four nesting levels
      // later than the earlier occurrence does: the last "A" opens the block
      // "A, B, C", while the block that truly repeats is "A, A, B, B, C".
      'misaligned block detected earlier' => [
        1,
        ['A', 'A', 'B', 'B', 'C'],
        10,
      ],
    ];
  }

  /**
   * Simulates nesting a repeating pattern of events into each other.
   *
   * @param \Drupal\eca\Processor $processor
   *   The ECA processor service.
   * @param array $pattern
   *   The event IDs that the execution history repeats, in execution order.
   *
   * @return int|null
   *   The nesting level at which the processor reported the recursion
   *   threshold to be surpassed, or NULL if it never reported that.
   *
   * @throws \ReflectionException
   */
  private function firstDetectedNestingLevel(Processor $processor, array $pattern): ?int {
    $method = $this->getPrivateMethod(Processor::class, 'recursionThresholdSurpassed');
    $executionHistory = $this->getPrivateProperty(Processor::class, 'executionHistory');
    $eca = $this->getEca('1');
    $event_ids = array_values($pattern);
    $history = [];
    for ($level = 0; $level < self::MAX_SIMULATED_NESTING; $level++) {
      $ecaEvent = $this->getEcaEvent($eca, $event_ids[$level % count($event_ids)]);
      $executionHistory->setValue($processor, $history);
      if ($method->invokeArgs($processor, [$eca, $ecaEvent])) {
        return $level;
      }
      $history[] = $eca->id() . ':' . $ecaEvent->getId();
    }
    return NULL;
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
