<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement;

use Drupal\easy_encryption\Encryption\EncryptionKeyId;

/**
 * Application service for activating an encryption key.
 *
 * Activation sets which encryption key ID is considered active for new
 * encryption operations on the site.
 */
interface KeyActivatorInterface {

  /**
   * Activates an existing encryption key by ID.
   *
   * Activation MUST be allowed when private key is missing, as long as a public
   * key exists (encrypt-only environments).
   *
   * @param \Drupal\easy_encryption\Encryption\EncryptionKeyId $keyId
   *   The encryption key identifier.
   *
   * @throws \Drupal\easy_encryption\KeyManagement\KeyActivatorException
   *   Thrown when activation fails.
   */
  public function activate(EncryptionKeyId $keyId): void;

}
