<?php

declare(strict_types=1);

namespace Drupal\acquia_id\Events;

use Drupal\acquia_id\OAuth2\Provider\IdpProvider;
use Drupal\user\UserInterface;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Contracts\EventDispatcher\Event;

final class OAuth2AuthorizationEvent extends Event {

  private UserInterface|null $user = NULL;

  public function __construct(
    private readonly IdpProvider $provider,
    private readonly AccessToken $accessToken,
  ) {
  }

  public function getProvider(): IdpProvider {
    return $this->provider;
  }

  public function getAccessToken(): AccessToken {
    return $this->accessToken;
  }

  public function setUser(UserInterface $user): void {
    $this->user = $user;
  }

  public function getUser(): ?UserInterface {
    return $this->user;
  }

}
