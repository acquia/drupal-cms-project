<?php

declare(strict_types=1);

namespace Drupal\trash\Hook\TrashHandler;

use Drupal\Core\Entity\EntityInterface;
use Drupal\path_alias\PathAliasInterface;
use Drupal\trash\Exception\UnrestorableEntityException;
use Drupal\trash\Handler\DefaultTrashHandler;

/**
 * Trash handler for path alias entities.
 */
class PathAliasTrashHandler extends DefaultTrashHandler {

  /**
   * {@inheritdoc}
   */
  public function validateRestore(EntityInterface $entity): void {
    $entity_key = $entity->getEntityTypeId() . ':' . $entity->id();
    $this->validatedEntities[$entity_key] = TRUE;

    assert($entity instanceof PathAliasInterface);

    // Check if there's a non-deleted path alias with the same alias.
    $result = $this->entityTypeManager->getStorage('path_alias')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('alias', $entity->getAlias(), '=')
      ->condition('langcode', $entity->language()->getId(), '=')
      ->notExists('deleted')
      ->range(0, 1)
      ->execute();

    if ($result) {
      throw new UnrestorableEntityException((string) $this->t('Cannot restore path alias: An alias with the path "@alias" already exists.', [
        '@alias' => $entity->getAlias(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preTrashRestore(EntityInterface $entity): void {
    $entity_key = $entity->getEntityTypeId() . ':' . $entity->id();

    // Only run validation if it hasn't been done already (e.g., by form
    // validation).
    if (empty($this->validatedEntities[$entity_key])) {
      $this->validateRestore($entity);
    }

    // Clear the validation flag for this entity.
    unset($this->validatedEntities[$entity_key]);
  }

}
