<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement\Port;

/**
 * Thrown when the encryption key registry cannot be read or written.
 *
 * This exception is intended to be thrown by KeyRegistryInterface
 * implementations, so callers do not need to depend on underlying storage
 * exceptions (config, database, etc.).
 */
final class KeyRegistryException extends \RuntimeException {

  /**
   * Creates an exception for failures while reading registry data.
   *
   * @param \Throwable|null $previous
   *   (optional) The underlying exception.
   */
  public static function readFailed(?\Throwable $previous = NULL): self {
    return new self('Failed to read encryption key registry.', 0, $previous);
  }

  /**
   * Creates an exception for failures while registering a key id.
   *
   * @param string $keyId
   *   The encryption key identifier that could not be registered.
   * @param \Throwable|null $previous
   *   (optional) The underlying exception.
   */
  public static function registerFailed(string $keyId, ?\Throwable $previous = NULL): self {
    return new self(sprintf('Failed to register encryption key id "%s".', $keyId), 0, $previous);
  }

  /**
   * Creates an exception for failures while unregistering a key id.
   *
   * @param string $keyId
   *   The encryption key identifier that could not be registered.
   * @param \Throwable|null $previous
   *   (optional) The underlying exception.
   */
  public static function unregisterFailed(string $keyId, ?\Throwable $previous = NULL): self {
    return new self(sprintf('Failed to unregister encryption key id "%s".', $keyId), 0, $previous);
  }

  /**
   * Creates an exception for failures while setting the active key id.
   *
   * @param string $keyId
   *   The encryption key identifier that could not be activated.
   * @param \Throwable|null $previous
   *   (optional) The underlying exception.
   */
  public static function setActiveFailed(string $keyId, ?\Throwable $previous = NULL): self {
    return new self(sprintf('Failed to set active encryption key id "%s".', $keyId), 0, $previous);
  }

  /**
   * Creates an exception when attempting to set an active key that is unknown.
   *
   * @param string $keyId
   *   The encryption key identifier.
   */
  public static function unknownKey(string $keyId): self {
    return new self(sprintf('Unknown encryption key id "%s".', $keyId));
  }

}
