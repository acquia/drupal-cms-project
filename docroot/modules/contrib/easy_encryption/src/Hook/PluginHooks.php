<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\easy_encryption\Plugin\KeyProvider\SitePrivateKeyFileKeyProviderDecorator;

/**
 * Plugin hook implementations.
 *
 * @internal
 *   This is an internal part of Easy Encrypt and may be changed or removed at
 *   any time without warning. External code should not interact with
 *   this class.
 */
final class PluginHooks {

  /**
 * Implements hook_key_provider_info_alter().
 */
  #[Hook('key_provider_info_alter')]
  public function keyProviderInfoAlter(array &$definitions): void {
    $definitions['file']['class'] = SitePrivateKeyFileKeyProviderDecorator::class;
  }

}
