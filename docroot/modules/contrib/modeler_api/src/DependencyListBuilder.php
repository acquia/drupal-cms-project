<?php

namespace Drupal\modeler_api;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\modeler_api\Plugin\DependencyPluginManager;

/**
 * Builds resolved dependency lists with an accompanying JSON schema.
 *
 * This service provides a merged list of all dependency rules for a given
 * model owner. Multiple dependency definitions from different modules are
 * merged into a single set of rules per component type. A JSON schema
 * definition is shipped as a file at config/schema/dependency_list.schema.json
 * and can be retrieved through this service so that consumers can understand
 * and validate the data structure.
 */
class DependencyListBuilder {

  /**
   * The JSON schema file path relative to the module directory.
   */
  protected const string SCHEMA_FILE = 'config/schema/dependency_list.schema.json';

  /**
   * Constructs a DependencyListBuilder.
   *
   * @param \Drupal\modeler_api\Plugin\DependencyPluginManager $dependencyPluginManager
   *   The dependency plugin manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    protected DependencyPluginManager $dependencyPluginManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Gets the merged dependency rules for a given model owner.
   *
   * All dependency definitions for the model owner are merged into a single
   * set of rules per component type.
   *
   * @param string $modelOwnerId
   *   The model owner plugin ID.
   *
   * @return array
   *   An associative array keyed by component type name, where each value is
   *   an associative array keyed by plugin ID pattern, each containing a list
   *   of predecessor definitions with 'type' and 'id' keys.
   */
  public function getList(string $modelOwnerId): array {
    $merged = [];
    foreach ($this->dependencyPluginManager->getDependenciesByModelOwner($modelOwnerId) as $dependency) {
      foreach ($dependency->getComponents() as $typeName => $rules) {
        foreach ($rules as $pluginPattern => $predecessors) {
          if (!isset($merged[$typeName][$pluginPattern])) {
            $merged[$typeName][$pluginPattern] = $predecessors;
          }
          else {
            foreach ($predecessors as $predecessor) {
              if (!in_array($predecessor, $merged[$typeName][$pluginPattern], FALSE)) {
                $merged[$typeName][$pluginPattern][] = $predecessor;
              }
            }
          }
        }
      }
    }
    return $merged;
  }

  /**
   * Gets the absolute file path to the JSON schema.
   *
   * @return string
   *   The absolute path to the dependency_list.schema.json file.
   */
  public function getJsonSchemaPath(): string {
    return $this->moduleHandler->getModule('modeler_api')->getPath() . '/' . self::SCHEMA_FILE;
  }

  /**
   * Gets the JSON schema that describes the structure of the dependency list.
   *
   * The schema follows the JSON Schema draft-07 specification and describes
   * the structure returned by getList(). The schema is loaded from
   * config/schema/dependency_list.schema.json.
   *
   * @return array
   *   The JSON schema as an associative array.
   */
  public function getJsonSchema(): array {
    return json_decode(file_get_contents($this->getJsonSchemaPath()), TRUE);
  }

}
