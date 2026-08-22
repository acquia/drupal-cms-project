<?php

namespace Drupal\eca_node_access\Event;

use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Event\AccountEventInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when an account asks what grants it has.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
class NodeGrants extends Event implements AccountEventInterface {

  /**
   * The user account receiving the grants.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * The node operation being requested ('view', 'update' or 'delete').
   *
   * @var string
   */
  protected string $operation;

  /**
   * Realm (if given).
   *
   * @var string|null
   */
  protected ?string $realm = NULL;

  /**
   * The grants, keyed by realm with arrays of integer grant IDs as values.
   *
   * The structure matches hook_node_grants(): [realm => [id, id, ...]].
   *
   * @var array|null
   */
  protected ?array $grants = NULL;

  /**
   * Constructs a new NodeGrants object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account receiving the grants.
   * @param string $operation
   *   The node operation being requested, such as 'view', 'update' or
   *   'delete'.
   */
  public function __construct(AccountInterface $account, string $operation) {
    $this->account = $account;
    $this->operation = $operation;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccount(): AccountInterface {
    return $this->account;
  }

  /**
   * Returns the requested node operation.
   *
   * @return string
   *   The requested node operation: 'view', 'update' or 'delete'.
   */
  public function getOperation(): string {
    return $this->operation;
  }

  /**
   * Returns the grants keyed by realm with arrays of integer grant IDs.
   *
   * @return array|null
   *   The grants in the hook_node_grants() structure, keyed by realm with
   *   arrays of integer grant IDs as values ([realm => [id, id, ...]]), or
   *   NULL if no grants were added.
   */
  public function getGrants(): ?array {
    return $this->grants;
  }

  /**
   * Adds a single grant ID to a realm for the account.
   *
   * @param string $realm
   *   The grant realm.
   * @param int $id
   *   The grant ID within the realm.
   *
   * @return \Drupal\eca_node_access\Event\NodeGrants
   *   This event, for chaining.
   */
  public function addGrant(string $realm, int $id): NodeGrants {
    $this->grants[$realm][] = $id;
    return $this;
  }

}
