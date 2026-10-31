<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyTransfer;

use Drupal\easy_encryption\Encryption\EncryptionKeyId;

/**
 * Application service for importing/exporting keys as portable packages.
 */
interface KeyTransferInterface {

  /**
   * Exports a known key as a portable package string.
   *
   * @throws \Drupal\easy_encryption\KeyTransfer\KeyTransferException
   *   When export fails or cannot be performed.
   */
  public function exportKey(EncryptionKeyId $keyId): string;

  /**
   * Imports a portable package string & optionally activates the imported key.
   *
   * @return array{key_id: \Drupal\easy_encryption\Encryption\EncryptionKeyId, activated: bool}
   *   The imported key id and whether activation was requested/performed.
   *
   * @throws \Drupal\easy_encryption\KeyTransfer\KeyTransferException
   *   When import fails or the package is invalid/unsupported.
   */
  public function importKey(string $package, bool $activate = FALSE): array;

}
