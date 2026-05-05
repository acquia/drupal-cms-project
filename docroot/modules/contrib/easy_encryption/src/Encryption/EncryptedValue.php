<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\Encryption;

/**
 * Immutable value object representing encrypted data with key metadata.
 *
 * The ciphertext is stored internally as raw binary data. Use
 * getCiphertextHex() for storage in configuration, databases, or files that
 * expect string data.
 *
 * @immutable
 */
final class EncryptedValue {

  public function __construct(
    private readonly string $ciphertext,
    public readonly EncryptionKeyId $keyId,
  ) {
    if (!$this->isBinaryString($ciphertext)) {
      throw new \InvalidArgumentException('Ciphertext must not be empty.');
    }
  }

  /**
   * Checks whether an input string is binary or not.
   */
  private function isBinaryString(string $str): bool {
    if ($str === '') {
      return FALSE;
    }

    $sampleSize = 8192;
    $sample = strlen($str) > $sampleSize ? substr($str, 0, $sampleSize) : $str;

    if (!mb_check_encoding($sample, 'UTF-8') || str_contains($sample, "\0")) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Returns the raw binary ciphertext.
   *
   * @return string
   *   The binary ciphertext.
   */
  public function getCiphertext(): string {
    return $this->ciphertext;
  }

  /**
   * Returns hex-encoded ciphertext for storage.
   *
   * Use this method when persisting encrypted data to configuration, databases,
   * or files. To reconstruct the object from stored hex, use
   * EncryptedValue::fromHex().
   *
   * @return string
   *   The hex-encoded ciphertext.
   */
  public function getCiphertextHex(): string {
    try {
      return sodium_bin2hex($this->ciphertext);
    }
    catch (\SodiumException $e) {
      // Should not happen after the check inside the constructor.
      throw new \LogicException(sprintf('Ciphertext could not be encrypted. %s', $e->getMessage()));
    }
  }

  /**
   * Creates an EncryptedValue from hex-encoded ciphertext.
   *
   * Use this factory method when reading encrypted data from storage that was
   * previously encoded with getCiphertextHex().
   *
   * @param string $hex
   *   The hex-encoded ciphertext.
   * @param string $keyId
   *   The key pair identifier.
   *
   * @return static
   *   A new EncryptedValue instance.
   *
   * @throws \InvalidArgumentException
   *   If the ciphertext is empty or invalid, or the key ID is empty.
   */
  public static function fromHex(string $hex, string $keyId): self {
    try {
      return new self(sodium_hex2bin($hex), EncryptionKeyId::fromNormalized($keyId));
    }
    catch (\SodiumException $e) {
      throw new \InvalidArgumentException($e->getMessage());
    }
  }

}
