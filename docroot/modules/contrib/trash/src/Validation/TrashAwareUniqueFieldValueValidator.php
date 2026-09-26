<?php

declare(strict_types=1);

namespace Drupal\trash\Validation;

use Drupal\Core\Validation\Plugin\Validation\Constraint\UniqueFieldValueValidator;
use Drupal\trash\TrashManagerInterface;
use Symfony\Component\Validator\Constraint;

/**
 * Extends UniqueFieldValueValidator to run in 'ignore' trash context.
 *
 * This ensures that unique field validation considers all entities, including
 * those in the trash, preventing SQL constraint violations when creating
 * entities with values that conflict with trashed content.
 */
class TrashAwareUniqueFieldValueValidator extends UniqueFieldValueValidator {

  /**
   * The trash manager.
   */
  protected TrashManagerInterface $trashManager;

  /**
   * Sets the trash manager.
   */
  public function setTrashManager(TrashManagerInterface $trashManager): void {
    $this->trashManager = $trashManager;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    $this->trashManager->executeInTrashContext('ignore', function () use ($value, $constraint): void {
      parent::validate($value, $constraint);
    });
  }

}
