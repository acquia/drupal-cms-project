<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement;

/**
 * Result of pruning unused encryption keys.
 *
 * @immutable
 */
final class KeyPruneResult {

  /**
   * Construct a KeyPruneResult.
   *
   * @param \Drupal\easy_encryption\Encryption\EncryptionKeyId[] $deleted
   *   Deleted encryption key IDs.
   * @param \Drupal\easy_encryption\Encryption\EncryptionKeyId[] $failed
   *   Encryption key IDs that failed deletion.
   */
  public function __construct(
    public readonly array $deleted = [],
    public readonly array $failed = [],
  ) {}

}
