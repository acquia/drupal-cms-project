<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement;

/**
 * Public API for tracking encryption key usage across the entire system.
 */
interface KeyUsageTrackerInterface {

  /**
   * Returns the aggregated mapping of all consumers and their encryption keys.
   *
   * @return array{result: array<string, \Drupal\easy_encryption\KeyManagement\Port\KeyUsageMapping>, cacheability: \Drupal\Core\Cache\CacheableDependencyInterface}
   *   The 'result' is keyed by consumer ID.
   *   'cacheability' applies to the entire set.
   */
  public function getKeyUsageMapping(): array;

}
