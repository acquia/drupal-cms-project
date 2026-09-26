<?php

namespace Drupal\acquia_trials_countdown;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;

/**
 * Client for fetching trial end timestamps from the Acquia API.
 */
class TrialEndClient {

  /**
   * Default TTL in seconds (3 days) used as fallback when the API is unavailable.
   */
  const DEFAULT_EXPIRATION_TTL_SECONDS = 259200;

  public function __construct(
    protected readonly ClientInterface $httpClient,
    protected readonly string $apiBaseUrl,
    protected readonly LoggerInterface $logger,
  ) {}

  /**
   * Fetches the trial end timestamp for a given subscription.
   *
   * @param string $subscriptionId
   *   The Acquia subscription ID.
   *
   * @return int
   *   The trial end Unix timestamp.
   */
  public function fetchTrialEnd(string $subscriptionId): int {
    try {
      $response = $this->httpClient->request('POST', $this->apiBaseUrl, [
        'json' => ['subscription_id' => $subscriptionId],
      ]);
    }
    catch (\GuzzleHttp\Exception\GuzzleException $e) {
      // If any type of GuzzleException is thrown, just log it and provide a default expiration of a few days from now.
      $response = $this->getMockResponse();
      $this->logger->error($e->getMessage());
    }

    $responseBody = (string) $response->getBody();
    $data = json_decode($responseBody, TRUE);
    if (empty($data['timestamp']) || !is_numeric($data['timestamp'])) {
      // Just give an expiration time of a few days from now if we somehow got bad data.
      $data = ['timestamp' => time() + self::DEFAULT_EXPIRATION_TTL_SECONDS];
      $this->logger->error('Invalid response from Acquia API.');
    }

    return (int) $data['timestamp'];
  }

  private function getMockResponse(): Response {
    return new Response(
      200,
      [],
      json_encode(['timestamp' => time() + self::DEFAULT_EXPIRATION_TTL_SECONDS]),
    );
  }

}
