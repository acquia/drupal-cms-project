<?php

namespace Drupal\modeler_api;

/**
 * Contains a dependency definition for modeler plugin predecessors.
 *
 * A dependency defines predecessor constraints for plugins within a model
 * owner's scope. A plugin listed as a key in the dependency rules can only be
 * used as a successor of one of its listed predecessor plugins. Glob-style
 * wildcards are supported in both the plugin ID keys and predecessor ID values,
 * where '*' matches any sequence of characters.
 *
 * Dependencies are defined in YAML files named
 * MODULE.modeler_api.dependencies.yml and are discovered by the
 * DependencyPluginManager. Each top-level key in the YAML file is a dependency
 * definition ID containing model_owner and components.
 *
 * @see \Drupal\modeler_api\Plugin\DependencyPluginManager
 */
readonly class Dependency {

  /**
   * Instantiates a new dependency definition.
   *
   * @param string $id
   *   The dependency definition ID.
   * @param string $modelOwner
   *   The model owner plugin ID this dependency applies to.
   * @param array $components
   *   An associative array keyed by component type name (as defined in
   *   \Drupal\modeler_api\Api::COMPONENT_TYPE_NAMES), where each value is an
   *   associative array keyed by plugin ID pattern (glob-style wildcards
   *   supported), and each value is a list of predecessor definitions. Each
   *   predecessor is an associative array with:
   *   - type: The component type name of the predecessor.
   *   - id: The plugin ID pattern of the predecessor (glob-style wildcards
   *     supported).
   * @param string $provider
   *   The module that provides this dependency definition.
   */
  public function __construct(
    protected string $id,
    protected string $modelOwner,
    protected array $components,
    protected string $provider,
  ) {}

  /**
   * Gets the dependency definition ID.
   *
   * @return string
   *   The dependency definition ID.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Gets the model owner plugin ID.
   *
   * @return string
   *   The model owner plugin ID.
   */
  public function getModelOwner(): string {
    return $this->modelOwner;
  }

  /**
   * Gets the component definitions for all types.
   *
   * @return array
   *   An associative array keyed by component type name, where each value is
   *   an associative array of dependency rules. Each rule key is a plugin ID
   *   pattern (may contain glob-style wildcards), and each value is a list of
   *   predecessor definitions with 'type' and 'id' keys.
   */
  public function getComponents(): array {
    return $this->components;
  }

  /**
   * Gets the provider module name.
   *
   * @return string
   *   The module that provides this dependency definition.
   */
  public function getProvider(): string {
    return $this->provider;
  }

  /**
   * Gets the dependency rules for a given component type.
   *
   * @param int $type
   *   The component type constant from \Drupal\modeler_api\Api.
   *
   * @return array
   *   An associative array keyed by plugin ID pattern, where each value is a
   *   list of predecessor definitions. Each predecessor is an associative
   *   array with:
   *   - type: The component type name of the predecessor.
   *   - id: The plugin ID pattern of the predecessor.
   *   Returns an empty array if no rules are defined for the given type.
   */
  public function getRules(int $type): array {
    $name = Api::COMPONENT_TYPE_NAMES[$type] ?? NULL;
    if ($name === NULL) {
      return [];
    }
    return $this->components[$name] ?? [];
  }

  /**
   * Checks if a plugin ID matches a pattern with glob-style wildcards.
   *
   * The '*' character matches any sequence of characters (including empty).
   *
   * @param string $pattern
   *   The pattern to match against, may contain '*' wildcards.
   * @param string $pluginId
   *   The plugin ID to test.
   *
   * @return bool
   *   TRUE if the plugin ID matches the pattern, FALSE otherwise.
   */
  public static function matchesPattern(string $pattern, string $pluginId): bool {
    if ($pattern === $pluginId) {
      return TRUE;
    }
    if (!str_contains($pattern, '*')) {
      return FALSE;
    }
    $regex = '/^' . implode('.*', array_map(
      static fn(string $part): string => preg_quote($part, '/'),
      explode('*', $pattern),
    )) . '$/';
    return (bool) preg_match($regex, $pluginId);
  }

  /**
   * Checks whether a specific plugin has dependency rules in this definition.
   *
   * Evaluates both exact matches and wildcard patterns.
   *
   * @param int $type
   *   The component type constant from \Drupal\modeler_api\Api.
   * @param string $pluginId
   *   The plugin ID to check.
   *
   * @return bool
   *   TRUE if the plugin has predecessor rules, FALSE otherwise.
   */
  public function hasDependency(int $type, string $pluginId): bool {
    foreach ($this->getRules($type) as $pattern => $predecessors) {
      if (self::matchesPattern($pattern, $pluginId)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Gets the required predecessors for a specific plugin.
   *
   * Collects predecessors from all matching patterns (including wildcards).
   *
   * @param int $type
   *   The component type constant from \Drupal\modeler_api\Api.
   * @param string $pluginId
   *   The plugin ID.
   *
   * @return array[]
   *   A list of predecessor definitions. Each predecessor is an associative
   *   array with 'type' (the component type name) and 'id' (the plugin ID
   *   pattern). Returns an empty array if no predecessors are required.
   */
  public function getRequiredPredecessors(int $type, string $pluginId): array {
    $result = [];
    foreach ($this->getRules($type) as $pattern => $predecessors) {
      if (self::matchesPattern($pattern, $pluginId)) {
        foreach ($predecessors as $predecessor) {
          if (!in_array($predecessor, $result, FALSE)) {
            $result[] = $predecessor;
          }
        }
      }
    }
    return $result;
  }

}
