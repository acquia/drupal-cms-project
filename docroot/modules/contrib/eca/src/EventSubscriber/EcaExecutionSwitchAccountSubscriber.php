<?php

namespace Drupal\eca\EventSubscriber;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\eca\Attribute\Token;
use Drupal\eca\EcaEvents;
use Drupal\eca\Event\AfterInitialExecutionEvent;
use Drupal\eca\Event\BeforeInitialExecutionEvent;

/**
 * Switches to a different user account, if specified.
 */
class EcaExecutionSwitchAccountSubscriber extends EcaExecutionSubscriberBase {

  /**
   * The account switcher service.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected AccountSwitcherInterface $accountSwitcher;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|null
   */
  protected ?ConfigFactoryInterface $configFactory = NULL;

  /**
   * The current user.
   *
   * This is the account proxy, so it always reflects the account that is
   * current right now, not the one that was current when this service was
   * built.
   *
   * @var \Drupal\Core\Session\AccountInterface|null
   */
  protected ?AccountInterface $currentUser = NULL;

  /**
   * The stack of session users, one entry per account switch held right now.
   *
   * This mirrors the stack that \Drupal\Core\Session\AccountSwitcher keeps
   * itself: an entry is pushed when this subscriber switches an account, and
   * popped when it switches back. It exists because a nested execution has to
   * report the account that dispatched the outermost event, not the model user
   * it is already running as, and Drupal offers no way to ask the account
   * switcher what it switched away from.
   *
   * Entries may be NULL when the session user could not be determined, so
   * emptiness of the stack is the only thing that may be tested.
   *
   * @var array<int, \Drupal\Core\Session\AccountInterface|null>
   */
  protected array $sessionUsers = [];

  /**
   * The model user value that was last reported as non-existing.
   *
   * Used to keep the log free of one error message per model execution, while
   * still reporting a value that starts to fail after having worked before.
   *
   * @var string|null
   */
  protected ?string $loggedMissingUser = NULL;

  /**
   * Get the service instance of this class.
   *
   * @return \Drupal\eca\EventSubscriber\EcaExecutionSwitchAccountSubscriber
   *   The service instance.
   */
  public static function get(): EcaExecutionSwitchAccountSubscriber {
    return \Drupal::service('eca.execution.switch_account_subscriber');
  }

  /**
   * Set the account switcher.
   *
   * @param \Drupal\Core\Session\AccountSwitcherInterface $account_switcher
   *   The account switcher.
   */
  public function setAccountSwitcher(AccountSwitcherInterface $account_switcher): void {
    $this->accountSwitcher = $account_switcher;
  }

  /**
   * Set the logger channel.
   *
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function setLoggerChannel(LoggerChannelInterface $logger): void {
    $this->logger = $logger;
  }

  /**
   * Provides the services needed to resolve the model user to switch to.
   *
   * This only stores the two services. The model user itself is resolved again
   * for every single model execution by ::resolveModelUser(), because neither
   * the configured account nor the current user can be assumed to stay the
   * same for the lifetime of this service. This service is instantiated at the
   * first dispatch of an ECA execution event, and a single PHP process
   * commonly executes many models after that: a Drush command, a cron run, a
   * queue worker or a test run. The current user can change in between - core's
   * \Drupal\Core\Cron switches to anonymous, and ECA's own
   * eca_switch_account action switches too - and so can the configuration.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function initializeUser(ConfigFactoryInterface $config_factory, AccountInterface $current_user): void {
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
  }

  /**
   * Resolves the user account that models should be executed under.
   *
   * @return \Drupal\Core\Session\AccountInterface|null
   *   The model user, or NULL when models should be executed under the current
   *   user, which is the default.
   */
  protected function resolveModelUser(): ?AccountInterface {
    if ($this->configFactory === NULL) {
      // ::initializeUser() was never called, so there is nothing to resolve.
      return NULL;
    }

    $configured = (string) $this->configFactory->get('eca.settings')->get('user');
    if ($configured === '') {
      // Nothing to do, which is the default. Bailing out here keeps the cost
      // of resolving on every execution down to reading one configuration
      // object, which the config factory has already cached.
      return NULL;
    }

    $uid = trim((string) $this->tokenService->replaceClear($configured));
    $user = NULL;
    $storage = $this->entityTypeManager->getStorage('user');
    if (ctype_digit($uid)) {
      $user = $storage->load($uid);
    }
    elseif (Uuid::isValid($uid)) {
      $users = $storage->loadByProperties(['uuid' => $uid]);
      $user = reset($users);
    }

    if (!($user instanceof AccountInterface)) {
      if ($this->loggedMissingUser !== $configured) {
        $this->loggedMissingUser = $configured;
        $this->logger->error("A different user is specified in the global settings to be switched to when executing ECA models, but the user does not exist. Falling back to default behavior, which is execution using the current user. You need to make sure that the specified user exists.");
      }
      return NULL;
    }

    $this->loggedMissingUser = NULL;
    return $user;
  }

  /**
   * Resolves the account that dispatched the event being processed.
   *
   * @return \Drupal\Core\Session\AccountInterface|null
   *   The session user, or NULL when it cannot be determined.
   */
  protected function resolveSessionUser(): ?AccountInterface {
    if ($this->sessionUsers !== []) {
      // A switch is already held, so the current user is the model user. The
      // session user of the outermost switch is the one that dispatched the
      // event that led here.
      return end($this->sessionUsers);
    }
    if ($this->currentUser === NULL) {
      return NULL;
    }
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    return $user instanceof AccountInterface ? $user : NULL;
  }

  /**
   * Subscriber method before initial execution.
   *
   * Switches to the configured model user, and provides the account that
   * dispatched the event as the "session_user" Token.
   *
   * @param \Drupal\eca\Event\BeforeInitialExecutionEvent $before_event
   *   The according event.
   */
  #[Token(
    name: 'session_user',
    description: 'The user account that dispatched the event, regardless if ECA is processing models under a different account. This is only available if ECA is configured to always run under a specific account.',
    type: 'user',
  )]
  public function onBeforeInitialExecution(BeforeInitialExecutionEvent $before_event): void {
    $model_user = $this->resolveModelUser();
    if ($model_user === NULL) {
      return;
    }

    // Determine the session user before switching, because switching replaces
    // the current user.
    $session_user = $this->resolveSessionUser();

    $this->accountSwitcher->switchTo($model_user);
    // Arm the switch back right after the switch succeeded, and before
    // anything else that could throw. A failing switch must never be unwound,
    // and a successful one must always be unwound.
    $this->sessionUsers[] = $session_user;
    // ::setPrestate() takes its value by reference, so it needs a variable.
    $switch_account = TRUE;
    $before_event->setPrestate('switch_account', $switch_account);

    if ($session_user !== NULL) {
      $this->tokenService->addTokenData('session_user', $session_user);
    }
  }

  /**
   * Subscriber method after initial execution.
   *
   * Unwinds the account switch that ::onBeforeInitialExecution() armed, if it
   * armed one.
   *
   * @param \Drupal\eca\Event\AfterInitialExecutionEvent $after_event
   *   The according event.
   */
  public function onAfterInitialExecution(AfterInitialExecutionEvent $after_event): void {
    if (!$after_event->getPrestate('switch_account')) {
      return;
    }
    try {
      $this->accountSwitcher->switchBack();
    }
    finally {
      array_pop($this->sessionUsers);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    $events[EcaEvents::BEFORE_INITIAL_EXECUTION][] = [
      'onBeforeInitialExecution',
      500,
    ];
    $events[EcaEvents::AFTER_INITIAL_EXECUTION][] = [
      'onAfterInitialExecution',
      -500,
    ];
    return $events;
  }

}
