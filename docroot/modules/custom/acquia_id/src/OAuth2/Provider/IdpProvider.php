<?php

declare(strict_types=1);

namespace Drupal\acquia_id\OAuth2\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

abstract class IdpProvider extends AbstractProvider {

  use BearerAuthorizationTrait;

  /**
   * {@inheritdoc}
   *
   * @return string[]
   */
  protected function getDefaultScopes(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getPkceMethod(): string {
    return self::PKCE_METHOD_S256;
  }

  /**
   * {@inheritdoc}
   *
   * @phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
   */
  protected function checkResponse(ResponseInterface $response, $data): void {
    if ($response->getStatusCode() >= 400) {
      throw new IdentityProviderException(
        $response->getReasonPhrase(),
        $response->getStatusCode(),
        $response
      );
    }
  }

}
