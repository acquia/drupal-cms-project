<?php

namespace Drupal\Tests\ai_provider_amazeeio\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_provider_amazeeio_test\MockHttpClient;
use GuzzleHttp\ClientInterface;

/**
 * Test class for the API mocking framework.
 */
class MockHttpClientTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ai_provider_amazeeio_test'];

  /**
   * The http client instance.
   */
  protected ClientInterface $client;

  /**
   * Retrieve the http client.
   */
  public function client(): MockHttpClient {
    return $this->container->get('http_client');
  }

  /**
   * Requests to hosts that don't contain 'amazee' should pass through.
   */
  public function testUnmockedRequests(): void {
    $response = $this->client()->get('https://www.drupal.org');
    $this->assertEquals(
      expected: 200,
      actual: $response->getStatusCode(),
    );
  }

  /**
   * Unmocked paths to 'amazee' domains throw an exception.
   */
  public function testUnmockedPath(): void {
    $this->expectExceptionMessage('MockHttpClient: Unhandled request GET:https://www.amazee.ai/auth/login');
    $this->client()->get('https://www.amazee.ai/auth/login');
  }

  /**
   * Client exceptions in mocks are thrown and can be handled.
   */
  public function testMockException(): void {
    $this->expectExceptionMessage('Missing authentication header');
    $this->client()->get(
      'https://www.amazee.ai/mocked/request', [
        'body' => json_encode(['message' => 'hello'], JSON_THROW_ON_ERROR),
      ]
    );
  }

  /**
   * A successful mocked request.
   */
  public function testMockSuccess(): void {
    $result = $this->client()->get(
      'https://www.amazee.ai/mocked/request', [
        'headers' => ['Authentication' => 'Bearer 1234'],
        'body' => json_encode(['message' => 'hello'], JSON_THROW_ON_ERROR),
      ]
    );
    $this->assertEquals(200, $result->getStatusCode());
    $this->assertEquals(
      [
        'uppercase' => 'HELLO',
      ], json_decode($result->getBody()->getContents(), TRUE, flags: JSON_THROW_ON_ERROR)
    );
  }

}
