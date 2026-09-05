<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_id\Unit\OAuth2\Provider;

use Drupal\acquia_id\OAuth2\Provider\AcquiaIdProvider;
use Drupal\Tests\UnitTestCase;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(AcquiaIdProvider::class)]
#[Group('acquia_id')]
class AcquiaIdProviderTest extends UnitTestCase {

  private AcquiaIdProvider $provider;

  protected function setUp(): void {
    parent::setUp();
    $this->provider = new AcquiaIdProvider([
      'clientId' => 'test-client',
      'redirectUri' => 'https://example.com/callback',
    ]);
    $this->provider
      ->setIdpBaseUri('https://id.acquia.com/oauth2/default')
      ->setCloudApiBaseUri('https://cloud.acquia.com');
  }

  public function testBaseAuthorizationUrl(): void {
    $this->assertSame(
      'https://id.acquia.com/oauth2/default/v1/authorize',
      $this->provider->getBaseAuthorizationUrl(),
    );
  }

  public function testBaseAccessTokenUrl(): void {
    $this->assertSame(
      'https://id.acquia.com/oauth2/default/v1/token',
      $this->provider->getBaseAccessTokenUrl([]),
    );
  }

  public function testResourceOwnerDetailsUrl(): void {
    $token = new AccessToken(['access_token' => 'test', 'expires_in' => 3600]);
    $this->assertSame(
      'https://cloud.acquia.com/api/account',
      $this->provider->getResourceOwnerDetailsUrl($token),
    );
  }

  public function testAuthorizationUrlContainsExpectedScopes(): void {
    $url = $this->provider->getAuthorizationUrl();
    $query = parse_url($url, PHP_URL_QUERY);
    parse_str($query, $params);

    $this->assertStringContainsString('openid', $params['scope']);
    $this->assertStringContainsString('email', $params['scope']);
    $this->assertStringContainsString('profile', $params['scope']);
    $this->assertStringContainsString('offline_access', $params['scope']);
  }

  public function testAuthorizationUrlIncludesPkceChallenge(): void {
    $url = $this->provider->getAuthorizationUrl();
    $query = parse_url($url, PHP_URL_QUERY);
    parse_str($query, $params);

    $this->assertArrayHasKey('code_challenge', $params);
    $this->assertSame('S256', $params['code_challenge_method']);
  }

}
