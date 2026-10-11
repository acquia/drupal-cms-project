<?php

namespace Drupal\altcha\Controller;

use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Hasher\Algorithm;
use AltchaOrg\Altcha\ChallengeOptions;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\altcha\SecretManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provide challenge related endpoints.
 *
 * @package Drupal\altcha\ChallengeController
 */
class ChallengeController extends ControllerBase {

  /**
   * The ALTCHA algorithm.
   */
  const ALGORITHM = Algorithm::SHA256;

  /**
   * The length of the randomly generated salt.
   */
  const SALT_LENGTH = 32;

  /**
   * The challenge expiration in seconds.
   */
  const CHALLENGE_EXPIRATION = 3600;

  /**
   * Fallback maximum number to use.
   */
  const DEFAULT_MAX_NUMBER = 20000;

  /**
   * The ALTCHA config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected Config $altchaConfig;

  /**
   * Constructs a ChallengeController object.
   *
   * @param \Drupal\altcha\SecretManager $secretManager
   *   The ALTCHA secret manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(protected SecretManager $secretManager, protected TimeInterface $time) {
    $this->altchaConfig = $this->config('altcha.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('altcha.secret_manager'),
      $container->get('datetime.time')
    );
  }

  /**
   * Get a challenge.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  public function getChallenge(): Response {
    $timestamp = $this->time->getRequestTime() + static::CHALLENGE_EXPIRATION;
    $date = DrupalDateTime::createFromTimestamp($timestamp);

    $options = new ChallengeOptions(
      algorithm: static::ALGORITHM,
      maxNumber: $this->altchaConfig->get('max_number') ?? static::DEFAULT_MAX_NUMBER,
      expires: $date->getPhpDateTime(),
      saltLength: static::SALT_LENGTH,
    );

    $altcha = new Altcha($this->secretManager->getSecretKey());
    $challenge = $altcha->createChallenge($options);
    return new JsonResponse($challenge);
  }

}
