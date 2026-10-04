<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\EcaEvents;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Event\AfterInitialExecutionEvent;
use Drupal\eca\Event\BeforeInitialExecutionEvent;
use Drupal\eca\Processor;
use Drupal\eca\Token\Browser;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests where the processor draws the root execution boundary.
 *
 * Covers issue #3590441. Processor::execute() derives $is_root_execution from
 * the execution history being empty, and that history is also the stack the
 * recursion guard walks. The history entry of the running chain used to be
 * popped before AFTER_INITIAL_EXECUTION was dispatched, which left the history
 * empty for the whole teardown of a root chain. A subscriber on that event is
 * still inside ECA processing, so anything it dispatches has to be recognized
 * as nested, but it was misread as another root execution.
 *
 * @see \Drupal\eca\Processor::execute()
 * @see \Drupal\eca\Processor::isEcaContext()
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class ProcessorRootExecutionBoundaryTest extends KernelTestBase {

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
   * Saves an ECA model reacting on one array write and writing another key.
   *
   * @param string $id
   *   The ID of the model.
   * @param string $reactsOn
   *   The array key whose write the model reacts on.
   * @param string $writes
   *   The array key the successor writes. Choosing a key that no model reacts
   *   on keeps the chain from growing any further.
   */
  private function saveModel(string $id, string $reactsOn, string $writes): void {
    Eca::create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => $id,
      'label' => 'ECA ' . $id,
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'array_write' => [
          'plugin' => 'eca_test_array:write',
          'label' => 'Write event',
          'configuration' => [
            'key' => $reactsOn,
            'value' => 'go',
          ],
          'successors' => [
            ['id' => 'write_array', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'write_array' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Write into array',
          'configuration' => [
            'key' => $writes,
            'value' => 'done',
          ],
          'successors' => [],
        ],
      ],
    ])->save();
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
   * Returns the current depth of the processor's execution history.
   *
   * @return int
   *   The number of chains that are currently on the stack.
   */
  private function historyDepth(): int {
    $history = new \ReflectionProperty(Processor::class, 'executionHistory');
    return count((array) $history->getValue(\Drupal::service('eca.processor')));
  }

  /**
   * Tests that a chain started from an after listener counts as nested.
   *
   * The execution history is what tells a root chain from a nested one, and
   * the chain that is being torn down is still on that stack conceptually: its
   * AFTER_INITIAL_EXECUTION subscribers run inside its own finally block. A
   * third-party subscriber on that event may well dispatch a Drupal event that
   * ECA subscribes to, and the chain that starts from there is nested inside
   * the one being torn down, not a new root chain.
   *
   * Execution depth is measured from BEFORE_INITIAL_EXECUTION, which is the
   * same observation point ProcessorRuntimeCacheTest uses for genuine nesting.
   * A root chain reports a depth of 1 there, because it pushed its own entry
   * just before the dispatch, and a nested chain reports 2.
   *
   * @see \Drupal\Tests\eca\Kernel\ProcessorRuntimeCacheTest::testNestedExecutionKeepsTheParentChainCache()
   */
  public function testChainFromAfterListenerIsNested(): void {
    $this->saveModel('outer_process', 'outer', 'leaf');
    $this->saveModel('after_process', 'from_after', 'after_leaf');

    $depths = [];
    $boundaries = [];
    $triggered = FALSE;

    $dispatcher = \Drupal::service('event_dispatcher');
    $dispatcher->addListener(
      EcaEvents::BEFORE_INITIAL_EXECUTION,
      function (BeforeInitialExecutionEvent $event) use (&$depths, &$boundaries): void {
        $id = (string) $event->getEca()->id();
        $depths[$id] = $this->historyDepth();
        $boundaries[] = 'start:' . $id;
      },
    );
    // A plain third-party subscriber, at the default priority, that reacts to
    // a finished chain by doing something which happens to dispatch an event
    // ECA subscribes to.
    $dispatcher->addListener(
      EcaEvents::AFTER_INITIAL_EXECUTION,
      function (AfterInitialExecutionEvent $event) use (&$boundaries, &$triggered): void {
        $id = (string) $event->getEca()->id();
        $boundaries[] = 'end:' . $id;
        if ($id === 'outer_process' && !$triggered) {
          $triggered = TRUE;
          $this->triggerWrite('from_after', 'go');
        }
      },
    );

    $this->triggerWrite('outer', 'go');

    $this->assertTrue($triggered, 'The after listener must have started a second chain.');
    $this->assertSame(
      ['outer_process' => 1, 'after_process' => 2],
      $depths,
      'A chain started from an AFTER_INITIAL_EXECUTION subscriber must be treated as nested inside the chain being torn down, not as another root execution.',
    );
    $this->assertSame(
      ['start:outer_process', 'end:outer_process', 'start:after_process', 'end:after_process'],
      $boundaries,
      'The second chain must open and close inside the teardown of the first.',
    );
    $this->assertSame(
      0,
      $this->historyDepth(),
      'Both chains must have unwound their history entry.',
    );
  }

  /**
   * Tests that the after listener still observes an ECA context.
   *
   * The teardown of a chain genuinely is still ECA processing, so an after
   * listener asking Processor::isEcaContext() has to be told so. This is the
   * one observable change the reorder makes for existing subscribers, and it
   * is the accurate answer rather than a side effect.
   *
   * @see \Drupal\eca_content\Plugin\Action\FieldUpdateActionBase::save()
   */
  public function testAfterListenerObservesAnEcaContext(): void {
    $this->saveModel('outer_process', 'outer', 'leaf');

    $inContext = NULL;
    \Drupal::service('event_dispatcher')->addListener(
      EcaEvents::AFTER_INITIAL_EXECUTION,
      static function () use (&$inContext): void {
        $inContext = Processor::get()->isEcaContext();
      },
    );

    $this->triggerWrite('outer', 'go');

    $this->assertTrue($inContext, 'A chain is still ECA context while it is being torn down.');
    $this->assertFalse(
      Processor::get()->isEcaContext(),
      'Once the chain has fully unwound, the ECA context must be gone again.',
    );
  }

  /**
   * Tests that a throwing after listener cannot leak the history entry.
   *
   * Dispatching AFTER_INITIAL_EXECUTION after the history entry is popped
   * makes a throwing listener harmless for free. Dispatching it before the pop
   * does not, so the pop has to be guarded by its own finally block. Without
   * that guard the entry stays on the stack forever, every later chain in the
   * request is misread as nested, and the recursion guard starts rejecting
   * models that never recursed.
   */
  public function testThrowingAfterListenerDoesNotLeakTheHistoryEntry(): void {
    $this->saveModel('outer_process', 'outer', 'leaf');

    \Drupal::service('event_dispatcher')->addListener(
      EcaEvents::AFTER_INITIAL_EXECUTION,
      static function (): void {
        throw new \RuntimeException('After listener failed.');
      },
    );

    try {
      $this->triggerWrite('outer', 'go');
      $this->fail('Expected the failing after listener to bubble up.');
    }
    catch (\RuntimeException $e) {
      $this->assertSame('After listener failed.', $e->getMessage());
    }

    $this->assertSame(
      0,
      $this->historyDepth(),
      'A throwing after listener must not leave its history entry behind.',
    );
    $this->assertFalse(
      Processor::get()->isEcaContext(),
      'A throwing after listener must not leave the processor in ECA context.',
    );
  }

  /**
   * Tests that a throwing after listener still releases the token cache.
   *
   * The root execution boundary releases the normalized token value cache, and
   * that release sits behind the same guard as the pop. A listener that throws
   * must not turn the cache into a permanent leak either.
   *
   * @see \Drupal\eca\Token\Browser::resetProcessedValues()
   */
  public function testThrowingAfterListenerStillReleasesTheTokenCache(): void {
    \Drupal::state()->set('_eca_internal_debug_mode', TRUE);
    \Drupal::service('eca.processor');
    $this->saveModel('outer_process', 'outer', 'leaf');

    /** @var \Drupal\eca\Token\Browser $browser */
    $browser = \Drupal::service('eca.token_browser');
    $cache = new \ReflectionProperty(Browser::class, 'processedValues');

    $cachedDuringChain = NULL;
    \Drupal::service('event_dispatcher')->addListener(
      EcaEvents::AFTER_INITIAL_EXECUTION,
      static function () use (&$cachedDuringChain, $browser, $cache): void {
        $cachedDuringChain = $cache->getValue($browser);
        throw new \RuntimeException('After listener failed.');
      },
    );

    try {
      $this->triggerWrite('outer', 'go');
      $this->fail('Expected the failing after listener to bubble up.');
    }
    catch (\RuntimeException $e) {
      $this->assertSame('After listener failed.', $e->getMessage());
    }

    $this->assertNotEmpty(
      $cachedDuringChain,
      'The cache must be populated while the chain is open, otherwise its release proves nothing.',
    );
    $this->assertSame(
      [],
      $cache->getValue($browser),
      'The root execution boundary must release the cache even when an after listener throws.',
    );
  }

}
