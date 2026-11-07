<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyTransfer\Port;

/**
 * Thrown when a key package cannot be encoded or decoded.
 */
final class KeyPackageCodecException extends \RuntimeException {

  /**
   * Creates an exception for failures while encoding a key package.
   *
   * @param \Throwable|null $previous
   *   (optional) The underlying exception.
   */
  public static function encodeFailed(?\Throwable $previous = NULL): self {
    return new self('Unable to encode key package.', 0, $previous);
  }

  /**
   * Creates an exception for failures while decoding a key package.
   *
   * @param string $reason
   *   Human-readable error describing what part of decoding failed.
   * @param \Throwable|null $previous
   *   (optional) The underlying exception.
   */
  public static function decodeFailed(string $reason, ?\Throwable $previous = NULL): self {
    return new self(sprintf('Unable to decode key package: %s', $reason), 0, $previous);
  }

}
