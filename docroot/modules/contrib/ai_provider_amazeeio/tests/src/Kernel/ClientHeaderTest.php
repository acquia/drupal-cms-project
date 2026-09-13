<?php

namespace Drupal\Tests\ai_provider_amazeeio\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_provider_amazeeio\AmazeeIoApi\AmazeeClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Tests the X-Amazee-Client request tracking header.
 */
class ClientHeaderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ai', 'key', 'ai_provider_amazeeio'];

  /**
   * The header value identifies the module and its version.
   */
  public function testClientHeaderValue(): void {
    $this->assertStringStartsWith('ai_provider_amazeeio/', AmazeeClient::clientHeaderValue());
  }

  /**
   * The SDK client stamps the header on regular and streamed requests.
   *
   * The provider hands the OpenAI SDK a real Guzzle client carrying the
   * header as a default option (a PSR-18 decorator broke streamed requests
   * under BigPipe Fibers, #3586239). Guzzle must apply that default on both
   * request paths the SDK uses: PSR-18 sendRequest() and streamed send().
   */
  public function testDefaultHeaderStampsRequests(): void {
    $history = [];
    $stack = HandlerStack::create(new MockHandler([new Response(200), new Response(200)]));
    $stack->push(Middleware::history($history));

    // Build the client exactly as AmazeeioAiProvider::loadClient() does.
    $client = $this->container->get('http_client_factory')->fromOptions([
      'headers' => [AmazeeClient::CLIENT_HEADER => AmazeeClient::clientHeaderValue()],
      'handler' => $stack,
    ]);
    $this->assertInstanceOf(Client::class, $client, 'The SDK only supports streaming through a real Guzzle client.');

    $request = new Request('POST', 'https://amazeeio.llm/v1/chat/completions');
    // Regular SDK requests go through PSR-18 sendRequest().
    $client->sendRequest($request);
    // Streamed requests (forced under BigPipe Fibers) go through send().
    $client->send($request, ['stream' => TRUE]);

    $this->assertCount(2, $history);
    foreach ($history as $transaction) {
      $this->assertSame(
        AmazeeClient::clientHeaderValue(),
        $transaction['request']->getHeaderLine(AmazeeClient::CLIENT_HEADER),
      );
    }
  }

}
