<?php

namespace Drupal\altcha;

use AltchaOrg\Altcha\Altcha;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;

/**
 * Verification of Sentinel provided challenge solutions.
 */
class SentinelSolutionVerification extends SolutionVerificationBase {

  /**
   * Construct a new sentinel solution verification service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $key_value_expirable_factory
   *   The expirable key/value factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The shared lock backend.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    KeyValueExpirableFactoryInterface $key_value_expirable_factory,
    TimeInterface $time,
    LockBackendInterface $lock,
  ) {
    parent::__construct(
      $key_value_expirable_factory,
      $time,
      $lock,
      'altcha_consumed_sentinel_solutions',
      'altcha:sentinel_solution:',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function verify(array $payload): bool {
    $config = $this->configFactory->get('altcha.settings');
    $altcha = new Altcha($config->get('sentinel_api_secret'));
    $result = $altcha->verifyServerSignature($payload);
    if (!$result->verified) {
      return FALSE;
    }

    $solution_id = $payload['signature'] ?? NULL;
    if (!is_string($solution_id) || $solution_id === '') {
      return FALSE;
    }

    return $this->consumeSolution($solution_id, $result->data?->expire);
  }

}
