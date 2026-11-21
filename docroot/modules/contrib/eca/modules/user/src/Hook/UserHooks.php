<?php

namespace Drupal\eca_user\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Event\TriggerEvent;
use Drupal\user\UserInterface;

/**
 * Implements user hooks for the ECA User submodule.
 */
class UserHooks {

  /**
   * Constructs a new UserHooks object.
   */
  public function __construct(
    protected TriggerEvent $triggerEvent,
  ) {}

  /**
   * Implements hook_user_login().
   */
  #[Hook('user_login')]
  public function userLogin(UserInterface $account): void {
    $this->triggerEvent->dispatchFromPlugin('user:login', $account);
  }

  /**
   * Implements hook_user_logout().
   */
  #[Hook('user_logout')]
  public function userLogout(AccountInterface $account): void {
    $this->triggerEvent->dispatchFromPlugin('user:logout', $account);
  }

  /**
   * Implements hook_user_cancel().
   */
  #[Hook('user_cancel')]
  public function userCancel(array $edit, UserInterface $account, string $method): void {
    $this->triggerEvent->dispatchFromPlugin('user:cancel', $account);
  }

}
