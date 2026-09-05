<?php

declare(strict_types=1);

namespace Drupal\Tests\drupical\Kernel;

use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/**
 * Base class for Drupical Kernel tests.
 */
class DrupicalTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'system',
    'drupical',
  ];

  /**
   * History of requests/responses.
   *
   * @var array
   */
  protected array $history = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('system');
    $this->installConfig(['user']);
    $this->installConfig(['drupical']);
  }

  /**
   * Sets the event items to be returned for the test.
   *
   * @param mixed[][] $event_items
   *   The event items to test. Every time the http_client makes a request the
   *   next item in this array will be returned.
   */
  protected function setEventItems(array $event_items): void {
    $responses = [];
    foreach ($event_items as $events_page) {
      $responses[] = new Response(200, [], json_encode(['list' => $events_page]));
    }
    $this->setTestFeedResponses($responses);
  }

  /**
   * Sets test feed responses.
   *
   * @param \GuzzleHttp\Psr7\Response[] $responses
   *   The responses for the http_client service to return.
   */
  protected function setTestFeedResponses(array $responses): void {
    // Create a mock and queue responses.
    $mock = new MockHandler($responses);
    $handler_stack = HandlerStack::create($mock);
    $history = Middleware::history($this->history);
    $handler_stack->push($history);
    // Rebuild the container because the 'drupical.fetcher' service and other
    // services may already have an instantiated instance of the 'http_client'
    // service without these changes.
    $this->container->get('kernel')->rebuildContainer();
    $this->container = $this->container->get('kernel')->getContainer();
    $this->container->set('http_client', new Client(['handler' => $handler_stack]));
  }

}
