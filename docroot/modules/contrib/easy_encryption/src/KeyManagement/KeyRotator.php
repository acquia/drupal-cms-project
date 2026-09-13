<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\easy_encryption\Encryption\EncryptedValue;
use Drupal\easy_encryption\Encryption\EncryptionException;
use Drupal\easy_encryption\Encryption\EncryptionKeyId;
use Drupal\easy_encryption\Encryption\EncryptorInterface;
use Drupal\easy_encryption\KeyManagement\Port\KeyRegistryInterface;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Default application service for key rotation.
 *
 * This service generates a new encryption key, activates it for subsequent
 * encryption operations, and can optionally re-encrypt existing Key entities
 * that use the easy_encrypted provider.
 *
 * Re-encryption is only possible when the private key is available in the
 * current environment.
 *
 * @internal This class is not part of the module's public programming API.
 */
final class KeyRotator implements KeyRotatorInterface {

  public function __construct(
    private readonly KeyGeneratorInterface $keyGenerator,
    private readonly KeyActivatorInterface $keyActivator,
    private readonly EncryptorInterface $encryptor,
    private readonly KeyRegistryInterface $keyRegistry,
    private readonly KeyRepositoryInterface $keyRepository,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function plan(bool $includeReencryptCounts = TRUE): KeyRotationPlan {
    try {
      $active_id = $this->keyRegistry->getActiveKeyId()['result'];

      if (!$includeReencryptCounts) {
        return new KeyRotationPlan(activeKeyId: $active_id);
      }

      if ($active_id === NULL) {
        // Active key is missing: cannot compute "toUpdate", but can still
        // report how many keys are configured and how many are broken.
        [$total, $toSkip] = $this->countEasyEncryptedKeysAndBrokenOnes();
        return new KeyRotationPlan(
          activeKeyId: NULL,
          total: $total,
          toUpdate: 0,
          toSkip: $toSkip,
        );
      }

      [$total, $toUpdate, $toSkip] = $this->computeReencryptPlan($active_id);

      return new KeyRotationPlan(
        activeKeyId: $active_id,
        total: $total,
        toUpdate: $toUpdate,
        toSkip: $toSkip,
      );
    }
    catch (\Throwable $e) {
      throw KeyRotationException::planFailed($e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function rotate(KeyRotationOptions $options): KeyRotationResult {
    $old_active_id = $this->keyRegistry->getActiveKeyId()['result'];

    try {
      $new_active_id = $this->keyGenerator->generate();
      $this->keyActivator->activate($new_active_id);
    }
    catch (KeyGeneratorException | KeyActivatorException $e) {
      throw KeyRotationException::rotateFailed($e);
    }

    $this->logger->notice('Rotated active encryption key from {old} to {new}.', [
      'old' => $old_active_id->value ?? '(none)',
      'new' => $new_active_id->value,
    ]);

    if (!$options->reencryptKeys) {
      return new KeyRotationResult($old_active_id, $new_active_id);
    }

    $activeKeyId = $this->keyRegistry->getActiveKeyId()['result'];
    if ($activeKeyId === NULL) {
      throw new KeyRotationException('No active encryption key is configured.');
    }

    [$updated, $skipped, $failed] = $this->reencryptEasyEncryptedKeys();

    if ($failed > 0 && $options->failOnReencryptErrors) {
      throw KeyRotationException::reencryptFailed($failed);
    }

    return new KeyRotationResult(
      oldActiveKeyId: $old_active_id,
      newActiveKeyId: $new_active_id,
      updated: $updated,
      skipped: $skipped,
      failed: $failed,
    );
  }

  /**
   * Computes what would be re-encrypted without making any changes.
   *
   * @param \Drupal\easy_encryption\Encryption\EncryptionKeyId $activeKeyId
   *   The currently active encryption key ID.
   *
   * @return array{0:int,1:int,2:int}
   *   A tuple of (total, toUpdate, toSkip).
   */
  private function computeReencryptPlan(EncryptionKeyId $activeKeyId): array {
    $total = 0;
    $toUpdate = 0;
    $toSkip = 0;

    foreach ($this->keyRepository->getKeysByProvider('easy_encrypted') as $key) {
      $total++;

      $provider = $key->getKeyProvider();
      $config = $provider?->getConfiguration() ?? [];

      if (empty($config['value']) || empty($config['encryption_key_id'])) {
        $toSkip++;
        continue;
      }

      if ($config['encryption_key_id'] !== $activeKeyId->value) {
        $toUpdate++;
      }
    }

    return [$total, $toUpdate, $toSkip];
  }

  /**
   * Counts Key entities using the Easy Encrypted provider and the broken ones.
   *
   * @return array{0:int,1:int}
   *   A tuple of (total, toSkip).
   */
  private function countEasyEncryptedKeysAndBrokenOnes(): array {
    $total = 0;
    $toSkip = 0;

    foreach ($this->keyRepository->getKeysByProvider('easy_encrypted') as $key) {
      $total++;

      $provider = $key->getKeyProvider();
      $config = $provider?->getConfiguration() ?? [];

      if (empty($config['value']) || empty($config['encryption_key_id'])) {
        $toSkip++;
      }
    }

    return [$total, $toSkip];
  }

  /**
   * Re-encrypts all Key entities using the Easy Encrypted key provider.
   *
   * @return array{0:int,1:int,2:int}
   *   A tuple of (updated, skipped, failed).
   */
  private function reencryptEasyEncryptedKeys(): array {
    $updated = 0;
    $skipped = 0;
    $failed = 0;

    foreach ($this->keyRepository->getKeysByProvider('easy_encrypted') as $key) {
      try {
        $did_update = $this->reencryptOneKey($key);
        if ($did_update) {
          $updated++;
        }
        else {
          $skipped++;
        }
      }
      catch (EncryptionException $e) {
        $failed++;
        $this->logger->error('Re-encryption failed for Key entity {id}: {message}', [
          'id' => $key->id(),
          'message' => $e->getMessage(),
        ]);
      }
      catch (\Throwable $e) {
        $failed++;
        $this->logger->error('Unexpected error re-encrypting Key entity {id}: {message}', [
          'id' => $key->id(),
          'message' => $e->getMessage(),
        ]);
      }
    }

    return [$updated, $skipped, $failed];
  }

  /**
   * Re-encrypts a single Key entity value.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The Key entity to re-encrypt.
   *
   * @return bool
   *   TRUE if the key was updated, FALSE if it was skipped.
   *
   * @throws \Drupal\easy_encryption\Encryption\EncryptionException
   *   Thrown when encryption or decryption fails.
   * @throws \InvalidArgumentException
   *   Thrown when the ciphertext cannot be decoded (for example, invalid hex).
   */
  private function reencryptOneKey(KeyInterface $key): bool {
    $provider = $key->getKeyProvider();
    if (!$provider || $provider->getPluginId() !== 'easy_encrypted') {
      return FALSE;
    }

    $config = $provider->getConfiguration();
    if (empty($config['value']) || empty($config['encryption_key_id'])) {
      $this->logger->warning('Skipping Key entity {id}: missing easy_encrypted configuration.', [
        'id' => $key->id(),
      ]);
      return FALSE;
    }

    $encrypted = EncryptedValue::fromHex($config['value'], $config['encryption_key_id']);
    $plaintext = $this->encryptor->decrypt($encrypted);
    $reencrypted = $this->encryptor->encrypt($plaintext);

    $provider->setConfiguration([
      'value' => $reencrypted->getCiphertextHex(),
      'encryption_key_id' => $reencrypted->keyId->value,
    ]);
    try {
      $key->save();
    }
    catch (EntityStorageException $e) {
      throw new EncryptionException(sprintf('Failed to save Key entity "%s": %s', $key->id(), $e->getMessage()), previous: $e);
    }

    return TRUE;
  }

}
