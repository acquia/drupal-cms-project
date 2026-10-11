<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\EcaEvents;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Event\BeforeInitialExecutionEvent;
use Drupal\eca\EventSubscriber\EcaExecutionSwitchAccountSubscriber;
use Drupal\eca\Processor;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for switching the account during ECA model execution.
 *
 * These tests deliberately exercise more than one model execution within the
 * same PHP process, and change the current user and the configuration in
 * between. That is not an exotic setup: a Drush command, a cron run, a queue
 * worker and a test run all execute many models in one process, and the
 * current user does change in between - core's \Drupal\Core\Cron switches to
 * anonymous for the duration of a cron run, and ECA's own
 * eca_switch_account action switches too. Anything the subscriber remembers
 * across executions therefore has to stay correct.
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class EcaExecutionSwitchAccountSubscriberTest extends KernelTestBase {

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
   * The user IDs seen as the current user while a model was being executed.
   *
   * @var string[]
   */
  protected array $observedUids = [];

  /**
   * The user IDs seen in the "session_user" Token while executing a model.
   *
   * A missing Token is recorded as an empty string.
   *
   * @var string[]
   */
  protected array $observedSessionUids = [];

  /**
   * The number of times AFTER_INITIAL_EXECUTION was dispatched.
   *
   * @var int
   */
  protected int $observedAfterEvents = 0;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    User::create(['uid' => 2, 'name' => 'editor'])->save();
    $this->createModel();
    $this->observeExecutions();
  }

  /**
   * Tests that a changed model user setting is picked up by a later execution.
   */
  public function testChangedModelUserIsPickedUp(): void {
    $this->setModelUser('1');
    $this->triggerModel();
    $this->assertSame(['1'], $this->observedUids);
    $this->assertTrue(\Drupal::currentUser()->isAnonymous(), 'The account must be switched back after execution.');

    // Change the setting within the very same process. The next execution has
    // to use the newly configured account, not the one that was resolved for
    // the first execution.
    $this->setModelUser('2');
    $this->triggerModel();
    $this->assertSame(['1', '2'], $this->observedUids);
    $this->assertTrue(\Drupal::currentUser()->isAnonymous(), 'The account must be switched back after execution.');

    // Clearing the setting has to stop the account switching altogether.
    $this->setModelUser('');
    $this->triggerModel();
    $this->assertSame(['1', '2', '0'], $this->observedUids);
    $this->assertTrue(\Drupal::currentUser()->isAnonymous(), 'The account must be switched back after execution.');
  }

  /**
   * Tests that the model user is resolved from a UUID on every execution.
   */
  public function testChangedModelUserUuidIsPickedUp(): void {
    $this->setModelUser(User::load(1)->uuid());
    $this->triggerModel();
    $this->assertSame(['1'], $this->observedUids);

    $this->setModelUser(User::load(2)->uuid());
    $this->triggerModel();
    $this->assertSame(['1', '2'], $this->observedUids);
  }

  /**
   * Tests that a changed current user is reflected in the session_user Token.
   *
   * This is the shape of an ordinary request on a site with automated_cron
   * enabled: a model executes for the authenticated visitor, then \Drupal\
   * Core\Cron switches the account for the cron run at kernel.terminate and a
   * model executes again in the very same process. It is also the shape of a
   * cron run or a queue run that processes items belonging to different
   * accounts.
   */
  public function testChangedCurrentUserIsPickedUp(): void {
    $this->setModelUser('1');
    $this->triggerModel();
    $this->assertSame(['0'], $this->observedSessionUids);

    // A second execution in the same process, under a different account. This
    // is what \Drupal\Core\Cron::run() does with its switchTo() before it
    // invokes hook_cron().
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo(User::load(2));
    try {
      $this->triggerModel();
      $this->assertSame('2', (string) \Drupal::currentUser()->id(), 'Only the ECA account switch may be unwound.');
    }
    finally {
      $account_switcher->switchBack();
    }

    $this->assertSame(['0', '2'], $this->observedSessionUids, 'The session_user Token must follow the current user of the execution.');
    $this->assertSame(['1', '1'], $this->observedUids, 'Both executions must still run under the configured model user.');
    $this->assertTrue(\Drupal::currentUser()->isAnonymous());
  }

  /**
   * Tests that a nested execution keeps the session user of the outer one.
   */
  public function testNestedExecutionKeepsTheSessionUser(): void {
    $this->createNestedModel();
    $this->setModelUser('1');

    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo(User::load(2));
    try {
      $this->triggerModel();
      $this->assertSame('2', (string) \Drupal::currentUser()->id(), 'Only the ECA account switches may be unwound.');
    }
    finally {
      $account_switcher->switchBack();
    }

    $this->assertSame(['1', '1'], $this->observedUids, 'The nested execution must run under the model user as well.');
    $this->assertSame(['2', '2'], $this->observedSessionUids, 'The nested execution must keep the session user of the outer one.');
  }

  /**
   * Tests that a failing switchTo() does not arm a switchBack().
   */
  public function testFailingSwitchDoesNotArmSwitchBack(): void {
    $this->setModelUser('1');

    $account_switcher = $this->createMock(AccountSwitcherInterface::class);
    $account_switcher->expects($this->once())
      ->method('switchTo')
      ->willThrowException(new \RuntimeException('Switching failed.'));
    $account_switcher->expects($this->never())
      ->method('switchBack');
    EcaExecutionSwitchAccountSubscriber::get()->setAccountSwitcher($account_switcher);

    try {
      $this->triggerModel();
      $this->fail('Expected the failing account switch to bubble up.');
    }
    catch (\RuntimeException $e) {
      $this->assertSame('Switching failed.', $e->getMessage());
    }
  }

  /**
   * Tests unwinding when the execution of a successor throws.
   *
   * Processor::execute() re-throws the exception from a catch block and then
   * dispatches AFTER_INITIAL_EXECUTION from the matching finally block, so
   * the switch has to be unwound even though the execution failed.
   */
  public function testSwitchIsUnwoundWhenSuccessorThrows(): void {
    $this->setModelUser('1');
    \Drupal::service('event_dispatcher')->addListener(
      EcaEvents::BEFORE_ACTION_EXECUTION,
      static function (): void {
        throw new \RuntimeException('Action failed.');
      }
    );

    try {
      $this->triggerModel();
      $this->fail('Expected the failing action to bubble up.');
    }
    catch (\RuntimeException $e) {
      $this->assertSame('Action failed.', $e->getMessage());
    }

    $this->assertSame(1, $this->observedAfterEvents, 'AFTER_INITIAL_EXECUTION must still be dispatched.');
    $this->assertTrue(\Drupal::currentUser()->isAnonymous(), 'The account switch must be unwound when a successor throws.');
    $this->assertFalse(Processor::get()->isEcaContext(), 'The execution history must not be left behind.');
  }

  /**
   * Tests unwinding when the execution of a successor throws an \Error.
   *
   * The catch block in Processor::execute() only catches \Exception, so for an
   * \Error the unwinding guarantee comes from the finally block alone. This
   * exercises that path explicitly instead of assuming it.
   */
  public function testSwitchIsUnwoundWhenSuccessorThrowsAnError(): void {
    $this->setModelUser('1');
    \Drupal::service('event_dispatcher')->addListener(
      EcaEvents::BEFORE_ACTION_EXECUTION,
      static function (): void {
        throw new \TypeError('Action failed with an Error.');
      }
    );

    try {
      $this->triggerModel();
      $this->fail('Expected the failing action to bubble up.');
    }
    catch (\TypeError $e) {
      $this->assertSame('Action failed with an Error.', $e->getMessage());
    }

    $this->assertSame(1, $this->observedAfterEvents, 'AFTER_INITIAL_EXECUTION must still be dispatched for an \Error.');
    $this->assertTrue(\Drupal::currentUser()->isAnonymous(), 'The account switch must be unwound even for an \Error.');
    $this->assertFalse(Processor::get()->isEcaContext(), 'The execution history must not be left behind.');
  }

  /**
   * Tests that a non-existing model user falls back to the current user.
   */
  public function testMissingModelUserFallsBackToCurrentUser(): void {
    $this->setModelUser('99');
    $this->triggerModel();
    $this->assertSame(['0'], $this->observedUids);

    // Creating the missing account makes the next execution use it, without
    // any container rebuild.
    User::create(['uid' => 99, 'name' => 'late'])->save();
    $this->triggerModel();
    $this->assertSame(['0', '99'], $this->observedUids);
  }

  /**
   * Sets the account that ECA should execute its models under.
   *
   * @param string $uid
   *   The user ID, the UUID, or an empty string to disable the switching.
   */
  protected function setModelUser(string $uid): void {
    \Drupal::configFactory()
      ->getEditable('eca.settings')
      ->set('user', $uid)
      ->save();
  }

  /**
   * Records the account state of every model execution.
   *
   * The listener is registered at priority 450, which is right below the
   * priority 500 of the account switching subscriber, so it observes the state
   * that the model itself will run under.
   */
  protected function observeExecutions(): void {
    \Drupal::service('event_dispatcher')->addListener(
      EcaEvents::BEFORE_INITIAL_EXECUTION,
      function (BeforeInitialExecutionEvent $event): void {
        $this->observedUids[] = (string) \Drupal::currentUser()->id();
        $session_user = \Drupal::service('eca.service.token')->getTokenData('session_user');
        $this->observedSessionUids[] = $session_user instanceof AccountInterface ? (string) $session_user->id() : '';
      },
      450
    );
    \Drupal::service('event_dispatcher')->addListener(
      EcaEvents::AFTER_INITIAL_EXECUTION,
      function (): void {
        $this->observedAfterEvents++;
      },
      -400
    );
  }

  /**
   * Creates the ECA model that all tests in this class execute.
   *
   * The model reacts on a write into the static test array and writes a
   * different value into it, which does not match the event configuration
   * again, so the nested write does not trigger another execution.
   */
  protected function createModel(): void {
    Eca::create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'switch_account_process',
      'label' => 'ECA switch account process',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'array_write' => [
          'plugin' => 'eca_test_array:write',
          'label' => 'Write event',
          'configuration' => [
            'key' => 'mykey',
            'value' => 'myvalue',
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
            'key' => 'mykey',
            'value' => 'done',
          ],
          'successors' => [],
        ],
      ],
    ])->save();
  }

  /**
   * Creates a second model that reacts on the write of ::createModel().
   *
   * Executing the model of ::createModel() therefore leads to a nested
   * execution of this one, while the account switch of the outer execution is
   * still active.
   */
  protected function createNestedModel(): void {
    Eca::create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'switch_account_nested_process',
      'label' => 'ECA switch account nested process',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'array_write' => [
          'plugin' => 'eca_test_array:write',
          'label' => 'Write event',
          'configuration' => [
            'key' => 'mykey',
            'value' => 'done',
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
            'key' => 'mykey',
            'value' => 'nested',
          ],
          'successors' => [],
        ],
      ],
    ])->save();
  }

  /**
   * Triggers one execution of the model created by ::createModel().
   */
  protected function triggerModel(): void {
    \Drupal::service('plugin.manager.action')
      ->createInstance('eca_test_array_write', [
        'key' => 'mykey',
        'value' => 'myvalue',
      ])
      ->execute();
  }

}
