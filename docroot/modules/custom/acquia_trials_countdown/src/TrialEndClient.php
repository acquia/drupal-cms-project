<?php

namespace Drupal\acquia_trials_countdown;

use Drupal;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;

/**
 * Client for fetching trial end timestamps from the Acquia API.
 */
class TrialEndClient {

  const DEFAULT_EXPIRATION_SECONDS = 1800000000;

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
      // If any type of GuzzleException is thrown, just log it and provide a default expiration of a few days from now.
      $response = $this->getMockResponse();
      if (\Drupal::hasContainer()) {
        // Container isn't initialized in Unit test.
        Drupal::logger('acquia_trials_countdown')->error($e->getMessage());
      }
    }

    $data = json_decode($response->getBody(), TRUE);
    if (empty($data['timestamp']) || !is_numeric($data['timestamp'])) {
      // Just give an expiration time of a few days from now if we somehow got bad data.
      $data = ['timestamp' => self::DEFAULT_EXPIRATION_SECONDS];
      if (\Drupal::hasContainer()) {
        // Container isn't initialized in Unit test.
        Drupal::logger('acquia_trials_countdown')->error('Invalid response from Acquia API.');
      }
    }

    return (int) $data['timestamp'];
  }

  private function getMockResponse(): Response {
    return new Response(
      200,
      [],
      json_encode(['timestamp' => self::DEFAULT_EXPIRATION_SECONDS]),
    );
  }

}
