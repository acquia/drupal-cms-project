<?php

namespace Drupal\altcha;

use AltchaOrg\Altcha\Altcha;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Verification of Sentinel provided challenge solutions.
 */
class SentinelSolutionVerification implements SolutionVerificationInterface {

  /**
   * Construct a new sentinel solution verification service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function verify(array $payload): bool {
    $config = $this->configFactory->get('altcha.settings');
    $altcha = new Altcha($config->get('sentinel_api_secret'));
    $result = $altcha->verifyServerSignature($payload);
    return $result->verified;
  }

}
