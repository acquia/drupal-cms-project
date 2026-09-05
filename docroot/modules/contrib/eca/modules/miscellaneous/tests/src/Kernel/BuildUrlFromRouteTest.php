<?php

namespace Drupal\Tests\eca_misc\Kernel;

use Drupal\Core\Action\ActionManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Token\TokenInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Kernel tests for the "eca_build_url_from_route" action plugin.
 */
#[Group('eca')]
#[Group('eca_misc')]
#[RunTestsInSeparateProcesses]
class BuildUrlFromRouteTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'node',
    'text',
    'user',
    'eca',
    'eca_misc',
    'modeler_api',
  ];

  /**
   * Action plugin manager.
   *
   * @var \Drupal\Core\Action\ActionManager|null
   */
  protected ?ActionManager $actionManager;

  /**
   * A test node.
   *
   * @var \Drupal\node\NodeInterface|null
   */
  protected ?NodeInterface $node;

  /**
   * ECA token service.
   *
   * @var \Drupal\eca\Token\TokenInterface|null
   */
  protected ?TokenInterface $tokenService;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    $this->node = Node::create([
      'type' => 'article',
      'uid' => 1,
      'title' => 'Build URL test article',
      'status' => 1,
    ]);
    $this->node->save();
    $this->actionManager = \Drupal::service('plugin.manager.action');
    $this->tokenService = \Drupal::service('eca.token_services');
  }

  /**
   * Tests building a relative URL from a route with parameters.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testBuildRelativeUrl(): void {
    $config = [
      'token_name' => 'myurl',
      'route_name' => 'entity.node.canonical',
      'parameters' => 'node: ' . $this->node->id(),
      'options' => '',
      'absolute' => FALSE,
    ];
    /** @var \Drupal\eca_misc\Plugin\Action\BuildUrlFromRoute $action */
    $action = $this->actionManager->createInstance('eca_build_url_from_route', $config);
    $action->execute();
    $this->assertSame(
      '/node/' . $this->node->id(),
      (string) $this->tokenService->getTokenData('myurl'),
      'The relative URL has been added to the token system.'
    );
  }

  /**
   * Tests building an absolute URL via the absolute checkbox.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testBuildAbsoluteUrl(): void {
    $config = [
      'token_name' => 'myurl',
      'route_name' => 'entity.node.canonical',
      'parameters' => 'node: ' . $this->node->id(),
      'options' => '',
      'absolute' => TRUE,
    ];
    /** @var \Drupal\eca_misc\Plugin\Action\BuildUrlFromRoute $action */
    $action = $this->actionManager->createInstance('eca_build_url_from_route', $config);
    $action->execute();
    $url = (string) $this->tokenService->getTokenData('myurl');
    $this->assertStringStartsWith('http', $url, 'The absolute URL starts with a scheme.');
    $this->assertStringEndsWith('/node/' . $this->node->id(), $url, 'The absolute URL ends with the canonical path.');
  }

  /**
   * Tests options like query and fragment are applied to the URL.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testBuildUrlWithOptions(): void {
    $config = [
      'token_name' => 'myurl',
      'route_name' => 'entity.node.canonical',
      'parameters' => 'node: ' . $this->node->id(),
      'options' => "query:\n  foo: bar\nfragment: comments",
      'absolute' => FALSE,
    ];
    /** @var \Drupal\eca_misc\Plugin\Action\BuildUrlFromRoute $action */
    $action = $this->actionManager->createInstance('eca_build_url_from_route', $config);
    $action->execute();
    $url = (string) $this->tokenService->getTokenData('myurl');
    $this->assertStringContainsString('foo=bar', $url, 'The query option is part of the URL.');
    $this->assertStringContainsString('#comments', $url, 'The fragment option is part of the URL.');
  }

  /**
   * Tests that empty parameters and options are tolerated.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testBuildUrlWithEmptyConfiguration(): void {
    $config = [
      'token_name' => 'myurl',
      'route_name' => 'user.login',
      'parameters' => '',
      'options' => '',
      'absolute' => FALSE,
    ];
    /** @var \Drupal\eca_misc\Plugin\Action\BuildUrlFromRoute $action */
    $action = $this->actionManager->createInstance('eca_build_url_from_route', $config);
    $action->execute();
    $this->assertSame(
      '/user/login',
      (string) $this->tokenService->getTokenData('myurl'),
      'A URL without parameters or options has been generated.'
    );
  }

  /**
   * Tests that an unknown route makes the action throw an exception.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testBuildUrlWithInvalidRoute(): void {
    $config = [
      'token_name' => 'myurl',
      'route_name' => 'this.route.does.not.exist',
      'parameters' => '',
      'options' => '',
      'absolute' => FALSE,
    ];
    /** @var \Drupal\eca_misc\Plugin\Action\BuildUrlFromRoute $action */
    $action = $this->actionManager->createInstance('eca_build_url_from_route', $config);
    $this->expectException(RouteNotFoundException::class);
    $action->execute();
  }

  /**
   * Tests access validation of the YAML configuration fields.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testAccessYamlValidation(): void {
    // Valid YAML in both fields grants access.
    $config = [
      'token_name' => 'myurl',
      'route_name' => 'entity.node.canonical',
      'parameters' => 'node: ' . $this->node->id(),
      'options' => "query:\n  foo: bar",
      'absolute' => FALSE,
    ];
    /** @var \Drupal\eca_misc\Plugin\Action\BuildUrlFromRoute $action */
    $action = $this->actionManager->createInstance('eca_build_url_from_route', $config);
    $this->assertTrue($action->access(NULL, User::load(1)), 'Valid YAML configuration grants access.');

    // Invalid YAML in the parameters field forbids access.
    $config['parameters'] = "node: [1, 2";
    /** @var \Drupal\eca_misc\Plugin\Action\BuildUrlFromRoute $action */
    $action = $this->actionManager->createInstance('eca_build_url_from_route', $config);
    $this->assertFalse($action->access(NULL, User::load(1)), 'Invalid parameters YAML forbids access.');

    // Invalid YAML in the options field forbids access.
    $config['parameters'] = 'node: ' . $this->node->id();
    $config['options'] = "query: {foo: bar";
    /** @var \Drupal\eca_misc\Plugin\Action\BuildUrlFromRoute $action */
    $action = $this->actionManager->createInstance('eca_build_url_from_route', $config);
    $this->assertFalse($action->access(NULL, User::load(1)), 'Invalid options YAML forbids access.');
  }

}
