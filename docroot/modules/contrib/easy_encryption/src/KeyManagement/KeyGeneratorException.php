<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement;

/**
 * Thrown when key generation or activation fails.
 */
final class KeyGeneratorException extends \RuntimeException {

  /**
   * Creates an exception for a failed encryption key generation.
   *
   * @param string|null $keyId
   *   The key identifier, if one was known at the time of failure.
   * @param \Throwable|null $previous
   *   (optional) The underlying exception that caused generation to fail.
   *
   * @return self
   *   The exception instance.
   */
  public static function generationFailed(?string $keyId = NULL, ?\Throwable $previous = NULL): self {
    $suffix = $keyId !== NULL ? sprintf(' (key id: %s)', $keyId) : '';
    return new self('Encryption key generation failed' . $suffix . '.', 0, $previous);
  }

}
