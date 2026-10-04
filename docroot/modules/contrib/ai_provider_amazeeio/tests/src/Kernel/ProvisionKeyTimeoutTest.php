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
 * Tests the timeout and retry behaviour of key provisioning requests.
 *
 * @group ai_provider_amazeeio
 */
class ProvisionKeyTimeoutTest extends KernelTestBase {

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
   * The create call waits much longer than a normal request.
   */
  public function testCreateKeyUsesLongTimeout(): void {
    $history = [];
    $client = $this->clientWithMock([
      new Response(200, [], '{"litellm_token":"tok","litellm_api_url":"https://llm.example"}'),
    ], $history);
    $client->setHost(AmazeeClient::AMAZEE_API_HOST);
    $client->setToken('a-token');

    $this->assertSame([
      'litellm_token' => 'tok',
      'litellm_api_url' => 'https://llm.example',
    ], $client->createPrivateAiKey('1', 'my key', 1));
    $this->assertCount(1, $history);
    $this->assertSame('POST', $history[0]['request']->getMethod());
    $this->assertSame(60, $history[0]['options']['timeout'], 'Key provisioning must wait for the slow create call.');
  }

  /**
   * A failed create call is reported, not repeated.
   */
  public function testCreateKeyIsNotRetried(): void {
    $history = [];
    // Queue extra failures so a regression to retrying would consume >1.
    $client = $this->clientWithMock([
      new Response(500),
      new Response(500),
      new Response(500),
      new Response(500),
    ], $history);
    $client->setHost(AmazeeClient::AMAZEE_API_HOST);
    $client->setToken('a-token');

    $this->assertSame([], $client->createPrivateAiKey('1', 'my key', 1));
    $this->assertCount(1, $history, 'A failed create must not be re-sent; the API keeps the key.');
  }

  /**
   * Every other request keeps the short default timeout.
   */
  public function testOtherRequestsKeepDefaultTimeout(): void {
    $history = [];
    $client = $this->clientWithMock([new Response(200, [], '[]')], $history);
    $client->setHost(AmazeeClient::AMAZEE_API_HOST);
    $client->setToken('a-token');

    $client->getRegions();
    $this->assertCount(1, $history);
    $this->assertSame(5, $history[0]['options']['timeout'], 'Only the create call gets the long timeout.');
  }

}
