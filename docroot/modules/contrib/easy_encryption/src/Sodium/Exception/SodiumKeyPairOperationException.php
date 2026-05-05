<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\Sodium\Exception;

/**
 * Thrown when a key pair operation fails.
 *
 * This exception covers failures related to key pair generation, storage,
 * retrieval, deletion, and cryptographic operations that depend on key
 * availability or validity.
 */
final class SodiumKeyPairOperationException extends SodiumKeyPairException {

  /**
   * Creates an exception for a failed generation or activation.
   *
   * @param \Throwable|null $previous
   *   (optional) The previous throwable.
   *
   * @return self
   *   The exception instance.
   */
  public static function generationFailed(?\Throwable $previous = NULL): self {
    return new self('Failed to generate and activate a sodium key pair.', 0, $previous);
  }

  /**
   * Creates an exception for a failed load of an existing key pair.
   *
   * @param string $id
   *   The key pair identifier.
   * @param \Throwable|null $previous
   *   (optional) The previous throwable.
   *
   * @return self
   *   The exception instance.
   */
  public static function loadFailed(string $id, ?\Throwable $previous = NULL): self {
    return new self(sprintf('Failed to load sodium key pair "%s".', $id), 0, $previous);
  }

  /**
   * Creates an exception for an attempt to delete the active key pair.
   *
   * @param string $id
   *   The key pair identifier.
   *
   * @return self
   *   The exception instance.
   */
  public static function cannotDeleteActive(string $id): self {
    return new self(sprintf('Cannot delete active sodium key pair "%s".', $id));
  }

  /**
   * Creates an exception for a failed deletion operation.
   *
   * @param string $id
   *   The key pair identifier.
   * @param \Throwable|null $previous
   *   (optional) The previous throwable.
   *
   * @return self
   *   The exception instance.
   */
  public static function deleteFailed(string $id, ?\Throwable $previous = NULL): self {
    return new self(sprintf('Failed to delete sodium key pair "%s".', $id), 0, $previous);
  }

  /**
   * Creates an exception for a failed decryption operation.
   *
   * @param string $keyId
   *   The key pair identifier.
   * @param \Throwable|null $previous
   *   (optional) The previous throwable.
   *
   * @return self
   *   The exception instance.
   */
  public static function decryptionFailed(string $keyId, ?\Throwable $previous = NULL): self {
    return new self(
      sprintf('Decryption failed for key pair "%s".', $keyId),
      0,
      $previous
    );
  }

  /**
   * Creates an exception when the private key is not available.
   *
   * This typically occurs in encrypt-only environments where the private key
   * has been intentionally excluded for security reasons.
   *
   * @param string $keyId
   *   The key pair identifier.
   *
   * @return self
   *   The exception instance.
   */
  public static function privateKeyNotAvailable(string $keyId): self {
    return new self(
      sprintf('Cannot decrypt with key pair "%s": private key not available.', $keyId)
    );
  }

  /**
   * Creates an exception when the public key is not available.
   *
   * @param string $keyId
   *   The key pair identifier.
   *
   * @return self
   *   The exception instance.
   */
  public static function publicKeyNotAvailable(string $keyId): self {
    return new self(
      sprintf('Cannot create Sodium keypair for "%s": public key not available.', $keyId)
    );
  }

  /**
   * Creates an exception when keypair construction fails.
   *
   * This occurs when sodium_crypto_box_keypair_from_secretkey_and_publickey()
   * throws an exception, typically indicating key corruption or mismatch.
   *
   * @param string $keyId
   *   The key pair identifier.
   * @param \Throwable $previous
   *   The previous throwable from the Sodium operation.
   *
   * @return self
   *   The exception instance.
   */
  public static function keypairConstructionFailed(string $keyId, \Throwable $previous): self {
    return new self(
      sprintf('Failed to construct Sodium keypair for "%s".', $keyId),
      0,
      $previous
    );
  }

}
