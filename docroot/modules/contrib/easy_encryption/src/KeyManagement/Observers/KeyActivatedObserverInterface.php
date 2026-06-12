<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement\Observers;

use Drupal\easy_encryption\Encryption\EncryptionKeyId;

/**
 * Contract for encryption key activation observers.
 */
interface KeyActivatedObserverInterface {

  /**
   * Act when an encryption key gets activated.
   *
   * @param \Drupal\easy_encryption\Encryption\EncryptionKeyId $activeKeyId
   *   The ID of the activated encryption key.
   * @param \Drupal\easy_encryption\Encryption\EncryptionKeyId|null $previousKeyId
   *   The ID of the previously active key if there was any.
   */
  public function onKeyActivation(EncryptionKeyId $activeKeyId, ?EncryptionKeyId $previousKeyId): void;

}
