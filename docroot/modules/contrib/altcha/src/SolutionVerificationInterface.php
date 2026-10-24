<?php

namespace Drupal\altcha;

/**
 * Provides ALTCHA challenge solution verification methods.
 */
interface SolutionVerificationInterface {

  /**
   * Verify the provided challenge solution.
   *
   * @param array $payload
   *   The challenge solution payload provided by the client.
   *
   * @return bool
   *   Whether verification succeeded.
   */
  public function verify(array $payload): bool;

}
