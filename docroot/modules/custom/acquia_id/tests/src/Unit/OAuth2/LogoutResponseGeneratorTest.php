<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_id\Unit\OAuth2;

use Drupal\acquia_id\OAuth2\AccessTokenRepository;
use Drupal\acquia_id\OAuth2\LogoutResponseGenerator;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(LogoutResponseGenerator::class)]
#[Group('acquia_id')]
class LogoutResponseGeneratorTest extends UnitTestCase {

  private const IDP_BASE_URI = 'https://id.acquia.com/oauth2/default';
  private const LOGOUT_REDIRECT_URI = 'https://cloud.acquia.com';

  public function testRedirectsThroughIdpLogoutWhenTokenHasIdToken(): void {
    $token = new AccessToken([
      'access_token' => 'tok-123',
      'expires' => time() + 3600,
      'id_token' => 'eyJhbGciOiJSUzI1NiJ9.test',
    ]);

    $repository = $this->createMock(AccessTokenRepository::class);
    $repository->method('get')->with(42)->willReturn($token);

    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(42);

    $generator = new LogoutResponseGenerator(
      self::IDP_BASE_URI,
      self::LOGOUT_REDIRECT_URI,
      $repository,
      $account,
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
    $repository = $this->createMock(AccessTokenRepository::class);
    $repository->method('get')->with(42)->willReturn(NULL);

    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(42);

    $generator = new LogoutResponseGenerator(
      self::IDP_BASE_URI,
      self::LOGOUT_REDIRECT_URI,
      $repository,
      $account,
    );

    $response = $generator->get();

    $this->assertSame(self::LOGOUT_REDIRECT_URI, $response->getTargetUrl());
    $this->assertSame(0, $response->getCacheableMetadata()->getCacheMaxAge());
  }

  public function testRedirectsToLogoutUriOnIdentityProviderException(): void {
    $repository = $this->createMock(AccessTokenRepository::class);
    $repository->method('get')
      ->willThrowException(new IdentityProviderException('Refresh failed', 0, ''));

    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(42);

    $generator = new LogoutResponseGenerator(
      self::IDP_BASE_URI,
      self::LOGOUT_REDIRECT_URI,
      $repository,
      $account,
    );

    $response = $generator->get();

    $this->assertSame(self::LOGOUT_REDIRECT_URI, $response->getTargetUrl());
    $this->assertSame(0, $response->getCacheableMetadata()->getCacheMaxAge());
  }

}
