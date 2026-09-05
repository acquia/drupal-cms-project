<?php

namespace Drupal\eca\Hook;

/**
 * Provides method to alter schema for field types.
 */
trait ConfigSchemaHooksTrait {

  /**
   * Alters field type for non-string scalar fields to also support tokens.
   *
   * The given key and every ECA owned base type it inherits from are altered.
   * Walking the ancestry is what makes shared base types reachable at all:
   * hook_config_schema_info_alter() fires against the raw per-file definitions,
   * and type inheritance is only resolved afterwards and lazily, by
   * \Drupal\Core\Config\TypedConfigManager::getDefinitionWithReplacements().
   * A key declared on a base type such as "eca_render.action_base" is therefore
   * not part of the leaf definition yet, and altering the leaf alone leaves it
   * untouched. Altering the base type instead reaches every section descending
   * from it, including the sections that inherit it through another base type.
   *
   * @param array $definitions
   *   Associative array of configuration type definitions keyed by schema type
   *   names. The elements are themselves array with information about the type.
   * @param string $key
   *   The key of the schema definition that should be altered.
   */
  protected function alterSchemaFieldType(array &$definitions, string $key): void {
    foreach ($this->schemaTypeAncestry($definitions, $key) as $type) {
      foreach ($definitions[$type]['mapping'] ?? [] as $field => $schema) {
        $definitions[$type]['mapping'][$field]['type'] = match ($schema['type']) {
          'float' => 'eca_float_or_token',
          // Rewriting the "weight" type also drops the "Range" constraint that
          // type carries. That is intentional and currently inert: ECA
          // configuration is not constraint validated.
          'integer', 'weight' => 'eca_integer_or_token',
          default => $schema['type'],
        };
      }
    }
  }

  /**
   * Lists a schema type and the ECA owned base types it inherits from.
   *
   * @param array $definitions
   *   Associative array of configuration type definitions keyed by schema type
   *   names.
   * @param string $key
   *   The key of the schema definition to start at.
   *
   * @return string[]
   *   The key itself, followed by the ECA owned base types it inherits from,
   *   innermost last.
   */
  private function schemaTypeAncestry(array $definitions, string $key): array {
    // The starting key is always altered: it is one of the plugin configuration
    // sections ECA owns, whatever it happens to be named. Only ECA owned base
    // types are then followed into, identified by their name: every schema type
    // ECA declares is "eca" prefixed. An allowlist is deliberate here, because
    // rewriting a base type rewrites it for every section that inherits it, and
    // a base type belonging to another module is shared site-wide. Not
    // recognizing an ECA type is a missed improvement; walking into a foreign
    // one would silently retype another module's configuration.
    $types = [$key];
    $key = $definitions[$key]['type'] ?? '';
    // The in_array() check also guards against a schema that declares itself as
    // its own base type, directly or through a cycle.
    while (str_starts_with($key, 'eca') && isset($definitions[$key]) && !in_array($key, $types, TRUE)) {
      $types[] = $key;
      $key = $definitions[$key]['type'] ?? '';
    }
    return $types;
  }

}
