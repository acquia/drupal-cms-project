<?php

declare(strict_types=1);

namespace Drupal\acquia_id\Helper;

/**
 * Helper for determining Acquia environment URLs based on UUID presence.
 */
final class AcquiaEnvironmentUrls {

  /**
   * Returns the correct Acquia ID URL based on UUID presence in prod.
   *
   * @param string $uuid
   *   The application or environment UUID.
   * @param callable $cloudApiChecker
   *   A callable that checks if the UUID exists in prod (returns bool).
   *
   * @return string
   *   The Acquia ID URL (prod or staging).
   */
  public static function getIdpUrl(string $uuid, callable $cloudApiChecker): string {
    if ($cloudApiChecker($uuid)) {
      return 'https://id.acquia.com';
    }
    return 'https://staging.id.acquia.com';
  }

  /**
   * Returns the correct Acquia Cloud URL based on UUID presence in prod.
   *
   * @param string $uuid
   *   The application or environment UUID.
   * @param callable $cloudApiChecker
   *   A callable that checks if the UUID exists in prod (returns bool).
   *
   * @return string
   *   The Acquia Cloud URL (prod or staging).
   */
  public static function getCloudUrl(string $uuid, callable $cloudApiChecker): string {
    if ($cloudApiChecker($uuid)) {
      return 'https://cloud.acquia.com';
    }
    return 'https://staging.cloud.acquia.com';
  }

}
