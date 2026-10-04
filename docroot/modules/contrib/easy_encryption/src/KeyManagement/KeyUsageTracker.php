<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * Aggregates key usage information from all registered providers.
 *
 * @internal This class is not part of the module's public programming API.
 */
final class KeyUsageTracker implements KeyUsageTrackerInterface {

  /**
   * Constructs a new object.
   *
   * @param iterable<\Drupal\easy_encryption\KeyManagement\Port\KeyUsageProviderInterface> $providers
   *   List of key usage providers.
   */
  public function __construct(
    private readonly iterable $providers,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getKeyUsageMapping(): array {
    $all_mappings = [];
    $aggregate_cacheability = new CacheableMetadata();

    foreach ($this->providers as $provider) {
      $response = $provider->getKeyUsageMapping();

      // 1. Bubble up the provider's global cacheability
      // (e.g. 'config:key_list')
      $aggregate_cacheability->addCacheableDependency($response['cacheability']);

      // 2. Aggregate the results efficiently
      foreach ($response['result'] as $consumer_id => $usage_mapping) {
        $all_mappings[$consumer_id] = $usage_mapping;
      }
    }

    return [
      'result' => $all_mappings,
      'cacheability' => $aggregate_cacheability,
    ];
  }

}
