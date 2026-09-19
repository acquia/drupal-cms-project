<?php

namespace Drupal\eca_endpoint\Event;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Event\AccessEventInterface;

/**
 * Dispatched when an ECA Endpoint is being checked for access.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_endpoint\Event
 */
class EndpointAccessEvent extends EndpointEventBase implements AccessEventInterface {

  /**
   * The arguments provided in the URL path.
   *
   * @var array
   */
  public array $pathArguments;

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
   * The predefined access result, used as long as no ECA model reacted.
   *
   * This is deliberately kept separate from $accessResult: the predefined
   * result is provided by the caller and is not necessarily a neutral result,
   * so it must not take part in the accumulation performed by
   * ::setAccessResult(). Otherwise a predefined forbidden result would - due
   * to the contagious nature of a forbidden access result - revoke access even
   * when an ECA model explicitly granted it.
   *
   * @var \Drupal\Core\Access\AccessResultInterface|null
   */
  protected ?AccessResultInterface $defaultAccessResult = NULL;

  /**
   * Constructs a new EcaRenderEndpointResponseEvent object.
   *
   * @param array &$path_arguments
   *   The arguments provided in the URL path.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param \Drupal\Core\Access\AccessResultInterface|null $access_result
   *   (optional) The predefined access result.
   */
  public function __construct(array &$path_arguments, AccountInterface $account, ?AccessResultInterface $access_result = NULL) {
    $this->pathArguments = &$path_arguments;
    $this->account = $account;
    $this->defaultAccessResult = $access_result;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessResult(): ?AccessResultInterface {
    return $this->accessResult ?? $this->defaultAccessResult;
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
  public function setAccessResult(AccessResultInterface $result): EndpointAccessEvent {
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
