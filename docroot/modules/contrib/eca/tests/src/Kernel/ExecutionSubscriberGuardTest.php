<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\eca\Kernel\Fixtures\FormEventStub;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Event\AfterInitialExecutionEvent;
use Drupal\eca\Event\BeforeInitialExecutionEvent;
use Drupal\eca\EventSubscriber\EcaExecutionFormSubscriber;
use Drupal\eca\EventSubscriber\EcaExecutionTokenSubscriber;
use Drupal\eca_test_array\Event\ArrayWriteEvent;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the execution subscribers unwind only what they themselves did.
 *
 * Covers step 1 of issue #3590439. Processor::execute() dispatches
 * BEFORE_INITIAL_EXECUTION outside its try block, so a listener that throws
 * there leaks whatever earlier listeners already changed. Moving the dispatch
 * inside the try fixes the leak but makes AFTER_INITIAL_EXECUTION fire for
 * subscribers whose before handler never ran, and an after handler that
 * unwinds unconditionally then corrupts the enclosing scope.
 *
 * Two of the four in-tree pairs were unguarded:
 * - EcaExecutionTokenSubscriber cleared the token data and restored from an
 *   absent prestate, wiping the enclosing scope.
 * - EcaExecutionFormSubscriber shifted a form event stack entry that its own
 *   before handler never pushed.
 *
 * Both now arm a prestate flag once the thing that needs unwinding has
 * actually happened, and the after handler returns early without it - the
 * pattern EcaExecutionSwitchAccountSubscriber already used for its
 * "switch_account" flag.
 *
 * These tests drive the subscribers directly rather than through the
 * Processor. The form subscriber only acts on a FormEventInterface, which
 * cannot be produced without a real form build, and driving the pair directly
 * states the contract - "unwind only your own work" - without that machinery.
 * The equivalent end-to-end proof for the token subscriber lives in
 * ProcessorBeforeExecutionLeakTest.
 *
 * @see \Drupal\eca\EventSubscriber\EcaExecutionSwitchAccountSubscriber::onAfterInitialExecution()
 * @see \Drupal\Tests\eca\Kernel\ProcessorBeforeExecutionLeakTest
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class ExecutionSubscriberGuardTest extends KernelTestBase {

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
    Eca::create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'guard_process',
      'label' => 'ECA guard process',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'array_write' => [
          'plugin' => 'eca_test_array:write',
          'label' => 'Write event',
          'configuration' => ['key' => 'mykey', 'value' => 'myvalue'],
          'successors' => [],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [],
    ])->save();
  }

  /**
   * Builds the pair of events the subscribers are handed.
   *
   * The after event shares the before event's prestate array by reference,
   * exactly as Processor::execute() wires it up, so a flag armed by the before
   * handler is visible to the after handler.
   *
   * @param \Symfony\Contracts\EventDispatcher\Event|null $triggered
   *   The system event that ECA is reacting to.
   *
   * @return array
   *   The before event and the after event, in that order.
   */
  private function eventPair(?object $triggered = NULL): array {
    $eca = Eca::load('guard_process');
    $ecaEvent = $eca->getEcaEvent('array_write');
    $triggered = $triggered ?? new ArrayWriteEvent('mykey', 'myvalue');

    $before = new BeforeInitialExecutionEvent($eca, $ecaEvent, $triggered, 'eca_test_array.array_write');
    $prestate = &$before->getPrestate(NULL);
    $after = new AfterInitialExecutionEvent($eca, $ecaEvent, $triggered, 'eca_test_array.array_write', $prestate);
    return [$before, $after];
  }

  /**
   * Builds a form event carrying a form object that is not an entity form.
   *
   * That keeps the before handler out of its entity token branch, so these
   * tests stay on the stack bookkeeping the guard actually affects.
   *
   * @return \Drupal\Tests\eca\Kernel\Fixtures\FormEventStub
   *   The form event.
   */
  private function formEvent(): FormEventStub {
    $formState = $this->createStub(FormStateInterface::class);
    $formState->method('getFormObject')->willReturn($this->createStub(FormInterface::class));
    return new FormEventStub($formState);
  }

  /**
   * Returns what a model would see when replacing the given token.
   *
   * The token service normalizes scalars it is handed into a data transfer
   * object, so asserting on the replaced value rather than on the stored one
   * keeps these assertions about the observable behavior. A token whose data
   * was wiped replaces to an empty string.
   *
   * @param string $key
   *   The token key.
   *
   * @return string
   *   The replaced value.
   */
  private function tokenValue(string $key): string {
    return (string) \Drupal::service('eca.token_services')->replaceClear('[' . $key . ']');
  }

  /**
   * Tests that an after handler alone leaves the enclosing token data alone.
   *
   * This is the failure the naive fix produced: the token subscriber runs at
   * priority 1000, so a listener throwing above it means its before handler
   * never saved the enclosing scope. Restoring from that absent prestate
   * replaced real data with nothing.
   */
  public function testTokenDataOfTheEnclosingScopeSurvives(): void {
    /** @var \Drupal\eca\Token\TokenInterface $tokenService */
    $tokenService = \Drupal::service('eca.token_services');
    $tokenService->addTokenData('outer', 'i belong to the outer scope');

    /** @var \Drupal\eca\EventSubscriber\EcaExecutionTokenSubscriber $subscriber */
    $subscriber = \Drupal::service('eca.execution.token_subscriber');
    $this->assertInstanceOf(EcaExecutionTokenSubscriber::class, $subscriber);

    // Only the after handler runs, as if the before handler had been skipped.
    [, $after] = $this->eventPair();
    $subscriber->onAfterInitialExecution($after);

    $this->assertSame(
      'i belong to the outer scope',
      $this->tokenValue('outer'),
      'An after handler whose before handler never ran must not wipe the token data of the enclosing scope.',
    );
  }

  /**
   * Tests that the normal pair still scopes and restores token data.
   *
   * The guard must not turn the subscriber into a no-op: when the before
   * handler did run, the after handler still has to restore the enclosing
   * scope and discard everything the inner scope added.
   */
  public function testTokenDataIsStillScopedWhenTheBeforeHandlerRan(): void {
    /** @var \Drupal\eca\Token\TokenInterface $tokenService */
    $tokenService = \Drupal::service('eca.token_services');
    $tokenService->addTokenData('outer', 'i belong to the outer scope');

    /** @var \Drupal\eca\EventSubscriber\EcaExecutionTokenSubscriber $subscriber */
    $subscriber = \Drupal::service('eca.execution.token_subscriber');
    [$before, $after] = $this->eventPair();

    $subscriber->onBeforeInitialExecution($before);
    $this->assertSame(
      '',
      $this->tokenValue('outer'),
      'The before handler must scope the enclosing token data out.',
    );
    $tokenService->addTokenData('inner', 'local to the chain');

    $subscriber->onAfterInitialExecution($after);
    $this->assertSame(
      'i belong to the outer scope',
      $this->tokenValue('outer'),
      'The after handler must restore the enclosing scope.',
    );
    $this->assertSame(
      '',
      $this->tokenValue('inner'),
      'Locally scoped token data must not break out of the chain.',
    );
  }

  /**
   * Tests that an after handler alone does not shift the form event stack.
   *
   * The form subscriber pushes onto its own stack in the before handler and
   * shifts in the after handler. Shifting without having pushed removes the
   * entry belonging to the enclosing form scope.
   */
  public function testFormEventStackIsNotShiftedWithoutPush(): void {
    /** @var \Drupal\eca\EventSubscriber\EcaExecutionFormSubscriber $subscriber */
    $subscriber = \Drupal::service('eca.execution.form_subscriber');
    $this->assertInstanceOf(EcaExecutionFormSubscriber::class, $subscriber);

    // Seed the stack as an enclosing form scope would have.
    $enclosing = $this->formEvent();
    $stack = new \ReflectionProperty(EcaExecutionFormSubscriber::class, 'eventStack');
    $stack->setValue($subscriber, [$enclosing]);

    // Only the after handler runs, with a form event, as if the before handler
    // had been skipped.
    [, $after] = $this->eventPair($this->formEvent());
    $subscriber->onAfterInitialExecution($after);

    $stacked = $subscriber->getStackedFormEvents();
    $this->assertCount(
      1,
      $stacked,
      'An after handler whose before handler never pushed must not shift the enclosing form scope off the stack.',
    );
    $this->assertSame($enclosing, $stacked[0], 'The surviving entry must be the enclosing one.');
  }

  /**
   * Tests that the normal pair still pushes and shifts the form event stack.
   */
  public function testFormEventStackIsStillMaintainedForTheNormalPair(): void {
    /** @var \Drupal\eca\EventSubscriber\EcaExecutionFormSubscriber $subscriber */
    $subscriber = \Drupal::service('eca.execution.form_subscriber');

    $enclosing = $this->formEvent();
    $stack = new \ReflectionProperty(EcaExecutionFormSubscriber::class, 'eventStack');
    $stack->setValue($subscriber, [$enclosing]);

    $inner = $this->formEvent();
    [$before, $after] = $this->eventPair($inner);
    $subscriber->onBeforeInitialExecution($before);
    $pushed = $subscriber->getStackedFormEvents();
    $this->assertCount(2, $pushed, 'The before handler must push the current form event onto the stack.');
    $this->assertSame($inner, $pushed[0], 'The most recent entry must be the current form event.');

    $subscriber->onAfterInitialExecution($after);
    $shifted = $subscriber->getStackedFormEvents();
    $this->assertCount(1, $shifted, 'The after handler must shift exactly the entry its before handler pushed.');
    $this->assertSame($enclosing, $shifted[0], 'The enclosing entry must remain.');
  }

}
