<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_trials_id\Unit\Api;

use Drupal\acquia_trials_id\Api\Client;
use Drupal\acquia_trials_id\Api\ClientFactory;
use Drupal\Core\Http\ClientFactory as HttpClientFactory;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client as HttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(ClientFactory::class)]
#[Group('acquia_trials_id')]
class ClientFactoryTest extends UnitTestCase {

  public function testGetReturnsClientWithCorrectConfiguration(): void {
    $httpClient = $this->createMock(HttpClient::class);

    $httpClientFactory = $this->createMock(HttpClientFactory::class);
    $httpClientFactory->expects($this->once())
      ->method('fromOptions')
      ->with([
        'base_uri' => 'https://cloud.acquia.com',
        'headers' => [
          'Accept' => 'application/json, version=2',
          'Authorization' => 'Bearer test-token-xyz',
        ],
      ])
      ->willReturn($httpClient);

    $factory = new ClientFactory($httpClientFactory, 'https://cloud.acquia.com');
    $client = $factory->get('test-token-xyz');

    $this->assertInstanceOf(Client::class, $client);
  }

}
