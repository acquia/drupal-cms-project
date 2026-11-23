<?php

namespace Drupal\modeler;

use Drupal\Core\Config\Schema\Undefined;
use Drupal\Core\Config\TypedConfigManagerInterface;

/**
 * Looks up YAML schemas from Drupal config schema definitions.
 *
 * Convention: For a plugin with config schema key "action.configuration.foo"
 * and a textarea field "bar", a YAML schema is discovered at the config
 * schema key "yaml.action.configuration.foo.bar". If such a definition
 * exists, it is converted to the frontend YamlEditor JSON format.
 */
class YamlSchemaLookup {

  public function __construct(
    protected TypedConfigManagerInterface $typedConfigManager,
  ) {}

  /**
   * Look up a YAML schema for a textarea field from Drupal config schema.
   *
   * @param string $plugin_schema_key
   *   The plugin's config schema key.
   * @param string $field_key
   *   The form field key (e.g. 'value', 'arguments').
   *
   * @return array|null
   *   The YamlEditor-compatible JSON schema, or NULL if no schema is defined.
   */
  public function lookup(string $plugin_schema_key, string $field_key): ?array {
    $yaml_schema_key = 'yaml.' . $plugin_schema_key . '.' . $field_key;
    try {
      $definition = $this->typedConfigManager->getDefinition($yaml_schema_key);
      // getDefinition() never returns NULL — it falls back to the Undefined
      // type for unknown keys. Check the definition class to distinguish a
      // real schema from the fallback.
      if (($definition['class'] ?? '') === Undefined::class) {
        return NULL;
      }
      return $this->convertConfigSchemaToYamlSchema($definition);
    }
    catch (\Exception) {
      // Schema not found or invalid — fall back gracefully.
    }
    return NULL;
  }

  /**
   * Convert a Drupal config schema definition to the YamlEditor JSON format.
   *
   * Drupal types map to YamlEditor types as follows:
   *   - string/text/label/required_label/machine_name/email/uri/path → string.
   *   - string with static Choice constraint → string with options.
   *   - integer/weight → number (step: 1).
   *   - float → number.
   *   - boolean → boolean.
   *   - sequence → list (or keyed_mapping when 'keyed: true' is set).
   *   - mapping → mapping.
   *
   * Note: After TypedConfigManager::getDefinition() processes a definition,
   * the 'type' field contains the full resolved schema key name, NOT the base
   * type name like 'mapping'. We use resolveBaseType() which inspects the
   * definition's 'class' property and structural keys to reliably determine
   * the effective type.
   *
   * @param array $definition
   *   The Drupal config schema definition array.
   * @param int $depth
   *   (internal) Current recursion depth. Capped at 10 as a safety guard.
   *
   * @return array
   *   The YamlEditor-compatible JSON schema.
   */
  protected function convertConfigSchemaToYamlSchema(array $definition, int $depth = 0): array {
    // Guard against infinite recursion from circular type references.
    if ($depth > 10) {
      return ['type' => 'string'];
    }

    // Determine the effective base type. After TypedConfigManager resolves
    // a definition, the 'type' field contains the full schema key (e.g.
    // 'yaml.eca.event.plugin.xxx.arguments'), not the base type name like
    // 'mapping' or 'string'. We use the definition's 'class' property and
    // structural keys ('mapping', 'sequence') to reliably identify the type.
    $base_type = $this->resolveBaseType($definition);

    $schema = [];

    if ($base_type === 'mapping') {
      $schema['type'] = 'mapping';
      $schema['properties'] = [];
      if (isset($definition['mapping']) && is_array($definition['mapping'])) {
        foreach ($definition['mapping'] as $prop_key => $prop_def) {
          // Skip special meta-keys used for discriminated unions.
          if ($prop_key === '_discriminator' || $prop_key === '_conditional_properties') {
            continue;
          }
          $schema['properties'][$prop_key] = $this->convertConfigSchemaToYamlSchema($prop_def, $depth + 1);
        }
      }

      // Handle discriminated unions.
      if (isset($definition['mapping']['_discriminator'])) {
        $schema['discriminator'] = $definition['mapping']['_discriminator'];
      }
      if (isset($definition['mapping']['_conditional_properties']) && is_array($definition['mapping']['_conditional_properties'])) {
        $schema['conditionalProperties'] = [];
        foreach ($definition['mapping']['_conditional_properties'] as $discriminator_value => $conditional_props) {
          $schema['conditionalProperties'][$discriminator_value] = [];
          if (is_array($conditional_props)) {
            foreach ($conditional_props as $prop_key => $prop_def) {
              $schema['conditionalProperties'][$discriminator_value][$prop_key] = $this->convertConfigSchemaToYamlSchema($prop_def, $depth + 1);
            }
          }
        }
      }
    }
    elseif ($base_type === 'sequence') {
      // A sequence with 'keyed: true' represents a mapping with user-defined
      // keys. Each item's key is editable and the value follows the sequence
      // item schema. This is emitted as 'keyed_mapping' for the frontend.
      $keyed = !empty($definition['keyed']);
      $schema['type'] = $keyed ? 'keyed_mapping' : 'list';
      if (isset($definition['sequence']) && is_array($definition['sequence'])) {
        $schema['items'] = $this->convertConfigSchemaToYamlSchema($definition['sequence'], $depth + 1);
      }
      else {
        // Default: sequence of strings.
        $schema['items'] = ['type' => 'string'];
      }
    }
    elseif ($base_type === 'boolean') {
      $schema['type'] = 'boolean';
    }
    elseif ($base_type === 'integer') {
      $schema['type'] = 'number';
      $schema['step'] = 1;
    }
    elseif ($base_type === 'float') {
      $schema['type'] = 'number';
    }
    elseif ($base_type === 'scalar') {
      // Accepts any scalar value (string, number, or boolean).
      $schema['type'] = 'scalar';
    }
    else {
      // All other types (string, label, email, uri, etc.) → string.
      $schema['type'] = 'string';
    }

    // Add label if present.
    if (isset($definition['label'])) {
      $schema['label'] = (string) $definition['label'];
    }

    // Check for constraints to derive additional schema properties.
    if (isset($definition['constraints']) && is_array($definition['constraints'])) {
      $this->applyConstraintsToSchema($schema, $definition['constraints']);
    }

    return $schema;
  }

  /**
   * Resolve the effective base type of a config schema definition.
   *
   * After TypedConfigManager::getDefinition() processes a definition, the
   * 'type' field contains the resolved schema key name (e.g.
   * 'yaml.foo.bar'), not the base type like 'mapping'. We determine the
   * base type by inspecting the definition's 'class' property and structural
   * keys.
   *
   * @param array $definition
   *   A Drupal config schema definition array.
   *
   * @return string
   *   One of: 'mapping', 'sequence', 'boolean', 'integer', 'float', 'string'.
   */
  protected function resolveBaseType(array $definition): string {
    $class = $definition['class'] ?? '';

    // Structural types identified by class.
    if ($class === 'Drupal\Core\Config\Schema\Mapping' || isset($definition['mapping'])) {
      return 'mapping';
    }
    if ($class === 'Drupal\Core\Config\Schema\Sequence' || isset($definition['sequence'])) {
      return 'sequence';
    }

    // Scalar types identified by class.
    $boolean_classes = [
      'Drupal\Core\TypedData\Plugin\DataType\BooleanData',
    ];
    if (in_array($class, $boolean_classes, TRUE)) {
      return 'boolean';
    }

    $integer_classes = [
      'Drupal\Core\TypedData\Plugin\DataType\IntegerData',
    ];
    if (in_array($class, $integer_classes, TRUE)) {
      return 'integer';
    }

    $float_classes = [
      'Drupal\Core\TypedData\Plugin\DataType\FloatData',
    ];
    if (in_array($class, $float_classes, TRUE)) {
      return 'float';
    }

    // Also check the 'type' field directly — for unresolved sub-definitions
    // (e.g. mapping property definitions that haven't been through
    // getDefinition()), the type is the plain base type name.
    $type = $definition['type'] ?? '';
    if ($type === 'mapping') {
      return 'mapping';
    }
    if ($type === 'sequence') {
      return 'sequence';
    }
    if ($type === 'boolean') {
      return 'boolean';
    }
    if (in_array($type, ['integer', 'weight'], TRUE)) {
      return 'integer';
    }
    if ($type === 'float') {
      return 'float';
    }
    if ($type === 'scalar') {
      return 'scalar';
    }

    // Everything else is treated as a string (string, text, label, email,
    // uri, path, date_format, color_hex, langcode, uuid, etc.).
    return 'string';
  }

  /**
   * Apply Drupal config schema constraints to a YamlEditor schema.
   *
   * Handles:
   *   - NotBlank → required: true.
   *   - Choice (static values) → options.
   *   - Choice (callback) → options (invoked at runtime).
   *   - Range → min/max on number types.
   *
   * @param array &$schema
   *   The YamlEditor schema being built (modified in place).
   * @param array $constraints
   *   The Drupal constraints array from the config schema definition.
   */
  protected function applyConstraintsToSchema(array &$schema, array $constraints): void {
    foreach ($constraints as $constraint_name => $constraint_options) {
      switch ($constraint_name) {
        case 'NotBlank':
          $schema['required'] = TRUE;
          break;

        case 'Choice':
          if ($schema['type'] === 'string' || $schema['type'] === 'number') {
            $options = $this->resolveChoiceConstraint($constraint_options);
            if (!empty($options)) {
              $schema['options'] = $options;
            }
          }
          break;

        case 'Range':
          if ($schema['type'] === 'number' && is_array($constraint_options)) {
            if (isset($constraint_options['min'])) {
              $schema['min'] = $constraint_options['min'];
            }
            if (isset($constraint_options['max'])) {
              $schema['max'] = $constraint_options['max'];
            }
          }
          break;
      }
    }
  }

  /**
   * Resolve a Choice constraint to a key-value options map.
   *
   * Supports:
   *   - Static arrays of values: [0, 1, 2] or ['view', 'update', 'delete']
   *   - Callback-based choices: { callback: 'ClassName::method' }
   *
   * @param mixed $constraint_options
   *   The Choice constraint configuration.
   *
   * @return array
   *   Associative array of option_value => option_label.
   */
  protected function resolveChoiceConstraint(mixed $constraint_options): array {
    $options = [];

    if (is_array($constraint_options)) {
      // Check for callback-based choices.
      if (isset($constraint_options['callback'])) {
        $callback = $constraint_options['callback'];
        try {
          if (is_string($callback) && str_contains($callback, '::')) {
            $values = call_user_func($callback);
          }
          elseif (is_array($callback) && count($callback) === 2) {
            $values = call_user_func($callback);
          }
          else {
            $values = [];
          }
          if (is_array($values)) {
            foreach ($values as $key => $value) {
              $options[(string) $key] = (string) $value;
            }
          }
        }
        catch (\Exception) {
          // Callback failed — return empty options.
        }
        return $options;
      }

      // Static array of values — use value as both key and label.
      foreach ($constraint_options as $value) {
        $str_value = (string) $value;
        $options[$str_value] = $str_value;
      }
    }

    return $options;
  }

}
