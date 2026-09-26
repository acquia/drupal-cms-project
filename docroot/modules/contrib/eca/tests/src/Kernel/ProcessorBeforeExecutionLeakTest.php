<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\EcaEvents;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Processor;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that a throwing BEFORE_INITIAL_EXECUTION listener leaks nothing.
 *
 * Covers issue #3590439. Processor::execute() pushed its execution history
 * entry and dispatched BEFORE_INITIAL_EXECUTION before entering the try block
 * that owns the cleanup, so a listener throwing from that dispatch escaped
 * past every unwind:
 *
 * - The account switch that EcaExecutionSwitchAccountSubscriber performs at
 *   priority 500 stayed active for the rest of the process.
 * - The execution history entry pushed just before the dispatch was never
 *   popped, so every later chain in the request looked nested. That keeps the
 *   root execution boundary from ever being reached again and lets the
 *   recursion guard start rejecting models that never recursed.
 *
 * This is the same class of defect as #3503270, which covered the window after
 * the try was entered; this covers the window before it.
 *
 * @see \Drupal\eca\Processor::execute()
 * @see \Drupal\Tests\eca\Kernel\ExecutionSubscriberGuardTest
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class ProcessorBeforeExecutionLeakTest extends KernelTestBase {

  /**
   * The message of the throwable raised from the before listener.
   *
   * @var string
   */
  protected const string THROW_MESSAGE = 'Before listener failed.';

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
    User::create(['uid' => 1, 'name' => 'model_user'])->save();

    Eca::create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'leak_process',
      'label' => 'ECA leak process',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'array_write' => [
          'plugin' => 'eca_test_array:write',
          'label' => 'Write event',
          'configuration' => ['key' => 'mykey', 'value' => 'myvalue'],
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
          'configuration' => ['key' => 'target', 'value' => 'written'],
          'successors' => [],
        ],
      ],
    ])->save();
  }

  /**
   * Registers a before listener that throws at the given priority.
   *
   * The priority decides which subscribers already did their work when the
   * throwable escapes, so every test states it explicitly.
   *
   * @param int $priority
   *   The listener priority.
   */
  private function throwFromBeforeListener(int $priority): void {
    \Drupal::service('event_dispatcher')->addListener(
      EcaEvents::BEFORE_INITIAL_EXECUTION,
      static function (): void {
        throw new \RuntimeException(self::THROW_MESSAGE);
      },
      $priority,
    );
  }

  /**
   * Triggers the model and asserts the throwable reached the caller.
   *
   * The exception must never be swallowed: this issue is about the state left
   * behind, not about hiding the failure.
   */
  private function triggerAndExpectThrow(): void {
    try {
      \Drupal::service('plugin.manager.action')
        ->createInstance('eca_test_array_write', [
          'key' => 'mykey',
          'value' => 'myvalue',
        ])
        ->execute();
      $this->fail('Expected the failing before listener to bubble up to the caller.');
    }
    catch (\RuntimeException $e) {
      $this->assertSame(self::THROW_MESSAGE, $e->getMessage());
    }
  }

  /**
   * Returns the current depth of the processor's execution history.
   *
   * @return int
   *   The number of entries on the stack.
   */
  private function historyDepth(): int {
    $history = new \ReflectionProperty(Processor::class, 'executionHistory');
    return count((array) $history->getValue(\Drupal::service('eca.processor')));
  }

  /**
   * Tests that a throwing before listener leaves no history entry behind.
   *
   * The listener is registered below the priority of every in-tree subscriber
   * so that the whole before phase has run when it throws, which is the worst
   * case for what is left dangling.
   */
  public function testThrowingBeforeListenerDoesNotLeakTheHistoryEntry(): void {
    $this->throwFromBeforeListener(-1000);
    $this->assertSame(0, $this->historyDepth(), 'The history must start out empty.');

    $this->triggerAndExpectThrow();

    $this->assertSame(
      0,
      $this->historyDepth(),
      'A throwing before listener must not strand its execution history entry.',
    );
    $this->assertFalse(
      Processor::get()->isEcaContext(),
      'Once the failed chain has unwound, the processor must not still report an ECA context.',
    );
  }

  /**
   * Tests that a later chain is still recognized as a root execution.
   *
   * This is what the stranded history entry actually costs. A leaked entry
   * makes every subsequent chain in the request look nested, which is exactly
   * the state in which the recursion guard starts rejecting models.
   */
  public function testLaterChainIsStillRootExecution(): void {
    $this->throwFromBeforeListener(-1000);
    $this->triggerAndExpectThrow();

    // Registered above the throwing listener, which sits at -1000, so that it
    // still observes the second chain before that chain fails as well.
    $depths = [];
    \Drupal::service('event_dispatcher')->addListener(
      EcaEvents::BEFORE_INITIAL_EXECUTION,
      function () use (&$depths): void {
        $depths[] = $this->historyDepth();
      },
      -900,
    );

    // The throwing listener is still registered, so this chain fails too - but
    // it must fail from a clean starting point.
    $this->triggerAndExpectThrow();

    $this->assertSame(
      [1],
      $depths,
      'A chain started after a failed one must see a depth of 1, meaning it pushed the only entry on the stack and counts as a root execution.',
    );
  }

  /**
   * Tests that a throwing before listener does not leak the account switch.
   *
   * EcaExecutionSwitchAccountSubscriber switches at priority 500, so a
   * listener throwing below that has a switch already in place. Nothing else
   * would ever switch it back, leaving the rest of the process running under
   * the model user.
   *
   * @see \Drupal\eca\EventSubscriber\EcaExecutionSwitchAccountSubscriber
   */
  public function testThrowingBeforeListenerDoesNotLeakTheAccountSwitch(): void {
    \Drupal::configFactory()->getEditable('eca.settings')->set('user', '1')->save();
    $this->throwFromBeforeListener(-1000);

    $this->assertSame('0', (string) \Drupal::currentUser()->id(), 'The test starts out anonymous.');

    $switchedDuringChain = NULL;
    \Drupal::service('event_dispatcher')->addListener(
      EcaEvents::BEFORE_INITIAL_EXECUTION,
      static function () use (&$switchedDuringChain): void {
        $switchedDuringChain = (string) \Drupal::currentUser()->id();
      },
      450,
    );

    $this->triggerAndExpectThrow();

    $this->assertSame(
      '1',
      $switchedDuringChain,
      'The account switch must have happened before the listener threw, otherwise there is no leak to prove.',
    );
    $this->assertSame(
      '0',
      (string) \Drupal::currentUser()->id(),
      'A throwing before listener must not leave the account switch in place.',
    );
  }

  /**
   * Tests that the enclosing scope's token data survives a failing chain.
   *
   * The throwing listener sits above the token subscriber's priority of 1000,
   * so that subscriber's before handler never runs and never records the
   * enclosing scope. Its after handler must therefore leave the token data
   * alone rather than restore an absent prestate over it.
   *
   * This is the end-to-end counterpart of the direct subscriber test, and it
   * is the case that made the naive version of this fix unsafe.
   *
   * @see \Drupal\Tests\eca\Kernel\ExecutionSubscriberGuardTest::testTokenDataOfTheEnclosingScopeSurvives()
   */
  public function testTokenDataOfTheEnclosingScopeSurvives(): void {
    /** @var \Drupal\eca\Token\TokenInterface $tokenService */
    $tokenService = \Drupal::service('eca.token_services');
    $tokenService->addTokenData('outer', 'i belong to the outer scope');

    $this->throwFromBeforeListener(2000);
    $this->triggerAndExpectThrow();

    // The token service normalizes a scalar it is handed into a data transfer
    // object, so assert on the replaced value: that is what a model sees, and
    // a wiped token replaces to an empty string.
    $this->assertSame(
      'i belong to the outer scope',
      (string) $tokenService->replaceClear('[outer]'),
      'A failed chain must not wipe the token data of the scope that enclosed it.',
    );
  }

}
