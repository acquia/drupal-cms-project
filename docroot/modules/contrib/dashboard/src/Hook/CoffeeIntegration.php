<?php

declare(strict_types=1);

namespace Drupal\dashboard\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Coffee integration hooks.
 */
class CoffeeIntegration {

  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * Provide commands for the coffee module.
   */
  #[Hook('coffee_commands')]
  public function commands(): array {
    $commands = [];
    $dashboards = $this->entityTypeManager->getStorage('dashboard')->loadMultiple();
    foreach ($dashboards as $dashboard) {
      if ($dashboard->access('view')) {
        $commands[] = [
          'value' => $dashboard->toUrl('canonical')->toString(),
          'label' => $dashboard->label(),
          'command' => sprintf(':dashboard %s', $dashboard->id()),
        ];
      }
    }
    return $commands;
  }

}
