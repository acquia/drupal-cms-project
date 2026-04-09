<?php

declare(strict_types=1);

namespace Drupal\acquia_id\OAuth2\Provider;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;

/**
 * OAuth2 provider for Acquia ID (id.acquia.com).
 */
final class AcquiaIdProvider extends IdpProvider {

  private string $idpBaseUri;

  private string $cloudApiBaseUri;

  public function setIdpBaseUri(string $baseUri): self {
    $this->idpBaseUri = $baseUri;
    return $this;
  }

  public function setCloudApiBaseUri(string $baseUri): self {
    $this->cloudApiBaseUri = $baseUri;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseAuthorizationUrl(): string {
    return "$this->idpBaseUri/v1/authorize";
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseAccessTokenUrl(array $params): string {
    return "$this->idpBaseUri/v1/token";
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceOwnerDetailsUrl(AccessToken $token): string {
    return $this->cloudApiBaseUri . '/api/account';
  }

  /**
   * {@inheritdoc}
   */
  protected function createResourceOwner(array $response, AccessToken $token): ResourceOwnerInterface {
    return new AcquiaIdResourceOwner($response);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultScopes(): array {
    return [
      'openid',
      'email',
      'profile',
      'offline_access',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getScopeSeparator(): string {
    return ' ';
  }

}
