<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement\Observers;

use Drupal\easy_encryption\Encryption\EncryptionKeyId;

/**
 * Contract for encryption key deletion observers.
 */
interface KeyDeletedObserverInterface {

  /**
   * Act when an encryption key gets deleted.
   *
   * @param \Drupal\easy_encryption\Encryption\EncryptionKeyId $keyId
   *   The ID of the activated encryption key.
   */
  public function onKeyDeletion(EncryptionKeyId $keyId): void;

}
