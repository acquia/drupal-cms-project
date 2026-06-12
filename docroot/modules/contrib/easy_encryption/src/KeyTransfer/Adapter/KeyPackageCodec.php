<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyTransfer\Adapter;

use Drupal\easy_encryption\KeyTransfer\Port\KeyPackageCodecException;
use Drupal\easy_encryption\KeyTransfer\Port\KeyPackageCodecInterface;

/**
 * Default package codec (header + base64(json) + footer).
 *
 * @internal This class is not part of the module's public programming API.
 */
final class KeyPackageCodec implements KeyPackageCodecInterface {

  private const string HEADER = 'EASY_ENCRYPTION_KEY_EXPORT_V1';
  private const string FOOTER = 'END_EASY_ENCRYPTION_KEY_EXPORT_V1';

  /**
   * {@inheritdoc}
   */
  public function encode(array $data): string {
    try {
      $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
      $b64 = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
      return self::HEADER . "\n" . $b64 . "\n" . self::FOOTER . "\n";
    }
    catch (\Throwable $e) {
      throw KeyPackageCodecException::encodeFailed($e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function decode(string $text): array {
    $text = trim($text);

    $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];
    if (count($lines) < 3) {
      throw KeyPackageCodecException::decodeFailed('The input string does not contain enough lines for a valid key package (expected at least 3).');
    }

    $firstLine = trim($lines[0]);
    $lastLine = trim(end($lines));

    if ($firstLine !== self::HEADER) {
      throw KeyPackageCodecException::decodeFailed('Invalid package header. Expected "' . self::HEADER . '", got "' . $firstLine . '".');
    }
    if ($lastLine !== self::FOOTER) {
      throw KeyPackageCodecException::decodeFailed('Invalid package footer. Expected "' . self::FOOTER . '", got "' . $lastLine . '".');
    }

    $b64 = trim($lines[1]);
    $json = base64_decode(strtr($b64, '-_', '+/'), TRUE);
    if ($json === FALSE) {
      throw KeyPackageCodecException::decodeFailed('Invalid base64 payload.');
    }

    try {
      $data = json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\Throwable $e) {
      throw KeyPackageCodecException::decodeFailed('Invalid JSON payload.', $e);
    }

    if (!is_array($data)) {
      throw KeyPackageCodecException::decodeFailed('JSON payload is not an object.');
    }

    return $data;
  }

}
