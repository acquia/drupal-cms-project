<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\Sodium;

/**
 * Read-only repository for sodium key pairs.
 */
interface SodiumKeyPairReadRepositoryInterface {

  /**
   * Returns a key pair by its identifier.
   *
   * Implementations MAY omit the private key when the current environment is
   * not allowed to perform decryption.
   *
   * @throws \Drupal\easy_encryption\Sodium\Exception\SodiumKeyPairNotFoundException
   * @throws \Drupal\easy_encryption\Sodium\Exception\SodiumKeyPairOperationException
   */
  public function getKeyPairById(string $id): SodiumKeyPair;

  /**
   * Returns identifiers for all stored key pairs.
   *
   * Implementations MUST return identifiers for all key pairs known to
   * the repository, regardless of whether the corresponding private
   * keys are available in the current environment.
   *
   * @return string[]
   *   An array of key pair identifiers.
   */
  public function listKeyPairIds(): array;

}
