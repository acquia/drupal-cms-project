<?php

namespace Drupal\checklistapi\Storage;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\State\StateInterface;

/**
 * Provides state-based checklist progress storage.
 */
class StateStorage extends StorageBase {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private $state;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  private CacheTagsInvalidatorInterface $cacheTagsInvalidator;

  /**
   * Constructs a class instance.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator.
   */
  public function __construct(StateInterface $state, CacheTagsInvalidatorInterface $cache_tags_invalidator) {
    $this->state = $state;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
  }

  /**
   * {@inheritdoc}
   */
  public function getSavedProgress() {
    return $this->state->get($this->stateKey());
  }

  /**
   * {@inheritdoc}
   */
  public function setSavedProgress(array $progress) {
    $this->state->set($this->stateKey(), $progress);
    $this->cacheTagsInvalidator->invalidateTags($this->getCacheTags());
  }

  /**
   * {@inheritdoc}
   */
  public function deleteSavedProgress() {
    $this->state->delete($this->stateKey());
    $this->cacheTagsInvalidator->invalidateTags($this->getCacheTags());
  }

  /**
   * Returns the state key.
   *
   * @return string
   *   The state key.
   */
  private function stateKey() {
    return 'checklistapi.progress.' . $this->getChecklistId();
  }

}
