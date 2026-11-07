<?php

namespace Drupal\drupal_cms_helper\EventSubscriber;

use Drupal\Core\Site\Settings;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Enables Package Manager's direct-write mode in DDEV environments.
 *
 * This could also be done by setting package_manager_allow_direct_write to TRUE
 * in settings.php, but we also set it here for convenience.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class SettingsSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::REQUEST => 'onBoot'];
  }

  /**
   * Reacts when a request is processed.
   */
  public function onBoot(): void {
    // Enable Package Manager's direct-write mode by default in DDEV. This makes
    // Composer operations in the UI smoother and faster by skipping sandboxing,
    // which isn't really needed in a local development environment.
    $settings = Settings::getAll();
    $settings['package_manager_allow_direct_write'] ??= (bool) getenv('IS_DDEV_PROJECT');
    new Settings($settings);
  }

}
