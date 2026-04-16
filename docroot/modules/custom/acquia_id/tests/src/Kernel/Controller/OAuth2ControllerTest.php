<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_id\Kernel\Controller;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\acquia_id\Kernel\HttpClientMiddleware\MockedCloudApiMiddleware;
use Drupal\Tests\acquia_id\Kernel\HttpClientMiddleware\MockedIdpMiddleware;
use Drupal\Tests\user\Traits\UserCreationTrait;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Drupal\acquia_id\Controller\OAuth2Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[CoversClass(OAuth2Controller::class)]
#[Group('acquia_id')]
class OAuth2ControllerTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'acquia_id',
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
  }

  public function testInitialVisitRedirectsToIdp(): void {
    $response = $this->doRequest(
      Request::create(Url::fromRoute('acquia_id.sso')->toString()),
    );

    $this->assertSame(Response::HTTP_SEE_OTHER, $response->getStatusCode());
    $location = $response->headers->get('Location');
    $this->assertStringContainsString(
      'https://id.acquia.com/oauth2/default/v1/authorize',
      $location,
    );
    $this->assertStringContainsString('code_challenge=', $location);
    $this->assertStringContainsString('code_challenge_method=S256', $location);
    $this->assertStringContainsString('scope=openid+email+profile+offline_access', $location);
  }

  public function testDestinationIsPreservedInSession(): void {
    $response = $this->doRequest(
      Request::create(Url::fromRoute('acquia_id.sso', [], [
        'query' => ['destination' => '/admin'],
      ])->toString()),
    );

    $this->assertSame(Response::HTTP_SEE_OTHER, $response->getStatusCode());
    $this->assertStringContainsString(
      'https://id.acquia.com/oauth2/default/v1/authorize',
      $response->headers->get('Location'),
    );
  }

  public function testMissingStateThrowsAccessDenied(): void {
    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing state');

    $this->doRequest(
      Request::create(Url::fromRoute('acquia_id.sso', [], [
        'query' => ['code' => 'some-code'],
      ])->toString()),
    );
  }

  public function testMismatchedStateThrowsAccessDenied(): void {
    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Invalid state');

    $this->doRequest(
      Request::create(Url::fromRoute('acquia_id.sso', [], [
        'query' => [
          'code' => 'some-code',
          'state' => 'wrong-state',
        ],
      ])->toString()),
    );
  }

  public function testErrorFromIdpRedirectsToLogoutUri(): void {
    // First, initiate the flow to set state in session.
    $this->doRequest(
      Request::create(Url::fromRoute('acquia_id.sso')->toString()),
    );

    $state = $this->container->get('session')->get('oauth2_state');
    $response = $this->doRequest(
      Request::create(Url::fromRoute('acquia_id.sso', [], [
        'query' => [
          'state' => $state,
          'error' => 'invalid_client',
          'error_description' => 'Client authentication failed.',
        ],
      ])->toString()),
    );

    $this->assertTrue($response->isRedirect());
    $this->assertSame(Response::HTTP_SEE_OTHER, $response->getStatusCode());
    $this->assertStringContainsString(
      'https://cloud.acquia.com',
      $response->headers->get('Location'),
    );
  }

  public function testExceptionSubrequestResetsQueryParams(): void {
    $request = Request::create(Url::fromRoute('acquia_id.sso', [], [
      'query' => [
        'code' => '1234',
        'state' => 'ABCD',
      ],
    ])->toString());
    $request->attributes->set('exception', new \Exception());

    $response = $this->doRequest($request);

    $this->assertStringContainsString(
      'https://id.acquia.com/oauth2/default/v1/authorize',
      $response->headers->get('Location'),
    );
  }

  public function testSsoRouteAccessAnonymousAllowed(): void {
    $access_manager = $this->container->get('access_manager');
    $result = $access_manager->checkNamedRoute('acquia_id.sso', []);
    $this->assertTrue($result);
  }

  public function testSsoRouteAccessAuthenticatedWithoutTokenAllowed(): void {
    $access_manager = $this->container->get('access_manager');
    $user = $this->setUpCurrentUser();
    $result = $access_manager->checkNamedRoute('acquia_id.sso', [], $user);
    $this->assertTrue($result);
  }

  public function testSsoRouteAccessAuthenticatedWithTokenDenied(): void {
    $access_manager = $this->container->get('access_manager');
    $user = $this->setUpCurrentUser();

    // Store a valid access token for this user.
    $token = new AccessToken([
      'access_token' => 'VALID_ACCESS_TOKEN',
      'expires' => time() + 3600,
    ]);
    $this->container->get('user.data')
      ->set('acquia_id', $user->id(), 'acquia_id_access_token', [
        'access_token' => $token,
        'timestamp' => time(),
      ]);

    $result = $access_manager->checkNamedRoute('acquia_id.sso', [], $user);
    $this->assertFalse($result);
  }

  /**
   * Makes an HTTP request through the kernel.
   */
  private function doRequest(Request $request): Response {
    $request_stack = $this->container->get('request_stack');
    while ($request_stack->getCurrentRequest() !== NULL) {
      $request_stack->pop();
    }

    $http_kernel = $this->container->get('http_kernel');
    return $http_kernel->handle($request);
  }

}
