<?php

namespace Drupal\eca_node_access\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Event\TriggerEvent;
use Drupal\eca_node_access\Event\NodeAccessRecords;
use Drupal\eca_node_access\Event\NodeGrants;
use Drupal\node\NodeInterface;

/**
 * Implements node access hooks for the ECA Node Access submodule.
 */
class NodeAccessHooks {

  /**
   * Constructs a new NodeAccessHooks object.
   *
   * @param \Drupal\eca\Event\TriggerEvent $triggerEvent
   *   The trigger event service.
   */
  public function __construct(
    protected TriggerEvent $triggerEvent,
  ) {}

  /**
   * Implements hook_node_grants().
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account object whose grants are requested.
   * @param string $operation
   *   The node operation to be performed, such as 'view', 'update', or
   *   'delete'.
   *
   * @return array
   *   An array whose keys are "realms" of grants, and whose values are arrays
   *   of the grant IDs within this realm that this user is being granted.
   */
  #[Hook('node_grants')]
  public function nodeGrants(AccountInterface $account, string $operation): array {
    // The first part of the first argument handed to dispatchFromPlugin must
    // match a plugin ID in Plugin/ECA/Event, and the second part a definition
    // key in the array returned by the definitions() method.
    $event = $this->triggerEvent->dispatchFromPlugin('node_access:node_access_grants', $account, $operation);
    if ($event instanceof NodeGrants) {
      return $event->getGrants() ?? [];
    }
    return [];
  }

  /**
   * Implements hook_node_access_records().
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node that has just been saved.
   *
   * @return array
   *   An array of grants.
   */
  #[Hook('node_access_records')]
  public function nodeAccessRecords(NodeInterface $node): array {
    // The first part of the first argument handed to dispatchFromPlugin must
    // match a plugin ID in Plugin/ECA/Event, and the second part a definition
    // key in the array returned by the definitions() method.
    $event = $this->triggerEvent->dispatchFromPlugin('node_access:node_access_records', $node);
    if ($event instanceof NodeAccessRecords) {
      return $event->getNodeAccessRecords();
    }
    return [];
  }

}
