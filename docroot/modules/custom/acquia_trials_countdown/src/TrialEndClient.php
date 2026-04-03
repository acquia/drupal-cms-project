<?php

namespace Drupal\acquia_trials_countdown;

use Drupal;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;

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
   */
  public function fetchTrialEnd(string $subscriptionId): int {
    try {
      $response = $this->httpClient->request('POST', $this->apiBaseUrl, [
        'json' => ['subscription_id' => $subscriptionId],
      ]);
    }
    catch (\GuzzleHttp\Exception\GuzzleException $e) {
      Drupal::logger('acquia_trials_countdown')->error($e->getMessage());
      // If any type of GuzzleException is thrown, just log it and provide a default expiration of a few days from now.
      $response = $this->getMockResponse();
    }

    $data = json_decode($response->getBody(), TRUE);
    if (empty($data['timestamp']) || !is_numeric($data['timestamp'])) {
      Drupal::logger('acquia_trials_countdown')->error('Invalid or empty timestamp returned. Timestamp: ' . $data['timestamp']);
      // Just give an expiration time of a few days from now if we somehow got bad data.
      $data = ['timestamp' => time() + (8 * 24 * 60 * 60)];
    }

    return (int) $data['timestamp'];
  }

  private function getMockResponse(): Response {
    return new Response(200, [], json_encode(['timestamp' => time() + (8 * 24 * 60 * 60)]));
  }

}
