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
        $originalType = $schema['type'];
        $tokenizedType = match ($originalType) {
          'float' => 'eca_float_or_token',
          'integer', 'weight' => 'eca_integer_or_token',
          default => NULL,
        };
        if ($tokenizedType === NULL) {
          continue;
        }
        $definitions[$type]['mapping'][$field]['type'] = $tokenizedType;
        // Rewriting the type also leaves behind whatever constraints the
        // abandoned type carried. For core's "weight" that includes a "Range"
        // bounding the value, and losing it means an out-of-range weight is no
        // longer reported by anything that validates typed configuration. The
        // bounds are therefore handed to the replacement type, which applies
        // them to genuine integers only.
        // The "Range" constraint cannot be kept as it is, because its validator
        // reports every non-numeric value as an invalid number and would reject
        // exactly the token values the replacement type exists to allow.
        if ($tokenizedType === 'eca_integer_or_token' && ($bounds = $this->inheritedBounds($definitions, $originalType)) !== []) {
          $definitions[$type]['mapping'][$field]['constraints']['EcaIntegerOrToken'] = $bounds;
        }
      }
    }
  }

  /**
   * Reads the value bounds a schema type declares for itself.
   *
   * Only the type is looked at, not the key that declares it. A key is free to
   * carry constraints of its own, and one of them being a "Range" would be a
   * problem this cannot solve by copying it: that "Range" is not lost by the
   * retyping, it stays on the key and rejects every token on it. No ECA schema
   * declares one, and none should.
   *
   * @param array $definitions
   *   Associative array of configuration type definitions keyed by schema type
   *   names.
   * @param string $type
   *   The name of the schema type to read the bounds of.
   *
   * @return array
   *   The "min" and "max" bounds, as far as the type declares them as integers,
   *   in the option names the "EcaIntegerOrToken" constraint expects. An empty
   *   array when the type bounds its value in no way this can carry over.
   *
   * @see \Drupal\eca\Plugin\Validation\Constraint\EcaIntegerOrTokenConstraint
   */
  private function inheritedBounds(array $definitions, string $type): array {
    $range = $definitions[$type]['constraints']['Range'] ?? [];
    if (!is_array($range)) {
      return [];
    }
    // Only plain integer bounds are carried over. A "Range" can also express
    // its limits as a property path, which has no meaning for a single
    // configuration value, and reading one as a bound would invent a limit the
    // type never declared.
    $bounds = [];
    foreach (['min', 'max'] as $option) {
      if (isset($range[$option]) && is_int($range[$option])) {
        $bounds[$option] = $range[$option];
      }
    }
    return $bounds;
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
