<?php

namespace Drupal\eca_node_access\Plugin\ECA\Event;

use Drupal\eca\Plugin\ECA\Event\EventDeriverBase;

/**
 * Deriver for ECA Node Access event plugins.
 */
class NodeAccessEventDeriver extends EventDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function definitions(): array {
    return NodeAccessEvent::definitions();
  }

}
