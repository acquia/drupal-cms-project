<?php

declare(strict_types=1);

namespace Drupal\acquia_trials_id\Api;

use Drupal\Core\Http\ClientFactory as HttpClientFactory;

final readonly class ClientFactory {

  public function __construct(
    private HttpClientFactory $httpClientFactory,
    private string $baseUri,
  ) {}

  public function get(string $accessToken): Client {
    return new Client($this->httpClientFactory->fromOptions([
      'base_uri' => $this->baseUri,
      'headers' => [
        'Accept' => 'application/json, version=2',
        'Authorization' => "Bearer $accessToken",
      ],
    ]));
  }

}
