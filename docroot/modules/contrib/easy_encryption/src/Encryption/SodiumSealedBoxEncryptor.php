<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\Encryption;

use Drupal\easy_encryption\KeyManagement\Port\KeyRegistryInterface;
use Drupal\easy_encryption\Sodium\Exception\SodiumKeyPairNotFoundException;
use Drupal\easy_encryption\Sodium\Exception\SodiumKeyPairOperationException;
use Drupal\easy_encryption\Sodium\SodiumKeyPairReadRepositoryInterface;

/**
 * Encryptor implementation based on Sodium sealed box design.
 *
 * @internal This class is not part of the module's public programming API.
 */
final class SodiumSealedBoxEncryptor implements EncryptorInterface {

  public function __construct(
    private readonly KeyRegistryInterface $keyRegistry,
    private readonly SodiumKeyPairReadRepositoryInterface $keyPairRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function encrypt(#[\SensitiveParameter] string $value): EncryptedValue {
    if ($value === '') {
      throw new \InvalidArgumentException('Value must not be empty.');
    }

    $activeKeyId = $this->keyRegistry->getActiveKeyId()['result'];
    if ($activeKeyId === NULL) {
      throw new EncryptionException('No active encryption key is configured.');
    }

    try {
      $active_key = $this->keyPairRepository->getKeyPairById($activeKeyId->value);
    }
    catch (SodiumKeyPairNotFoundException $e) {
      throw new EncryptionException(sprintf('No Sodium key pair exists for the configured active key "%s".', $activeKeyId), 0, $e);
    }
    catch (SodiumKeyPairOperationException $e) {
      throw new EncryptionException(sprintf('Failed to load a Sodium key pair for the configured active key "%s".', $activeKeyId), 0, $e);
    }

    if (!$active_key->canEncrypt()) {
      throw new EncryptionException(sprintf(
        'Cannot encrypt: public key not available for key pair "%s".',
        $active_key->id
      ));
    }

    try {
      $ciphertext = sodium_crypto_box_seal($value, $active_key->publicKey);
    }
    catch (\SodiumException $e) {
      throw new EncryptionException(sprintf(
        'Encryption failed with key pair "%s".',
        $active_key->id
      ), 0, $e);
    }

    return new EncryptedValue($ciphertext, EncryptionKeyId::fromNormalized($active_key->id));
  }

  /**
   * {@inheritdoc}
   */
  public function decrypt(EncryptedValue $value): string {
    try {
      $key = $this->keyPairRepository->getKeyPairById($value->keyId->value);
    }
    catch (SodiumKeyPairNotFoundException $e) {
      throw new EncryptionException(sprintf(
        'Key pair "%s" not found.',
        $value->keyId->value
      ), 0, $e);
    }
    catch (SodiumKeyPairOperationException $e) {
      throw new EncryptionException('Failed to load key pair.', 0, $e);
    }

    if (!$key->canDecrypt()) {
      throw new EncryptionException(sprintf(
        'Cannot decrypt: private key not available for key pair "%s".',
        $key->id
      ));
    }

    // Store the keypair in a variable so we can zero it from memory after use.
    // While passing the return value directly to sodium_crypto_box_seal_open()
    // looks cleaner, PHP still allocates memory for that temporary value, but
    // without a variable reference we cannot call sodium_memzero() on it. The
    // keypair would remain in memory until PHP's garbage collector eventually
    // runs. Explicit variable assignment gives us the only handle to
    // immediately zero sensitive key material from memory in the finally block.
    $sodiumKeypair = $key->toSodiumKeypair();

    try {
      $result = sodium_crypto_box_seal_open($value->getCiphertext(), $sodiumKeypair);
    }
    catch (\SodiumException $e) {
      throw new EncryptionException(sprintf(
        'Decryption failed with key pair "%s".',
        $key->id
      ), 0, $e);
    }
    finally {
      try {
        sodium_memzero($sodiumKeypair);
      }
      catch (\SodiumException) {
        // Polyfill doesn't support memzero; silently continue.
      }
      unset($sodiumKeypair);
    }

    if ($result === FALSE) {
      throw new EncryptionException(sprintf(
        'Decryption failed with key pair "%s".',
        $key->id
      ));
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function selfTest(): void {
    $activeKeyId = $this->keyRegistry->getActiveKeyId()['result'];
    if ($activeKeyId === NULL) {
      throw new EncryptionException('Self-test failed: No active encryption key is configured.');
    }

    try {
      $activeKey = $this->keyPairRepository->getKeyPairById($activeKeyId->value);
    }
    catch (SodiumKeyPairNotFoundException $e) {
      throw new EncryptionException(sprintf('Self-test failed: No Sodium key pair exists for the configured active key "%s".', $activeKeyId), 0, $e);
    }
    catch (SodiumKeyPairOperationException $e) {
      throw new EncryptionException(sprintf('Self-test failed: Failed to load a Sodium key pair for the configured active key "%s".', $activeKeyId), 0, $e);
    }

    if (!$activeKey->canDecrypt()) {
      // Self-test is skipped in encrypt-only environments.
      return;
    }

    $plaintext = 'sodium-self-test-' . bin2hex(random_bytes(8));

    try {
      $encrypted = $this->encrypt($plaintext);
      $decrypted = $this->decrypt($encrypted);
    }
    catch (EncryptionException $e) {
      throw new EncryptionException('Self-test failed during encrypt/decrypt cycle.', 0, $e);
    }

    if (!hash_equals($plaintext, $decrypted)) {
      throw new EncryptionException('Self-test failed: encryption/decryption mismatch.');
    }
  }

}
