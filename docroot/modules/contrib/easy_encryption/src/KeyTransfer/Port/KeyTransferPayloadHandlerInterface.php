<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyTransfer\Port;

use Drupal\easy_encryption\Encryption\EncryptionKeyId;

/**
 * Port for handling algorithm-specific key transfer payloads.
 *
 * Implementations are discovered via tagged services.
 *
 * @template TPayload of array
 */
interface KeyTransferPayloadHandlerInterface {

  /**
   * Returns TRUE if this handler supports importing the given payload.
   *
   * This method must be defensive: it can receive any decoded payload data.
   *
   * @param array $payload
   *   Payload data.
   *
   * @phpstan-assert-if-true TPayload $payload
   */
  public function applies(array $payload): bool;

  /**
   * Exports key material as payload data.
   *
   * @return TPayload
   *   Payload data for this handler.
   *
   * @throws \Drupal\easy_encryption\KeyTransfer\Port\KeyTransferPayloadHandlerException
   *   When export cannot be performed.
   */
  public function exportPayload(EncryptionKeyId $keyId): array;

  /**
   * Imports key material from payload data for the provided key id.
   *
   * This method must not activate the key. Activation is application logic.
   *
   * @param \Drupal\easy_encryption\Encryption\EncryptionKeyId $keyId
   *   Key identifier from the package envelope.
   * @param TPayload $payload
   *   Payload data for this handler.
   *
   * @throws \Drupal\easy_encryption\KeyTransfer\Port\KeyTransferPayloadHandlerException
   *   When import cannot be performed.
   */
  public function importPayload(EncryptionKeyId $keyId, array $payload): void;

}
