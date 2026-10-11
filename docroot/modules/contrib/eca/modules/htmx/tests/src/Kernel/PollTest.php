<?php

namespace Drupal\Tests\eca_htmx\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\Action\ActionManager;
use Drupal\eca\Token\TokenInterface;
use Drupal\eca_test_render_basics\Event\BasicRenderEvent;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests regarding the ECA HTMX poll action.
 */
#[Group('eca')]
#[Group('eca_htmx')]
#[RunTestsInSeparateProcesses]
class PollTest extends KernelTestBase {

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
   * Tests the action plugin "eca_htmx_poll".
   */
  public function testPoll(): void {
    /** @var \Drupal\eca_htmx\Plugin\Action\Poll $action */
    $action = $this->actionManager->createInstance('eca_htmx_poll', [
      'url' => '/eca/htmx/fragment',
      'interval' => '5',
      'swap' => 'innerHTML',
      'target' => '#status',
      'content' => 'Loading status…',
      'wrapper_tag' => 'div',
      'htmx_only' => TRUE,
      'name' => 'status_region',
      'token_name' => '',
      'weight' => '',
      'mode' => 'set:clear',
    ]);

    $build = [];
    $event = new BasicRenderEvent($build);
    $action->setEvent($event);
    $action->execute();
    $result = $event->getRenderArray();

    $this->assertArrayHasKey('status_region', $result);
    $region = $result['status_region'];

    $this->assertSame('html_tag', $region['#type']);
    $this->assertSame('div', $region['#tag']);
    $this->assertSame('/eca/htmx/fragment', (string) $region['#attributes']['data-hx-get']);
    $this->assertSame('every 5s', (string) $region['#attributes']['data-hx-trigger']);
    $this->assertSame('innerHTML', (string) $region['#attributes']['data-hx-swap']);
    $this->assertSame('#status', (string) $region['#attributes']['data-hx-target']);
    // The htmx_only checkbox adds the "only main content" attribute via the
    // core Htmx builder.
    $this->assertArrayHasKey('data-hx-drupal-only-main-content', $region['#attributes']);
    $this->assertSame('Loading status…', (string) $region['#value']);
    $this->assertContains('core/drupal.htmx', $region['#attached']['library']);
  }

  /**
   * Tests the action plugin "eca_htmx_poll" without an explicit target.
   */
  public function testPollWithoutTarget(): void {
    /** @var \Drupal\eca_htmx\Plugin\Action\Poll $action */
    $action = $this->actionManager->createInstance('eca_htmx_poll', [
      'url' => '/eca/htmx/fragment',
      'interval' => '',
      'swap' => '',
      'target' => '',
      'content' => '',
      'wrapper_tag' => '',
      'htmx_only' => FALSE,
      'name' => '',
      'token_name' => '',
      'weight' => '',
      'mode' => 'set:clear',
    ]);

    $build = [];
    $event = new BasicRenderEvent($build);
    $action->setEvent($event);
    $action->execute();
    $region = $event->getRenderArray();

    // Defaults are applied when the configuration is left empty.
    $this->assertSame('html_tag', $region['#type']);
    $this->assertSame('div', $region['#tag']);
    $this->assertSame('every 10s', (string) $region['#attributes']['data-hx-trigger']);
    $this->assertSame('innerHTML', (string) $region['#attributes']['data-hx-swap']);
    $this->assertArrayNotHasKey('data-hx-target', $region['#attributes']);
    // When htmx_only is disabled, the "only main content" attribute is absent.
    $this->assertArrayNotHasKey('data-hx-drupal-only-main-content', $region['#attributes']);
    $this->assertContains('core/drupal.htmx', $region['#attached']['library']);
  }

  /**
   * Tests "eca_htmx_poll" with a wrapper tag defined by a token.
   */
  public function testPollWrapperTagFromToken(): void {
    $this->tokenService->addTokenData('eca_htmx_poll_wrapper_tag', 'section');

    /** @var \Drupal\eca_htmx\Plugin\Action\Poll $action */
    $action = $this->actionManager->createInstance('eca_htmx_poll', [
      'url' => '/eca/htmx/fragment',
      'interval' => '5',
      'swap' => 'innerHTML',
      'target' => '#status',
      'content' => 'Loading status…',
      'wrapper_tag' => '_eca_token',
      'htmx_only' => TRUE,
      'name' => 'status_region',
      'token_name' => '',
      'weight' => '',
      'mode' => 'set:clear',
    ]);

    $build = [];
    $event = new BasicRenderEvent($build);
    $action->setEvent($event);
    $action->execute();
    $result = $event->getRenderArray();

    $this->assertArrayHasKey('status_region', $result);
    $region = $result['status_region'];

    $this->assertSame('html_tag', $region['#type']);
    // The wrapper tag is resolved from the token, not the literal "_eca_token".
    $this->assertSame('section', $region['#tag']);
  }

}
