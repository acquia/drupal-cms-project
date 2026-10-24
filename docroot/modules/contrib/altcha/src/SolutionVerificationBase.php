<?php

namespace Drupal\altcha;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Lock\LockBackendInterface;

/**
 * Shared one-time consumption for verified ALTCHA solutions.
 */
abstract class SolutionVerificationBase implements SolutionVerificationInterface {

  /**
   * Lifetime for a solution without an expiry value.
   */
  const DEFAULT_REPLAY_TTL = 660;

  /**
   * Store of solution identifiers that have already been consumed.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   */
  protected KeyValueStoreExpirableInterface $consumedSolutions;

  /**
   * Prefix used to lock solution consumption.
   *
   * @var string
   */
  protected string $lockPrefix;

  /**
   * Constructs a solution verification service.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $key_value_expirable_factory
   *   The expirable key/value factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The shared lock backend.
   * @param string $collection
   *   The collection used to store consumed solutions.
   * @param string $lock_prefix
   *   The prefix for a solution-consumption lock name.
   */
  public function __construct(
    KeyValueExpirableFactoryInterface $key_value_expirable_factory,
    protected TimeInterface $time,
    protected LockBackendInterface $lock,
    string $collection,
    string $lock_prefix,
  ) {
    $this->consumedSolutions = $key_value_expirable_factory->get($collection);
    $this->lockPrefix = $lock_prefix;
  }

  /**
   * Consumes a verified solution, rejecting subsequent reuse.
   *
   * @param string $solution_id
   *   The stable identifier for the verified solution.
   * @param mixed $expires_at
   *   The solution expiry timestamp.
   *
   * @return bool
   *   TRUE if this solution had not been consumed before, FALSE otherwise.
   */
  protected function consumeSolution(string $solution_id, mixed $expires_at): bool {
    if ($solution_id === '') {
      return FALSE;
    }

    $key = hash('sha256', $solution_id);
    $lock_name = $this->lockPrefix . $key;
    // Competing requests must not both observe an unconsumed solution.
    if (!$this->lock->acquire($lock_name)) {
      return FALSE;
    }

    try {
      if ($this->consumedSolutions->has($key)) {
        return FALSE;
      }

      // Keep the record longer than the solution expiration
      // time so that it cannot be replayed.
      $ttl = is_numeric($expires_at) ? max((int) $expires_at - $this->time->getRequestTime(), 0) + 60 : static::DEFAULT_REPLAY_TTL;
      $this->consumedSolutions->setWithExpire($key, TRUE, $ttl);
      return TRUE;
    }
    finally {
      $this->lock->release($lock_name);
    }
  }

}
