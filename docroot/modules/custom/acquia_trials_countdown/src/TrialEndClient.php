<?php

namespace Drupal\acquia_trials_countdown;

use Drupal;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Client for fetching trial end timestamps from the Acquia API.
 */
class TrialEndClient {

  public function __construct(
    protected readonly ClientInterface $httpClient,
    protected readonly string $apiBaseUrl,
  ) {}

  /**
   * Fetches the trial end timestamp for a given subscription.
   *
   * @param string $subscriptionId
   *   The Acquia subscription ID.
   *
   * @return int
   *   The trial end Unix timestamp.
   *
   * @throws \RuntimeException
   *   If the API response is missing a valid timestamp.
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If the HTTP request fails.
   */
  public function fetchTrialEnd(string $subscriptionId): int {
    try {
      $response = $this->httpClient->request('POST', $this->apiBaseUrl, [
        'json' => ['subscription_id' => $subscriptionId],
      ]);
      $data = json_decode((string) $response->getBody(), TRUE);
    }
    catch (\GuzzleHttp\Exception\ConnectException $e) {
      // If the API is unreachable, just give a default value of a few days from now.
      Drupal::logger('acquia_trials_countdown')->error($e->getMessage());
      $data = [
        'timestamp' => time() + (8 * 24 * 60 * 60),
      ];
      // @todo are there other scenarios we should consider? Authentication fails? Trial doesn't exist?
    }

    if (empty($data['timestamp']) || !is_numeric($data['timestamp'])) {
      throw new \RuntimeException('API response missing valid timestamp.');
    }

    return (int) $data['timestamp'];
  }

}
