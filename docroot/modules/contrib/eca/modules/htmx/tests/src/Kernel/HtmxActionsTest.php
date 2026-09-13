<?php

declare(strict_types=1);

namespace Drupal\Tests\eca_htmx\Kernel;

use Drupal\Core\Action\ActionManager;
use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Token\TokenInterface;
use Drupal\eca_endpoint\Event\EndpointResponseEvent;
use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Kernel tests for the ECA HTMX actions.
 */
#[Group('eca')]
#[Group('eca_htmx')]
#[RunTestsInSeparateProcesses]
class HtmxActionsTest extends KernelTestBase {

  /**
   * Core action manager.
   *
   * @var \Drupal\Core\Action\ActionManager|null
   */
  protected ?ActionManager $actionManager;

  /**
   * Token services.
   *
   * @var \Drupal\eca\Token\TokenInterface|null
   */
  protected ?TokenInterface $tokenService;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'eca_render',
    'eca_misc',
    'eca_endpoint',
    'eca_htmx',
    'eca_test_render_basics',
    'modeler_api',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installConfig(static::$modules);
    $this->actionManager = \Drupal::service('plugin.manager.action');
    $this->tokenService = \Drupal::service('eca.token_services');
  }

  /**
   * Tests the action plugin "eca_htmx_element".
   */
  public function testElement(): void {
    /** @var \Drupal\eca_htmx\Plugin\Action\HtmxElement $action */
    $action = $this->actionManager->createInstance('eca_htmx_element', [
      'method' => 'post',
      'url' => '/eca/htmx/save',
      'trigger' => 'click',
      'swap' => 'outerHTML',
      'target' => '#result',
      'select' => '#fragment',
      'confirm' => 'Are you sure?',
      'indicator' => '#spinner',
      'vals' => "foo: bar",
      'boost' => TRUE,
      'htmx_only' => TRUE,
      'content' => 'Click me',
      'wrapper_tag' => 'button',
      'name' => 'my_button',
      'token_name' => '',
      'weight' => '',
      'mode' => 'set:clear',
    ]);

    $build = [];
    $event = new BasicRenderEvent($build);
    $action->setEvent($event);
    $action->execute();
    $result = $event->getRenderArray();

    $this->assertArrayHasKey('my_button', $result);
    $element = $result['my_button'];
    $attributes = $element['#attributes'];

    $this->assertSame('html_tag', $element['#type']);
    $this->assertSame('button', $element['#tag']);
    $this->assertSame('/eca/htmx/save', (string) $attributes['data-hx-post']);
    $this->assertSame('click', (string) $attributes['data-hx-trigger']);
    $this->assertSame('outerHTML', (string) $attributes['data-hx-swap']);
    $this->assertSame('#result', (string) $attributes['data-hx-target']);
    $this->assertSame('#fragment', (string) $attributes['data-hx-select']);
    $this->assertSame('Are you sure?', (string) $attributes['data-hx-confirm']);
    $this->assertSame('#spinner', (string) $attributes['data-hx-indicator']);
    $this->assertSame('true', (string) $attributes['data-hx-boost']);
    $this->assertArrayHasKey('data-hx-vals', $attributes);
    $this->assertArrayHasKey('data-hx-drupal-only-main-content', $attributes);
    $this->assertContains('core/drupal.htmx', $element['#attached']['library']);
  }

  /**
   * Tests "eca_htmx_element" with a wrapper tag defined by a token.
   */
  public function testElementWrapperTagFromToken(): void {
    $this->tokenService->addTokenData('eca_htmx_element_wrapper_tag', 'span');

    /** @var \Drupal\eca_htmx\Plugin\Action\HtmxElement $action */
    $action = $this->actionManager->createInstance('eca_htmx_element', [
      'method' => 'post',
      'url' => '/eca/htmx/save',
      'trigger' => 'click',
      'swap' => 'outerHTML',
      'target' => '#result',
      'select' => '#fragment',
      'confirm' => 'Are you sure?',
      'indicator' => '#spinner',
      'vals' => "foo: bar",
      'boost' => TRUE,
      'htmx_only' => TRUE,
      'content' => 'Click me',
      'wrapper_tag' => '_eca_token',
      'name' => 'my_element',
      'token_name' => '',
      'weight' => '',
      'mode' => 'set:clear',
    ]);

    $build = [];
    $event = new BasicRenderEvent($build);
    $action->setEvent($event);
    $action->execute();
    $result = $event->getRenderArray();

    $this->assertArrayHasKey('my_element', $result);
    $element = $result['my_element'];

    $this->assertSame('html_tag', $element['#type']);
    // The wrapper tag is resolved from the token, not the literal "_eca_token".
    $this->assertSame('span', $element['#tag']);
  }

  /**
   * Tests the action plugin "eca_htmx_set_route".
   */
  public function testSetRoute(): void {
    /** @var \Drupal\eca_htmx\Plugin\Action\SetRoute $action */
    $action = $this->actionManager->createInstance('eca_htmx_set_route', [
      'route_name' => 'eca_test.*',
    ]);

    $collection = new RouteCollection();
    $collection->add('eca_test.one', new Route('/eca-test/one'));
    $collection->add('eca_test.two', new Route('/eca-test/two'));
    $collection->add('other.route', new Route('/other'));

    $event = new RouteBuildEvent($collection);
    $action->setEvent($event);
    $this->assertTrue($action->access(NULL));
    $action->execute();

    $this->assertTrue($collection->get('eca_test.one')->getOption('_htmx_route'));
    $this->assertTrue($collection->get('eca_test.two')->getOption('_htmx_route'));
    $this->assertNull($collection->get('other.route')->getOption('_htmx_route'));
  }

  /**
   * Tests the action plugin "eca_htmx_set_route" with an exact match.
   */
  public function testSetRouteExactMatch(): void {
    /** @var \Drupal\eca_htmx\Plugin\Action\SetRoute $action */
    $action = $this->actionManager->createInstance('eca_htmx_set_route', [
      'route_name' => 'eca_test.one',
    ]);

    $collection = new RouteCollection();
    $collection->add('eca_test.one', new Route('/eca-test/one'));
    $collection->add('eca_test.two', new Route('/eca-test/two'));

    $event = new RouteBuildEvent($collection);
    $action->setEvent($event);
    $action->execute();

    $this->assertTrue($collection->get('eca_test.one')->getOption('_htmx_route'));
    $this->assertNull($collection->get('eca_test.two')->getOption('_htmx_route'));
  }

  /**
   * Tests the action plugin "eca_htmx_minimal_response".
   */
  public function testMinimalResponse(): void {
    $request = Request::create('/some/path');
    $request->setSession(new Session());
    /** @var \Symfony\Component\HttpFoundation\RequestStack $stack */
    $stack = $this->container->get('request_stack');
    $stack->pop();
    $stack->push($request);

    $this->assertFalse($request->query->has('_wrapper_format'));

    /** @var \Drupal\eca_htmx\Plugin\Action\MinimalResponse $action */
    $action = $this->actionManager->createInstance('eca_htmx_minimal_response', []);
    $this->assertTrue($action->access(NULL));
    $action->execute();

    $this->assertSame('drupal_htmx', $request->query->get('_wrapper_format'));
  }

  /**
   * Tests the action plugin "eca_htmx_response_header" for a redirect.
   */
  public function testResponseHeaderRedirect(): void {
    $response = $this->executeResponseHeaderAction([
      'header' => 'redirect',
      'value' => '/node/1',
    ]);
    $this->assertSame('/node/1', $response->headers->get('HX-Redirect'));
  }

  /**
   * Tests the action plugin "eca_htmx_response_header" for a refresh.
   */
  public function testResponseHeaderRefresh(): void {
    $response = $this->executeResponseHeaderAction([
      'header' => 'refresh',
      'value' => 'true',
    ]);
    $this->assertSame('true', $response->headers->get('HX-Refresh'));
  }

  /**
   * Tests the action plugin "eca_htmx_response_header" for a trigger.
   */
  public function testResponseHeaderTrigger(): void {
    $response = $this->executeResponseHeaderAction([
      'header' => 'trigger',
      'value' => 'myEvent',
    ]);
    $this->assertSame('myEvent', $response->headers->get('HX-Trigger'));
  }

  /**
   * Tests the action plugin "eca_htmx_response_header" for a reswap.
   */
  public function testResponseHeaderReswap(): void {
    $response = $this->executeResponseHeaderAction([
      'header' => 'reswap',
      'value' => 'beforeend',
    ]);
    $this->assertSame('beforeend', $response->headers->get('HX-Reswap'));
  }

  /**
   * Tests the action plugin "eca_htmx_response_header" for a plain location.
   */
  public function testResponseHeaderLocationPlain(): void {
    $response = $this->executeResponseHeaderAction([
      'header' => 'location',
      'value' => '/node/1',
    ]);
    $data = json_decode($response->headers->get('HX-Location'), TRUE);
    $this->assertSame(['path' => '/node/1'], $data);
  }

  /**
   * Tests the action plugin "eca_htmx_response_header" for a rich location.
   */
  public function testResponseHeaderLocationRich(): void {
    $response = $this->executeResponseHeaderAction([
      'header' => 'location',
      'value' => '/node/1',
      'location_source' => '#source',
      'location_event' => 'click',
      'location_handler' => 'myHandler',
      'location_target' => '#target',
      'location_swap' => 'innerHTML',
      'location_select' => '#fragment',
      'location_values' => "foo: bar",
      'location_headers' => "X-Custom: custom-value",
    ]);
    $data = json_decode($response->headers->get('HX-Location'), TRUE);

    $this->assertSame('/node/1', $data['path']);
    $this->assertSame('#source', $data['source']);
    $this->assertSame('click', $data['event']);
    $this->assertSame('myHandler', $data['handler']);
    $this->assertSame('#target', $data['target']);
    $this->assertSame('innerHTML', $data['swap']);
    $this->assertSame('#fragment', $data['select']);
    $this->assertSame(['foo' => 'bar'], $data['values']);
    $this->assertSame(['X-Custom' => 'custom-value'], $data['headers']);
  }

  /**
   * Executes the response header action against a fresh response.
   *
   * @param array $configuration
   *   The action configuration.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response after the action ran.
   */
  protected function executeResponseHeaderAction(array $configuration): Response {
    /** @var \Drupal\eca_htmx\Plugin\Action\ResponseHeader $action */
    $action = $this->actionManager->createInstance('eca_htmx_response_header', $configuration);

    $path_arguments = [];
    $request = Request::create('/some/path');
    $response = new Response();
    $account = \Drupal::currentUser();
    $build = [];
    $event = new EndpointResponseEvent($path_arguments, $request, $response, $account, $build);

    $action->setEvent($event);
    $this->assertTrue($action->access(NULL));
    $action->execute();

    return $response;
  }

}
