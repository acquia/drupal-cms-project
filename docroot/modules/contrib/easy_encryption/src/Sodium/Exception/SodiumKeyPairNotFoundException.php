<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\Sodium\Exception;

/**
 * Thrown when a requested key pair cannot be found.
 */
final class SodiumKeyPairNotFoundException extends SodiumKeyPairException {

  /**
   * Creates an exception for a missing key pair identifier.
   *
   * @param string $id
   *   The missing key pair identifier.
   * @param \Throwable|null $previous
   *   (optional) The previous throwable.
   *
   * @return static
   *   The exception instance.
   */
  public static function forId(string $id, ?\Throwable $previous = NULL): self {
    return new self(sprintf('Sodium key pair "%s" does not exist.', $id), 0, $previous);
  }

  /**
   * Creates an exception for a missing active key pair.
   *
   * @return self
   *   The exception instance.
   */
  public static function forActive(): self {
    return new self('No active sodium key pair is configured.');
  }

}
