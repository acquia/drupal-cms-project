<?php

namespace Drupal\altcha;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\State\StateInterface;

/**
 * Manage ALTCHA related secrets.
 */
class SecretManager {

  /**
   * The secret key name used by ALTCHA self-hosted.
   */
  public const SECRET_KEY_NAME = 'altcha-hmac-key';

  /**
   * The secret key length used by ALTCHA self-hosted.
   */
  public const SECRET_KEY_LENGTH = 64;

  /**
   * Construct a new secret manager.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(protected StateInterface $state) {}

  /**
   * Generates a secret key.
   *
   * @return string
   *   The secret key.
   */
  public function generateSecretKey(): string {
    $secret = Crypt::randomBytesBase64(static::SECRET_KEY_LENGTH);
    $this->state->set(static::SECRET_KEY_NAME, $secret);
    return $secret;
  }

  /**
   * Gets the current secret key from the State API.
   *
   * @return string|null
   *   The secret key. Null in case it does not exist.
   */
  public function getSecretKey(): ?string {
    return $this->state->get(static::SECRET_KEY_NAME);
  }

  /**
   * Deletes the current key from the State API.
   */
  public function deleteSecretKey(): void {
    $this->state->delete(static::SECRET_KEY_NAME);
  }

}
