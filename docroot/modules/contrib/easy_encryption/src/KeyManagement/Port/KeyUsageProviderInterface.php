<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement\Port;

/**
 * Interface for reporters that identify encryption key dependencies.
 */
interface KeyUsageProviderInterface {

  /**
   * Returns a mapping of consumers.
   *
   * @return array{result: array<string, \Drupal\easy_encryption\KeyManagement\Port\KeyUsageMapping>, cacheability: \Drupal\Core\Cache\CacheableDependencyInterface}
   *   The 'result' is keyed by consumer ID. 'cacheability' applies to the
   *   entire set.
   */
  public function getKeyUsageMapping(): array;

}
