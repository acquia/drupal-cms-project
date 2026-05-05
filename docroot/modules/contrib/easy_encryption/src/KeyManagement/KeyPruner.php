<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement;

use Drupal\easy_encryption\KeyManagement\Port\KeyRegistryInterface;
use Drupal\easy_encryption\KeyManagement\Port\MutableKeyRegistryInterface;
use Psr\Log\LoggerInterface;

/**
 * Default application service for pruning unused encryption keys.
 *
 * An encryption key is considered unused when it is not the active key and
 * is not referenced by any Key entity using the easy_encrypted provider.
 *
 * The prune operation returns partial results: individual delete failures are
 * logged and returned, and do not abort the whole run.
 *
 * @internal This class is not part of the module's public programming API.
 */
final class KeyPruner implements KeyPrunerInterface {

  /**
   * Constructs a new object.
   *
   * @phpstan-param iterable<\Drupal\easy_encryption\KeyManagement\Observers\KeyDeletedObserverInterface> $observers
   */
  public function __construct(
    private readonly KeyUsageTrackerInterface $usageTracker,
    private readonly KeyRegistryInterface&MutableKeyRegistryInterface $keyRegistry,
    private readonly iterable $observers,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function planPruning(): KeyPrunePlan {
    try {
      $active = $this->keyRegistry->getActiveKeyId();
      $usage_mapping = $this->usageTracker->getKeyUsageMapping();

      $active_id = $active['result'];

      // Extract a unique list of referenced IDs.
      /** @var array<string, \Drupal\easy_encryption\Encryption\EncryptionKeyId> $referenced_ids */
      $referenced_ids = [];
      foreach ($usage_mapping['result'] as $mapping) {
        $referenced_ids[(string) $mapping->keyId] = $mapping->keyId;
      }

      $all_ids = $this->keyRegistry->listKnownKeyIds()['result'];
      $to_delete = [];
      foreach ($all_ids as $id) {
        if ($active_id !== NULL && $id->value === $active_id->value) {
          continue;
        }
        if (isset($referenced_ids[(string) $id])) {
          continue;
        }
        $to_delete[] = $id;
      }

      // KeyPrunePlan currently has no cacheability: that’s fine because pruning
      // is an operational action, not something we cache.
      return new KeyPrunePlan(
        activeKeyId: $active_id,
        toDelete: $to_delete,
        referenced: array_values($referenced_ids),
      );
    }
    catch (\Throwable $e) {
      throw KeyPrunerException::planFailed($e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function pruneUnused(): KeyPruneResult {
    try {
      $plan = $this->planPruning();
    }
    catch (\Throwable $e) {
      // If planning fails, pruning cannot proceed.
      throw KeyPrunerException::pruneFailed($e);
    }

    $deleted = [];
    $failed = [];

    foreach ($plan->toDelete as $id) {
      try {
        $this->keyRegistry->unregister($id);
        $deleted[] = $id;

        $this->logger->notice('Pruned unused encryption key {id}.', ['id' => $id->value]);

        try {
          foreach ($this->observers as $observer) {
            $observer->onKeyDeletion($id);
          }
        }
        catch (\Throwable) {
          // Do not allow secondary actions to fail the primary operation.
        }
      }
      catch (\Throwable $e) {
        $failed[] = $id;

        $this->logger->error('Failed pruning encryption key {id}: {message}', [
          'id' => $id->value,
          'message' => $e->getMessage(),
        ]);
      }
    }

    return new KeyPruneResult(deleted: $deleted, failed: $failed);
  }

}
