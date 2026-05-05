<?php

namespace Drupal\eca\Hook;

/**
 * Provides method to alter schema for field types.
 */
trait ConfigSchemaHooksTrait {

  /**
   * Alters field type for non-string scalar fields to also support tokens.
   *
   * @param array $definitions
   *   Associative array of configuration type definitions keyed by schema type
   *   names. The elements are themselves array with information about the type.
   * @param string $key
   *   The key of the schema definition that should be altered.
   */
  protected function alterSchemaFieldType(array &$definitions, string $key): void {
    foreach ($definitions[$key]['mapping'] ?? [] as $field => $schema) {
      $definitions[$key]['mapping'][$field]['type'] = match ($schema['type']) {
        'float' => 'eca_float_or_token',
        'integer' => 'eca_integer_or_token',
        default => $schema['type'],
      };
    }
  }

}
