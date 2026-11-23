<?php

namespace Drupal\altcha;

use AltchaOrg\Altcha\Altcha;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;

/**
 * Verification of Self-hosted provided challenge solutions.
 */
class SelfHostedSolutionVerification extends SolutionVerificationBase {

  /**
   * Construct a new self-hosted solution verification service.
   *
   * @param \Drupal\altcha\SecretManager $secretManager
   *   The ALTCHA secret manager.
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $key_value_expirable_factory
   *   The expirable key/value factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The shared lock backend.
   */
  public function __construct(
    protected SecretManager $secretManager,
    KeyValueExpirableFactoryInterface $key_value_expirable_factory,
    TimeInterface $time,
    LockBackendInterface $lock,
  ) {
    parent::__construct(
      $key_value_expirable_factory,
      $time,
      $lock,
      'altcha_consumed_self_hosted_solutions',
      'altcha:self_hosted_solution:',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function verify(array $payload): bool {
    $altcha = new Altcha($this->secretManager->getSecretKey());

    if (!$altcha->verifySolution($payload)) {
      return FALSE;
    }

    $solution_id = $payload['signature'] ?? NULL;
    if (!is_string($solution_id) || $solution_id === '') {
      return FALSE;
    }

    // Legacy challenges carry their signed expiry in the salt query string.
    $salt_parts = explode('?', $payload['salt']);
    $parameters = [];
    if (isset($salt_parts[1])) {
      parse_str($salt_parts[1], $parameters);
    }

    return $this->consumeSolution($solution_id, $parameters['expires'] ?? NULL);
  }

}
