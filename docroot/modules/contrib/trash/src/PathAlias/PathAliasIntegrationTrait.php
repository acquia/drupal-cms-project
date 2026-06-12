<?php

declare(strict_types=1);

namespace Drupal\trash\PathAlias;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\path\Plugin\Field\FieldType\PathFieldItemList;
use Drupal\pathauto\PathautoFieldItemList;
use Drupal\pathauto\PathautoState;

/**
 * Provides path alias integration for trash handlers.
 */
trait PathAliasIntegrationTrait {

  /**
   * Whether path aliases should be handled for the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if path aliases should be handled, FALSE otherwise.
   */
  protected function shouldHandlePathAliases(EntityInterface $entity): bool {
    if ($entity->getEntityTypeId() === 'path_alias' || !$this->trashManager->isEntityTypeEnabled('path_alias')) {
      return FALSE;
    }

    // Only entity types with a "canonical" or "edit-form" link template may
    // support path aliases.
    $entity_type = $entity->getEntityType();
    return $entity_type->hasLinkTemplate('canonical') || $entity_type->hasLinkTemplate('edit-form');
  }

  /**
   * Automatically deletes associated path aliases when entity is trashed.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being trashed.
   */
  protected function deleteAssociatedPathAliases(EntityInterface $entity): void {
    if (!$this->shouldHandlePathAliases($entity)) {
      return;
    }

    // Loop through all fields and call delete() if needed.
    assert($entity instanceof FieldableEntityInterface);
    foreach ($entity->getFields() as $field) {
      if ($field instanceof PathFieldItemList) {
        $field->delete();
      }
    }
  }

  /**
   * Automatically restores associated path aliases when an entity is restored.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being restored.
   * @param int|string $deleted_timestamp
   *   The timestamp when the entity was deleted.
   */
  protected function restoreAssociatedPathAliases(EntityInterface $entity, int|string $deleted_timestamp): void {
    if (!$this->shouldHandlePathAliases($entity)) {
      return;
    }

    // Find path aliases deleted at the exact same time.
    $storage = $this->entityTypeManager->getStorage('path_alias');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('deleted', $deleted_timestamp)
      ->condition('path', '/' . $entity->toUrl()->getInternalPath())
      ->execute();

    if (!empty($ids)) {
      $path_aliases = $this->trashManager->executeInTrashContext('ignore', function () use ($storage, $ids) {
        return $storage->loadMultiple($ids);
      });
      $storage->restoreFromTrash($path_aliases);
    }
  }

  /**
   * Ensure that pathauto doesn't act during trash operations.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being trashed or restored.
   */
  protected function skipPathauto(EntityInterface $entity): void {
    if (!$this->shouldHandlePathAliases($entity)) {
      return;
    }

    assert($entity instanceof FieldableEntityInterface);
    foreach ($entity->getFields() as $field) {
      if ($field instanceof PathautoFieldItemList) {
        $field->first()->set('pathauto', PathautoState::SKIP);
      }
    }
  }

}
