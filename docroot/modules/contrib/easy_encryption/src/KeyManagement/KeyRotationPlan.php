<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement;

use Drupal\easy_encryption\Encryption\EncryptionKeyId;

/**
 * Describes what would be re-encrypted without performing changes.
 *
 * @immutable
 */
final class KeyRotationPlan {

  public function __construct(
    public readonly ?EncryptionKeyId $activeKeyId,
    public readonly int $total = 0,
    public readonly int $toUpdate = 0,
    public readonly int $toSkip = 0,
  ) {}

}
