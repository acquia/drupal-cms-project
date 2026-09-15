<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyTransfer;

/**
 * Thrown when key transfer operations fail at the application layer.
 */
final class KeyTransferException extends \RuntimeException {

  /**
   * Creates an exception for an invalid package.
   *
   * @param string $reason
   *   Human-readable validation error.
   * @param \Throwable|null $previous
   *   (optional) The underlying exception.
   */
  public static function invalidPackage(string $reason, ?\Throwable $previous = NULL): self {
    return new self(sprintf('Invalid key package: %s', $reason), 0, $previous);
  }

  /**
   * Creates an exception when no payload handler supports the package payload.
   *
   * @param string $reason
   *   Human-readable explanation, for example "Unsupported format foo".
   * @param \Throwable|null $previous
   *   (optional) The underlying exception.
   */
  public static function unsupportedPayload(string $reason, ?\Throwable $previous = NULL): self {
    return new self(sprintf('Unsupported key package payload: %s', $reason), 0, $previous);
  }

  /**
   * Creates an exception for export failures.
   *
   * @param string $keyId
   *   The key id that was being exported.
   * @param string $reason
   *   Human-readable explanation of the failure.
   * @param \Throwable|null $previous
   *   (optional) The underlying exception.
   */
  public static function exportFailed(string $keyId, string $reason, ?\Throwable $previous = NULL): self {
    return new self(sprintf('Key export failed (key id: %s). %s', $keyId, $reason), 0, $previous);
  }

  /**
   * Creates an exception for import failures.
   *
   * @param string $reason
   *   Human-readable explanation of the failure.
   * @param \Throwable|null $previous
   *   (optional) The underlying exception.
   */
  public static function importFailed(string $reason, ?\Throwable $previous = NULL): self {
    return new self(sprintf('Key import failed. %s', $reason), 0, $previous);
  }

  /**
   * Creates an exception for failures while activating a key after import.
   *
   * @param string $keyId
   *   The imported key id that could not be activated.
   * @param \Throwable|null $previous
   *   (optional) The underlying exception.
   */
  public static function activationFailed(string $keyId, ?\Throwable $previous = NULL): self {
    return new self(sprintf('Key imported but activation failed (key id: %s).', $keyId), 0, $previous);
  }

}
