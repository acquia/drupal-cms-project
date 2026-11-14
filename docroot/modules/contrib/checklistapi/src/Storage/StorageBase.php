<?php

namespace Drupal\checklistapi\Storage;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Provides a base storage implementation for others to extend.
 */
abstract class StorageBase implements StorageInterface, CacheableDependencyInterface {

  /**
   * The checklist ID.
   *
   * @var string
   */
  private $checklistId;

  /**
   * Sets the checklist ID.
   *
   * @param string $id
   *   The checklist ID.
   *
   * @return self
   *   The storage object.
   */
  public function setChecklistId($id) {
    if (!is_string($id)) {
      throw new \InvalidArgumentException('A checklist ID must be a string.');
    }
    $this->checklistId = $id;
    return $this;
  }

  /**
   * Gets the checklist ID.
   *
   * @return string
   *   Returns the checklist ID.
   */
  protected function getChecklistId() {
    if (empty($this->checklistId)) {
      throw new \LogicException('You must set the checklist ID before accessing saved progress.');
    }
    return $this->checklistId;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return ['checklistapi:' . $this->getChecklistId()];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

}
