<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\Encryption;

/**
 * Value object representing an Encryption key identifier.
 *
 * Instances are immutable and always contain a safe, normalized identifier
 * consisting only of lowercase letters, digits, and underscores.
 *
 * This is a shared domain concept used by both key management (generation,
 * rotation, transfer) and encryption (encryptors/ciphertexts). It is not the
 * same as a Key module Key entity ID.
 */
final class EncryptionKeyId implements \Stringable {

  private const NORMALIZED_PATTERN = '/^[a-z0-9_]+$/';

  private function __construct(
    public readonly string $value,
  ) {}

  /**
   * Creates a EncryptionKeyId from arbitrary input by normalizing it.
   *
   * Normalization rules:
   * - lowercases
   * - replaces any run of characters not in [a-z0-9_] with a single underscore
   * - trims leading/trailing underscores.
   */
  public static function fromUserInput(string $keyId): self {
    $normalized = strtolower($keyId);
    $normalized = preg_replace('/[^a-z0-9_]+/', '_', $normalized) ?? '';
    $normalized = trim($normalized, '_');

    if ($normalized === '') {
      throw new \InvalidArgumentException('Encryption key id must not be empty after normalization.');
    }

    return self::fromNormalized($normalized);
  }

  /**
   * Creates a EncryptionKeyId from an already-normalized value.
   */
  public static function fromNormalized(string $keyId): self {
    if ($keyId === '') {
      throw new \InvalidArgumentException('Encryption key ID must not be empty.');
    }
    if (!preg_match(self::NORMALIZED_PATTERN, $keyId)) {
      throw new \InvalidArgumentException(sprintf(
        'Encryption key ID "%s" is invalid; expected only lowercase letters, numbers, and underscores.',
        $keyId
      ));
    }

    return new self($keyId);
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return $this->value;
  }

}
