<?php

declare(strict_types=1);

namespace Drupal\acquia_id\OAuth2\Provider;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

/**
 * Represents the authenticated user from Acquia ID.
 *
 * @phpstan-type Account array{mail: string, timezone: string, uuid: string, first_name?: string, last_name?: string, company?: string, zoneinfo?: string, sub?: string}
 */
final class AcquiaIdResourceOwner implements ResourceOwnerInterface {

  /**
   * @phpstan-param Account $response
   */
  public function __construct(
    private readonly array $response,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->response['mail'];
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return Account
   */
  public function toArray(): array {
    return $this->response;
  }

}
