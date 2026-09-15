<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement;

/**
 * Thrown when key activation fails.
 *
 * This exception is intended to be thrown by the KeyActivator application
 * service, so callers do not need to depend on the underlying repository
 * exception hierarchy.
 */
final class KeyActivatorException extends \RuntimeException {

  /**
   * Creates an exception for a failed encryption key activation.
   *
   * @param string $keyId
   *   The key identifier that could not be activated.
   * @param \Throwable|null $previous
   *   (optional) The underlying exception that caused activation to fail.
   *
   * @return self
   *   The exception instance.
   */
  public static function activationFailed(string $keyId, ?\Throwable $previous = NULL): self {
    return new self(sprintf('Encryption key activation failed (key id: %s).', $keyId), 0, $previous);
  }

}
