<?php

namespace Drupal\modeler_api\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Dependency;

/**
 * Plugin manager for modeler API plugin dependencies.
 *
 * Dependencies are defined in YAML files named
 * MODULE.modeler_api.dependencies.yml. Each top-level key is a dependency
 * definition ID containing model_owner and components. Glob-style wildcards
 * are supported in both the plugin ID keys and predecessor ID values.
 *
 * Example YAML:
 * @code
 * my_dependencies:
 *   model_owner: my_model_owner
 *   components:
 *     element:
 *       plugin_z:
 *         - { type: start, id: plugin_a }
 *         - { type: element, id: plugin_x }
 *       audit_*:
 *         - { type: element, id: approval_* }
 *     link:
 *       condition_b:
 *         - { type: element, id: plugin_y }
 * @endcode
 *
 * @see \Drupal\modeler_api\Dependency
 * @see \Drupal\modeler_api\Api::COMPONENT_TYPE_NAMES
 */
class DependencyPluginManager extends DefaultPluginManager {

  /**
   * All dependency instances.
   *
   * @var \Drupal\modeler_api\Dependency[]
   */
  protected array $allInstances;

  /**
   * Valid component type names.
   *
   * @var string[]
   */
  protected array $validTypeNames;

  /**
   * Constructs a DependencyPluginManager object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   */
  public function __construct(
    ModuleHandlerInterface $module_handler,
    CacheBackendInterface $cache_backend,
  ) {
    // Do not call parent::__construct() as that sets up attribute-based
    // discovery. YAML-based discovery is configured here directly.
    $this->moduleHandler = $module_handler;
    $this->discovery = new ContainerDerivativeDiscoveryDecorator(
      new YamlDiscovery('modeler_api.dependencies', $this->moduleHandler->getModuleDirectories()),
    );
    $this->alterInfo('modeler_api_dependency_info');
    $this->setCacheBackend($cache_backend, 'modeler_api_dependency_plugins', ['modeler_api_dependency_plugins']);
    $this->validTypeNames = array_values(Api::COMPONENT_TYPE_NAMES);
  }

  /**
   * Gets all dependency instances.
   *
   * @param bool $reload
   *   If TRUE, force reloading all instances.
   *
   * @return \Drupal\modeler_api\Dependency[]
   *   The list of all dependency instances, keyed by dependency ID.
   */
  public function getAllDependencies(bool $reload = FALSE): array {
    if (!isset($this->allInstances) || $reload) {
      $this->allInstances = [];
      foreach ($this->getDefinitions() as $id => $definition) {
        $dependency = $this->createDependency($id, $definition);
        if ($dependency !== NULL) {
          $this->allInstances[$id] = $dependency;
        }
      }
    }
    return $this->allInstances;
  }

  /**
   * Gets a single dependency definition by its ID.
   *
   * @param string $id
   *   The dependency ID.
   *
   * @return \Drupal\modeler_api\Dependency|null
   *   The dependency, or NULL if not found.
   */
  public function getDependency(string $id): ?Dependency {
    $dependencies = $this->getAllDependencies();
    return $dependencies[$id] ?? NULL;
  }

  /**
   * Gets all dependencies for a given model owner.
   *
   * @param string $modelOwnerId
   *   The model owner plugin ID.
   *
   * @return \Drupal\modeler_api\Dependency[]
   *   The list of dependencies for the given model owner, keyed by ID.
   */
  public function getDependenciesByModelOwner(string $modelOwnerId): array {
    $dependencies = [];
    foreach ($this->getAllDependencies() as $id => $dependency) {
      if ($dependency->getModelOwner() === $modelOwnerId) {
        $dependencies[$id] = $dependency;
      }
    }
    return $dependencies;
  }

  /**
   * Creates a Dependency object from a plugin definition.
   *
   * @param string $id
   *   The dependency ID.
   * @param array $definition
   *   The plugin definition from YAML.
   *
   * @return \Drupal\modeler_api\Dependency|null
   *   The dependency object, or NULL if the definition is invalid.
   */
  protected function createDependency(string $id, array $definition): ?Dependency {
    if (empty($definition['model_owner'])) {
      return NULL;
    }

    $components = [];
    foreach ($definition['components'] ?? [] as $typeName => $typeDefinition) {
      if (!in_array($typeName, $this->validTypeNames, TRUE)) {
        continue;
      }
      if (!is_array($typeDefinition)) {
        continue;
      }
      $rules = [];
      foreach ($typeDefinition as $pluginPattern => $predecessors) {
        if (!is_array($predecessors)) {
          continue;
        }
        $validPredecessors = [];
        foreach ($predecessors as $predecessor) {
          if (is_array($predecessor)
            && isset($predecessor['type'], $predecessor['id'])
            && in_array($predecessor['type'], $this->validTypeNames, TRUE)) {
            $validPredecessors[] = [
              'type' => $predecessor['type'],
              'id' => $predecessor['id'],
            ];
          }
        }
        if (!empty($validPredecessors)) {
          $rules[(string) $pluginPattern] = $validPredecessors;
        }
      }
      if (!empty($rules)) {
        $components[$typeName] = $rules;
      }
    }

    return new Dependency(
      id: $id,
      modelOwner: $definition['model_owner'],
      components: $components,
      provider: $definition['provider'] ?? '',
    );
  }

}
