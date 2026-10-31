<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement;

/**
 * Exception is thrown by key rotation workflows.
 */
final class KeyRotationException extends \RuntimeException {

  /**
   * Creates an exception for failures while building a rotation plan.
   *
   * Planning is a non-mutating operation (used for dry-run previews). This
   * exception indicates that the system could not compute a plan, typically due
   * to storage access errors or unexpected runtime issues.
   *
   * @param \Throwable|null $previous
   *   (optional) The underlying exception that caused planning to fail.
   *
   * @return self
   *   The exception instance.
   */
  public static function planFailed(?\Throwable $previous = NULL): self {
    return new self('Key rotation plan failed.', 0, $previous);
  }

  /**
   * Creates an exception for failures while rotating the active enc. keys.
   *
   * This exception indicates that generating or activating a new encryption key
   * failed.
   *
   * @param \Throwable|null $previous
   *   (optional) The underlying exception that caused rotation to fail.
   *
   * @return self
   *   The exception instance.
   */
  public static function rotateFailed(?\Throwable $previous = NULL): self {
    return new self('Key rotation failed.', 0, $previous);
  }

  /**
   * Creates an exception when re-encryption cannot be performed.
   *
   * This is used when the operation is not possible in the current environment,
   * for example because the private key is not available and existing encrypted
   * values therefore cannot be decrypted for re-encryption.
   *
   * @param string $reason
   *   A human-readable explanation of why re-encryption cannot be performed.
   * @param \Throwable|null $previous
   *   (optional) The underlying exception, if any.
   *
   * @return self
   *   The exception instance.
   */
  public static function reencryptNotPossible(string $reason, ?\Throwable $previous = NULL): self {
    return new self(sprintf('Re-encryption is not possible: %s', $reason), 0, $previous);
  }

  /**
   * Creates an exception when one or more credentials fail to re-encrypt.
   *
   * @param int $failedCount
   *   The number of credentials that failed to re-encrypt.
   * @param \Throwable|null $previous
   *   (optional) The underlying exception, if any.
   *
   * @return self
   *   The exception instance.
   */
  public static function reencryptFailed(int $failedCount, ?\Throwable $previous = NULL): self {
    return new self(sprintf('Re-encryption failed for %d credential(s).', $failedCount), 0, $previous);
  }

}
