<?php

declare(strict_types=1);

namespace Drupal\easy_encryption\KeyManagement;

/**
 * Application service for pruning unused encryption keys.
 */
interface KeyPrunerInterface {

  /**
   * Builds a non-mutating plan describing what would be pruned.
   *
   * @throws \Drupal\easy_encryption\KeyManagement\KeyPrunerException
   *   Thrown when the plan cannot be computed.
   */
  public function planPruning(): KeyPrunePlan;

  /**
   * Deletes unused encryption keys.
   *
   * This operation returns partial results: failures are reported in the result
   * object rather than aborting the whole run.
   *
   * @return \Drupal\easy_encryption\KeyManagement\KeyPruneResult
   *   The prune result.
   *
   * @throws \Drupal\easy_encryption\KeyManagement\KeyPrunerException
   *   Thrown when the prune operation cannot start
   *   (for example, planning fails).
   */
  public function pruneUnused(): KeyPruneResult;

}
