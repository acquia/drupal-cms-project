<?php

namespace Drupal\modeler_api\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Context;

/**
 * Plugin manager for modeler API contexts.
 *
 * Contexts are defined in YAML files named MODULE.modeler_api.contexts.yml.
 * Each entry in the YAML file defines a context with a topic, a model owner,
 * and available plugins per component type.
 *
 * Example YAML:
 * @code
 * base_context:
 *   topic: 'Base context'
 *   model_owner: my_model_owner
 *   components:
 *     start:
 *       plugins:
 *         - plugin_a
 *         - plugin_b
 *
 * my_context:
 *   topic: 'My context topic'
 *   model_owner: my_model_owner
 *   includes:
 *     - base_context
 *   components:
 *     element:
 *       plugins:
 *         - plugin_x
 *         - plugin_y
 *         - plugin_z
 *     link:
 *       plugins:
 *         - condition_a
 *         - condition_b
 * @endcode
 *
 * @see \Drupal\modeler_api\Context
 * @see \Drupal\modeler_api\Api::COMPONENT_TYPE_NAMES
 */
class ContextPluginManager extends DefaultPluginManager {

  /**
   * All context instances.
   *
   * @var \Drupal\modeler_api\Context[]
   */
  protected array $allInstances;

  /**
   * Valid component type names.
   *
   * @var string[]
   */
  protected array $validTypeNames;

  /**
   * Constructs a ContextPluginManager object.
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
    // discovery. YAML-based discovery is configured via getDiscovery().
    $this->moduleHandler = $module_handler;
    $yaml_discovery = new YamlDiscovery('modeler_api.contexts', $this->moduleHandler->getModuleDirectories());
    $yaml_discovery->addTranslatableProperty('topic');
    $this->discovery = new ContainerDerivativeDiscoveryDecorator($yaml_discovery);
    $this->alterInfo('modeler_api_context_info');
    $this->setCacheBackend($cache_backend, 'modeler_api_context_plugins', ['modeler_api_context_plugins']);
    $this->validTypeNames = array_values(Api::COMPONENT_TYPE_NAMES);
  }

  /**
   * Gets all context instances.
   *
   * @param bool $reload
   *   If TRUE, force reloading all instances.
   *
   * @return \Drupal\modeler_api\Context[]
   *   The list of all context instances, keyed by context ID.
   */
  public function getAllContexts(bool $reload = FALSE): array {
    if (!isset($this->allInstances) || $reload) {
      $this->allInstances = [];
      foreach ($this->getDefinitions() as $id => $definition) {
        $context = $this->createContext($id, $definition);
        if ($context !== NULL) {
          $this->allInstances[$id] = $context;
        }
      }
    }
    return $this->allInstances;
  }

  /**
   * Gets a single context by its ID.
   *
   * @param string $id
   *   The context ID.
   *
   * @return \Drupal\modeler_api\Context|null
   *   The context, or NULL if not found.
   */
  public function getContext(string $id): ?Context {
    $contexts = $this->getAllContexts();
    return $contexts[$id] ?? NULL;
  }

  /**
   * Gets all contexts for a given model owner.
   *
   * @param string $modelOwnerId
   *   The model owner plugin ID.
   *
   * @return \Drupal\modeler_api\Context[]
   *   The list of contexts for the given model owner, keyed by context ID.
   */
  public function getContextsByModelOwner(string $modelOwnerId): array {
    $contexts = [];
    foreach ($this->getAllContexts() as $id => $context) {
      if ($context->getModelOwner() === $modelOwnerId) {
        $contexts[$id] = $context;
      }
    }
    return $contexts;
  }

  /**
   * Creates a Context object from a plugin definition.
   *
   * @param string $id
   *   The context ID.
   * @param array $definition
   *   The plugin definition from YAML.
   *
   * @return \Drupal\modeler_api\Context|null
   *   The context object, or NULL if the definition is invalid.
   */
  protected function createContext(string $id, array $definition): ?Context {
    if (empty($definition['topic']) || empty($definition['model_owner'])) {
      return NULL;
    }

    $components = [];
    foreach ($definition['components'] ?? [] as $typeName => $typeDefinition) {
      if (!in_array($typeName, $this->validTypeNames, TRUE)) {
        continue;
      }
      $components[$typeName] = [
        'plugins' => $typeDefinition['plugins'] ?? [],
      ];
    }

    $includes = [];
    foreach ($definition['includes'] ?? [] as $includeId) {
      if (is_string($includeId)) {
        $includes[] = $includeId;
      }
    }

    return new Context(
      id: $id,
      topic: $definition['topic'],
      modelOwner: $definition['model_owner'],
      components: $components,
      provider: $definition['provider'] ?? '',
      includes: $includes,
      contextPluginManager: $this,
    );
  }

}
