<?php

declare(strict_types=1);

namespace Drupal\trash_test\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a trash test entity.
 *
 * @ContentEntityType(
 *   id = "trash_test_entity",
 *   label = @Translation("Trash test"),
 *   label_collection = @Translation("Trash test"),
 *   label_singular = @Translation("Trash test entity"),
 *   label_plural = @Translation("Trash test entities"),
 *   label_count = @PluralTranslation(
 *     singular = "@count trash test entity",
 *     plural = "@count trash test entities",
 *   ),
 *   handlers = {
 *     "form" = {
 *       "default" = "\Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "\Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "\Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "\Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *     "list_builder" = "\Drupal\trash_test\TrashTestEntityListBuilder",
 *     "views_data" = "\Drupal\views\EntityViewsData",
 *   },
 *   base_table = "trash_test",
 *   data_table = "trash_test_field_data",
 *   revision_table = "trash_test_revision",
 *   revision_data_table = "trash_test_field_revision",
 *   translatable = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode",
 *   },
 *   show_revision_ui = TRUE,
 *   links = {
 *     "collection" = "/admin/content/trash_test",
 *     "canonical" = "/admin/content/trash_test/{trash_test_entity}",
 *     "add-form" = "/admin/content/trash_test/add",
 *     "edit-form" = "/admin/content/trash_test/{trash_test_entity}/edit",
 *     "delete-form" = "/admin/content/trash_test/{trash_test_entity}/delete",
 *     "delete-multiple-form" = "/admin/content/trash_test/delete",
 *     "revision" = "/admin/content/trash_test/{trash_test_entity}/revisions/{trash_test_entity_revision}/view",
 *   },
 *   field_ui_base_route = "entity.trash_test_entity.collection",
 *   common_reference_target = TRUE,
 *   admin_permission = "administer trash_test",
 * )
 */
class TrashTestEntity extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Label'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['reference'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Reference'))
      ->setDescription(new TranslatableMarkup('Reference to another TrashTestEntity.'))
      ->setSetting('target_type', 'trash_test_entity')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setRequired(FALSE);

    $fields['required_field'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Required field'))
      ->setDescription(new TranslatableMarkup('A required field for testing validation filtering during restore.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDefaultValue('required!')
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['unique_code'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Unique code'))
      ->setDescription(new TranslatableMarkup('A unique code for testing unique field constraints.'))
      ->setSetting('max_length', 255)
      ->addConstraint('UniqueField')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}
