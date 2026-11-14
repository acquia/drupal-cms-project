<?php

namespace Drupal\eca_node_access;

/**
 * Defines events provided by the ECA Node Access module.
 */
final class NodeAccessEvents {

  /**
   * Dispatches when being asked to define node grants for an account.
   *
   * @Event
   *
   * @var string
   */
  public const GRANTS = 'eca_node_access.node_access_grants';

  /**
   * Dispatches when being asked to define access records for a node.
   *
   * @Event
   *
   * @var string
   */
  public const NODE_ACCESS_RECORDS = 'eca_node_access.node_access_records';

}
