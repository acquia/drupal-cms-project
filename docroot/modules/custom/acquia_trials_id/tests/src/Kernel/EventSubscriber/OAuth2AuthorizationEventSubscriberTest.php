<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_trials_id\Kernel\EventSubscriber;

use Drupal\Core\Access\AccessException;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder as SymfonyContainerBuilder;
use Drupal\Tests\acquia_id\Kernel\HttpClientMiddleware\MockedCloudApiMiddleware;
use Drupal\Tests\acquia_id\Kernel\HttpClientMiddleware\MockedIdpMiddleware;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\acquia_id\Events\OAuth2AuthorizationEvent;
use Drupal\acquia_id\OAuth2\Provider\AcquiaIdProvider;
use Drupal\acquia_id\OAuth2\Provider\IdpProvider;
use Drupal\acquia_trials_id\EventSubscriber\OAuth2AuthorizationEventSubscriber;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversClass(OAuth2AuthorizationEventSubscriber::class)]
#[Group('acquia_trials_id')]
#[RunTestsInSeparateProcesses]
class OAuth2AuthorizationEventSubscriberTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'acquia_id',
    'acquia_trials_id',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installConfig('system');
    // Create uid 1.
    $this->createUser();
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->register(MockedIdpMiddleware::class)
      ->addTag('http_client_middleware');
    $container->register(MockedCloudApiMiddleware::class)
      ->addTag('http_client_middleware');

    // Override after AcquiaIdServiceProvider::alter() sets staging URLs.
    $container->addCompilerPass(new class implements CompilerPassInterface {
      public function process(SymfonyContainerBuilder $container): void {
        $container->setParameter('acquia_id.idp_base_uri', 'https://id.acquia.com/oauth2/default');
        $container->setParameter('acquia_id.cloud_api_base_uri', 'https://cloud.acquia.com');
        $container->setParameter('acquia_id.idp_logout_redirect_uri', 'https://cloud.acquia.com');
      }
    }, priority: -200);
  }

  public function testThrowsWhenApplicationUuidNotAvailable(): void {
    // Ensure AH_APPLICATION_UUID is not set.
    $original = getenv('AH_APPLICATION_UUID');
    putenv('AH_APPLICATION_UUID');

    try {
      $event = $this->createEvent('VALID_ACCESS_TOKEN');
      $subscriber = $this->container->get(OAuth2AuthorizationEventSubscriber::class);
      $this->expectException(AccessException::class);
      $this->expectExceptionMessage('Acquia application UUID is not available');
      $subscriber->onAuthorization($event);
    }
    finally {
      if ($original !== FALSE) {
        putenv("AH_APPLICATION_UUID=$original");
      }
    }
  }

  public function testThrowsWhenUserLacksApplicationAccess(): void {
    putenv('AH_APPLICATION_UUID=test-app-uuid');

    try {
      $event = $this->createEvent('NO_APP_ACCESS_TOKEN');
      $subscriber = $this->container->get(OAuth2AuthorizationEventSubscriber::class);
      $this->expectException(AccessException::class);
      $this->expectExceptionMessage('User does not have access to this application');
      $subscriber->onAuthorization($event);
    }
    finally {
      putenv('AH_APPLICATION_UUID');
    }
  }

  public function testCreatesNewUserOnFirstSso(): void {
    putenv('AH_APPLICATION_UUID=test-app-uuid');

    try {
      $event = $this->createEvent('VALID_ACCESS_TOKEN');
      $subscriber = $this->container->get(OAuth2AuthorizationEventSubscriber::class);
      $subscriber->onAuthorization($event);

      $user = $event->getUser();
      $this->assertNotNull($user);
      $this->assertSame('test@example.com', $user->getEmail());
      $this->assertSame('test@example.com', $user->getAccountName());
      $this->assertTrue($user->isActive());
      $this->assertTrue($user->hasRole('administrator'));
      $this->assertSame('user-uuid-12345', $user->uuid());
    }
    finally {
      putenv('AH_APPLICATION_UUID');
    }
  }

  public function testLoadsExistingUserByEmail(): void {
    putenv('AH_APPLICATION_UUID=test-app-uuid');

    try {
      // Create an existing user with the same email.
      $existing = $this->createUser([], 'test@example.com', FALSE, [
        'mail' => 'test@example.com',
      ]);
      $existing_uid = $existing->id();

      $event = $this->createEvent('VALID_ACCESS_TOKEN');
      $subscriber = $this->container->get(OAuth2AuthorizationEventSubscriber::class);
      $subscriber->onAuthorization($event);

      $user = $event->getUser();
      $this->assertNotNull($user);
      // Should be the same user, not a new one.
      $this->assertSame($existing_uid, $user->id());
      $this->assertSame('test@example.com', $user->getEmail());
      $this->assertTrue($user->hasRole('administrator'));
      $this->assertSame('user-uuid-12345', $user->uuid());
    }
    finally {
      putenv('AH_APPLICATION_UUID');
    }
  }

  /**
   * Creates an OAuth2AuthorizationEvent with a mock provider.
   */
  private function createEvent(string $accessTokenValue): OAuth2AuthorizationEvent {
    $token = new AccessToken([
      'access_token' => $accessTokenValue,
      'expires' => time() + 3600,
    ]);

    $provider = $this->container->get('acquia_id.oauth2.provider');
    return new OAuth2AuthorizationEvent($provider, $token);
  }

}
