<?php

namespace Drupal\modeler_api;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\modeler_api\Plugin\ContextPluginManager;

/**
 * Builds resolved context lists with an accompanying JSON schema.
 *
 * This service provides a fully resolved list of all contexts for a given
 * model owner. Each context in the list has its includes resolved, meaning
 * plugin IDs from included contexts are merged into the result. A JSON schema
 * definition is shipped as a file at config/schema/context_list.schema.json
 * and can be retrieved through this service so that consumers can understand
 * and validate the data structure.
 */
class ContextListBuilder {

  /**
   * The JSON schema file path relative to the module directory.
   */
  protected const string SCHEMA_FILE = 'config/schema/context_list.schema.json';

  /**
   * Constructs a ContextListBuilder.
   *
   * @param \Drupal\modeler_api\Plugin\ContextPluginManager $contextPluginManager
   *   The context plugin manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    protected ContextPluginManager $contextPluginManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Gets the resolved list of all contexts for a given model owner.
   *
   * Each context entry contains its plugins fully resolved, including
   * contributions from all transitively included contexts.
   *
   * @param string $modelOwnerId
   *   The model owner plugin ID.
   *
   * @return array
   *   A list of resolved context arrays, each containing:
   *   - id: The context ID.
   *   - topic: The human-readable topic.
   *   - model_owner: The model owner plugin ID.
   *   - components: An object keyed by component type name, each containing:
   *     - plugins: A list of plugin IDs (includes resolved).
   */
  public function getList(string $modelOwnerId): array {
    $list = [];
    foreach ($this->contextPluginManager->getContextsByModelOwner($modelOwnerId) as $context) {
      $list[] = $this->resolveContext($context);
    }
    return $list;
  }

  /**
   * Resolves a context into a plain array with all includes merged.
   *
   * @param \Drupal\modeler_api\Context $context
   *   The context to resolve.
   *
   * @return array
   *   The resolved context as a plain array.
   */
  protected function resolveContext(Context $context): array {
    $components = [];
    foreach (Api::COMPONENT_TYPE_NAMES as $type => $name) {
      $plugins = $context->getPlugins($type);
      if (empty($plugins)) {
        continue;
      }
      $components[$name] = [
        'plugins' => $plugins,
      ];
    }

    return [
      'id' => $context->getId(),
      'topic' => (string) $context->getTopic(),
      'model_owner' => $context->getModelOwner(),
      'components' => $components,
    ];
  }

  /**
   * Gets the absolute file path to the JSON schema.
   *
   * @return string
   *   The absolute path to the context_list.schema.json file.
   */
  public function getJsonSchemaPath(): string {
    return $this->moduleHandler->getModule('modeler_api')->getPath() . '/' . self::SCHEMA_FILE;
  }

  /**
   * Gets the JSON schema that describes the structure of the context list.
   *
   * The schema follows the JSON Schema draft-07 specification and describes
   * the array structure returned by getList(). The schema is loaded from
   * config/schema/context_list.schema.json.
   *
   * @return array
   *   The JSON schema as an associative array.
   */
  public function getJsonSchema(): array {
    return json_decode(file_get_contents($this->getJsonSchemaPath()), TRUE);
  }

}
