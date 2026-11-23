<?php

namespace Drupal\eca_views\Event;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Event\AccessEventInterface;
use Drupal\views\ViewExecutable;

/**
 * Dispatched when view with ECA access is being checked for access.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_views\Event
 */
class Access extends ViewsBase implements AccessEventInterface {

  /**
   * The user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  public AccountInterface $account;

  /**
   * The access result accumulated from all reacting ECA models.
   *
   * @var \Drupal\Core\Access\AccessResultInterface|null
   */
  protected ?AccessResultInterface $accessResult = NULL;

  /**
   * Constructs a new access event object.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   */
  public function __construct(ViewExecutable $view, AccountInterface $account) {
    parent::__construct($view);
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessResult(): ?AccessResultInterface {
    return $this->accessResult;
  }

  /**
   * {@inheritdoc}
   *
   * The given result is being accumulated with the result of any ECA model
   * that reacted upon this event before, using
   * \Drupal\Core\Access\AccessResultInterface::orIf(). Consequently, access is
   * being revoked as soon as at least one ECA model revokes it, regardless of
   * the order in which the models are being executed.
   */
  public function setAccessResult(AccessResultInterface $result): Access {
    $this->accessResult = $this->accessResult === NULL ? $result : $this->accessResult->orIf($result);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccount(): AccountInterface {
    return $this->account;
  }

}
