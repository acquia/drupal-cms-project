<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyTransfer\Port;

/**
 * Thrown when a key transfer payload handler cannot import or export a payload.
 */
final class KeyTransferPayloadHandlerException extends \RuntimeException {

  /**
   * Creates an exception for invalid payload content.
   *
   * @param string $reason
   *   Human-readable validation error.
   * @param \Throwable|null $previous
   *   (optional) The underlying exception.
   */
  public static function invalidPayload(string $reason, ?\Throwable $previous = NULL): self {
    return new self(sprintf('Invalid payload: %s', $reason), 0, $previous);
  }

  /**
   * Creates an exception when exporting a key is not possible.
   *
   * @param string $keyId
   *   The key id that could not be exported.
   * @param string $reason
   *   Human-readable explanation.
   * @param \Throwable|null $previous
   *   (optional) The underlying exception.
   */
  public static function exportNotPossible(string $keyId, string $reason, ?\Throwable $previous = NULL): self {
    return new self(sprintf('Cannot export key "%s": %s', $keyId, $reason), 0, $previous);
  }

  /**
   * Creates an exception when importing a key is not possible.
   *
   * @param string $keyId
   *   The key id that could not be imported.
   * @param string $reason
   *   Human-readable explanation.
   * @param \Throwable|null $previous
   *   (optional) The underlying exception.
   */
  public static function importNotPossible(string $keyId, string $reason, ?\Throwable $previous = NULL): self {
    return new self(sprintf('Cannot import key "%s": %s', $keyId, $reason), 0, $previous);
  }

}
