<?php

namespace Drupal\altcha_obfuscate\Utility;

/**
 * Utility class to provide encryption functions.
 */
class ObfuscationUtility {

  /**
   * Encrypt data according to the provided ALTCHA encryption guidelines.
   *
   * @param string $data
   *   The data to obfuscate.
   * @param string $key
   *   An optional encryption key.
   * @param int|null $max_number
   *   The max number, which indicates the complexity of the challenge.
   *
   * @see https://github.com/altcha-org/altcha/blob/main/scripts/obfuscate.ts
   * @see https://altcha.org/docs/obfuscation
   *
   * @return string
   *   A base64 encoded string.
   */
  public static function encrypt(string $data, string $key = '', ?int $max_number = NULL): string {
    $iv = static::numberToUint8Array(static::getRandomNumber($max_number));
    $key_hash = hash('sha256', $key, TRUE);
    $encrypted = openssl_encrypt($data, 'aes-256-gcm', $key_hash, OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($encrypted . $tag);
  }

  /**
   * Get a random number.
   *
   * @param int|null $max
   *   An optional maximum number.
   *
   * @return int
   *   A random number between 10 and the given maximum.
   */
  protected static function getRandomNumber(?int $max = NULL): int {
    return random_int(1, $max ?? 10000);
  }

  /**
   * Convert integer to a fixed length binary string.
   *
   * @return string
   *   A fixed length binary string.
   */
  protected static function numberToUint8Array(int $num, int $len = 12): string {
    $result = str_repeat("\0", $len);
    for ($i = 0; $i < $len; $i++) {
      $result[$i] = chr($num % 256);
      $num = intdiv($num, 256);
    }
    return $result;
  }

}
