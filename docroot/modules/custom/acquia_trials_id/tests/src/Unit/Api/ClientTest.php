<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_trials_id\Unit\Api;

use Drupal\acquia_trials_id\Api\Client;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

#[CoversClass(Client::class)]
#[Group('acquia_trials_id')]
class ClientTest extends UnitTestCase {

  public function testGetApplicationReturnsDecodedResponse(): void {
    $responseData = ['uuid' => 'app-uuid-123', 'name' => 'My App'];

    $body = $this->createMock(StreamInterface::class);
    $body->method('__toString')->willReturn(json_encode($responseData));

    $response = $this->createMock(ResponseInterface::class);
    $response->method('getBody')->willReturn($body);

    $httpClient = $this->createMock(HttpClient::class);
    $httpClient->expects($this->once())
      ->method('get')
      ->with('/api/applications/app-uuid-123')
      ->willReturn($response);

    $client = new Client($httpClient);
    $result = $client->getApplication('app-uuid-123');

    $this->assertSame($responseData, $result);
  }

  public function testGetApplicationThrowsOnHttpError(): void {
    $httpClient = $this->createMock(HttpClient::class);
    $httpClient->method('get')
      ->willThrowException(new ClientException(
        'Forbidden',
        $this->createMock(RequestInterface::class),
        $this->createMock(ResponseInterface::class),
      ));

    $client = new Client($httpClient);

    $this->expectException(ClientException::class);
    $client->getApplication('no-access-uuid');
  }

}
