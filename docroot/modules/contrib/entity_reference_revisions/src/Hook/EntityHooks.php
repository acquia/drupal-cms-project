<?php

namespace Drupal\entity_reference_revisions\Hook;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TranslatableRevisionableStorageInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldStorageConfigInterface;

/**
 * Entity hooks service for the entity_reference_revisions module.
 */
final class EntityHooks {

  /**
   * Constructor of the entity hooks service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FieldTypePluginManagerInterface $fieldTypeManager,
    protected QueueFactory $queueFactory,
    protected LanguageManagerInterface $languageManager,
    protected ?ContentTranslationManagerInterface $contentTranslationManager = NULL,
  ) {}

  /**
   * Implements hook_entity_delete() and hook_entity_revision_delete().
   *
   * Performs garbage collection for composite entities that were not removed
   * by EntityReferenceRevisionsItem.
   */
  #[Hook('entity_delete')]
  public function delete(EntityInterface $entity): void {
    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }
    foreach ($entity->getFieldDefinitions() as $field_name => $field_definition) {
      $field_class = $this->fieldTypeManager->getPluginClass($field_definition->getType());
      if ($field_class == EntityReferenceRevisionsItem::class || is_subclass_of($field_class, EntityReferenceRevisionsItem::class)) {
        $target_entity_type_id = $field_definition->getSetting('target_type');
        $target_entity_storage = $this->entityTypeManager->getStorage($target_entity_type_id);
        $target_entity_type = $target_entity_storage->getEntityType();

        $parent_type_field = $target_entity_type->get('entity_revision_parent_type_field');
        $parent_id_field = $target_entity_type->get('entity_revision_parent_id_field');
        $parent_name_field = $target_entity_type->get('entity_revision_parent_field_name_field');

        if ($parent_type_field && $parent_id_field && $parent_name_field) {
          $entity_ids = $target_entity_storage
            ->getQuery()
            ->allRevisions()
            ->condition($parent_type_field, $entity->getEntityTypeId())
            ->condition($parent_id_field, $entity->id())
            ->condition($parent_name_field, $field_name)
            ->accessCheck(FALSE)
            ->execute();

          if (empty($entity_ids)) {
            continue;
          }
          $entity_ids = array_unique($entity_ids);
          foreach ($entity_ids as $entity_id) {
            $this->queueFactory->get('entity_reference_revisions_orphan_purger')->createItem([
              'entity_id' => $entity_id,
              'entity_type_id' => $target_entity_type_id,
            ]);
          }
        }
      }
    }
  }

  /**
   * Implements hook_entity_revision_create().
   */
  #[Hook('entity_revision_create')]
  public function entityRevisionCreate(ContentEntityInterface $new_revision, ContentEntityInterface $entity, $keep_untranslatable_fields): void {
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    foreach ($entity->getFieldDefinitions() as $field_name => $field_definition) {
      if ($field_definition->getType() == 'entity_reference_revisions' && !$field_definition->isTranslatable()) {
        $target_entity_type_id = $field_definition->getSetting('target_type');
        if ($this->entityTypeManager->getDefinition($target_entity_type_id)->get('entity_revision_parent_id_field')) {
          // The default implementation copied the values from the current
          // default revision into the field since it is not translatable.
          // Take the originally referenced entity, create a new revision
          // of it and set that instead on the new entity revision.
          $active_langcode = $entity->language()->getId();
          if ($active_langcode === LanguageInterface::LANGCODE_NOT_APPLICABLE) {
            // Do not try to add translations to untranslatable entities.
            continue;
          }
          if ($active_langcode === LanguageInterface::LANGCODE_NOT_SPECIFIED) {
            $active_langcode = $this->languageManager->getDefaultLanguage()->getId();
          }
          $target_storage = $this->entityTypeManager->getStorage($target_entity_type_id);
          if ($target_storage instanceof TranslatableRevisionableStorageInterface) {
            $items = $entity->get($field_name);
            $translation_items = NULL;
            if (!$new_revision->isDefaultTranslation() && $storage instanceof TranslatableRevisionableStorageInterface) {
              $translation_items = $items;
              $items = $storage->load($new_revision->id())->get($field_name);
            }
            $values = [];
            foreach ($items as $delta => $item) {
              // If the target entity is missing, let's skip it.
              if (empty($item->entity)) {
                continue;
              }
              // Use the item from the translation if it exists.
              // If we have translation items, use that if one with the matching
              // target id exists.
              if ($translation_items) {
                foreach ($translation_items as $translation_item) {
                  if ($item->target_id == $translation_item->target_id) {
                    $item = $translation_item;
                    break;
                  }
                }
              }
              /** @var \Drupal\Core\Entity\ContentEntityInterface $target_entity */
              $target_entity = $item->entity;
              if ($target_entity->isTranslatable()) {
                if ($active_langcode != $this->languageManager->getDefaultLanguage()->getId() && !$target_entity->hasTranslation($active_langcode)) {
                  if ($this->contentTranslationManager) {
                    $source_langcode = $this->contentTranslationManager->getTranslationMetadata($entity)->getSource();
                    if ($target_entity->hasTranslation($source_langcode)) {
                      $target_entity = $target_entity->getTranslation($source_langcode);
                    }
                    $target_entity_translation = $target_entity->addTranslation($active_langcode, $target_entity->toArray());
                    $this->contentTranslationManager->getTranslationMetadata($target_entity_translation)->setSource($target_entity->language()->getId());
                  }
                  else {
                    $target_entity->addTranslation($active_langcode, $target_entity->toArray());
                  }
                }
                if ($target_entity->hasTranslation($active_langcode)) {
                  $target_entity = $item->entity->getTranslation($active_langcode);
                }
              }
              $revised_entity = $target_storage->createRevision($target_entity, $new_revision->isDefaultRevision(), $keep_untranslatable_fields);
              // Restore the revision ID.
              $revision_key = $revised_entity->getEntityType()->getKey('revision');
              $revised_entity->set($revision_key, $revised_entity->getLoadedRevisionId());
              $values[$delta] = $revised_entity;
            }
            $new_revision->set($field_name, $values);
          }
        }
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for 'field_storage_config'.
   *
   * Reset the instance handler settings, when the target type is changed.
   */
  #[Hook('field_storage_config_update')]
  public function fieldStorageConfigUpdate(FieldStorageConfigInterface $field_storage): void {
    if ($field_storage->getType() != 'entity_reference_revisions') {
      // Only act on entity reference fields.
      return;
    }
    if ($field_storage->isSyncing()) {
      // Don't change anything during a configuration sync.
      return;
    }
    if ($field_storage->getSetting('target_type') == (method_exists($field_storage, 'getOriginal') ? $field_storage->getOriginal()->getSetting('target_type') : DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0', fn() => $field_storage->getOriginal(), fn() => $field_storage->original)->getSetting('target_type'))) {
      // Target type didn't change.
      return;
    }
    if (empty($field_storage->bundles)) {
      // Field storage has no fields.
      return;
    }
    $field_name = $field_storage->getName();
    foreach ($field_storage->bundles() as $entity_type => $bundles) {
      foreach ($bundles as $bundle) {
        $field = FieldConfig::loadByName($entity_type, $bundle, $field_name);
        $field->setSetting('handler_settings', []);
        $field->save();
      }
    }
  }

}
