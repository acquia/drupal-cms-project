<?php

namespace Drupal\altcha;

use AltchaOrg\Altcha\Altcha;

/**
 * Verification of Self-hosted provided challenge solutions.
 */
class SelfHostedSolutionVerification implements SolutionVerificationInterface {

  /**
   * Construct a new self-hosted solution verification service.
   *
   * @param \Drupal\altcha\SecretManager $secretManager
   *   The ALTCHA secret manager.
   */
  public function __construct(
    protected SecretManager $secretManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function verify(array $payload): bool {
    $altcha = new Altcha($this->secretManager->getSecretKey());
    return $altcha->verifySolution($payload);
  }

}
