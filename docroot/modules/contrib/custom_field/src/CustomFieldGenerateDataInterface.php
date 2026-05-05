<?php

namespace Drupal\custom_field;

use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Defines an interface for custom field data generation.
 */
interface CustomFieldGenerateDataInterface {

  /**
   * Generates field data for custom field.
   *
   * @param array $settings
   *   The field definition settings.
   * @param string $target_entity_type
   *   The entity type the field is attached to.
   *
   * @return array
   *   Array of key/value pairs to populate custom field.
   */
  public function generateFieldData(array $settings, string $target_entity_type): array;

  /**
   * Generates random form data that is ready to save.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field
   *   The field definition.
   * @param array|null $deltas
   *   An array of deltas to generate form data for.
   *
   * @return array|string[]
   *   An associative array of form data.
   */
  public function generateSampleFormData(FieldDefinitionInterface $field, ?array $deltas = NULL): array;

}
