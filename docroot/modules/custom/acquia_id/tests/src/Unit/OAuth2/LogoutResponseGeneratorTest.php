<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_id\Unit\OAuth2;

use Drupal\acquia_id\OAuth2\AccessTokenRepository;
use Drupal\acquia_id\OAuth2\LogoutResponseGenerator;
use Drupal\acquia_id\OAuth2\ProviderFactory;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(LogoutResponseGenerator::class)]
#[Group('acquia_id')]
class LogoutResponseGeneratorTest extends UnitTestCase {

  private const IDP_BASE_URI = 'https://id.acquia.com/oauth2/default';
  private const LOGOUT_REDIRECT_URI = 'https://cloud.acquia.com';
  private const USER_ID = 42;
  private const STORAGE_KEY = 'acquia_id_access_token';

  public function testRedirectsThroughIdpLogoutWhenTokenHasIdToken(): void {
    $token = new AccessToken([
      'access_token' => 'tok-123',
      'expires' => time() + 3600,
      'id_token' => 'eyJhbGciOiJSUzI1NiJ9.test',
    ]);

    $repository = $this->buildRepository($token);
    $generator = new LogoutResponseGenerator(
      self::IDP_BASE_URI,
      self::LOGOUT_REDIRECT_URI,
      $repository,
      $this->buildAccount(),
    );

    $response = $generator->get();

    $this->assertStringContainsString(
      self::IDP_BASE_URI . '/v1/logout',
      $response->getTargetUrl(),
    );
    $this->assertStringContainsString(
      'id_token_hint=eyJhbGciOiJSUzI1NiJ9.test',
      $response->getTargetUrl(),
    );
    $this->assertStringContainsString(
      'post_logout_redirect_uri=' . self::LOGOUT_REDIRECT_URI,
      $response->getTargetUrl(),
    );
    $this->assertSame(0, $response->getCacheableMetadata()->getCacheMaxAge());
  }

  public function testRedirectsToLogoutUriWhenNoTokenStored(): void {
    $repository = $this->buildRepository(NULL);
    $generator = new LogoutResponseGenerator(
      self::IDP_BASE_URI,
      self::LOGOUT_REDIRECT_URI,
      $repository,
      $this->buildAccount(),
    );

    $response = $generator->get();

    $this->assertSame(self::LOGOUT_REDIRECT_URI, $response->getTargetUrl());
    $this->assertSame(0, $response->getCacheableMetadata()->getCacheMaxAge());
  }

  public function testRedirectsToLogoutUriWhenRefreshTokenTtlExpired(): void {
    // Token stored 91 minutes ago — beyond the 90-minute refresh TTL.
    $repository = $this->buildRepository(
      new AccessToken(['access_token' => 'tok-old', 'expires_in' => 3600]),
      storedMinutesAgo: 91,
    );
    $generator = new LogoutResponseGenerator(
      self::IDP_BASE_URI,
      self::LOGOUT_REDIRECT_URI,
      $repository,
      $this->buildAccount(),
    );

    $response = $generator->get();

    $this->assertSame(self::LOGOUT_REDIRECT_URI, $response->getTargetUrl());
    $this->assertSame(0, $response->getCacheableMetadata()->getCacheMaxAge());
  }

  /**
   * Builds a real AccessTokenRepository backed by mocked UserData.
   *
   * AccessTokenRepository is final, so it cannot be mocked directly. Instead
   * we construct a real instance with controlled dependencies.
   */
  private function buildRepository(?AccessToken $token, int $storedMinutesAgo = 0): AccessTokenRepository {
    $now = time();

    $userData = $this->createMock(UserDataInterface::class);
    if ($token === NULL) {
      $userData->method('get')->willReturn(NULL);
    }
    else {
      $userData->method('get')
        ->with('acquia_id', self::USER_ID, self::STORAGE_KEY)
        ->willReturn([
          'access_token' => $token,
          'timestamp' => $now - ($storedMinutesAgo * 60),
        ]);
    }

    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturn($now);

    $providerFactory = $this->createMock(ProviderFactory::class);

    return new AccessTokenRepository($providerFactory, $userData, $time);
  }

  private function buildAccount(): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(self::USER_ID);
    return $account;
  }

}
