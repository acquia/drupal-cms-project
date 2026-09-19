<?php

declare(strict_types=1);

namespace Drupal\trash\Handler;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\trash\Exception\UnrestorableEntityException;
use Drupal\trash\MenuLinkContent\MenuLinkContentIntegrationTrait;
use Drupal\trash\PathAlias\PathAliasIntegrationTrait;
use Drupal\trash\TrashManagerInterface;
use Drupal\trash\Validation\TrashAwareUniqueFieldValueValidator;

/**
 * Provides the default trash handler.
 */
class DefaultTrashHandler implements TrashHandlerInterface {

  use StringTranslationTrait;
  use MenuLinkContentIntegrationTrait;
  use PathAliasIntegrationTrait;

  /**
   * The ID of the entity type managed by this handler.
   */
  protected string $entityTypeId;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The trash manager.
   */
  protected TrashManagerInterface $trashManager;

  /**
   * Tracks whether validation has already been performed for an entity.
   *
   * This prevents duplicate validation when validateRestore() is called
   * explicitly (e.g., from form validation) before preTrashRestore().
   *
   * @var array<string, bool>
   */
  protected array $validatedEntities = [];

  /**
   * {@inheritdoc}
   */
  public function preTrashDelete(EntityInterface $entity): void {
    // Ensure that Pathauto doesn't try to auto-create aliases when deleting an
    // entity.
    $this->skipPathauto($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function postTrashDelete(EntityInterface $entity): void {
    // Automatically delete associated path aliases and custom menu links to
    // match core's behavior.
    $this->deleteAssociatedPathAliases($entity);
    $this->deleteAssociatedMenuLinkContent($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function validateRestore(EntityInterface $entity): void {
    $entity_key = $entity->getEntityTypeId() . ':' . $entity->id();
    $this->validatedEntities[$entity_key] = TRUE;

    // Run entity validation for fieldable entities to check for conflicts.
    if ($entity instanceof FieldableEntityInterface) {
      $violations = $entity->validate();

      // Find only violations from constraints validated by
      // TrashAwareUniqueFieldValueValidator. This catches any constraint whose
      // validatedBy() returns TrashAwareUniqueFieldValueValidator itself or a
      // parent class (e.g., UniqueFieldValueValidator, which is aliased to it
      // via the service container).
      foreach ($violations as $violation) {
        $validator_class = ltrim($violation->getConstraint()->validatedBy(), '\\');
        if (is_a(TrashAwareUniqueFieldValueValidator::class, $validator_class, TRUE)) {
          throw new UnrestorableEntityException((string) $violation->getMessage());
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preTrashRestore(EntityInterface $entity): void {
    // Ensure that Pathauto doesn't try to auto-create aliases when restoring an
    // entity.
    $this->skipPathauto($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function postTrashRestore(EntityInterface $entity, int|string $deleted_timestamp): void {
    // Automatically restore associated path aliases and content menu links.
    $this->restoreAssociatedPathAliases($entity, $deleted_timestamp);
    $this->restoreAssociatedMenuLinkContent($entity, $deleted_timestamp);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteFormAlter(array &$form, FormStateInterface $form_state, bool $multiple = FALSE): void {}

  /**
   * {@inheritdoc}
   */
  public function restoreFormAlter(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function purgeFormAlter(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function setEntityTypeId(string $entity_type_id): static {
    $this->entityTypeId = $entity_type_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager): static {
    $this->entityTypeManager = $entity_type_manager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setTrashManager(TrashManagerInterface $trash_manager): static {
    $this->trashManager = $trash_manager;
    return $this;
  }

}
