<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement;

use Drupal\easy_encryption\Encryption\EncryptionKeyId;

/**
 * Describes what would be pruned without performing changes.
 *
 * @immutable
 */
final class KeyPrunePlan {

  /**
   * Construct a KeyPrunePlan.
   *
   * @param \Drupal\easy_encryption\Encryption\EncryptionKeyId|null $activeKeyId
   *   The active encryption key ID, if any.
   * @param \Drupal\easy_encryption\Encryption\EncryptionKeyId[] $toDelete
   *   Encryption key IDs that would be deleted.
   * @param \Drupal\easy_encryption\Encryption\EncryptionKeyId[] $referenced
   *   Encryption key IDs referenced, in use.
   */
  public function __construct(
    public readonly ?EncryptionKeyId $activeKeyId,
    public readonly array $toDelete = [],
    public readonly array $referenced = [],
  ) {}

}
