<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\Sodium\Exception;

/**
 * Base exception for sodium key pair repository failures.
 *
 * All exceptions thrown by Sodium repositories
 * implementations SHOULD extend this class so that callers can
 * catch and handle repository errors in a generic way.
 */
class SodiumKeyPairException extends \RuntimeException {

  /**
   * Creates an exception for a key pair that was not found.
   *
   * @param string $id
   *   The key pair identifier.
   *
   * @return self
   *   The exception instance.
   */
  public static function notFound(string $id): self {
    return new self(sprintf('Sodium key pair "%s" not found.', $id));
  }

}
