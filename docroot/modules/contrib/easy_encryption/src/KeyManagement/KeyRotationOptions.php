<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement;

/**
 * Options for the rotation operation.
 */
final class KeyRotationOptions {

  public function __construct(
    public readonly bool $reencryptKeys = FALSE,
    public readonly bool $failOnReencryptErrors = TRUE,
  ) {}

}
