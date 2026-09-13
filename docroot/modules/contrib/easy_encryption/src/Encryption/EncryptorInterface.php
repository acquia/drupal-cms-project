<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\Encryption;

/**
 * Defines an interface for encrypting and decrypting sensitive data.
 */
interface EncryptorInterface {

  /**
   * Encrypts a value.
   *
   * @param string $value
   *   The plaintext value to encrypt.
   *
   * @return \Drupal\easy_encryption\Encryption\EncryptedValue
   *   The encrypted value with metadata.
   *
   * @throws \InvalidArgumentException
   *   If the value is empty.
   * @throws \Drupal\easy_encryption\Encryption\EncryptionException
   *   If encryption fails.
   */
  public function encrypt(#[\SensitiveParameter] string $value): EncryptedValue;

  /**
   * Decrypts a value.
   *
   * @param \Drupal\easy_encryption\Encryption\EncryptedValue $value
   *   The encrypted value to decrypt.
   *
   * @return string
   *   The decrypted plaintext.
   *
   * @throws \Drupal\easy_encryption\Encryption\EncryptionException
   *   If decryption fails.
   */
  public function decrypt(EncryptedValue $value): string;

  /**
   * Validates that encryption and decryption work correctly.
   *
   * Implementations may skip this test silently if the environment does not
   * support decryption (for example, when only a public key is available).
   *
   * @throws \Drupal\easy_encryption\Encryption\EncryptionException
   *   If the self-test fails.
   */
  public function selfTest(): void;

}
