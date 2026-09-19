<?php

declare(strict_types=1);

namespace Drupal\custom_field\Hook;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\DefaultTableMapping;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Plugin\CustomFieldFormatterManagerInterface;
use Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\views\FieldViewsDataProvider;

/**
 * Provides hooks related to config schemas.
 */
class ViewsHooks {

  use StringTranslationTrait;

  /**
   * Constructs a ViewsHooks object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CustomFieldTypeManagerInterface $customFieldTypeManager,
    protected CustomFieldFormatterManagerInterface $customFieldFormatterManager,
    protected ?FieldViewsDataProvider $fieldViewsDataProvider,
  ) {}

  /**
   * Implements hook_field_views_data().
   */
  #[Hook('field_views_data')]
  public function fieldViewsData(FieldStorageConfigInterface $field_storage): array {
    $data = DeprecationHelper::backwardsCompatibleCall(
      currentVersion: \Drupal::VERSION,
      deprecatedVersion: '11.2.0',
      currentCallable: fn() => $this->fieldViewsDataProvider->defaultFieldImplementation($field_storage),
      deprecatedCallable: fn() => views_field_default_views_data($field_storage),
    );

    $entity_type_id = $field_storage->getTargetEntityTypeId();
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $entity_type_storage = $this->entityTypeManager->getStorage($entity_type_id);
    assert($entity_type_storage instanceof SqlEntityStorageInterface);
    $table_mapping = $entity_type_storage->getTableMapping();
    assert($table_mapping instanceof DefaultTableMapping);

    $field_name = $field_storage->getName();
    $custom_fields = $this->customFieldTypeManager->getCustomFieldItems($field_storage->getSettings());
    $types_with_relationships = [
      'file',
      'image',
      'entity_reference',
    ];

    foreach ($data as $table_name => $table_data) {
      foreach ($custom_fields as $name => $custom_field) {
        $default_formatter = $custom_field->getDefaultFormatter();
        $instance_options = $this->customFieldFormatterManager->createOptionsForInstance($custom_field, $default_formatter, [], 'default');
        $instance = $this->customFieldFormatterManager->getInstance($instance_options);
        $subfield = $table_mapping->getFieldColumnName($field_storage, $name);

        // Build the list of additional fields to add to queries.
        $field = $table_data[$subfield];
        $title_args = [];
        if ($field['title'] instanceof TranslatableMarkup) {
          $title_args = $field['title']->getArguments();
        }
        $field['title'] = $this->t('@label (@column)', $title_args);
        $add_fields = ['delta', 'langcode', 'bundle'];
        $extra_columns = [];
        $type = $custom_field->getDataType();
        if ($type === 'image') {
          $image_cols = ['alt', 'title', 'width', 'height'];
          foreach ($image_cols as $col) {
            $add_fields[] = $subfield . '__' . $col;
            $extra_columns[$col] = $this->t('@column', ['@column' => $col]);
          }
        }
        elseif ($type === 'link') {
          $add_fields[] = $subfield . '__title';
          $extra_columns['title'] = $this->t('title');
        }
        elseif (in_array($type, ['daterange', 'time_range'])) {
          foreach (['end', 'duration'] as $col) {
            $add_fields[] = $subfield . '__' . $col;
            $extra_columns[$col] = $this->t('@column', ['@column' => $col]);
          }
        }
        $field += [
          'id' => 'custom_field',
          'field_name' => $field_name,
          'property' => $name,
          'entity_type' => $entity_type_id,
          'default_formatter' => $default_formatter,
          'default_formatter_settings' => $instance::defaultSettings(),
          'additional fields' => $add_fields,
          'extra columns' => $extra_columns,
        ];
        $filter = [
          'id' => 'standard',
        ];
        $sort = [
          'id' => 'standard',
        ];
        $argument = [
          'id' => 'standard',
        ];

        switch ($type) {
          case 'boolean':
            $filter['id'] = 'boolean';
            break;

          case 'string':
          case 'string_long':
          case 'telephone':
          case 'email':
          case 'color':
            $filter['id'] = 'string';
            $argument['id'] = 'string';
            break;

          case 'datetime':
          case 'daterange':
            $datetime_type = $custom_field->getDatetimeType();
            $argument = [
              'id' => 'custom_field_datetime',
              'datetime_type' => $custom_field->getDatetimeType(),
            ];
            $arguments = [
              'year' => $this->t('Date in the form of YYYY.'),
              'month' => $this->t('Date in the form of MM (01 - 12).'),
              'day' => $this->t('Date in the form of DD (01 - 31).'),
              'week' => $this->t('Date in the form of WW (01 - 53).'),
              'year_month' => $this->t('Date in the form of YYYYMM.'),
              'full_date' => $this->t('Date in the form of CCYYMMDD.'),
            ];
            $filter['id'] = 'custom_field_datetime';
            $filter['datetime_type'] = $datetime_type;
            foreach ($arguments as $argument_type => $help_text) {
              $data[$table_name][$subfield . '_' . $argument_type] = [
                'title' => $this->t('@label (@name:@column) (@argument)', [
                  ...$title_args,
                  '@argument' => $argument_type,
                ]),
                'help' => $help_text,
                'argument' => [
                  'field' => $subfield,
                  'id' => 'custom_field_datetime_' . $argument_type,
                  'entity_type' => $entity_type_id,
                  'field_name' => $field_name,
                  'datetime_type' => $datetime_type,
                ],
                'group' => $entity_type->getLabel(),
              ];
            }
            $sort = [
              'id' => 'custom_field_datetime',
              'datetime_type' => $datetime_type,
            ];
            $data[$table_name][$subfield . '__timezone']['field'] = [
              'id' => 'standard',
              'field_name' => $field_name,
              'property' => $subfield . '__timezone',
            ];
            $data[$table_name][$subfield . '__timezone']['filter']['id'] = 'string';
            $data[$table_name][$subfield . '__timezone']['sort']['id'] = 'standard';
            $data[$table_name][$subfield . '__timezone']['argument']['id'] = 'string';

            // Daterange specific settings.
            if ($type === 'daterange') {
              $data[$table_name][$subfield . '__end']['filter'] = $filter;
              $data[$table_name][$subfield . '__end']['sort'] = $sort;
              $data[$table_name][$subfield . '__end']['argument'] = $argument;
              $data[$table_name][$subfield . '__duration']['field'] = [
                'id' => 'standard',
                'field_name' => $field_name,
                'property' => $subfield . '__duration',
              ];
              $data[$table_name][$subfield . '__duration']['filter']['id'] = 'numeric';
              $data[$table_name][$subfield . '__duration']['argument']['id'] = 'numeric';
            }
            break;

          case 'integer':
          case 'float':
          case 'decimal':
          case 'duration':
          case 'file':
          case 'image':
          case 'time':
            $filter['id'] = 'numeric';
            $argument['id'] = 'numeric';
            break;

          case 'time_range':
            $filter['id'] = 'numeric';
            $argument['id'] = 'numeric';
            $data[$table_name][$subfield . '__end']['filter']['id'] = 'numeric';
            $data[$table_name][$subfield . '__end']['argument']['id'] = 'numeric';
            $data[$table_name][$subfield . '__duration']['filter']['id'] = 'numeric';
            $data[$table_name][$subfield . '__duration']['argument']['id'] = 'numeric';
            break;

          case 'map':
          case 'map_string':
            $filter = [];
            $sort = [];
            $argument = [];
            break;

          case 'entity_reference':
            $target_entity_type_id = $custom_field->getTargetType();
            if ($target_entity_type_id === 'taxonomy_term') {
              $filter['id'] = 'taxonomy_index_tid';
              $argument['id'] = 'taxonomy_index_tid';
            }
            else {
              $filter = [
                'id' => 'custom_field_entity_reference',
                'target_type' => $target_entity_type_id,
                'allow empty' => TRUE,
                'field_name' => $field_name,
                'subfield' => $name,
              ];

              // Use entity_target_id as argument to get entity label handling.
              $argument = [
                'id' => 'entity_target_id',
                'target_entity_type_id' => $target_entity_type_id,
              ];
            }

            // Keep the sort as numeric/string based on the data type.
            $data_type = $field_storage->getPropertyDefinition($name)
              ->getSetting('data_type');
            $id_type = $data_type === 'integer' ? 'numeric' : 'string';
            $sort['id'] = $id_type;
            break;
        }
        $data[$table_name][$subfield]['field'] = $field;
        $data[$table_name][$subfield]['filter'] = $filter;
        $data[$table_name][$subfield]['sort'] = $sort;
        $data[$table_name][$subfield]['argument'] = $argument;

        // Build views relationships.
        if (in_array($type, $types_with_relationships)) {
          $target_entity_type_id = $custom_field->getTargetType();
          $target_entity_type = $this->entityTypeManager->getDefinition($target_entity_type_id);
          $target_base_table = $target_entity_type->getDataTable() ?: $target_entity_type->getBaseTable();
          if ($target_entity_type instanceof ContentEntityTypeInterface) {
            // Provide a relationship for the entity type with the entity
            // reference field.
            $args = [
              '@label' => $target_entity_type->getLabel(),
              '@field_name' => $field_name . '_' . $name,
            ];

            $data[$table_name][$subfield]['relationship'] = [
              'title' => $this->t('@label referenced from @field_name', $args),
              'label' => $this->t('@field_name: @label', $args),
              'group' => $entity_type->getLabel(),
              'help' => $this->t('Appears in: @bundles.', ['@bundles' => implode(', ', $field_storage->getBundles())]),
              'id' => 'standard',
              'base' => $target_base_table,
              'entity type' => $target_entity_type_id,
              'base field' => $target_entity_type->getKey('id'),
              'relationship field' => $subfield,
            ];

            // Provide a reverse relationship for the entity type that is
            // referenced by the field.
            $args['@entity'] = $entity_type->getLabel();
            $args['@label'] = $target_entity_type->getSingularLabel();
            $pseudo_field_name = 'reverse__' . $entity_type_id . '__' . $subfield;
            $data[$target_base_table][$pseudo_field_name]['relationship'] = [
              'title' => $this->t('@entity using @field_name', $args),
              'label' => $this->t('@field_name', ['@field_name' => $field_name]),
              'group' => $target_entity_type->getLabel(),
              'help' => $this->t('Relate each @entity with a @field_name set to the @label.', $args),
              'id' => 'entity_reverse',
              'base' => $entity_type->getDataTable() ?: $entity_type->getBaseTable(),
              'entity_type' => $entity_type_id,
              'base field' => $entity_type->getKey('id'),
              'field_name' => $field_name,
              'field table' => $table_mapping->getDedicatedDataTableName($field_storage),
              'field field' => $subfield,
              'join_extra' => [
                [
                  'field' => 'deleted',
                  'value' => 0,
                  'numeric' => TRUE,
                ],
              ],
            ];
          }
        }
      }
    }

    return $data;
  }

}
