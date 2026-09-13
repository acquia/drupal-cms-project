<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_id\Unit\Events;

use Drupal\acquia_id\Events\OAuth2AuthorizationEvent;
use Drupal\acquia_id\OAuth2\Provider\IdpProvider;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(OAuth2AuthorizationEvent::class)]
#[Group('acquia_id')]
class OAuth2AuthorizationEventTest extends UnitTestCase {

  public function testUserIsNullByDefault(): void {
    $event = new OAuth2AuthorizationEvent(
      $this->createMock(IdpProvider::class),
      new AccessToken(['access_token' => 'test', 'expires_in' => 3600]),
    );
    $this->assertNull($event->getUser());
  }

  public function testSetAndGetUser(): void {
    $event = new OAuth2AuthorizationEvent(
      $this->createMock(IdpProvider::class),
      new AccessToken(['access_token' => 'test', 'expires_in' => 3600]),
    );
    $user = $this->createMock(UserInterface::class);
    $event->setUser($user);
    $this->assertSame($user, $event->getUser());
  }

  public function testProviderAndTokenAreAccessible(): void {
    $provider = $this->createMock(IdpProvider::class);
    $token = new AccessToken(['access_token' => 'tok-123', 'expires_in' => 3600]);
    $event = new OAuth2AuthorizationEvent($provider, $token);

    $this->assertSame($provider, $event->provider);
    $this->assertSame($token, $event->accessToken);
  }

}
