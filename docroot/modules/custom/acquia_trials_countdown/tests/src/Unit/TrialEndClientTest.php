<?php

namespace Drupal\Tests\acquia_trials_countdown\Unit;

use Drupal\acquia_trials_countdown\TrialEndClient;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\StreamInterface;

#[CoversClass(TrialEndClient::class)]
#[Group('acquia_trials_countdown')]
class TrialEndClientTest extends UnitTestCase {

  /**
   * Tests fetchTrialEnd() returns the timestamp from a successful API response.
   */
  public function testFetchTrialEndSuccess(): void {
    $expectedTimestamp = 1750000000;

    $body = $this->createMock(StreamInterface::class);
    $body->method('__toString')
      ->willReturn(json_encode(['timestamp' => $expectedTimestamp]));

    $response = $this->createMock(ResponseInterface::class);
    $response->method('getBody')->willReturn($body);

    $httpClient = $this->createMock(ClientInterface::class);
    $httpClient->expects($this->once())
      ->method('request')
      ->with('POST', 'https://api.example.com/trials', [
        'json' => ['subscription_id' => 'sub-123'],
      ])
      ->willReturn($response);

    $client = new TrialEndClient($httpClient, 'https://api.example.com/trials');
    $result = $client->fetchTrialEnd('sub-123');

    $this->assertSame($expectedTimestamp, $result);
  }

  /**
   * Tests fetchTrialEnd() when the API response has no timestamp.
   */
  public function testFetchTrialEndMissingTimestamp(): void {
    $body = $this->createMock(StreamInterface::class);
    $body->method('__toString')
      ->willReturn(json_encode(['status' => 'ok']));

    $response = $this->createMock(ResponseInterface::class);
    $response->method('getBody')->willReturn($body);

    $httpClient = $this->createMock(ClientInterface::class);
    $httpClient->method('request')->willReturn($response);

    $client = new TrialEndClient($httpClient, 'https://api.example.com/trials');

    $result = $client->fetchTrialEnd('sub-123');
    $expected = TrialEndClient::DEFAULT_EXPIRATION_SECONDS;
    $this->assertEquals($expected, $result);

  }

  /**
   * Tests fetchTrialEnd() propagates HTTP exceptions.
   */
  public function testFetchTrialEndHttpError(): void {
    $httpClient = $this->createMock(ClientInterface::class);
    $httpClient->method('request')
      ->willThrowException(new RequestException(
        'Server error',
        $this->createMock(RequestInterface::class),
      ));

    $client = new TrialEndClient($httpClient, 'https://api.example.com/trials');
    $result = $client->fetchTrialEnd('sub-123');
    $expected = TrialEndClient::DEFAULT_EXPIRATION_SECONDS;
    $this->assertEquals($expected, $result);
  }

}
