<?php

namespace Drupal\Tests\eca\Unit;

use Drupal\Core\Action\ActionInterface as CoreActionInterface;
use Drupal\Core\Action\ActionManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\eca\Plugin\ECA\Condition\ConditionInterface;
use Drupal\eca\Plugin\ECA\Event\EventInterface;
use Drupal\eca\PluginManager\Action;
use Drupal\eca\PluginManager\Condition;
use Drupal\eca\PluginManager\Event;
use Drupal\eca\Service\Actions;
use Drupal\eca\Service\Conditions;
use Drupal\eca\Service\Events;
use Drupal\eca\Token\TokenInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the action, condition and event plugin collection services.
 */
#[Group('eca')]
class PluginCollectionServicesTest extends UnitTestCase {

  /**
   * The message of the throwable used to abort a collection loop.
   *
   * @var string
   */
  protected const string THROW_MESSAGE = 'Broken plugin definitions';

  /**
   * The message of the throwable the logger raises from inside a catch block.
   *
   * The collection services catch and log a failing ::createInstance(). A
   * logger that then throws is what turns that handled failure into a
   * throwable escaping the collection loop half way through, which is the
   * scenario this cache defect needs.
   *
   * @var string
   */
  protected const string LOGGER_THROW_MESSAGE = 'Logging failed';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    drupal_static_reset('eca_actions');
    drupal_static_reset('eca_conditions');
    drupal_static_reset('eca_events');
  }

  /**
   * Tests that the condition collection cache can be reset.
   */
  public function testConditionCollectionCacheCanBeReset(): void {
    $manager = $this->createMock(Condition::class);
    $manager->expects($this->exactly(2))
      ->method('getDefinitions')
      ->willReturn([]);
    $service = new Conditions(
      $manager,
      $this->createStub(LoggerChannelInterface::class),
      $this->createStub(EntityTypeManagerInterface::class),
      $this->createStub(LanguageManagerInterface::class),
      $this->createStub(TokenInterface::class),
      $this->createStub(ModuleExtensionList::class),
    );

    $service->conditions();
    $service->conditions();
    drupal_static_reset('eca_conditions');
    $service->conditions();
  }

  /**
   * Tests that the event collection cache can be reset.
   */
  public function testEventCollectionCacheCanBeReset(): void {
    $manager = $this->createMock(Event::class);
    $manager->expects($this->exactly(2))
      ->method('getDefinitions')
      ->willReturn([]);
    $service = new Events(
      $manager,
      $this->createStub(LoggerChannelInterface::class),
      $this->createStub(ModuleExtensionList::class),
    );

    $service->events();
    $service->events();
    drupal_static_reset('eca_events');
    $service->events();
  }

  /**
   * Tests that event instantiation failures are logged and skipped.
   */
  public function testEventInstantiationFailureIsLogged(): void {
    $manager = $this->createMock(Event::class);
    $manager->method('getDefinitions')->willReturn([
      'broken_event' => [],
    ]);
    $manager->method('createInstance')
      ->with('broken_event', [])
      ->willThrowException(new \TypeError('Broken event'));
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with(
        'The event plugin %pluginid can not be initialized. ECA is ignoring this event. The issue with this event: %msg',
        [
          '%pluginid' => 'broken_event',
          '%msg' => 'Broken event',
        ],
      );
    $service = new Events(
      $manager,
      $logger,
      $this->createStub(ModuleExtensionList::class),
    );

    $this->assertSame([], $service->events());
  }

  /**
   * Tests that a throwing event collection loop resets the error handling.
   */
  public function testEventCollectionResetsErrorHandlingOnThrow(): void {
    // This double is a stub because the test never verifies interactions with
    // it. A mock without expectations makes PHPUnit 12.5 emit a notice that
    // failOnPhpunitNotice escalates to a failure in core's next major.
    $manager = $this->createStub(Event::class);
    $manager->method('getDefinitions')
      ->willThrowException(new \TypeError(self::THROW_MESSAGE));
    $service = new Events(
      $manager,
      $this->createStub(LoggerChannelInterface::class),
      $this->createStub(ModuleExtensionList::class),
    );

    $this->assertErrorHandlingIsReset($service, static function () use ($service): void {
      $service->events();
    });
  }

  /**
   * Tests that a throwing condition collection loop resets the error handling.
   */
  public function testConditionCollectionResetsErrorHandlingOnThrow(): void {
    // This double is a stub because the test never verifies interactions with
    // it. A mock without expectations makes PHPUnit 12.5 emit a notice that
    // failOnPhpunitNotice escalates to a failure in core's next major.
    $manager = $this->createStub(Condition::class);
    $manager->method('getDefinitions')
      ->willThrowException(new \TypeError(self::THROW_MESSAGE));
    $service = new Conditions(
      $manager,
      $this->createStub(LoggerChannelInterface::class),
      $this->createStub(EntityTypeManagerInterface::class),
      $this->createStub(LanguageManagerInterface::class),
      $this->createStub(TokenInterface::class),
      $this->createStub(ModuleExtensionList::class),
    );

    $this->assertErrorHandlingIsReset($service, static function () use ($service): void {
      $service->conditions();
    });
  }

  /**
   * Tests that a throwing action collection loop resets the error handling.
   */
  public function testActionCollectionResetsErrorHandlingOnThrow(): void {
    // The Actions service does not use the ECA action plugin manager itself,
    // it immediately unwraps it into the core action manager it decorates.
    // The throwing ::getDefinitions() therefore belongs on the decorated
    // manager, not on the ECA one.
    // @see \Drupal\eca\Service\Actions::__construct()
    //
    // Both doubles are stubs because the test never verifies interactions with
    // them. A mock without expectations makes PHPUnit 12.5 emit a notice that
    // failOnPhpunitNotice escalates to a failure in core's next major.
    $decorated = $this->createStub(ActionManager::class);
    $decorated->method('getDefinitions')
      ->willThrowException(new \TypeError(self::THROW_MESSAGE));
    $manager = $this->createStub(Action::class);
    $manager->method('getDecoratedActionManager')->willReturn($decorated);
    $service = new Actions(
      $manager,
      $this->createStub(LoggerChannelInterface::class),
      $this->createStub(EntityTypeManagerInterface::class),
      $this->createStub(TokenInterface::class),
      $this->createStub(ModuleExtensionList::class),
    );

    $this->assertErrorHandlingIsReset($service, static function () use ($service): void {
      $service->actions();
    });
  }

  /**
   * Tests that a throwing event collection leaves no cache behind.
   */
  public function testEventCollectionCachesNothingOnThrow(): void {
    $manager = $this->createMock(Event::class);
    $manager->expects($this->exactly(2))
      ->method('getDefinitions')
      ->willReturn([
        'good_event' => ['id' => 'good_event'],
        'broken_event' => ['id' => 'broken_event'],
      ]);
    $plugin = $this->createStub(EventInterface::class);
    $manager->method('createInstance')
      ->willReturnCallback(static function (string $plugin_id) use ($plugin): EventInterface {
        if ($plugin_id === 'broken_event') {
          throw new \TypeError(self::THROW_MESSAGE);
        }
        return $plugin;
      });
    $service = new Events(
      $manager,
      $this->createThrowingLogger(),
      $this->createStub(ModuleExtensionList::class),
    );

    $this->assertCollectionRetriesAfterThrow('eca_events', static function () use ($service): void {
      $service->events();
    });
  }

  /**
   * Tests that a throwing condition collection leaves no cache behind.
   */
  public function testConditionCollectionCachesNothingOnThrow(): void {
    $manager = $this->createMock(Condition::class);
    $manager->expects($this->exactly(2))
      ->method('getDefinitions')
      ->willReturn([
        'good_condition' => ['id' => 'good_condition'],
        'broken_condition' => ['id' => 'broken_condition'],
      ]);
    $plugin = $this->createStub(ConditionInterface::class);
    $manager->method('createInstance')
      ->willReturnCallback(static function (string $plugin_id) use ($plugin): ConditionInterface {
        if ($plugin_id === 'broken_condition') {
          throw new \TypeError(self::THROW_MESSAGE);
        }
        return $plugin;
      });
    $service = new Conditions(
      $manager,
      $this->createThrowingLogger(),
      $this->createStub(EntityTypeManagerInterface::class),
      $this->createStub(LanguageManagerInterface::class),
      $this->createStub(TokenInterface::class),
      $this->createStub(ModuleExtensionList::class),
    );

    $this->assertCollectionRetriesAfterThrow('eca_conditions', static function () use ($service): void {
      $service->conditions();
    });
  }

  /**
   * Tests that a throwing action collection leaves no cache behind.
   */
  public function testActionCollectionCachesNothingOnThrow(): void {
    // The Actions service unwraps the ECA action plugin manager into the core
    // action manager it decorates, so the definitions belong on the decorated
    // manager. Its loop also reads $definition['id'] directly, which is why
    // every definition here carries one: a missing key would raise a warning
    // that the extended error handler forwards to the throwing logger, and the
    // test would abort for the wrong reason.
    // @see \Drupal\eca\Service\Actions::actions()
    $decorated = $this->createMock(ActionManager::class);
    $decorated->expects($this->exactly(2))
      ->method('getDefinitions')
      ->willReturn([
        'good_action' => ['id' => 'good_action'],
        'broken_action' => ['id' => 'broken_action'],
      ]);
    $plugin = $this->createStub(CoreActionInterface::class);
    $decorated->method('createInstance')
      ->willReturnCallback(static function (string $plugin_id) use ($plugin): CoreActionInterface {
        if ($plugin_id === 'broken_action') {
          throw new \TypeError(self::THROW_MESSAGE);
        }
        return $plugin;
      });
    $manager = $this->createStub(Action::class);
    $manager->method('getDecoratedActionManager')->willReturn($decorated);
    $service = new Actions(
      $manager,
      $this->createThrowingLogger(),
      $this->createStub(EntityTypeManagerInterface::class),
      $this->createStub(TokenInterface::class),
      $this->createStub(ModuleExtensionList::class),
    );

    $this->assertCollectionRetriesAfterThrow('eca_actions', static function () use ($service): void {
      $service->actions();
    });
  }

  /**
   * Creates a logger that throws when the collection loop logs a failure.
   *
   * Both collection attempts of ::assertCollectionRetriesAfterThrow() reach
   * the catch block of ::createInstance() exactly once, so the expectation
   * doubles as a check that the catch-and-log behavior itself is untouched.
   *
   * @return \Drupal\Core\Logger\LoggerChannelInterface
   *   The logger double.
   */
  protected function createThrowingLogger(): LoggerChannelInterface {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->exactly(2))
      ->method('error')
      ->willThrowException(new \RuntimeException(self::LOGGER_THROW_MESSAGE));
    return $logger;
  }

  /**
   * Asserts that a throwing collection caches nothing and retries next time.
   *
   * The collection services bind a reference to their static cache slot and
   * used to populate it in place, so a throwable escaping the collection loop
   * left the slot non-NULL, partially populated and never sorted. Every later
   * call in the same request then found a populated cache, skipped
   * re-collection and silently handed out an incomplete plugin list, which
   * makes real plugins look like plugins that do not exist.
   *
   * Collecting into a local array and publishing it only once sorting has
   * returned makes the failure loud instead: the cache stays NULL, so the next
   * caller either collects successfully or throws again. Both halves matter,
   * which is why this asserts the second attempt really does re-enter the
   * collection loop rather than merely returning something non-truncated.
   *
   * @param string $staticKey
   *   The drupal_static() key of the collection cache.
   * @param callable $collect
   *   A callable invoking the collection method that is expected to throw.
   */
  protected function assertCollectionRetriesAfterThrow(string $staticKey, callable $collect): void {
    $cache = &drupal_static($staticKey);
    $this->assertNull($cache, 'The cache must start out empty, otherwise the test proves nothing.');

    for ($attempt = 1; $attempt <= 2; $attempt++) {
      try {
        $collect();
        $this->fail(sprintf('Attempt %d: expected the collection method to propagate the throwable.', $attempt));
      }
      catch (\RuntimeException $e) {
        $this->assertSame(
          self::LOGGER_THROW_MESSAGE,
          $e->getMessage(),
          sprintf('Attempt %d: the throwable must reach the caller unchanged.', $attempt),
        );
      }

      $this->assertNull(
        $cache,
        sprintf('Attempt %d: a failed collection must not leave a partially populated cache behind.', $attempt),
      );
    }
  }

  /**
   * Asserts that a collection method resets extended error handling.
   *
   * The collection services suppress error reporting, install a custom error
   * handler and arm an echoing shutdown function while they instantiate all
   * available plugins. When the collection loop throws, all of that has to be
   * undone, otherwise the remainder of the request silently runs with error
   * reporting turned off and a fatal error handler that echoes into the
   * response.
   *
   * @param object $service
   *   The plugin collection service under test.
   * @param callable $collect
   *   A callable invoking the collection method that is expected to throw.
   */
  protected function assertErrorHandlingIsReset(object $service, callable $collect): void {
    $level = error_reporting();
    $handler = set_error_handler(NULL);
    restore_error_handler();

    try {
      $collect();
      $this->fail('Expected the collection method to propagate the throwable.');
    }
    catch (\TypeError $e) {
      $this->assertSame(self::THROW_MESSAGE, $e->getMessage());
    }

    $this->assertSame($level, error_reporting(), 'Error reporting has been restored to its original level.');

    $enabled = (new \ReflectionProperty($service, 'shutdownFunctionEnabled'))
      ->getValue($service);
    $this->assertFalse($enabled, 'The echoing shutdown function has been disarmed.');

    $current = set_error_handler(NULL);
    restore_error_handler();
    $this->assertSame($handler, $current, 'The custom error handler has been removed.');
  }

}
