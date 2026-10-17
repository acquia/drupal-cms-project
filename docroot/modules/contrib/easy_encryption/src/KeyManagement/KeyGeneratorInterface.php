<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement;

use Drupal\easy_encryption\Encryption\EncryptionKeyId;

/**
 * Application service for generating and activating encryption keys.
 *
 * This service returns key identifiers, not key material, to keep the
 * application layer decoupled from the underlying cryptographic library.
 */
interface KeyGeneratorInterface {

  /**
   * Generates and persists an encryption key.
   *
   * If no ID is provided, the generator MUST create a new identifier.
   *
   * @param \Drupal\easy_encryption\Encryption\EncryptionKeyId|null $keyId
   *   (optional) The encryption key identifier to use.
   *
   * @return \Drupal\easy_encryption\Encryption\EncryptionKeyId
   *   The encryption key identifier that was generated.
   *
   * @throws \Drupal\easy_encryption\KeyManagement\KeyGeneratorException
   *   Thrown when the encryption key generation fails.
   */
  public function generate(?EncryptionKeyId $keyId = NULL): EncryptionKeyId;

}
