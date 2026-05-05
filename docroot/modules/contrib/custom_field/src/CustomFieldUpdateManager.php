<?php

namespace Drupal\custom_field;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateRangeType;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeType;
use Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\FieldStorageConfigInterface;

/**
 * Provides the CustomFieldUpdateManager service.
 */
class CustomFieldUpdateManager implements CustomFieldUpdateManagerInterface {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The key value store service.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected KeyValueStoreInterface $keyValue;

  /**
   * Creates a CustomFieldUpdateManager object.
   */
  public function __construct(
    protected EntityDefinitionUpdateManagerInterface $entityDefinitionUpdateManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    protected Connection $database,
    protected CustomFieldTypeManagerInterface $customFieldTypeManager,
    protected EntityLastInstalledSchemaRepositoryInterface $lastInstalledSchemaRepository,
    KeyValueFactoryInterface $key_value,
    protected ConfigFactoryInterface $configFactory,
    protected ModuleHandlerInterface $moduleHandler,
    protected LoggerChannelInterface $logger,
    protected MessengerInterface $messenger,
  ) {
    $this->keyValue = $key_value->get('entity.storage_schema.sql');
  }

  /**
   * {@inheritdoc}
   */
  public function addColumn(string $entity_type_id, string $field_name, string $new_property, string $data_type, array $options = []): void {
    /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $field_storage_definition */
    $field_storage_definition = $this->entityDefinitionUpdateManager->getFieldStorageDefinition($field_name, $entity_type_id);

    // Return early if no storage definition.
    if (!$field_storage_definition) {
      $message = 'There is no field storage definition for field ' . $field_name . ' and entity type ' . $entity_type_id . '.';
      throw new \Exception($message);
    }

    // Calculate a safe max column length to coincide with SQL column limit.
    $max_name_length = 64 - strlen($field_name) - 12;

    // Validate machine name format (alphanumeric, underscores).
    if (!preg_match('/^(?!.*__)[a-zA-Z0-9_]+$/', $new_property)) {
      throw new \Exception('The new column name must contain only alphanumeric characters and single underscores, no double underscores.');
    }

    if (strlen($new_property) > $max_name_length) {
      throw new \Exception(sprintf('The new column name cannot exceed %d characters.', $max_name_length));
    }

    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $definitions = $this->customFieldTypeManager->getDefinitions();
    $column = $definitions[$data_type] ?? NULL;

    // If we don't have a matching data type, return early.
    if (!$column) {
      $allowed_data_types = array_keys($definitions);
      sort($allowed_data_types);
      $valid_types_string = "\n" . implode("\n", $allowed_data_types);
      throw new \InvalidArgumentException(sprintf("Field '%s' requires a valid data type. Valid data types are: %s",
        $new_property,
        $valid_types_string
      ));
    }

    // Validate options.
    $target_type = NULL;
    $date_time_type = NULL;
    $uri_scheme = NULL;
    switch ($data_type) {
      case 'string':
      case 'telephone':
        $max = $data_type === 'telephone' ? 256 : 255;
        if (isset($options['length']) && (!is_numeric($options['length']) || $options['length'] > $max)) {
          throw new \InvalidArgumentException(sprintf("Field '%s' requires a numeric 'length' <= %s characters.",
            $new_property,
            $max,
          ));
        }
        break;

      case 'integer':
      case 'float':
      case 'decimal':
        if (isset($options['unsigned']) && !is_bool($options['unsigned'])) {
          throw new \InvalidArgumentException(sprintf("Field '%s' requires a boolean 'unsigned' value.",
            $new_property,
          ));
        }
        if (in_array($data_type, ['integer', 'float']) && isset($options['size'])) {
          $valid_sizes = [
            'tiny',
            'small',
            'medium',
            'big',
            'normal',
          ];
          if (!in_array($options['size'], $valid_sizes)) {
            $valid_size_string = "\n" . implode("\n", $valid_sizes);
            throw new \InvalidArgumentException(sprintf("Field '%s' requires a valid 'size' value. Valid sizes are:%s",
              $new_property,
              $valid_size_string,
            ));
          }
        }
        if ($data_type === 'decimal') {
          if (isset($options['precision'])) {
            $precision = (int) $options['precision'];
            if ($precision < 10 || $precision > 32) {
              throw new \InvalidArgumentException(sprintf("Field '%s' requires a numeric 'precision' value between 10 and 32.",
                $new_property,
              ));
            }
            $options['precision'] = $precision;
          }
          if (isset($options['scale'])) {
            if (!is_numeric($options['scale']) || $options['scale'] > 10) {
              throw new \InvalidArgumentException(sprintf("Field '%s' requires a numeric 'scale' value <= 10.",
                $new_property,
              ));
            }
            // Cast to integer.
            $options['scale'] = (int) $options['scale'];
          }
        }
        break;

      case 'entity_reference':
        if (!isset($options['target_type'])) {
          $entity_types = $this->entityTypeManager->getDefinitions();
          $valid_entity_types = array_keys($entity_types);
          sort($valid_entity_types);
          $valid_types_string = "\n" . implode("\n", $valid_entity_types);
          throw new \InvalidArgumentException(sprintf("Field '%s' requires a 'target_type'. Valid target types are:%s",
            $new_property,
            $valid_types_string,
          ));
        }
        $target_type = $options['target_type'];
        break;

      case 'datetime':
      case 'daterange':
        $date_time_types = [
          DateTimeType::DATETIME_TYPE_DATE,
          DateTimeType::DATETIME_TYPE_DATETIME,
        ];
        if ($data_type === 'daterange') {
          $date_time_types[] = DateRangeType::DATETIME_TYPE_ALLDAY;
        }
        if (!isset($options['datetime_type']) || !in_array($options['datetime_type'], $date_time_types)) {
          $valid_types_string = "\n" . implode("\n", $date_time_types);
          throw new \InvalidArgumentException(sprintf("Field '%s' requires a 'datetime_type'. Valid datetime types are:%s",
            $new_property,
            $valid_types_string,
          ));
        }
        $date_time_type = $options['datetime_type'];
        break;

      case 'image':
      case 'file':
        $target_type = 'file';
        $options['target_type'] = 'file';
        $uri_scheme = $this->configFactory->get('system.file')->get('default_scheme');
        break;

      case 'viewfield':
        $target_type = 'view';
        $options['target_type'] = $target_type;
        if (!$this->moduleHandler->moduleExists('custom_field_viewfield')) {
          throw new \InvalidArgumentException(sprintf("Field '%s' requires the 'custom_field_viewfield' module to be enabled.",
            $new_property,
          ));
        }
        break;
    }
    /** @var \Drupal\custom_field\Plugin\CustomFieldTypeInterface $plugin */
    $plugin = $this->customFieldTypeManager->createInstance($data_type);
    $options['name'] = $new_property;
    $custom_field_schema = $plugin->schema($options);
    $default_schema = [
      'not null' => FALSE,
      'default' => NULL,
    ];
    foreach ($custom_field_schema as $key => $schema_column) {
      $custom_field_schema[$key] = array_merge($schema_column, $default_schema);
      // Map columns are serialized; a NULL default would be unserialized to
      // FALSE and break typed data. Seed existing rows with a serialized NULL.
      if (in_array($data_type, ['map', 'map_string'])) {
        unset($custom_field_schema[$key]['default']);
        $custom_field_schema[$key]['initial'] = serialize(NULL);
      }
    }
    // Get the main column spec.
    $spec = current($custom_field_schema);

    // If the storage is SqlContentEntityStorage, update the database schema.
    if (!$storage instanceof SqlContentEntityStorage) {
      return;
    }
    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
    $table_mapping = $storage->getTableMapping([
      $field_name => $field_storage_definition,
    ]);

    $column_name = "{$field_storage_definition->getName()}_{$new_property}";
    $schema = $this->database->schema();

    $existing_data = [];
    $is_revisionable = $entity_type->isRevisionable() && $field_storage_definition->isRevisionable();
    foreach ($table_mapping->getDedicatedTableNames() as $table_name) {
      $field_exists = $schema->fieldExists($table_name, $column_name);
      $table_exists = $schema->tableExists($table_name);

      // Skip revision tables for non-revisionable entity types.
      if ($table_name === $entity_type_id . '_revision__' . $field_name && !$is_revisionable) {
        continue;
      }

      // Add the new column.
      if (!$field_exists && $table_exists) {
        foreach ($custom_field_schema as $column_name => $column_spec) {
          $field_column_name = $field_name . '_' . $column_name;
          $schema->addField($table_name, $field_column_name, $column_spec);
        }
        // Get the old data for restoration.
        $rows = $this->database->select($table_name)
          ->fields($table_name)
          ->execute()
          ->fetchAll(\PDO::FETCH_ASSOC);
        if (!empty($rows)) {
          // Truncate the table.
          $existing_data[$table_name] = $rows;
          $this->database->truncate($table_name)->execute();
        }
      }
      else {
        // Show message that field already exists.
        $message = 'The column ' . $column_name . ' already exists in table ' . $table_name . '.';
        throw new \Exception($message);
      }
    }

    // Load the installed field schema so that it can be updated.
    $schema_key = "$entity_type_id.field_schema_data.$field_name";
    $field_schema_data = $this->keyValue->get($schema_key);

    // Add the new column to the installed field schema.
    foreach ($field_schema_data as $table_name => $fieldSchema) {
      foreach ($custom_field_schema as $column_name => $column_spec) {
        $field_schema_data[$table_name]['fields'][$column_name] = $column_spec;
      }
    }

    // Save changes to the installed field schema.
    $this->keyValue->set($schema_key, $field_schema_data);

    // Tell Drupal we have handled column changes.
    $new_field_storage_definition = $this->entityDefinitionUpdateManager->getFieldStorageDefinition($field_name, $entity_type_id);
    if ($new_field_storage_definition instanceof FieldStorageConfigInterface) {
      $new_field_storage_definition->setSetting('column_changes_handled', TRUE);
      $this->entityDefinitionUpdateManager->updateFieldStorageDefinition($new_field_storage_definition);

      // Update cached entity definitions for entity types.
      if ($table_mapping->allowsSharedTableStorage($new_field_storage_definition)) {
        $definitions = $this->lastInstalledSchemaRepository->getLastInstalledFieldStorageDefinitions($entity_type_id);
        $definitions[$field_name] = $new_field_storage_definition;
        $this->lastInstalledSchemaRepository->setLastInstalledFieldStorageDefinitions($entity_type_id, $definitions);
      }
    }

    // Update field storage config.
    $field_storage_config = FieldStorageConfig::loadByName($entity_type_id, $field_name);
    $columns = $field_storage_config->getSetting('columns');

    // These settings exist across all data types.
    $column_config = [
      'type' => $data_type,
      'name' => $new_property,
    ];

    // Add the conditional settings based on schema match from data type.
    $optional_config = array_filter([
      'length' => $spec['length'] ?? NULL,
      'unsigned' => $spec['unsigned'] ?? NULL,
      'precision' => $spec['precision'] ?? NULL,
      'scale' => $spec['scale'] ?? NULL,
      'size' => $spec['size'] ?? NULL,
      'datetime_type' => $date_time_type ?: NULL,
      'target_type' => $target_type ?: NULL,
      'uri_scheme' => $uri_scheme ?: NULL,
    ], fn($value) => $value !== NULL);

    $column_config = array_merge($column_config, $optional_config);
    $columns[$new_property] = $column_config;

    $field_storage_config->setSetting('columns', $columns);
    $field_storage_config->save();

    if (!empty($existing_data)) {
      $table_names = $table_mapping->getDedicatedTableNames();
      $this->restoreData($table_names, $existing_data);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function removeColumn(string $entity_type_id, string $field_name, string $property): void {
    $field_storage_definition = $this->entityDefinitionUpdateManager->getFieldStorageDefinition($field_name, $entity_type_id);

    // Return early if no storage definition.
    if (!$field_storage_definition) {
      $message = 'There is no field storage definition for field ' . $field_name . ' and entity type ' . $entity_type_id . '.';
      throw new \Exception($message);
    }

    $schema = $this->database->schema();
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    assert($storage instanceof SqlContentEntityStorage);
    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
    $table_mapping = $storage->getTableMapping([
      $field_name => $field_storage_definition,
    ]);
    $table_names = $table_mapping->getDedicatedTableNames();
    $table = $table_mapping->getDedicatedDataTableName($field_storage_definition);
    $column_name = $table_mapping->getFieldColumnName($field_storage_definition, $property);
    $table_columns = $field_storage_definition->getColumns();

    // Return early if there's only one column or if $property doesn't exist.
    if (!isset($table_columns[$property])) {
      $message = $column_name . ' cannot be removed because it does not exist for ' . $field_name;
      throw new \Exception($message);
    }
    elseif (count($field_storage_definition->getSetting('columns')) <= 1) {
      $message = 'Removing column ' . $column_name . ' would leave no remaining columns. The custom field requires at least 1 column.';
      throw new \Exception($message);
    }

    // Load the installed field schema so that it can be updated.
    $schema_key = "$entity_type_id.field_schema_data.$field_name";
    $field_schema_data = $this->keyValue->get($schema_key);

    // Save changes to the installed field schema.
    $existing_data = [];
    $removed_columns = [];
    $is_revisionable = $entity_type->isRevisionable() && $field_storage_definition->isRevisionable();
    if ($field_schema_data) {
      foreach ($table_names as $table_name) {
        $field_exists = $schema->fieldExists($table_name, $column_name);
        $table_exists = $schema->tableExists($table_name);

        // Skip revision tables for non-revisionable entity types.
        if ($table_name === $entity_type_id . '_revision__' . $field_name && !$is_revisionable) {
          continue;
        }

        // Remove the new column.
        if ($field_exists && $table_exists) {
          $all_columns = $table_mapping->getAllColumns($table_name);

          // Filter out the column to be removed and any related columns, and
          // collect removed columns for logging.
          $removed_columns[$table_name] = [];
          $columns_to_fetch = array_filter($all_columns, function ($col) use ($table_name, $column_name, &$removed_columns) {
            if ($col === $column_name || str_starts_with($col, $column_name . '__')) {
              $removed_columns[$table_name][] = $col;
              return FALSE;
            }
            return TRUE;
          });

          // Get the existing data, excluding the removed column.
          $query = $this->database->select($table_name);
          foreach ($columns_to_fetch as $col) {
            $query->addField($table_name, $col);
          }
          $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
          if (!empty($rows)) {
            // Truncate the table.
            $existing_data[$table_name] = $rows;
            $this->database->truncate($table_name)->execute();
          }
          // Remove the column from the schema data.
          unset($field_schema_data[$table_name]['fields'][$column_name]);
        }
      }
      // Update schema definition in database.
      $this->keyValue->set($schema_key, $field_schema_data);

      // Tell Drupal we have handled column changes.
      if ($field_storage_definition instanceof FieldStorageConfigInterface) {
        $field_storage_definition->setSetting('column_changes_handled', TRUE);
      }
      $this->entityDefinitionUpdateManager->updateFieldStorageDefinition($field_storage_definition);
    }

    // Update the field storage config.
    $field_storage_config = FieldStorageConfig::loadByName($entity_type_id, $field_name);
    // Remove the column from the field storage configuration.
    $columns = $field_storage_config->getSetting('columns');
    if (isset($columns[$property])) {
      unset($columns[$property]);
      $field_storage_config->setSetting('columns', $columns);
      $field_storage_config->save();
    }

    $bundles = array_keys($this->entityTypeBundleInfo->getBundleInfo($entity_type_id));
    foreach ($bundles as $bundle) {
      // Update the field config for each bundle.
      if ($field_config = FieldConfig::loadByName($entity_type_id, $bundle, $field_name)) {
        assert($field_config instanceof FieldConfigInterface);
        $settings = $field_config->getSettings();
        foreach ($settings as $setting_type => $setting) {
          if (is_array($setting) && isset($setting[$property])) {
            unset($settings[$setting_type][$property]);
            $field_config->setSettings($settings);
            $field_config->save();
          }
        }

        // Update entity form display configs.
        if ($displays = $this->entityTypeManager->getStorage('entity_form_display')->loadByProperties([
          'targetEntityType' => $field_config->getTargetEntityTypeId(),
          'bundle' => $field_config->getTargetBundle(),
        ])) {
          /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $display */
          foreach ($displays as $display) {
            if ($component = $display->getComponent($field_name)) {
              $changed = FALSE;
              // Check for settings to remove in the custom_flex plugin.
              if (isset($component['settings']['columns'][$property])) {
                unset($component['settings']['columns'][$property]);
                $changed = TRUE;
              }
              // Check for field settings.
              if (isset($component['settings']['fields'][$property])) {
                unset($component['settings']['fields'][$property]);
                $changed = TRUE;
              }
              if ($changed) {
                $display->setComponent($field_name, $component)->save();
              }
            }
          }
        }

        // Update entity view display configs.
        if ($displays = $this->entityTypeManager->getStorage('entity_view_display')->loadByProperties([
          'targetEntityType' => $field_config->getTargetEntityTypeId(),
          'bundle' => $field_config->getTargetBundle(),
        ])) {
          /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display */
          foreach ($displays as $display) {
            if ($component = $display->getComponent($field_name)) {
              if (isset($component['settings']['fields'][$property])) {
                unset($component['settings']['fields'][$property]);
                $display->setComponent($field_name, $component)->save();
              }
            }
          }
        }
      }
    }

    // Try to drop field data.
    $this->database->schema()->dropField($table, $column_name);

    // Restore the data after removing the column.
    if (!empty($existing_data)) {
      // Log the removed items as a list for each table.
      $log_message = 'Removed columns from the following tables:';
      foreach ($removed_columns as $table => $columns) {
        if (!empty($columns)) {
          $log_message .= "<br><br><strong>$table:</strong><br>- " . implode("<br>- ", $columns);
        }
      }
      $this->logger->info($log_message);
      $this->restoreData($table_names, $existing_data);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addExtraColumns(string $entity_type_id, string $field_name, array $extra_columns = []): void {
    if (empty($extra_columns)) {
      return;
    }

    $field_storage = $this->entityDefinitionUpdateManager->getFieldStorageDefinition($field_name, $entity_type_id);

    // Return early if no storage definition.
    if (!$field_storage) {
      $message = 'There is no field storage definition for field ' . $field_name . ' and entity type ' . $entity_type_id . '.';
      throw new \Exception($message);
    }

    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    if (!$storage instanceof SqlContentEntityStorage) {
      return;
    }

    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
    $table_mapping = $storage->getTableMapping([
      $field_name => $field_storage,
    ]);
    $table_names = $table_mapping->getDedicatedTableNames();
    $schema = $this->database->schema();
    $schema_key = "$entity_type_id.field_schema_data.$field_name";
    $field_schema_data = $this->keyValue->get($schema_key);
    $is_revisionable = $entity_type->isRevisionable() && $field_storage->isRevisionable();

    // Validate all extra columns and prepare schema definitions.
    $new_columns = [];
    foreach ($extra_columns as $suffix => $definition) {
      // Validate machine name format (alphanumeric, underscores).
      if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*(__[a-zA-Z0-9_]+)*$/', $suffix)) {
        throw new \Exception("Invalid column suffix: $suffix");
      }
      $db_column = $field_name . '_' . $suffix;

      // Enforce the MySQL 64-char identifier limit safely.
      if (strlen($db_column) > 64) {
        throw new \Exception("Column name $db_column exceeds 64 characters");
      }

      // Use the provided schema definition directly.
      $spec = $definition + [
        'not null' => FALSE,
        'default' => NULL,
      ];

      // Store the schema spec for this column.
      $new_columns[$suffix] = [
        'db_column_name' => $db_column,
        'spec' => $spec,
      ];
    }

    // Process tables: save existing data, truncate, and add new columns.
    $existing_data = [];
    foreach ($table_names as $table_name) {
      // Skip revision tables for non-revisionable entity types.
      if (str_contains($table_name, '_revision__') && !$is_revisionable) {
        continue;
      }
      if (!$schema->tableExists($table_name)) {
        continue;
      }

      // Add all new columns.
      foreach ($new_columns as $col) {
        if (!$schema->fieldExists($table_name, $col['db_column_name'])) {
          $schema->addField($table_name, $col['db_column_name'], $col['spec']);
        }
        else {
          $message = 'The column ' . $col['db_column_name'] . ' already exists in table ' . $table_name . '.';
          throw new \Exception($message);
        }
      }

      // Backup + truncate only once per table.
      $rows = $this->database->select($table_name)
        ->fields($table_name)
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC);

      if (!empty($rows)) {
        // Truncate the table.
        $existing_data[$table_name] = $rows;
        $this->database->truncate($table_name)->execute();
      }
    }

    // Update the field schema data.
    foreach ($field_schema_data as $table => $data) {
      foreach ($new_columns as $col) {
        $field_schema_data[$table]['fields'][(string) $col['db_column_name']] = $col['spec'];
      }
    }
    $this->keyValue->set($schema_key, $field_schema_data);

    $field_storage_config = FieldStorageConfig::loadByName($entity_type_id, $field_name);
    if ($field_storage_config) {
      // Tell Drupal we have handled column changes.
      $field_storage_config->setSetting('column_changes_handled', TRUE);
      $field_storage_config->save();
      $this->entityDefinitionUpdateManager->updateFieldStorageDefinition($field_storage_config);

      // Update cached entity definitions for entity types.
      if ($table_mapping->allowsSharedTableStorage($field_storage_config)) {
        $installed = $this->lastInstalledSchemaRepository->getLastInstalledFieldStorageDefinitions($entity_type_id);
        $installed[$field_name] = $field_storage_config;
        $this->lastInstalledSchemaRepository->setLastInstalledFieldStorageDefinitions($entity_type_id, $installed);
      }
    }

    // Restore existing data if applicable.
    if (!empty($existing_data)) {
      $this->restoreData($table_names, $existing_data);
    }
  }

  /**
   * A batch wrapper function for restoring data.
   *
   * @param array $tables
   *   The array of table names to restore data for.
   * @param array $existing_data
   *   The existing data to be restored for each table.
   */
  private function restoreData(array $tables, array $existing_data): void {
    $batch_size = 50;
    $tables_count = count($tables);

    // Initialize the batch.
    $batch = [
      'title' => $this->t('Restoring data...'),
      'operations' => [],
      'init_message' => $this->formatPlural($tables_count,
        'Starting data restoration for 1 table...',
        'Starting data restoration for @count tables...',
        ['@count' => count($tables)]),
      'error_message' => $this->t('An error occurred during data restoration. Please check the logs for errors.'),
      'finished' => [$this, 'restoreDataBatchFinished'],
    ];

    // Add table names to the batch context.
    $batch['context']['tables'] = $tables;

    // Process each table separately and create a batch for each one.
    foreach ($tables as $table_name) {
      if (!empty($existing_data[$table_name])) {
        // Populate the 'operations' array with data processing tasks.
        $total_rows = count($existing_data[$table_name]);
        $chunks = array_chunk($existing_data[$table_name], $batch_size);
        $chunk_total = count($chunks);

        foreach ($chunks as $chunk_index => $chunk) {
          $batch['operations'][] = [
            [$this, 'restoreDataBatchCallback'],
            [$table_name, $chunk, $total_rows, $chunk_total, $chunk_index + 1],
          ];
        }
      }
    }
    if (!empty($batch['operations'])) {
      // Queue the batch for processing.
      batch_set($batch);
    }
  }

  /**
   * The batch processing callback function for restoring data.
   *
   * @param string $table_name
   *   The table to batch process.
   * @param array $data
   *   The array of data to insert into the table.
   * @param int $total_rows
   *   The total number of rows in the table being processed.
   * @param int $chunk_total
   *   The total number of chunks for the table being processed.
   * @param int $chunk_index
   *   The index of the current chunk being processed (1-based).
   * @param array|\ArrayAccess $context
   *   The context array.
   *
   * @throws \Exception
   */
  public function restoreDataBatchCallback(string $table_name, array $data, int $total_rows, int $chunk_total, int $chunk_index, mixed &$context): void {
    // Initialize 'progress' key if it does not exist.
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
    }

    if (!isset($context['sandbox']['table'])) {
      $context['sandbox']['table'] = $table_name;
    }

    // Initialize 'processed_rows' key for the table if it does not exist.
    if (!isset($context['results'][$table_name]['processed_rows'])) {
      $context['results'][$table_name]['processed_rows'] = 0;
    }

    // Get the current schema to ensure we only insert valid columns.
    $schema = $this->database->schema();
    $valid_columns = [];
    if (!empty($data[0])) {
      foreach (array_keys($data[0]) as $column) {
        if ($schema->fieldExists($table_name, $column)) {
          $valid_columns[] = $column;
        }
      }
    }

    // Filter the data to include only columns that exist in the current schema.
    $filtered_data = [];
    foreach ($data as $row) {
      $filtered_row = array_intersect_key($row, array_flip($valid_columns));
      if (!empty($filtered_row)) {
        $filtered_data[] = $filtered_row;
      }
    }

    // If no valid data remains after filtering, skip the insert.
    if (empty($filtered_data)) {
      $this->logger->warning('No valid data to restore for table @table: All columns were filtered out.', [
        '@table' => $table_name,
      ]);
      $context['message'] = t('No valid data to restore for @table (Chunk: @chunk/@total_chunks)', [
        '@table' => $table_name,
        '@chunk' => $chunk_index,
        '@total_chunks' => $chunk_total,
      ]);
      $context['finished'] = 1;
      return;
    }

    // Get the fields for the insert query based on the filtered data.
    $fields = array_keys($filtered_data[0]);
    $insert_query = $this->database->insert($table_name)->fields($fields);

    // Process a batch of rows for the current chunk.
    $batch_size = 50;
    $start = $context['sandbox']['progress'];
    $total_rows_chunk = count($filtered_data);
    $rows_to_process = array_slice($filtered_data, $start, $batch_size);

    // Use batch insert to optimize insertion.
    foreach ($rows_to_process as $row) {
      $insert_query->values(array_values($row));
      $context['sandbox']['progress']++;
      $context['results'][$table_name]['processed_rows']++;
    }

    // Insert multiple rows in a single query using batch insert.
    $insert_query->execute();

    // Update the progress message to include the table information.
    $context['message'] = t('Processed @current out of @total. (Table: @table, Chunk: @chunk/@total_chunks)', [
      '@current' => $context['sandbox']['progress'],
      '@total' => $total_rows,
      '@table' => $table_name,
      '@chunk' => $chunk_index,
      '@total_chunks' => $chunk_total,
    ]);

    // Calculate the overall progress for the batch process.
    if ($total_rows_chunk > 0) {
      $context['finished'] = $context['sandbox']['progress'] / $total_rows_chunk;
    }
    else {
      unset($context['sandbox']['table']);
      $context['finished'] = 1;
    }
  }

  /**
   * The batch processing finished callback function.
   *
   * @param bool $success
   *   The end result status of the batching.
   * @param array $results
   *   The results array of the batching.
   */
  public function restoreDataBatchFinished(bool $success, array $results): void {
    if ($success) {
      foreach ($results as $table_name => $result) {
        if (isset($result['processed_rows'])) {
          $total_rows = $result['processed_rows'];
          $message = t('Restored @total_rows rows in @table', [
            '@table' => $table_name,
            '@total_rows' => $total_rows,
          ]);
          $this->messenger->addMessage($message, 'status');
        }
        $this->logger->info('Restored @count rows for table @table.', [
          '@count' => $result['processed_rows'] ?? 0,
          '@table' => $table_name,
        ]);
      }
    }
    else {
      $this->logger->error('Data restoration failed.');
    }
  }

}
