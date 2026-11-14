<?php

namespace Drupal\eca_node_access\Event;

use Drupal\eca\Event\ContentEntityEventInterface;
use Drupal\node\NodeInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when a node is saved or its node access records are rebuilt.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
class NodeAccessRecords extends Event implements ContentEntityEventInterface {

  /**
   * The node entity getting its node access set.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $node;

  /**
   * The accumulated node access grant records.
   *
   * Each entry is a complete record matching the return shape of
   * hook_node_access_records(), keyed by realm, gid and the grant_view,
   * grant_update and grant_delete flags.
   *
   * @var array
   */
  protected array $records = [];

  /**
   * Constructs a new NodeAccessRecords object.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity getting its node access set.
   */
  public function __construct(NodeInterface $node) {
    $this->node = $node;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): NodeInterface {
    return $this->node;
  }

  /**
   * Adds a single node access grant record.
   *
   * @param string $realm
   *   The grant realm.
   * @param int $gid
   *   The grant ID.
   * @param bool $grant_view
   *   TRUE to grant view access, FALSE otherwise.
   * @param bool $grant_update
   *   TRUE to grant update access, FALSE otherwise.
   * @param bool $grant_delete
   *   TRUE to grant delete access, FALSE otherwise.
   *
   * @return \Drupal\eca_node_access\Event\NodeAccessRecords
   *   This event, for chaining.
   */
  public function addRecord(string $realm, int $gid, bool $grant_view, bool $grant_update, bool $grant_delete): NodeAccessRecords {
    $this->records[] = [
      'realm' => $realm,
      'gid' => $gid,
      'grant_view' => (int) $grant_view,
      'grant_update' => (int) $grant_update,
      'grant_delete' => (int) $grant_delete,
    ];
    return $this;
  }

  /**
   * Returns the accumulated node access records.
   *
   * @return array
   *   The list of node access record arrays, each keyed by realm, gid and the
   *   grant_view, grant_update and grant_delete flags.
   */
  public function getNodeAccessRecords(): array {
    return $this->records;
  }

}
