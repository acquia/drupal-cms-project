<?php

namespace Drupal\Tests\eca_endpoint\Kernel;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\eca_endpoint\Controller\EndpointController;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Kernel tests regarding the ECA endpoint controller.
 */
#[Group('eca')]
#[Group('eca_endpoint')]
#[RunTestsInSeparateProcesses]
class EndpointControllerTest extends KernelTestBase {

  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'options',
    'node',
    'eca',
    'eca_endpoint',
    'eca_access',
    'modeler_api',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    User::create(['uid' => 2, 'name' => 'auth'])->save();

    // Create the Article content type with a standard body field.
    $this->createContentType(['type' => 'article', 'name' => 'Article']);
  }

  /**
   * Tests the custom access callback of the endpoint controller.
   */
  public function testControllerAccess(): void {
    $controller = $this->container->get(EndpointController::class);
    $result = $controller->access(User::load(0), 'first', 'second');
    $this->assertFalse($result->isAllowed());
    $this->assertTrue($result->isForbidden());

    $result = $controller->access(User::load(1), 'first', 'second');
    $this->assertFalse($result->isAllowed());
    $this->assertTrue($result->isForbidden());

    $result = $controller->access(User::load(2), 'first', 'second');
    $this->assertFalse($result->isAllowed());
    $this->assertTrue($result->isForbidden());

    // This config does the following:
    // 1. It reacts upon endpoint access for "/eca/first/second" URL path.
    // 2. Upon that, it grants access for all users.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'endpoint_access',
      'label' => 'ECA endpoint access',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'endpoint_access' => [
          'plugin' => 'eca_endpoint:access',
          'label' => 'ECA endpoint access',
          'configuration' => [
            'first_path_argument' => 'first',
            'second_path_argument' => 'second',
          ],
          'successors' => [
            ['id' => 'grant_access', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'grant_access' => [
          'plugin' => 'eca_access_set_result',
          'label' => 'Grant access',
          'configuration' => [
            'access_result' => 'allowed',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->save();

    $result = $controller->access(User::load(0), 'first', 'second');
    $this->assertTrue($result->isAllowed());

    $result = $controller->access(User::load(1), 'first', 'second');
    $this->assertTrue($result->isAllowed());

    $result = $controller->access(User::load(2), 'first', 'second');
    $this->assertTrue($result->isAllowed());

    $result = $controller->access(User::load(2), 'first', 'third');
    $this->assertFalse($result->isAllowed());
  }

  /**
   * Tests that the deferred access decision is not being cached over time.
   *
   * When the access callback is called for the endpoint route itself, it does
   * not decide anything: it grants access so that ::handle gets the chance to
   * decide. As ::handle re-decides on every request by dispatching to the ECA
   * models, the returned access result must not be cacheable in time.
   */
  public function testControllerAccessOnHandleRouteIsNotCacheableInTime(): void {
    $controller = $this->container->get(EndpointController::class);
    $route = $this->container->get('router.route_provider')->getRouteByName('eca_endpoint.endpoint');

    // The access callback reads the route object and the raw arguments from
    // the current route match, so put a matching request onto the stack.
    $request = Request::create('/eca/first/second');
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, 'eca_endpoint.endpoint');
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $request->attributes->set('_raw_variables', new InputBag([
      'eca_endpoint_argument_1' => 'first',
      'eca_endpoint_argument_2' => 'second',
    ]));
    $request_stack = $this->container->get('request_stack');
    $request_stack->push($request);
    $result = $controller->access(User::load(2), 'first', 'second');
    // Take the request off the stack again, before asserting anything. The
    // stack must be left as it was found: its topmost entry is the request
    // that DrupalKernel::preHandle() pushed, and the tear down of
    // KernelTestBase clears the mock session of that request through
    // RequestStack::getSession(), which only ever looks at the topmost entry.
    // Popping before the assertions also keeps a failing assertion readable,
    // as it can then no longer be masked by an exception from the tear down.
    $request_stack->pop();

    $this->assertTrue($result->isAllowed(), "Access is being granted, so that ::handle gets the chance to decide.");
    $this->assertInstanceOf(CacheableDependencyInterface::class, $result);
    $this->assertSame(0, $result->getCacheMaxAge(), "The deferred access decision must not be cached in time, because ::handle re-decides it on every request.");
    $this->assertEqualsCanonicalizing([
      'url.path',
      'url.query_args',
      'user',
      'user.permissions',
    ], $result->getCacheContexts(), "The cache contexts of the deferred access decision are being kept.");
  }

  /**
   * Tests that the verdict of the ECA models is not being cached.
   *
   * When the current route is not the endpoint route being served, the access
   * callback does not defer to ::handle. It dispatches "eca_endpoint:access"
   * itself and returns whatever the ECA models decided. This is the path taken
   * while building menu links, in Url::access() and during link generation,
   * and it is also the path taken here, because KernelTestBase pushes a
   * request that carries no route object.
   *
   * That verdict is as dynamic as the one ::handle makes: it may depend on
   * anything the models look at, none of which is expressed as a cache
   * context. It must therefore not be cached in time. It must additionally be
   * invalidated whenever ECA config changes, because the set of models
   * reacting upon the event is itself config.
   */
  public function testControllerAccessOnModelVerdictIsNotCacheable(): void {
    $controller = $this->container->get(EndpointController::class);

    // No model reacts upon the event yet, so the predefined verdict is being
    // returned. That verdict is no less volatile than one from a model: a
    // model granting access may be added at any time.
    $result = $controller->access(User::load(2), 'first', 'second');
    $this->assertTrue($result->isForbidden(), "Access is forbidden when no ECA model reacts upon the event.");
    $this->assertInstanceOf(CacheableDependencyInterface::class, $result);
    $this->assertSame(0, $result->getCacheMaxAge(), "The predefined verdict must not be cached in time, because adding an ECA model changes it.");
    $this->assertContains('config:eca_list', $result->getCacheTags(), "The predefined verdict must be invalidated when ECA config changes, because adding an ECA model changes it.");
    $this->assertSame([], $result->getCacheContexts(), "No cache contexts are being declared, because there is no way to know what the models base their verdict on. The zero max age is what keeps the result correct.");

    // Now let a model react upon the event and grant access.
    $this->createEndpointAccessModel('endpoint_access_allowed', 'allowed', 0);

    $result = $controller->access(User::load(2), 'first', 'second');
    $this->assertTrue($result->isAllowed(), "Access is being granted by the ECA model.");
    $this->assertInstanceOf(CacheableDependencyInterface::class, $result);
    $this->assertSame(0, $result->getCacheMaxAge(), "The verdict of the ECA model must not be cached in time, because the model re-decides it on every request.");
    $this->assertContains('config:eca_list', $result->getCacheTags(), "The verdict of the ECA model must be invalidated when ECA config changes, because editing or disabling the model changes it.");
    $this->assertSame([], $result->getCacheContexts(), "No cache contexts are being declared, because there is no way to know what the models base their verdict on. The zero max age is what keeps the result correct.");
  }

  /**
   * Tests the handle callback of the endpoint controller.
   */
  public function testControllerHandle(): void {
    $controller = $this->container->get(EndpointController::class);
    $request = Request::create('/eca/first/second');
    $request->setSession(new Session());

    $not_found = FALSE;
    try {
      $controller->handle($request, User::load(1), 'first', 'second');
    }
    catch (NotFoundHttpException $e) {
      $not_found = TRUE;
    }
    $this->assertTrue($not_found);

    // This config does the following:
    // 1. It reacts upon endpoint response for "/eca/first/second" URL path.
    // 2. Upon that, it writes a plain string into the response as content.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'endpoint_response',
      'label' => 'ECA endpoint response',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'endpoint_response' => [
          'plugin' => 'eca_endpoint:response',
          'label' => 'ECA endpoint response',
          'configuration' => [
            'first_path_argument' => 'first',
            'second_path_argument' => 'second',
          ],
          'successors' => [
            ['id' => 'set_content', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'set_content' => [
          'plugin' => 'eca_endpoint_set_response_content',
          'label' => 'Set content',
          'configuration' => [
            'content' => 'Hello from ECA!',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->save();

    $not_found = FALSE;
    try {
      $controller->handle($request, User::load(1), 'first', 'third');
    }
    catch (NotFoundHttpException $e) {
      $not_found = TRUE;
    }
    $this->assertTrue($not_found, "Despite of being defined, the endpoint still returns a 404, because no access has been defined yet.");

    // This config does the following:
    // 1. It reacts upon endpoint access for "/eca/first/second" URL path.
    // 2. Upon that, it grants access for all users.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'endpoint_access',
      'label' => 'ECA endpoint access',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'endpoint_access' => [
          'plugin' => 'eca_endpoint:access',
          'label' => 'ECA endpoint access',
          'configuration' => [
            'first_path_argument' => 'first',
            'second_path_argument' => 'second',
          ],
          'successors' => [
            ['id' => 'grant_access', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'grant_access' => [
          'plugin' => 'eca_access_set_result',
          'label' => 'Grant access',
          'configuration' => [
            'access_result' => 'allowed',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->save();

    $response = $controller->handle($request, User::load(1), 'first', 'second');
    $this->assertEquals('Hello from ECA!', $response->getContent());

    $not_found = FALSE;
    try {
      $controller->handle($request, User::load(1), 'first', 'third');
    }
    catch (NotFoundHttpException $e) {
      $not_found = TRUE;
    }
    $this->assertTrue($not_found);
  }

  /**
   * Tests the access callback when multiple models react upon the event.
   *
   * The access result is accumulated across all reacting ECA models, so a
   * single model revoking access must win regardless of the order in which
   * the models are being executed.
   */
  public function testControllerAccessWithMultipleModels(): void {
    $controller = $this->container->get(EndpointController::class);

    // Without any model reacting upon the event, access is being revoked.
    $result = $controller->access(User::load(2), 'first', 'second');
    $this->assertTrue($result->isForbidden(), "Access is forbidden when no ECA model reacts upon the event.");

    // Two models react upon the event for the same URL path: the one granting
    // access is being executed first, the one revoking access is being
    // executed last.
    $this->createEndpointAccessModel('endpoint_access_allowed', 'allowed', 0);
    $this->createEndpointAccessModel('endpoint_access_forbidden', 'forbidden', 10);

    $result = $controller->access(User::load(2), 'first', 'second');
    $this->assertFalse($result->isAllowed(), "Access is not allowed when another ECA model revoked access, even when that model is being executed last.");
    $this->assertTrue($result->isForbidden(), "Access is forbidden when another ECA model revoked access, even when that model is being executed last.");

    // Reverse the weights, so that the model revoking access is now being
    // executed first and the model granting access is being executed last.
    // The accumulated result must not depend on the execution order.
    $this->setModelWeight('endpoint_access_forbidden', 0);
    $this->setModelWeight('endpoint_access_allowed', 10);

    $result = $controller->access(User::load(2), 'first', 'second');
    $this->assertFalse($result->isAllowed(), "Access is not allowed when another ECA model revoked access, even when that model is being executed first.");
    $this->assertTrue($result->isForbidden(), "Access is forbidden when another ECA model revoked access, even when that model is being executed first.");
  }

  /**
   * Tests the handle callback when multiple models react upon the event.
   *
   * Just like for the access callback, a single ECA model revoking access must
   * win regardless of the order in which the models are being executed.
   */
  public function testControllerHandleWithMultipleModels(): void {
    $controller = $this->container->get(EndpointController::class);
    $request = Request::create('/eca/first/second');
    $request->setSession(new Session());

    // This config reacts upon endpoint response for the "/eca/first/second"
    // URL path and writes a plain string into the response as content. It only
    // ever gets executed when access was granted.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'endpoint_response',
      'label' => 'ECA endpoint response',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'endpoint_response' => [
          'plugin' => 'eca_endpoint:response',
          'label' => 'ECA endpoint response',
          'configuration' => [
            'first_path_argument' => 'first',
            'second_path_argument' => 'second',
          ],
          'successors' => [
            ['id' => 'set_content', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'set_content' => [
          'plugin' => 'eca_endpoint_set_response_content',
          'label' => 'Set content',
          'configuration' => [
            'content' => 'Hello from ECA!',
          ],
          'successors' => [],
        ],
      ],
    ];
    Eca::create($eca_config_values)->save();

    // Two models react upon the access event for the same URL path: the one
    // granting access is being executed first, the one revoking access is
    // being executed last.
    $this->createEndpointAccessModel('endpoint_access_allowed', 'allowed', 0);
    $this->createEndpointAccessModel('endpoint_access_forbidden', 'forbidden', 10);

    $this->assertHandleAccessDenied($controller, $request, "The endpoint denies access when an ECA model revoked access, even when that model is being executed last.");

    // Reverse the weights, so that the model revoking access is now being
    // executed first and the model granting access is being executed last.
    $this->setModelWeight('endpoint_access_forbidden', 0);
    $this->setModelWeight('endpoint_access_allowed', 10);

    $this->assertHandleAccessDenied($controller, $request, "The endpoint denies access when an ECA model revoked access, even when that model is being executed first.");
  }

  /**
   * Creates an ECA model that sets an access result for the endpoint.
   *
   * The model reacts upon endpoint access for the "/eca/first/second" URL
   * path.
   *
   * @param string $id
   *   The ID of the ECA model.
   * @param string $access_result
   *   The access result to set, one of "allowed", "neutral" or "forbidden".
   * @param int $weight
   *   The weight of the ECA model, which determines the order of execution.
   */
  private function createEndpointAccessModel(string $id, string $access_result, int $weight): void {
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => $id,
      'label' => 'ECA endpoint access: ' . $access_result,
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'weight' => $weight,
      'events' => [
        'endpoint_access' => [
          'plugin' => 'eca_endpoint:access',
          'label' => 'ECA endpoint access',
          'configuration' => [
            'first_path_argument' => 'first',
            'second_path_argument' => 'second',
          ],
          'successors' => [
            ['id' => 'set_access_result', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'set_access_result' => [
          'plugin' => 'eca_access_set_result',
          'label' => 'Set access result',
          'configuration' => [
            'access_result' => $access_result,
          ],
          'successors' => [],
        ],
      ],
    ];
    Eca::create($eca_config_values)->save();
  }

  /**
   * Changes the weight of an existing ECA model.
   *
   * @param string $id
   *   The ID of the ECA model.
   * @param int $weight
   *   The weight to set, which determines the order of execution.
   */
  private function setModelWeight(string $id, int $weight): void {
    $eca = Eca::load($id);
    if (!($eca instanceof Eca)) {
      $this->fail(sprintf('The ECA model %s does not exist.', $id));
    }
    $eca->set('weight', $weight);
    $eca->save();
  }

  /**
   * Asserts that handling the endpoint request results in access being denied.
   *
   * @param \Drupal\eca_endpoint\Controller\EndpointController $controller
   *   The endpoint controller.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to handle.
   * @param string $message
   *   The message to display when the assertion fails.
   */
  private function assertHandleAccessDenied(EndpointController $controller, Request $request, string $message): void {
    $denied = FALSE;
    try {
      $controller->handle($request, User::load(2), 'first', 'second');
    }
    catch (AccessDeniedHttpException) {
      $denied = TRUE;
    }
    $this->assertTrue($denied, $message);
  }

}
