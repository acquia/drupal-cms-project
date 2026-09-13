<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\easy_encryption\KeyManagement\Adapter\ConfigKeyRegistry;
use Drupal\easy_encryption\KeyManagement\KeyPrunerException;
use Drupal\easy_encryption\KeyManagement\KeyPrunerInterface;
use Drupal\easy_encryption\KeyManagement\Port\MutableKeyRegistryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * System hook implementations.
 *
 * @internal
 *   This is an internal part of Easy Encrypt and may be changed or removed at
 *   any time without warning. External code should not interact with
 *   this class.
 */
final class SystemHooks {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly KeyPrunerInterface $keyPruner,
    #[Autowire('@logger.channel.easy_encryption')]
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Implements hook_module_preuninstall().
   */
  #[Hook('module_preuninstall')]
  public function modulePreUninstall(string $module, bool $is_syncing): void {
    if ($module === 'easy_encryption') {
      if ($is_syncing) {
        return;
      }

      // @phpstan-ignore-next-line
      if (!\Drupal::service(MutableKeyRegistryInterface::class) instanceof ConfigKeyRegistry) {
        return;
      }

      // Uninstall-only teardown: clear the active key id directly in
      // configuration,
      // We intentionally bypass the key registry service here because
      // uninstall runs very late in the lifecycle and the registry may be
      // decorated or depend on services that are no longer reliable at this
      // point. Writing to config directly also avoids triggering
      // registry-level side effects (for example observers that
      // would treat this as a normal “key activation” workflow).
      // The active key normally cannot be deleted by design. During uninstall
      // we clear it first so the pruner can delete all remaining key material.
      $this->configFactory->getEditable(ConfigKeyRegistry::CONFIG_NAME)
        ->set('active_encryption_key_id', '')
        ->save(TRUE);

      try {
        $result = $this->keyPruner->pruneUnused();

        $this->logger->info(
          'Deleted @count key pair(s) during module uninstall.',
          ['@count' => count($result->deleted)]
        );

        if ($result->failed !== []) {
          $this->logger->info(
            '@count key pair(s) could not be deleted during module uninstall.',
            ['@count' => count($result->failed)]
          );
        }
      }
      catch (KeyPrunerException $e) {
        $this->logger->error(
          'Failed to clean up key pairs during uninstall: @message',
          ['@message' => $e->getMessage()]
        );
      }
    }
  }

}
