<?php

declare(strict_types=1);

namespace Drupal\trash\MenuLinkContent;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides menu link content integration for trash handlers.
 *
 * When an entity is trashed, menu links pointing to it become broken: the
 * route access check cannot load the entity, producing an uncacheable
 * AccessResult (max-age=0) that poisons the entire menu block render cache.
 */
trait MenuLinkContentIntegrationTrait {

  /**
   * Whether menu link content entities should be handled for the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if menu link content entities should be handled, FALSE otherwise.
   */
  protected function shouldHandleMenuLinkContent(EntityInterface $entity): bool {
    return $entity->getEntityTypeId() !== 'menu_link_content'
      && $this->trashManager->isEntityTypeEnabled('menu_link_content');
  }

  /**
   * Soft-deletes menu link content entities pointing to the trashed entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being trashed.
   */
  protected function deleteAssociatedMenuLinkContent(EntityInterface $entity): void {
    if (!$this->shouldHandleMenuLinkContent($entity)) {
      return;
    }

    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    $menu_links = $storage->loadByProperties([
      'link.uri' => 'entity:' . $entity->getEntityTypeId() . '/' . $entity->id(),
    ]);

    if (!empty($menu_links)) {
      $storage->delete($menu_links);
    }
  }

  /**
   * Restores menu link content entities that were soft-deleted with the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being restored.
   * @param int|string $deleted_timestamp
   *   The timestamp when the entity was deleted.
   */
  protected function restoreAssociatedMenuLinkContent(EntityInterface $entity, int|string $deleted_timestamp): void {
    if (!$this->shouldHandleMenuLinkContent($entity)) {
      return;
    }

    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('deleted', $deleted_timestamp)
      ->condition('link.uri', 'entity:' . $entity->getEntityTypeId() . '/' . $entity->id())
      ->execute();

    if (!empty($ids)) {
      $menu_links = $this->trashManager->executeInTrashContext('ignore', function () use ($storage, $ids) {
        return $storage->loadMultiple($ids);
      });
      $storage->restoreFromTrash(array_values($menu_links));
    }
  }

}
