<?php

declare(strict_types=1);

namespace Drupal\easy_encryption_admin\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\easy_encryption\Sodium\PrivateKeyStorageMigrator;

/**
 * Access check for the private key storage migrator.
 *
 * @internal This class is not part of the module's public programming API.
 */
final class PrivateKeyStorageMigratorAccessCheck implements AccessInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\easy_encryption\Sodium\PrivateKeyStorageMigrator $migrator
   *   The private key storage migrator.
   */
  public function __construct(
    private readonly PrivateKeyStorageMigrator $migrator,
  ) {
  }

  /**
   * Checks access for the private key storage migrator page.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(AccountInterface $account): AccessResult {
    $is_migration_needed = $this->migrator->isMigrationNeeded();
    $migration_needed_check = AccessResult::allowedIf($is_migration_needed['result'])->addCacheableDependency($is_migration_needed['cacheability']);
    if ($migration_needed_check->isAllowed()) {
      return AccessResult::allowedIfHasPermission($account, 'administer easy encryption keys')->andIf($migration_needed_check);
    }

    return $migration_needed_check;
  }

}
