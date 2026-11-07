<?php

declare(strict_types=1);

namespace Drupal\acquia_trials_id\Api;

use Drupal\Component\Serialization\Json;
use GuzzleHttp\Client as HttpClient;

/**
 * Cloud API client for application access checks.
 *
 * @phpstan-type ApplicationResponse array{uuid: string, name: string}
 */
final class Client {

  public function __construct(
    private readonly HttpClient $client,
  ) {}

  /**
   * Gets application data by UUID.
   *
   * @return array<string, mixed>
   *   The JSON decoded response.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If the request fails (including 403/404 when access is denied).
   */
  public function getApplication(string $applicationUuid): array {
    $response = $this->client->get("/api/applications/$applicationUuid");
    return Json::decode((string) $response->getBody());
  }

}
