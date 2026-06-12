<?php

declare(strict_types=1);

namespace Drupal\acquia_id\OAuth2;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\user\UserDataInterface;
use League\OAuth2\Client\Token\AccessToken;

final class AccessTokenRepository {

  /**
   * The refresh token TTL from Acquia Cloud is 90 minutes.
   */
  private const REFRESH_TOKEN_TTL = 90;

  private const string STORAGE_KEY = 'acquia_id_access_token';

  public function __construct(
    private readonly ProviderFactory $providerFactory,
    private readonly UserDataInterface $userData,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Gets the access token for the given user, refreshing it if expired.
   *
   * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
   */
  public function get(int $id): ?AccessToken {
    $tokenData = $this->userData->get('acquia_id', $id, self::STORAGE_KEY);

    if (empty($tokenData) ||
      ($tokenData['timestamp'] < ($this->time->getCurrentTime() - (self::REFRESH_TOKEN_TTL * 60)))) {
      return NULL;
    }

    $token = $tokenData['access_token'];
    if ($token->hasExpired()) {
      /** @var \League\OAuth2\Client\Token\AccessToken $token */
      $token = $this->providerFactory->get()->getAccessToken('refresh_token', [
        'refresh_token' => $token->getRefreshToken() ?? '',
      ]);
      $this->store($id, $token);
    }

    return $token;
  }

  public function store(int $id, AccessToken $token): void {
    $this->userData->set('acquia_id', $id, self::STORAGE_KEY, [
      'access_token' => $token,
      'timestamp' => $this->time->getCurrentTime(),
    ]);
  }

  public function delete(int $id): void {
    $this->userData->delete('acquia_id', $id, self::STORAGE_KEY);
  }

  /**
   * @return list<int|string>
   */
  public function getUserIdsWithTokens(): array {
    return array_keys($this->userData->get('acquia_id', name: self::STORAGE_KEY));
  }

}
