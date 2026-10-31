<?php

declare(strict_types=1);

namespace Drupal\project_browser_test;

use Drupal\Core\Site\Settings;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Alters site settings to enable Project Browser testing.
 */
final class SettingsSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => 'prepareForTesting',
    ];
  }

  /**
   * Adjusts settings to facilitate testing.
   */
  public function prepareForTesting(): void {
    $settings = Settings::getAll();
    // Make Package Manager installable in the UI.
    $settings['testing_package_manager'] = TRUE;
    // Allow certain source plugins to scan for test recipes and modules.
    $settings['extension_discovery_scan_tests'] = TRUE;
    new Settings($settings);
  }

}
