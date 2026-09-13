<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\Sodium;

/**
 * Write repository for sodium key pairs.
 */
interface SodiumKeyPairWriteRepositoryInterface {

  /**
   * Generates and persists a new key pair with the given id.
   *
   * @throws \Drupal\easy_encryption\Sodium\Exception\SodiumKeyPairOperationException
   *   If generation or persistence fails.
   */
  public function generateKeyPair(string $id): void;

  /**
   * Deletes persisted key material for the given id.
   *
   * @throws \Drupal\easy_encryption\Sodium\Exception\SodiumKeyPairOperationException
   *   If deletion fails.
   */
  public function deleteKeyPair(string $id): void;

  /**
   * Writes/updates public key material.
   *
   * @throws \Drupal\easy_encryption\Sodium\Exception\SodiumKeyPairOperationException
   *   If persistence fails.
   */
  public function upsertPublicKey(string $id, string $publicKeyBin): void;

  /**
   * Writes/updates private key material.
   *
   * @throws \Drupal\easy_encryption\Sodium\Exception\SodiumKeyPairOperationException
   *   If persistence fails.
   */
  public function upsertPrivateKey(string $id, string $privateKeyBin): void;

}
