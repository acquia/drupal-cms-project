<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\EcaEvents;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Event\AfterInitialExecutionEvent;
use Drupal\eca\EventSubscriber\EcaExecutionFormSubscriber;
use Drupal\eca\EventSubscriber\EcaExecutionGeneralSubscriber;
use Drupal\eca\EventSubscriber\EcaExecutionSwitchAccountSubscriber;
use Drupal\eca\EventSubscriber\EcaExecutionTokenSubscriber;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\ErrorHandler\BufferingLogger;

/**
 * Tests that one throwing after listener cannot starve the others.
 *
 * Covers issue #3590430. EcaExecutionSwitchAccountSubscriber unwinds the
 * account switch from AFTER_INITIAL_EXECUTION at priority -500, so it runs
 * last. Any listener above it that throws used to abort the whole dispatch,
 * and the switched account then leaked for the rest of the process. In a
 * request that is not much of a window; in Drush, a queue worker or a
 * persistent worker SAPI it can outlive the model by hours.
 *
 * The mirror problem on the before event (#3590439) was fixable by moving that
 * dispatch inside the try block that owns the cleanup. That does not work
 * here, because the after dispatch already runs from that very finally block:
 * the exception happens inside the mechanism meant to guarantee cleanup.
 *
 * The processor therefore dispatches this one event itself, calling every
 * listener in priority order, collecting throwables instead of letting the
 * first one abort the rest, and re-throwing afterwards.
 *
 * @see \Drupal\eca\Processor::execute()
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class ProcessorAfterExecutionIsolationTest extends KernelTestBase {

  /**
   * The service name of a logger that collects everything being logged.
   *
   * @var string
   */
  protected static string $testLogServiceName = 'eca_test.logger';

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
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container
      ->register(self::$testLogServiceName, BufferingLogger::class)
      ->addTag('logger');
  }

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'model_user'])->save();
    // ECA decorates its logger channel with a configurable severity
    // threshold. Open it up to ERROR so the secondary throwables this test
    // asserts on actually reach the buffering logger.
    // @see \Drupal\eca\ConfigurableLoggerChannel::updateLogLevel()
    \Drupal::service('logger.channel.eca')->updateLogLevel(RfcLogLevel::ERROR);

    Eca::create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'isolation_process',
      'label' => 'ECA isolation process',
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
   * Configures ECA to run its models under the given account.
   *
   * @param string $uid
   *   The user ID to switch to.
   */
  private function runModelsAs(string $uid): void {
    \Drupal::configFactory()->getEditable('eca.settings')->set('user', $uid)->save();
  }

  /**
   * Registers an after listener.
   *
   * @param callable $listener
   *   The listener.
   * @param int $priority
   *   The priority. Anything above -500 runs before the account switch is
   *   unwound, which is the interesting range for this issue.
   */
  private function onAfter(callable $listener, int $priority): void {
    \Drupal::service('event_dispatcher')
      ->addListener(EcaEvents::AFTER_INITIAL_EXECUTION, $listener, $priority);
  }

  /**
   * Triggers the model chain once.
   */
  private function trigger(): void {
    \Drupal::service('plugin.manager.action')
      ->createInstance('eca_test_array_write', [
        'key' => 'mykey',
        'value' => 'myvalue',
      ])
      ->execute();
  }

  /**
   * Returns everything logged so far, as "level: message" strings.
   *
   * BufferingLogger keeps the raw message and its context apart, so the
   * placeholders are substituted here. Without that, a message would be
   * matched against its literal "%message" placeholder rather than against the
   * value the test cares about.
   *
   * @return string[]
   *   The collected log entries, with placeholders substituted.
   */
  private function collectedLogs(): array {
    $logs = [];
    foreach (\Drupal::service(self::$testLogServiceName)->cleanLogs() as $log) {
      $context = array_filter($log[2] ?? [], 'is_scalar');
      $logs[] = $log[0] . ': ' . strtr($log[1], array_map('strval', $context));
    }
    return $logs;
  }

  /**
   * Tests what the dispatcher hands back for this event.
   *
   * The isolating dispatch invokes the listeners itself, so it depends on
   * ::getListeners() returning callables that are ready to call rather than
   * unresolved service references. Symfony resolves lazy service listeners
   * while sorting them, but that is an implementation detail worth pinning
   * with a test rather than trusting: getting it wrong would silently skip
   * every subscriber registered through the container.
   *
   * @see \Symfony\Component\EventDispatcher\EventDispatcher::sortListeners()
   */
  public function testGetListenersReturnsResolvedCallables(): void {
    $listeners = \Drupal::service('event_dispatcher')
      ->getListeners(EcaEvents::AFTER_INITIAL_EXECUTION);

    $this->assertNotEmpty($listeners, 'The event must have listeners, otherwise this proves nothing.');

    $classes = [];
    foreach ($listeners as $listener) {
      $this->assertIsCallable($listener, 'Every listener must be directly callable.');
      if (is_array($listener)) {
        $this->assertIsObject(
          $listener[0],
          'A service listener must arrive as an instantiated object, not as a service ID or an unresolved closure.',
        );
        $classes[] = $listener[0]::class;
      }
    }

    // All four in-tree subscribers must be present and resolved.
    foreach ([
      EcaExecutionTokenSubscriber::class,
      EcaExecutionSwitchAccountSubscriber::class,
      EcaExecutionGeneralSubscriber::class,
      EcaExecutionFormSubscriber::class,
    ] as $expected) {
      $this->assertContains($expected, $classes, sprintf('%s must be among the resolved listeners.', $expected));
    }
  }

  /**
   * Tests that a throwing listener no longer leaks the account switch.
   *
   * This is the defect itself. The listener throws at priority 0, well above
   * the -500 at which the account switch is unwound.
   */
  public function testThrowingListenerDoesNotLeakTheAccountSwitch(): void {
    $this->runModelsAs('1');
    $this->assertSame('0', (string) \Drupal::currentUser()->id(), 'The test starts out anonymous.');

    $switchedDuringChain = NULL;
    \Drupal::service('event_dispatcher')->addListener(
      EcaEvents::BEFORE_INITIAL_EXECUTION,
      static function () use (&$switchedDuringChain): void {
        $switchedDuringChain = (string) \Drupal::currentUser()->id();
      },
      450,
    );
    $this->onAfter(static function (): void {
      throw new \RuntimeException('After listener failed.');
    }, 0);

    try {
      $this->trigger();
      $this->fail('Expected the failing after listener to bubble up to the caller.');
    }
    catch (\RuntimeException $e) {
      $this->assertSame(
        'After listener failed.',
        $e->getMessage(),
        'The exception must still reach the caller, unchanged.',
      );
    }

    $this->assertSame(
      '1',
      $switchedDuringChain,
      'The account switch must have happened, otherwise there is no leak to prove.',
    );
    $this->assertSame(
      '0',
      (string) \Drupal::currentUser()->id(),
      'A throwing after listener must not stop the account switch from being unwound.',
    );
  }

  /**
   * Tests that every listener runs, in priority order, despite a thrower.
   *
   * The probes bracket the throwing listener on both sides, so this covers
   * both halves: listeners above it were always reached, and listeners below
   * it are the ones that used to be starved.
   */
  public function testEveryListenerRunsInPriorityOrder(): void {
    $order = [];
    $probe = static function (string $name) use (&$order): callable {
      return static function () use ($name, &$order): void {
        $order[] = $name;
      };
    };

    $this->onAfter($probe('first'), 900);
    $this->onAfter($probe('second'), 100);
    $this->onAfter(static function (): void {
      throw new \RuntimeException('After listener failed.');
    }, 0);
    $this->onAfter($probe('third'), -100);
    $this->onAfter($probe('fourth'), -900);

    try {
      $this->trigger();
      $this->fail('Expected the failing after listener to bubble up.');
    }
    catch (\RuntimeException) {
      // Expected.
    }

    $this->assertSame(
      ['first', 'second', 'third', 'fourth'],
      $order,
      'Every listener must run, in descending priority order, regardless of an earlier one throwing.',
    );
  }

  /**
   * Tests that the normal case is unchanged when nothing throws.
   *
   * Isolation must not become an excuse for a different dispatch order or a
   * skipped subscriber when everything behaves.
   */
  public function testNormalDispatchIsUnchanged(): void {
    $this->runModelsAs('1');

    $order = [];
    $probe = static function (string $name) use (&$order): callable {
      return static function () use ($name, &$order): void {
        $order[] = $name;
      };
    };
    $this->onAfter($probe('high'), 900);
    $this->onAfter($probe('mid'), 0);
    $this->onAfter($probe('low'), -900);

    $this->trigger();

    $this->assertSame(['high', 'mid', 'low'], $order, 'Listeners must run in descending priority order.');
    $this->assertSame(
      '0',
      (string) \Drupal::currentUser()->id(),
      'The account switch must be unwound in the normal case too.',
    );
    $this->assertSame([], $this->collectedLogs(), 'Nothing may be logged as an error when no listener throws.');
  }

  /**
   * Tests that stopPropagation() is still honored.
   *
   * Isolation guarantees that a *throwing* listener cannot starve the others.
   * It must not override the event contract: a listener that deliberately
   * stops propagation still skips everything below it, including the account
   * switch unwind. That is the caller's decision to make, not the processor's
   * to override.
   */
  public function testStopPropagationIsHonored(): void {
    $reached = [];
    $this->onAfter(static function () use (&$reached): void {
      $reached[] = 'above';
    }, 900);
    $this->onAfter(static function (AfterInitialExecutionEvent $event): void {
      $event->stopPropagation();
    }, 500);
    $this->onAfter(static function () use (&$reached): void {
      $reached[] = 'below';
    }, -900);

    $this->trigger();

    $this->assertSame(
      ['above'],
      $reached,
      'A listener below one that stopped propagation must not run.',
    );
  }

  /**
   * Tests that a second throwing listener is not silently lost.
   *
   * The first throwable is re-thrown unchanged, so the caller sees exactly
   * what it would have seen before this change. PHP offers no way to attach a
   * previous exception to an already constructed one, and wrapping the first
   * throwable would change the class the caller catches - conditionally on how
   * many listeners happened to throw, which is worse than either consistent
   * behavior. The remaining throwables are therefore logged.
   */
  public function testAdditionalThrowablesAreLoggedNotLost(): void {
    $this->onAfter(static function (): void {
      throw new \RuntimeException('First failure.');
    }, 100);
    $this->onAfter(static function (): void {
      throw new \LogicException('Second failure.');
    }, 0);
    $this->onAfter(static function (): void {
      throw new \TypeError('Third failure.');
    }, -100);

    try {
      $this->trigger();
      $this->fail('Expected the first failure to bubble up.');
    }
    catch (\RuntimeException $e) {
      $this->assertSame(
        'First failure.',
        $e->getMessage(),
        'The first throwable must be re-thrown unchanged, so the single-thrower case is unaffected.',
      );
    }

    $logs = implode("\n", $this->collectedLogs());
    $this->assertStringContainsString(
      'Second failure.',
      $logs,
      'A second throwing listener must be logged rather than discarded.',
    );
    $this->assertStringContainsString(
      'Third failure.',
      $logs,
      'A third throwing listener must be logged rather than discarded.',
    );
    $this->assertStringNotContainsString(
      'First failure.',
      $logs,
      'The re-thrown throwable must not also be logged, or every caller would see it twice.',
    );
  }

  /**
   * Tests that an \Error in a listener is isolated just like an exception.
   *
   * A TypeError from a subscriber is at least as likely as an exception and
   * leaks the account just the same, so the isolation catches \Throwable.
   */
  public function testErrorInListenerIsAlsoIsolated(): void {
    $this->runModelsAs('1');
    $this->onAfter(static function (): void {
      throw new \TypeError('After listener failed with an Error.');
    }, 0);

    try {
      $this->trigger();
      $this->fail('Expected the failing after listener to bubble up.');
    }
    catch (\TypeError $e) {
      $this->assertSame('After listener failed with an Error.', $e->getMessage());
    }

    $this->assertSame(
      '0',
      (string) \Drupal::currentUser()->id(),
      'An \Error must not leak the account switch either.',
    );
  }

}
