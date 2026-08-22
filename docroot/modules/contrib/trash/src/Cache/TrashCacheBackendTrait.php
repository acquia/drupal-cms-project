<?php

declare(strict_types=1);

namespace Drupal\trash\Cache;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Provides cache backend decorator methods for filtering deleted entities.
 */
trait TrashCacheBackendTrait {

  /**
   * {@inheritdoc}
   */
  public function get($cid, $allow_invalid = FALSE) {
    return $this->inner->get($cid, $allow_invalid);
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(&$cids, $allow_invalid = FALSE) {
    return $this->inner->getMultiple($cids, $allow_invalid);
  }

  /**
   * {@inheritdoc}
   */
  public function set($cid, $data, $expire = CacheBackendInterface::CACHE_PERMANENT, array $tags = []) {
    if ($data instanceof FieldableEntityInterface && $this->entityIsDeleted($data)) {
      return;
    }
    $this->inner->set($cid, $data, $expire, $tags);
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $items) {
    foreach ($items as $cid => $item) {
      if (isset($item['data'])
          && $item['data'] instanceof FieldableEntityInterface
          && $this->entityIsDeleted($item['data'])) {
        unset($items[$cid]);
      }
    }
    if ($items) {
      $this->inner->setMultiple($items);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete($cid) {
    $this->inner->delete($cid);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $cids) {
    $this->inner->deleteMultiple($cids);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    $this->inner->deleteAll();
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate($cid) {
    $this->inner->invalidate($cid);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateMultiple(array $cids) {
    $this->inner->invalidateMultiple($cids);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateAll() {
    if (method_exists($this->inner, 'invalidateAll')) {
      $this->inner->invalidateAll();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection() {
    $this->inner->garbageCollection();
  }

  /**
   * {@inheritdoc}
   */
  public function removeBin() {
    $this->inner->removeBin();
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateTags(array $tags) {
    if ($this->inner instanceof CacheTagsInvalidatorInterface) {
      $this->inner->invalidateTags($tags);
    }
  }

  /**
   * Reset statically cached variables.
   */
  public function reset(...$args) {
    if (method_exists($this->inner, 'reset')) {
      return $this->inner->reset(...$args);
    }
  }

  /**
   * Duplicates trash_entity_is_deleted() to help during module installation.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   An entity object.
   *
   * @return bool
   *   TRUE if the entity is deleted, FALSE otherwise.
   */
  protected function entityIsDeleted(FieldableEntityInterface $entity): bool {
    return $entity->getFieldDefinition('deleted')?->getFieldStorageDefinition()->getProvider() === 'trash'
      && !$entity->get('deleted')->isEmpty();
  }

}
