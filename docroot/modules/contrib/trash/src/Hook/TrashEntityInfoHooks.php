<?php

declare(strict_types=1);

namespace Drupal\trash\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\trash\EntityHandler\TrashNodeAccessControlHandler;
use Drupal\trash\Form\EntityPurgeForm;
use Drupal\trash\Form\EntityPurgeMultipleForm;
use Drupal\trash\Form\EntityRestoreForm;
use Drupal\trash\Form\EntityRestoreMultipleForm;
use Drupal\trash\TrashManagerInterface;

/**
 * Entity info hook implementations for Trash.
 */
class TrashEntityInfoHooks {

  use StringTranslationTrait;

  public function __construct(
    protected TrashManagerInterface $trashManager,
  ) {}

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    $base_field_definitions = [];

    // Add the 'deleted' base field.
    if ($this->trashManager->isEntityTypeEnabled($entity_type)) {
      $base_field_definitions['deleted'] = BaseFieldDefinition::create('timestamp')
        ->setLabel($this->t('Deleted'))
        ->setDescription($this->t('Time when the item got deleted.'))
        ->setInternal(TRUE)
        ->setTranslatable(FALSE)
        ->setRevisionable(TRUE);
    }

    return $base_field_definitions;
  }

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
    foreach ($entity_types as $entity_type_id => $entity_type) {
      if ($this->trashManager->isEntityTypeEnabled($entity_type)) {
        if (!$entity_type->getFormClass('restore')) {
          $entity_type->setFormClass('restore', EntityRestoreForm::class);
        }
        if (!$entity_type->getFormClass('purge')) {
          $entity_type->setFormClass('purge', EntityPurgeForm::class);
        }
        if (!$entity_type->getFormClass('restore-multiple-confirm')) {
          $entity_type->setFormClass('restore-multiple-confirm', EntityRestoreMultipleForm::class);
        }
        if (!$entity_type->getFormClass('purge-multiple-confirm')) {
          $entity_type->setFormClass('purge-multiple-confirm', EntityPurgeMultipleForm::class);
        }

        // Provide link templates for the 'restore' and 'purge' routes.
        if ($entity_type->hasLinkTemplate('canonical')) {
          $base_path = $entity_type->getLinkTemplate('canonical');
        }
        else {
          $base_path = "/admin/content/trash/$entity_type_id/{" . $entity_type_id . '}';
        }
        $entity_type->setLinkTemplate('restore', $base_path . '/restore');
        $entity_type->setLinkTemplate('purge', $base_path . '/purge');
        $entity_type->setLinkTemplate('restore-multiple-form', "/admin/content/trash/$entity_type_id/restore");
        $entity_type->setLinkTemplate('purge-multiple-form', "/admin/content/trash/$entity_type_id/purge");
      }

      // Override node's access control handler, so we can skip the
      // 'bypass node access' permission check if the node is deleted.
      if ($entity_type->id() === 'node') {
        $entity_type->setHandlerClass('access', TrashNodeAccessControlHandler::class);
      }
    }
  }

}
