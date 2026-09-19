<?php

namespace Drupal\custom_field\Plugin\Field\FieldType;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\Core\Validation\Plugin\Validation\Constraint\NotNullConstraint;
use Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface;

/**
 * Represents a configurable entity custom field.
 */
class CustomItemList extends FieldItemList implements CustomFieldItemListInterface {

  /**
   * {@inheritdoc}
   */
  public function getConstraints(): array {
    $constraints = parent::getConstraints();
    foreach ($constraints as $constraint) {
      if ($constraint instanceof NotNullConstraint) {
        $constraint->message = $this->t('@label is required', ['@label' => $this->getFieldDefinition()->getLabel()]);
      }
    }

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public function referencedEntities(): array {
    if ($this->isEmpty()) {
      return [];
    }
    // Collect the IDs of existing entities to load.
    $ids = [];
    $entities = [];
    $settings = $this->getSettings();
    $custom_fields = $this->getCustomFieldManager()->getCustomFieldItems($settings);
    foreach ($this->list as $item) {
      foreach ($custom_fields as $custom_field) {
        if ($target_type = $custom_field->getTargetType()) {
          $id = $item->{$custom_field->getName()};
          if (!empty($id)) {
            $ids[$target_type][] = $id;
          }
        }
      }
    }
    if ($ids) {
      foreach ($ids as $target_type => $entity_ids) {
        $target_entities[$target_type] = \Drupal::entityTypeManager()->getStorage($target_type)->loadMultiple($entity_ids);
      }
    }
    foreach ($this->list as $delta => $item) {
      foreach ($custom_fields as $custom_field) {
        if ($target_type = $custom_field->getTargetType()) {
          $id = $item->{$custom_field->getName()};
          if (!empty($id) && isset($target_entities[$target_type][$id])) {
            $entities[$delta][$custom_field->getName()] = $target_entities[$target_type][$id];
          }
        }
      }
    }

    return $entities;
  }

  /**
   * Gets all files referenced by this field.
   *
   * @return \Drupal\file\FileInterface[]
   *   An array of all file objects.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function referencedFiles(): array {
    if ($this->isEmpty()) {
      return [];
    }
    // Collect the IDs of existing files to load.
    $ids = [];
    $settings = $this->getSettings();
    $custom_fields = $this->getCustomFieldManager()->getCustomFieldItems($settings);
    foreach ($this->list as $item) {
      foreach ($custom_fields as $custom_field) {
        $data_type = $custom_field->getPluginId();
        if (in_array($data_type, ['file', 'image'])) {
          $id = $item->get($custom_field->getName())->getValue();
          if (!empty($id)) {
            if (is_array($id) && isset($id['target_id'])) {
              $id = $id['target_id'];
            }
            $ids[] = $id;
          }
        }
      }
    }
    if (!empty($ids)) {
      return \Drupal::entityTypeManager()->getStorage('file')->loadMultiple($ids);
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function postSave($update): bool {
    $entity = $this->getEntity();

    $files = $this->referencedFiles();

    if (!$update) {
      // Add a new usage for newly uploaded files.
      foreach ($files as $file) {
        \Drupal::service('file.usage')->add($file, 'custom_field', $entity->getEntityTypeId(), (string) $entity->id());
      }
    }
    else {
      // Get current target file entities and file IDs.
      $ids = [];

      foreach ($files as $file) {
        $ids[] = $file->id();
      }

      // On new revisions, all files are considered to be a new usage and no
      // deletion of previous file usages are necessary.
      if ($entity instanceof RevisionableInterface && !empty($entity->original) && $entity->getRevisionId() != $entity->original->getRevisionId()) {
        foreach ($files as $file) {
          \Drupal::service('file.usage')->add($file, 'custom_field', $entity->getEntityTypeId(), (string) $entity->id());
        }
        return FALSE;
      }

      // Get the file IDs attached to the field before this update.
      $field_name = $this->getFieldDefinition()->getName();
      $original_ids = [];
      if (!empty($entity->original)) {
        $original = $entity->original;
        $langcode = $this->getLangcode();
        if ($original->hasTranslation($langcode)) {
          $original_items = $original->getTranslation($langcode)->{$field_name};
          $settings = $this->getSettings();
          $custom_fields = $this->getCustomFieldManager()
            ->getCustomFieldItems($settings);
          foreach ($original_items as $item) {
            foreach ($custom_fields as $custom_field) {
              if (in_array($custom_field->getPluginId(), ['file', 'image'])) {
                $original_ids[] = $item->{$custom_field->getName()};
              }
            }
          }
        }
      }

      // Decrement file usage by 1 for files that were removed from the field.
      $removed_ids = array_filter(array_diff($original_ids, $ids));
      $removed_files = \Drupal::entityTypeManager()->getStorage('file')->loadMultiple($removed_ids);
      foreach ($removed_files as $removed_file) {
        \Drupal::service('file.usage')->delete($removed_file, 'custom_field', $entity->getEntityTypeId(), (string) $entity->id());
      }

      // Add new usage entries for newly added files.
      foreach ($files as $file) {
        if (!in_array($file->id(), $original_ids)) {
          \Drupal::service('file.usage')->add($file, 'custom_field', $entity->getEntityTypeId(), (string) $entity->id());
        }
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function delete(): void {
    parent::delete();
    $entity = $this->getEntity();

    // If a translation is deleted only decrement the file usage by one. If the
    // default translation is deleted remove all file usages within this entity.
    $count = $entity instanceof TranslatableInterface && $entity->isDefaultTranslation() ? 0 : 1;
    try {
      foreach ($this->referencedFiles() as $file) {
        \Drupal::service('file.usage')
          ->delete($file, 'custom_field', $entity->getEntityTypeId(), (string) $entity->id(), $count);
      }
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException | MissingDataException $e) {
      // Do nothing.
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRevision(): void {
    parent::deleteRevision();
    $entity = $this->getEntity();

    // Decrement the file usage by 1.
    foreach ($this->referencedFiles() as $file) {
      \Drupal::service('file.usage')->delete($file, 'custom_field', $entity->getEntityTypeId(), (string) $entity->id());
    }
  }

  /**
   * Get the custom field_type manager plugin.
   *
   * @return \Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface
   *   Returns the 'custom' field type plugin manager.
   */
  public function getCustomFieldManager(): CustomFieldTypeManagerInterface {
    return \Drupal::service('plugin.manager.custom_field_type');
  }

}
