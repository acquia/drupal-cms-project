<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Traits;

use Drupal\Core\Session\AccountInterface;

/**
 * Syncs the child site CSRF token seed into the test runner.
 *
 * Tokens generated via the test container only validate in Mink requests if
 * the runner uses the same session seed the child site created at login.
 *
 * @see \Drupal\Tests\ckeditor5\Traits\SynchronizeCsrfTokenSeedTrait
 * @see \Drupal\Core\Access\CsrfTokenGenerator::get()
 */
trait SynchronizeCsrfTokenSeedTrait {

  /**
   * {@inheritdoc}
   */
  protected function drupalLogin(AccountInterface $account): void {
    parent::drupalLogin($account);
    $session_data = $this->container->get('session_handler.write_safe')
      ->read($this->getSession()->getCookie($this->getSessionName()));
    $csrf_token_seed = unserialize(explode('_sf2_meta|', $session_data)[1])['s'];
    $this->container->get('session_manager.metadata_bag')
      ->setCsrfTokenSeed($csrf_token_seed);
  }

  /**
   * {@inheritdoc}
   */
  protected function rebuildContainer(): void {
    parent::rebuildContainer();

    // Ensure that the CSRF token seed is reset on container rebuild.
    if ($this->loggedInUser) {
      $current_user = $this->loggedInUser;
      $this->drupalLogout();
      $this->drupalLogin($current_user);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function drupalLogout(): void {
    parent::drupalLogout();
    $this->container->get('session_manager.metadata_bag')->stampNew();
  }

}
