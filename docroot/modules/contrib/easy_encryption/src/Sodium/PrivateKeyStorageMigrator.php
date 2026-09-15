<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\Sodium;

use Drupal\easy_encryption\KeyManagement\Port\KeyRegistryInterface;
use Psr\Log\LoggerInterface;

/**
 * Service to handle the migration of the private key from state to filesystem.
 *
 * @internal This class is not part of the module's public programming API.
 */
final class PrivateKeyStorageMigrator {

  /**
   * Constructor.
   *
   * @param \Drupal\easy_encryption\KeyManagement\Port\KeyRegistryInterface $keyRegistry
   *   The key registry.
   * @param \Drupal\easy_encryption\Sodium\SodiumKeyPairRepositoryUsingKeyEntities $repository
   *   The repository.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    private readonly KeyRegistryInterface $keyRegistry,
    private readonly SodiumKeyPairRepositoryUsingKeyEntities $repository,
    private readonly LoggerInterface $logger,
  ) {
  }

  /**
   * Checks if a migration is needed.
   *
   * @return array{result: bool, cacheability: \Drupal\Core\Cache\CacheableDependencyInterface}
   *   TRUE if a migration is needed, FALSE otherwise; plus
   *   cacheability metadata.
   */
  public function isMigrationNeeded(): array {
    $result = $this->keyRegistry->getActiveKeyId();
    $activeKeyId = $result['result'];
    if (!$activeKeyId) {
      return ['result' => FALSE, 'cacheability' => $result['cacheability']];
    }
    return [
      'result' => $this->repository->isStateStorageUsed($activeKeyId->value),
      'cacheability' => $result['cacheability'],
    ];
  }

  /**
   * Migrates the private key from state to filesystem.
   *
   * @throws \Drupal\easy_encryption\Sodium\Exception\PrivateKeyMigrationException
   * @throws \Drupal\easy_encryption\Sodium\Exception\FilesystemPermissionException
   */
  public function migrate(): void {
    $activeKeyId = $this->keyRegistry->getActiveKeyId()['result'];
    if (!$activeKeyId) {
      throw new \LogicException('There is no active key to migrate.');
    }

    $this->repository->migrateFromStateToFilesystem($activeKeyId->value);

    $this->logger->notice('Private key for key ID @key_id was successfully migrated from database to filesystem.', ['@key_id' => $activeKeyId->value]);
  }

}
