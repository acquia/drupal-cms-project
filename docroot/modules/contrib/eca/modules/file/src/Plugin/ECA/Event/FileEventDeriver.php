<?php

namespace Drupal\eca_file\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for the ECA file event plugins.
 */
class FileEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return FileEvent::definitions();
  }

}
