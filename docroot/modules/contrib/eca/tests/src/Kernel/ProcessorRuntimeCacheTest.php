<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\EcaEvents;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Event\AfterInitialExecutionEvent;
use Drupal\eca\Event\BeforeInitialExecutionEvent;
use Drupal\eca\ProcessDebugger;
use Drupal\eca\Processor;
use Drupal\eca\Token\Browser;
use Drupal\eca_test_array\Event\ArrayEvents;
use Drupal\eca_test_array\Event\ArrayWriteEvent;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the runtime caches the processor keeps while applying events.
 *
 * Covers issue #3590396. Two runtime caches were unbounded:
 *
 * - Processor::$appliedEvents kept a ProcessDebugger for every subscribed
 *   ECA event object of every dispatched event, appended before the wildcard
 *   appliance check and never released. In a long-running single process
 *   (drush cron, a queue worker, a batch) that grows without bound, and most
 *   of the retained debuggers never applied in the first place.
 * - Browser::$processedValues held a fully normalized sub-tree per token key
 *   for the lifetime of the container rather than for the execution chain it
 *   was built for.
 *
 * @see \Drupal\eca\Processor::execute()
 * @see \Drupal\eca\Processor::getAppliedEvents()
 * @see \Drupal\eca\Token\Browser::resetProcessedValues()
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class ProcessorRuntimeCacheTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'eca',
    'eca_test_array',
    'modeler_api',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
  }

  /**
   * Saves an ECA model reacting on a static array write.
   *
   * @param string $eventValue
   *   The value the event object matches on. Passing a value that the
   *   triggering action never writes makes the event subscribed but never
   *   applicable, which is the interesting case for the appliance check.
   */
  private function saveModel(string $eventValue): void {
    Eca::create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'array_write_process',
      'label' => 'ECA array write process',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'array_write' => [
          'plugin' => 'eca_test_array:write',
          'label' => 'Write event',
          'configuration' => [
            'key' => 'mykey',
            'value' => $eventValue,
          ],
          'successors' => [
            ['id' => 'write_array_1', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'write_array_1' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write into array',
          'configuration' => [
            // Deliberately a different key and value than the event object
            // matches on: the write dispatches another event, which must not
            // apply again, or the chain would recurse.
            'key' => 'target',
            'value' => 'written',
          ],
          'successors' => [],
        ],
      ],
    ])->save();
  }

  /**
   * Saves two ECA models that chain into a genuinely nested execution.
   *
   * The outer model reacts on a write of the 'outer' key, and its successor
   * writes the 'nested' key. Every write dispatches another array write event,
   * and the second model reacts on exactly that one, so the whole second chain
   * runs inside the successor of the first rather than after it. The nested
   * successor in turn writes the 'leaf' key, which no model subscribes to, so
   * the nesting stops at two levels.
   */
  private function saveNestedModels(): void {
    Eca::create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'outer_process',
      'label' => 'ECA outer process',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'array_write' => [
          'plugin' => 'eca_test_array:write',
          'label' => 'Outer write event',
          'configuration' => [
            'key' => 'outer',
            'value' => 'go',
          ],
          'successors' => [
            ['id' => 'trigger_nested', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'trigger_nested' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write the key the nested model reacts on',
          'configuration' => [
            'key' => 'nested',
            'value' => 'go',
          ],
          'successors' => [],
        ],
      ],
    ])->save();

    Eca::create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'nested_process',
      'label' => 'ECA nested process',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'array_write' => [
          'plugin' => 'eca_test_array:write',
          'label' => 'Nested write event',
          'configuration' => [
            'key' => 'nested',
            'value' => 'go',
          ],
          'successors' => [
            ['id' => 'write_leaf', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'write_leaf' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write into array',
          'configuration' => [
            'key' => 'leaf',
            'value' => 'done',
          ],
          'successors' => [],
        ],
      ],
    ])->save();
  }

  /**
   * Enables the ECA debug mode.
   *
   * Debug mode is required whenever a test looks at the normalized value
   * cache, because that data is only ever built from ProcessDebugger, which
   * short circuits when debugging is off. It has to be enabled through state
   * rather than by assigning the static directly, because the Processor
   * constructor re-reads state into that static when the service is first
   * instantiated.
   */
  private function enableDebugMode(): void {
    \Drupal::state()->set('_eca_internal_debug_mode', TRUE);
    // Force instantiation so the constructor applies the state, and confirm
    // the precondition the calling test depends on.
    \Drupal::service('eca.processor');
    $this->assertTrue(ProcessDebugger::$debug, 'Debug mode must be active.');
  }

  /**
   * Triggers the array write event once.
   *
   * @param string $value
   *   The value to write, which the event object matches against.
   */
  private function trigger(string $value): void {
    $this->triggerWrite('mykey', $value);
  }

  /**
   * Writes a key value pair, which dispatches the array write event.
   *
   * @param string $key
   *   The key to write, which the event object matches against.
   * @param string $value
   *   The value to write, which the event object matches against.
   */
  private function triggerWrite(string $key, string $value): void {
    \Drupal::service('plugin.manager.action')
      ->createInstance('eca_test_array_write', [
        'key' => $key,
        'value' => $value,
      ])
      ->execute();
  }

  /**
   * Tests that the list of applied events cannot grow without bound.
   */
  public function testAppliedEventsAreBounded(): void {
    $this->saveModel('myvalue');
    $this->assertSame([], Processor::getAppliedEvents(), 'Nothing applied yet.');

    $iterations = Processor::MAX_APPLIED_EVENTS + 5;
    for ($i = 0; $i < $iterations; $i++) {
      $this->trigger('myvalue');
    }

    $applied = Processor::getAppliedEvents();
    $this->assertCount(
      Processor::MAX_APPLIED_EVENTS,
      $applied,
      'The retained list must be capped, not grow with the number of events.',
    );
    $this->assertSame(
      range(0, Processor::MAX_APPLIED_EVENTS - 1),
      array_keys($applied),
      'The capped list must stay a sequential list.',
    );
    foreach ($applied as $debugger) {
      $this->assertTrue(
        $debugger->isStarted(),
        'Only debuggers whose event actually started may be retained.',
      );
    }
  }

  /**
   * Tests that a subscribed but not applicable event retains no debugger.
   *
   * The model subscribes to the array write event, so the processor does run
   * and does build a debugger, but the wildcard appliance check rejects it.
   * Such a debugger has no reachable history and no consumer, so retaining it
   * is pure memory growth.
   *
   * @see \Drupal\eca_ui\EventSubscriber\EcaEventCollector::onResponse()
   */
  public function testNotApplicableEventRetainsNoDebugger(): void {
    $this->saveModel('never_written_value');

    $subscribed = \Drupal::state()->get('eca.subscribed', []);
    $this->assertArrayHasKey(
      'eca_test_array.array_write',
      $subscribed,
      'The model must be subscribed, otherwise the processor exits early and the test proves nothing.',
    );

    $this->trigger('myvalue');

    $this->assertSame(
      [],
      Processor::getAppliedEvents(),
      'An event that does not apply must not be retained.',
    );
  }

  /**
   * Tests that a root execution clears the normalized value cache.
   *
   * The AFTER_INITIAL_EXECUTION listener observes the cache while the chain is
   * still open, so the test proves both halves: the cache is genuinely used
   * during the chain, and it is released when the chain ends.
   */
  public function testRootExecutionClearsTheNormalizedValueCache(): void {
    $this->enableDebugMode();
    $this->saveModel('myvalue');

    /** @var \Drupal\eca\Token\Browser $browser */
    $browser = \Drupal::service('eca.token_browser');
    $cache = new \ReflectionProperty(Browser::class, 'processedValues');

    $cachedDuringChain = NULL;
    \Drupal::service('event_dispatcher')->addListener(
      EcaEvents::AFTER_INITIAL_EXECUTION,
      static function (AfterInitialExecutionEvent $event) use (&$cachedDuringChain, $cache, $browser): void {
        $cachedDuringChain = $cache->getValue($browser);
      },
    );

    $this->trigger('myvalue');

    $this->assertNotEmpty(
      $cachedDuringChain,
      'The cache must be populated while the execution chain is open.',
    );
    $this->assertSame(
      [],
      $cache->getValue($browser),
      'The root execution boundary must release the cache.',
    );
  }

  /**
   * Tests that a nested execution leaves the parent chain's cache alone.
   *
   * Releasing the normalized value cache is guarded by the root execution
   * flag, and that guard is the only thing standing between this fix and a
   * real regression: while one chain is being processed, an action of that
   * chain can dispatch an event that starts a second, nested execution. If the
   * nested execution released the cache, it would wipe data the still open
   * parent chain is about to reuse.
   *
   * The flag holds because it is derived from the execution history before the
   * chain pushes its own entry, so a nested chain always sees a non-empty
   * history. This test pins that down rather than leaving it to an argument
   * about the current shape of the code, so that a later refactor of the root
   * execution boundary cannot silently reintroduce the problem.
   *
   * Three observation points are used:
   * - BEFORE_INITIAL_EXECUTION reports the execution history depth, which
   *   proves the second chain really is nested inside the first and not just
   *   a second root chain running afterwards.
   * - A listener on the array write event, registered below the priority the
   *   ECA processor subscribes at, observes the cache in the one instant where
   *   the nested chain has completely finished while the outer chain is still
   *   open and nothing has re-normalized token data in between. An unguarded
   *   reset is visible exactly here.
   * - After the trigger returns, the outer chain has ended and the cache must
   *   be gone.
   *
   * @see \Drupal\eca\Processor::execute()
   */
  public function testNestedExecutionKeepsTheParentChainCache(): void {
    $this->enableDebugMode();
    $this->saveNestedModels();

    /** @var \Drupal\eca\Token\Browser $browser */
    $browser = \Drupal::service('eca.token_browser');
    $cache = new \ReflectionProperty(Browser::class, 'processedValues');
    $processor = \Drupal::service('eca.processor');
    $history = new \ReflectionProperty(Processor::class, 'executionHistory');

    $depths = [];
    $boundaries = [];
    $cacheWhenNestedStarted = NULL;
    $cacheWhenNestedFinished = NULL;

    $dispatcher = \Drupal::service('event_dispatcher');
    $dispatcher->addListener(
      EcaEvents::BEFORE_INITIAL_EXECUTION,
      static function (BeforeInitialExecutionEvent $event) use (&$depths, &$boundaries, &$cacheWhenNestedStarted, $browser, $cache, $processor, $history): void {
        $id = (string) $event->getEca()->id();
        $depth = count((array) $history->getValue($processor));
        $depths[$id] = $depth;
        $boundaries[] = 'start:' . $id;
        if ($depth === 2) {
          // The outer chain has already recorded its first debug step, so
          // everything cached at this point belongs to the outer chain.
          $cacheWhenNestedStarted = $cache->getValue($browser);
        }
      },
    );
    $dispatcher->addListener(
      EcaEvents::AFTER_INITIAL_EXECUTION,
      static function (AfterInitialExecutionEvent $event) use (&$boundaries): void {
        $boundaries[] = 'end:' . (string) $event->getEca()->id();
      },
    );
    $dispatcher->addListener(
      ArrayEvents::WRITE,
      static function (ArrayWriteEvent $event) use (&$cacheWhenNestedFinished, $browser, $cache): void {
        if ($event->key === 'nested') {
          $cacheWhenNestedFinished = $cache->getValue($browser);
        }
      },
      -100,
    );

    $this->triggerWrite('outer', 'go');

    $this->assertSame(
      ['outer_process' => 1, 'nested_process' => 2],
      $depths,
      'The second model must run nested inside the first, not as another root chain.',
    );
    $this->assertSame(
      ['start:outer_process', 'start:nested_process', 'end:nested_process', 'end:outer_process'],
      $boundaries,
      'The nested chain must open and close while the outer chain is still open.',
    );

    $this->assertNotEmpty(
      $cacheWhenNestedStarted,
      'The outer chain must have populated the cache before the nested chain starts, otherwise there is nothing for the nested chain to destroy.',
    );
    $this->assertNotEmpty(
      $cacheWhenNestedFinished,
      'A nested execution must not release the cache of the still open outer chain.',
    );
    $this->assertSame(
      $cacheWhenNestedStarted,
      array_intersect_key((array) $cacheWhenNestedFinished, (array) $cacheWhenNestedStarted),
      'Every entry the outer chain cached must survive the nested chain unchanged.',
    );

    $this->assertSame(
      [],
      $cache->getValue($browser),
      'Only the outer, root chain may release the cache.',
    );
  }

}
