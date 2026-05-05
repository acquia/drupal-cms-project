<?php

namespace Drupal\custom_field;

/**
 * Defines an interface for custom field Type plugins.
 */
interface CustomFieldUpdateManagerInterface {

  /**
   * Adds a new column to the specified field storage.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $field_name
   *   The field name.
   * @param string $new_property
   *   The new property name (column name).
   * @param string $data_type
   *   The data type to add. Allowed values such as: "integer", "boolean" etc.
   * @param array $options
   *   An array of options to set for new column.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function addColumn(string $entity_type_id, string $field_name, string $new_property, string $data_type, array $options = []): void;

  /**
   * Adds extra columns to the specified field storage.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $field_name
   *   The field name.
   * @param array $extra_columns
   *   An array of extra column definitions, keyed by their suffix.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  public function addExtraColumns(string $entity_type_id, string $field_name, array $extra_columns): void;

  /**
   * Removes a column from the specified field storage.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $field_name
   *   The field name.
   * @param string $property
   *   The name of the column to remove.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Entity\Sql\SqlContentEntityStorageException
   * @throws \Exception
   */
  public function removeColumn(string $entity_type_id, string $field_name, string $property): void;

}
