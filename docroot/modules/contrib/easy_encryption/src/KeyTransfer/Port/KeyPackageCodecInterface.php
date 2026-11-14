<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyTransfer\Port;

/**
 * Encodes/decodes key transfer packages (envelope only).
 *
 * The codec is format-agnostic: algorithm-specific data is carried in the
 * payload structure and interpreted by payload handlers.
 */
interface KeyPackageCodecInterface {

  /**
   * Encodes the package structure into a portable string.
   *
   * @param array<string,mixed> $data
   *   Package data, typically with keys like 'key_id' and 'payload'.
   *
   * @throws \Drupal\easy_encryption\KeyTransfer\Port\KeyPackageCodecException
   *   When the package cannot be encoded.
   */
  public function encode(array $data): string;

  /**
   * Decodes a portable string into the package structure.
   *
   * @return array<string,mixed>
   *   Package data.
   *
   * @throws \Drupal\easy_encryption\KeyTransfer\Port\KeyPackageCodecException
   *   When the package cannot be decoded.
   */
  public function decode(string $text): array;

}
