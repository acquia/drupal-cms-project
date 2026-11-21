<?php

declare(strict_types=1);

namespace Drupal\trash\Hook\TrashHandler;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\redirect\Entity\Redirect;
use Drupal\trash\Exception\UnrestorableEntityException;
use Drupal\trash\Handler\DefaultTrashHandler;

/**
 * Provides a trash handler for the 'redirect' entity type.
 */
class RedirectTrashHandler extends DefaultTrashHandler {

  /**
   * Implements hook_ENTITY_TYPE_presave() for the 'redirect' entity type.
   */
  #[Hook('redirect_presave')]
  public function preSave(EntityInterface $entity): void {
    assert($entity instanceof Redirect);
    if (!$this->trashManager->isEntityTypeEnabled($entity->getEntityTypeId())) {
      return;
    }

    // Set a random hash value for deleted redirects in order to allow new ones
    // to be created with the same source URL.
    if (!$entity->get('deleted')->isEmpty()) {
      $entity->set('hash', 'deleted-' . $entity->uuid());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateRestore(EntityInterface $entity): void {
    $entity_key = $entity->getEntityTypeId() . ':' . $entity->id();
    $this->validatedEntities[$entity_key] = TRUE;

    assert($entity instanceof Redirect);
    $storage = $this->entityTypeManager->getStorage('redirect');

    // Execute the redirect's preSave() method directly to restore the original
    // hash value.
    $entity->preSave($storage);

    // Check if there's a non-deleted redirect with the same hash (source URL).
    $result = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('hash', $entity->get('hash')->value, '=')
      ->notExists('deleted')
      ->range(0, 1)
      ->execute();

    if ($result) {
      throw new UnrestorableEntityException((string) $this->t('There is an existing redirect with the same source URL.'));
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
