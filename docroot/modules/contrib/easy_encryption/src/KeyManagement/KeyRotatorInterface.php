<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement;

/**
 * Application service for planning and performing encryption key rotation.
 */
interface KeyRotatorInterface {

  /**
   * Builds a non-mutating plan describing what would change.
   *
   * @throws \Drupal\easy_encryption\KeyManagement\KeyRotationException
   *   If the plan cannot be computed.
   */
  public function plan(bool $includeReencryptCounts = TRUE): KeyRotationPlan;

  /**
   * Rotates the active encryption key and optionally re-encrypts credentials.
   *
   * @throws \Drupal\easy_encryption\KeyManagement\KeyRotationException
   *   If rotation fails, or re-encryption fails and failOnReencryptErrors
   *   is TRUE.
   */
  public function rotate(KeyRotationOptions $options): KeyRotationResult;

}
