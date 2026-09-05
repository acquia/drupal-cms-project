<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\Sodium;

use Drupal\easy_encryption\Sodium\Exception\SodiumKeyPairOperationException;

/**
 * Immutable value object representing a libsodium keypair.
 *
 * @immutable
 */
final class SodiumKeyPair {

  public function __construct(
    public readonly string $id,
    public readonly ?string $publicKey = NULL,
    #[\SensitiveParameter] private readonly ?string $privateKey = NULL,
  ) {
    if (empty($this->id)) {
      throw new \InvalidArgumentException('The id cannot be empty.');
    }
    if ($this->publicKey !== NULL && strlen($this->publicKey) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
      throw new \InvalidArgumentException('Invalid public key length.');
    }
    if ($this->privateKey !== NULL && strlen($this->privateKey) !== SODIUM_CRYPTO_BOX_SECRETKEYBYTES) {
      throw new \InvalidArgumentException('Invalid private key length.');
    }

    if ($this->publicKey === NULL && $this->privateKey === NULL) {
      throw new \InvalidArgumentException('Empty key pair with no public or private key.');
    }
  }

  /**
   * Returns TRUE if this keypair has the private key available.
   *
   * @phpstan-assert-if-true !null $this->privateKey
   */
  public function canDecrypt(): bool {
    return $this->privateKey !== NULL;
  }

  /**
   * Returns TRUE if this keypair has the public key available.
   *
   * @phpstan-assert-if-true !null $this->publicKey
   */
  public function canEncrypt(): bool {
    return $this->publicKey !== NULL;
  }

  /**
   * Returns the keypair string used by Sodium for sealed box operations.
   *
   * The caller is responsible for zeroing out the keypair from memory after
   * use by calling sodium_memzero() on the returned value.
   *
   * @return string
   *   The combined keypair for Sodium operations.
   *
   * @throws \Drupal\easy_encryption\Sodium\Exception\SodiumKeyPairOperationException
   *   If either the private key or public key is not available, or if keypair
   *   construction fails.
   *
   * @internal
   *   For use by the encryption service layer only.
   */
  public function toSodiumKeypair(): string {
    if (!$this->canDecrypt()) {
      throw SodiumKeyPairOperationException::privateKeyNotAvailable($this->id);
    }

    if (!$this->canEncrypt()) {
      throw SodiumKeyPairOperationException::publicKeyNotAvailable($this->id);
    }

    try {
      return sodium_crypto_box_keypair_from_secretkey_and_publickey(
        $this->privateKey,
        $this->publicKey
      );
    }
    catch (\SodiumException $e) {
      throw SodiumKeyPairOperationException::keypairConstructionFailed($this->id, $e);
    }
  }

}
