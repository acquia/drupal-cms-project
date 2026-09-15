<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\Sodium\Adapter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\easy_encryption\Encryption\EncryptionKeyId;
use Drupal\easy_encryption\KeyTransfer\Port\KeyTransferPayloadHandlerException;
use Drupal\easy_encryption\KeyTransfer\Port\KeyTransferPayloadHandlerInterface;
use Drupal\easy_encryption\Sodium\SodiumKeyPairRepositoryUsingKeyEntities;
use Drupal\easy_encryption\Sodium\SodiumKeyPairWriteRepositoryInterface;
use Drupal\key\KeyInterface;

/**
 * Payload handler for sodium key pair payloads.
 *
 * This handler is opinionated about the "format" discriminator key.
 *
 * @internal This class is not part of the module's public programming API.
 *
 * @phpstan-type SodiumKeyPairPayload array{
 *   format: 'easy_encryption_sodium_keypair_v1',
 *   public_key_hex: non-empty-string,
 *   private_key_hex?: non-empty-string|null
 * }
 *
 * @implements \Drupal\easy_encryption\KeyTransfer\Port\KeyTransferPayloadHandlerInterface<SodiumKeyPairPayload>
 */
final class SodiumKeyPairPayloadHandler implements KeyTransferPayloadHandlerInterface {

  public const string FORMAT = 'easy_encryption_sodium_keypair_v1';

  /**
   * Constructs the handler.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly SodiumKeyPairWriteRepositoryInterface $repository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(array $payload): bool {
    if ((string) ($payload['format'] ?? '') !== self::FORMAT) {
      return FALSE;
    }

    // Minimal cheap checks so the assertion is honest.
    $public = $payload['public_key_hex'] ?? NULL;
    if (!is_string($public) || $public === '') {
      return FALSE;
    }

    if (array_key_exists('private_key_hex', $payload)) {
      $private = $payload['private_key_hex'];
      if ($private !== NULL && (!is_string($private) || $private === '')) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Asserts that a payload matches the sodium key pair payload shape.
   *
   * @param array $payload
   *   The untrusted payload.
   *
   * @phpstan-assert SodiumKeyPairPayload $payload
   *
   * @throws \Drupal\easy_encryption\KeyTransfer\Port\KeyTransferPayloadHandlerException
   *   When the payload is invalid.
   */
  private function assertSodiumPayload(array $payload): void {
    $format = (string) ($payload['format'] ?? '');
    if ($format !== self::FORMAT) {
      throw KeyTransferPayloadHandlerException::invalidPayload(sprintf('Unsupported format "%s".', $format));
    }

    $public = $payload['public_key_hex'] ?? NULL;
    if (!is_string($public) || $public === '') {
      throw KeyTransferPayloadHandlerException::invalidPayload('public_key_hex is required and must be a non-empty string.');
    }

    if (array_key_exists('private_key_hex', $payload)) {
      $private = $payload['private_key_hex'];
      if ($private !== NULL && (!is_string($private) || $private === '')) {
        throw KeyTransferPayloadHandlerException::invalidPayload('private_key_hex must be a non-empty string or null when present.');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function exportPayload(EncryptionKeyId $keyId): array {
    $storage = $this->entityTypeManager->getStorage('key');

    $public_id = SodiumKeyPairRepositoryUsingKeyEntities::publicKeyKeyEntityId((string) $keyId);
    $private_id = SodiumKeyPairRepositoryUsingKeyEntities::privateKeyKeyEntityId((string) $keyId);

    /** @var \Drupal\key\KeyInterface|null $public */
    $public = $storage->load($public_id);
    if (!$public instanceof KeyInterface) {
      throw KeyTransferPayloadHandlerException::exportNotPossible((string) $keyId, 'Public key entity is missing.');
    }

    $public_hex = $public->getKeyProvider()->getKeyValue($public);
    if ($public_hex === '') {
      throw KeyTransferPayloadHandlerException::exportNotPossible((string) $keyId, 'Public key value is empty.');
    }

    /** @var \Drupal\key\KeyInterface|null $private */
    $private = $storage->load($private_id);
    $private_hex = NULL;
    if ($private instanceof KeyInterface) {
      $tmp = $private->getKeyProvider()->getKeyValue($private);
      $private_hex = ($tmp === '') ? NULL : $tmp;
    }

    $payload = [
      'format' => self::FORMAT,
      'public_key_hex' => $public_hex,
    ];

    if ($private_hex !== NULL) {
      $payload['private_key_hex'] = $private_hex;
    }

    return $payload;
  }

  /**
   * {@inheritdoc}
   */
  public function importPayload(EncryptionKeyId $keyId, array $payload): void {
    // @phpstan-ignore method.alreadyNarrowedType
    $this->assertSodiumPayload($payload);

    $public_hex = $payload['public_key_hex'];
    $private_hex = $payload['private_key_hex'] ?? NULL;

    try {
      $this->repository->upsertPublicKey($keyId->value, sodium_hex2bin($public_hex));
      if ($private_hex !== NULL) {
        $this->repository->upsertPrivateKey($keyId->value, sodium_hex2bin($private_hex));
      }
    }
    catch (\Throwable $e) {
      throw KeyTransferPayloadHandlerException::importNotPossible((string) $keyId, 'Failed to persist key material.', $e);
    }
  }

}
