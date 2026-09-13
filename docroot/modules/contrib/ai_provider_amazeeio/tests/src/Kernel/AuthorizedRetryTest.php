<?php

namespace Drupal\Tests\ai_provider_amazeeio\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_provider_amazeeio\AmazeeIoApi\AmazeeClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Log\NullLogger;

/**
 * Tests that AmazeeClient::authorized() does not waste HTTP round trips.
 *
 * @group ai_provider_amazeeio
 */
class AuthorizedRetryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ai_provider_amazeeio_test'];

  /**
   * Build an AmazeeClient backed by a Guzzle mock, recording every request.
   *
   * @param \GuzzleHttp\Psr7\Response[] $queue
   *   Responses the mock will return in order.
   * @param array $history
   *   Passed by reference; each performed request is appended here.
   */
  private function clientWithMock(array $queue, array &$history): AmazeeClient {
    $stack = HandlerStack::create(new MockHandler($queue));
    $stack->push(Middleware::history($history));
    $guzzle = new Client(['handler' => $stack]);
    return new AmazeeClient(
      $guzzle,
      new NullLogger(),
      $this->container->get('config.factory'),
    );
  }

  /**
   * An empty token short-circuits with zero HTTP requests.
   */
  public function testEmptyTokenMakesNoRequest(): void {
    $history = [];
    // Queue a response that must never be consumed.
    $client = $this->clientWithMock([new Response(200, [], '{"team_id":1}')], $history);
    $client->setHost(AmazeeClient::AMAZEE_API_HOST);
    $client->setToken('');

    $this->assertFalse($client->authorized());
    $this->assertCount(0, $history, 'authorized() must not hit the network with an empty token.');
  }

  /**
   * A 401 is the expected answer and must not be retried.
   */
  public function testInvalidTokenIsNotRetried(): void {
    $history = [];
    // Queue several 401s so a regression to retrying would consume >1.
    $client = $this->clientWithMock([
      new Response(401),
      new Response(401),
      new Response(401),
      new Response(401),
    ], $history);
    $client->setHost(AmazeeClient::AMAZEE_API_HOST);
    $client->setToken('an-expired-token');

    $this->assertFalse($client->authorized());
    $this->assertCount(1, $history, 'A 401 from /auth/me must not trigger the retry loop.');
  }

}
