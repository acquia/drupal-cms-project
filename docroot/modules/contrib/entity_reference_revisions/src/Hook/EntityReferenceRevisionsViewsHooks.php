<?php

namespace Drupal\entity_reference_revisions\Hook;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\FieldViewsDataProvider;

/**
 * Hook implementations for entity_reference_revisions.
 */
class EntityReferenceRevisionsViewsHooks {
  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ?FieldViewsDataProvider $fieldViewsDataProvider = NULL,
  ) {

  }

  /**
   * Implements hook_field_views_data().
   */
  #[Hook('field_views_data')]
  public function fieldViewsData(FieldStorageConfigInterface $field_storage) {
    $data = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0', fn() => $this->fieldViewsDataProvider->defaultFieldImplementation($field_storage), fn() => views_field_default_views_data($field_storage));
    foreach ($data as $table_name => $table_data) {
      // Add a relationship to the target entity type.
      $target_entity_type_id = $field_storage->getSetting('target_type');
      $target_entity_type = $this->entityTypeManager->getDefinition($target_entity_type_id);
      $entity_type_id = $field_storage->getTargetEntityTypeId();
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $target_base_table = $target_entity_type->getDataTable() ?: $target_entity_type->getBaseTable();
      $field_name = $field_storage->getName();
      // Provide a relationship for the entity type with the entity reference
      // revisions field.
      $args = [
        '@label' => $target_entity_type->getLabel(),
        '@field_name' => $field_name,
      ];
      $data[$table_name][$field_name]['relationship'] = [
        'title' => $this->t('@label referenced from @field_name', $args),
        'label' => $this->t('@field_name: @label', $args),
        'group' => $entity_type->getLabel(),
        'help' => $this->t('Appears in: @bundles.', [
          '@bundles' => implode(', ', $field_storage->getBundles()),
        ]),
        'id' => 'standard',
        'base' => $target_base_table,
        'entity type' => $target_entity_type_id,
        'base field' => $target_entity_type->getKey('revision'),
        'relationship field' => $field_name . '_target_revision_id',
      ];
      // Provide a reverse relationship for the entity type that is referenced
      // by the field.
      $args['@entity'] = $entity_type->getLabel();
      $args['@label'] = $target_entity_type->getSingularLabel();
      $pseudo_field_name = 'reverse__' . $entity_type_id . '__' . $field_name;
      /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
      $table_mapping = $this->entityTypeManager->getStorage($entity_type_id)->getTableMapping();
      $data[$target_base_table][$pseudo_field_name]['relationship'] = [
        'title' => $this->t('@entity using @field_name', $args),
        'label' => $this->t('@field_name', [
          '@field_name' => $field_name,
        ]),
        'group' => $target_entity_type->getLabel(),
        'help' => $this->t('Relate each @entity with a @field_name set to the @label.', $args),
        'id' => 'entity_reverse',
        'base' => $entity_type->getDataTable() ?: $entity_type->getBaseTable(),
        'entity_type' => $entity_type_id,
        'base field' => $entity_type->getKey('revision'),
        'field_name' => $field_name,
        'field table' => $table_mapping->getDedicatedDataTableName($field_storage),
        'field field' => $field_name . '_target_revision_id',
        'join_extra' => [
                [
                  'field' => 'deleted',
                  'value' => 0,
                  'numeric' => TRUE,
                ],
        ],
      ];
    }
    return $data;
  }

}
