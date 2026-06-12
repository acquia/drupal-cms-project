<?php

namespace Drupal\Tests\eca_render\Kernel;

use Drupal\Core\Action\ActionManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Token\TokenInterface;
use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use Drupal\eca_test_render_basics\RenderBasicsEvents;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\user\Entity\User;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Kernel tests regarding ECA render actions.
 */
abstract class RenderActionsTestBase extends KernelTestBase {

  use ContentTypeCreationTrait;

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
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface|null
   */
  protected ?EventDispatcherInterface $eventDispatcher;

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
    'image',
    'responsive_image',
    'serialization',
    'views',
    'breakpoint',
    'eca',
    'eca_render',
    'eca_test_render_basics',
    'modeler_api',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->container->get('theme_installer')->install(['claro', 'olivero']);

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    User::create(['uid' => 2, 'name' => 'auth'])->save();

    // Create the Article content type with a standard body field.
    $this->createContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    $request = Request::create('/eca/first/second?a=b', 'POST', [], [], [], [], 'hello');
    $request->setSession(new Session());
    /** @var \Symfony\Component\HttpFoundation\RequestStack $stack */
    $stack = $this->container->get('request_stack');
    $stack->pop();
    $stack->push($request);

    $this->actionManager = \Drupal::service('plugin.manager.action');
    $this->tokenService = \Drupal::service('eca.token_services');
    $this->eventDispatcher = \Drupal::service('event_dispatcher');
  }

  /**
   * Dispatches a basic render event.
   *
   * @param array $build
   *   (optional) The render array build.
   */
  protected function dispatchBasicRenderEvent(array $build = []): void {
    $this->eventDispatcher->dispatch(new BasicRenderEvent($build), RenderBasicsEvents::BASIC);
  }

}
