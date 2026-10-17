<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement;

use Drupal\easy_encryption\Encryption\EncryptionKeyId;

/**
 * Result of rotating the active encryption key and optionally re-encrypting.
 *
 * @immutable
 */
final class KeyRotationResult {

  public function __construct(
    public readonly ?EncryptionKeyId $oldActiveKeyId,
    public readonly EncryptionKeyId $newActiveKeyId,
    public readonly int $updated = 0,
    public readonly int $skipped = 0,
    public readonly int $failed = 0,
  ) {}

}
