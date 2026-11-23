<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement\Port;

use Drupal\easy_encryption\Encryption\EncryptionKeyId;

/**
 * Mutable registry of known encryption keys and the active key.
 */
interface MutableKeyRegistryInterface {

  /**
   * Registers a key id as known.
   *
   * Implementations must be idempotent.
   *
   * @throws \Drupal\easy_encryption\KeyManagement\Port\KeyRegistryException
   *   When the registry cannot be written.
   */
  public function register(EncryptionKeyId $key_id): void;

  /**
   * Unregisters a key id from the known list.
   *
   * @throws \Drupal\easy_encryption\KeyManagement\Port\KeyRegistryException
   *   When the registry cannot be written.
   */
  public function unregister(EncryptionKeyId $key_id): void;

  /**
   * Sets the active encryption key id.
   *
   * Implementations should either:
   * - implicitly register the key id if missing, or
   * - throw unknownKey() if the key id is not registered.
   *
   * Pick one policy and keep it consistent. For export/import and for
   * predictable behavior, I recommend: throw if unknown.
   *
   * @throws \Drupal\easy_encryption\KeyManagement\Port\KeyRegistryException
   *   When the registry cannot be written or the key is unknown.
   */
  public function setActive(EncryptionKeyId $key_id): void;

}
