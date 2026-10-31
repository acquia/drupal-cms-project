<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement\Port;

use Drupal\easy_encryption\Encryption\EncryptionKeyId;

/**
 * Read-only registry of known encryption keys and the active key.
 */
interface KeyRegistryInterface {

  /**
   * Lists encryption key IDs known to this site.
   *
   * @return array{result: \Drupal\easy_encryption\Encryption\EncryptionKeyId[], cacheability: \Drupal\Core\Cache\CacheableDependencyInterface}
   *   Known key ids plus cacheability metadata.
   *
   * @throws \Drupal\easy_encryption\KeyManagement\Port\KeyRegistryException
   *   When registry storage cannot be read.
   */
  public function listKnownKeyIds(): array;

  /**
   * Returns the active encryption key id, if any.
   *
   * @return array{result: \Drupal\easy_encryption\Encryption\EncryptionKeyId|null, cacheability: \Drupal\Core\Cache\CacheableDependencyInterface}
   *   Active key id plus cacheability metadata.
   *
   * @throws \Drupal\easy_encryption\KeyManagement\Port\KeyRegistryException
   *   When registry storage cannot be read.
   */
  public function getActiveKeyId(): array;

  /**
   * Checks whether a key id is known to the registry.
   *
   * @return array{result: bool, cacheability: \Drupal\Core\Cache\CacheableDependencyInterface}
   *   TRUE if known plus cacheability metadata.
   *
   * @throws \Drupal\easy_encryption\KeyManagement\Port\KeyRegistryException
   *   When registry storage cannot be read.
   */
  public function isKnown(EncryptionKeyId $key_id): array;

}
