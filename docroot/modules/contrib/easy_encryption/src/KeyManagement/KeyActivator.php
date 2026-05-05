<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement;

use Drupal\easy_encryption\Encryption\EncryptionKeyId;
use Drupal\easy_encryption\KeyManagement\Port\KeyRegistryInterface;
use Drupal\easy_encryption\KeyManagement\Port\MutableKeyRegistryInterface;
use Psr\Log\LoggerInterface;

/**
 * Default key activator.
 *
 * @internal This class is not part of the module's public programming API.
 */
final class KeyActivator implements KeyActivatorInterface {

  /**
   * Constructs a new object.
   *
   * @phpstan-param iterable<\Drupal\easy_encryption\KeyManagement\Observers\KeyActivatedObserverInterface> $observers
   */
  public function __construct(
    private readonly KeyRegistryInterface&MutableKeyRegistryInterface $keyRegistry,
    private readonly iterable $observers,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function activate(EncryptionKeyId $keyId): void {
    $oldKeyId = $this->keyRegistry->getActiveKeyId()['result'];

    if ($oldKeyId && $oldKeyId->value === $keyId->value) {
      // NOOP.
      return;
    }

    $this->keyRegistry->setActive($keyId);

    $this->logger->notice('Activated encryption key {id}.', ['id' => $keyId->value]);

    try {
      foreach ($this->observers as $observer) {
        $observer->onKeyActivation($keyId, $oldKeyId);
      }
    }
    catch (\Throwable) {
      // Do not allow secondary actions to fail the primary operation.
    }
  }

}
