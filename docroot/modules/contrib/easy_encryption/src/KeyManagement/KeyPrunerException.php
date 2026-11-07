<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement;

/**
 * Thrown when pruning unused encryption keys cannot be planned or started.
 */
final class KeyPrunerException extends \RuntimeException {

  /**
   * Creates an exception for failures while building a prune plan.
   *
   * @param \Throwable|null $previous
   *   (optional) The underlying exception.
   */
  public static function planFailed(?\Throwable $previous = NULL): self {
    return new self('Planning unused encryption keys pruning failed.', 0, $previous);
  }

  /**
   * Creates an exception for failures that prevent pruning from running at all.
   *
   * @param \Throwable|null $previous
   *   (optional) The underlying exception.
   */
  public static function pruneFailed(?\Throwable $previous = NULL): self {
    return new self('Pruning unused encryption keys failed.', 0, $previous);
  }

}
