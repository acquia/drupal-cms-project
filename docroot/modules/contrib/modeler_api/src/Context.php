<?php

namespace Drupal\modeler_api;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\modeler_api\Plugin\ContextPluginManager;

/**
 * Contains a context definition for modeler components.
 *
 * A context defines a topic, a model owner, and a list of plugin IDs for each
 * component type. A context can also include other contexts of the same model
 * owner, inheriting their plugins.
 */
readonly class Context {

  /**
   * Instantiates a new context.
   *
   * @param string $id
   *   The context ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $topic
   *   The human-readable topic of the context.
   * @param string $modelOwner
   *   The model owner plugin ID.
   * @param array $components
   *   An associative array keyed by component type name (as defined in
   *   \Drupal\modeler_api\Api::COMPONENT_TYPE_NAMES), where each value is an
   *   array with the following keys:
   *   - plugins: A list of plugin IDs available for this component type.
   * @param string $provider
   *   The module that provides this context.
   * @param string[] $includes
   *   A list of context IDs to include. Only contexts with the same model
   *   owner are allowed. Included contexts contribute their plugins to this
   *   context.
   * @param \Drupal\modeler_api\Plugin\ContextPluginManager $contextPluginManager
   *   The context plugin manager, used to resolve included contexts.
   */
  public function __construct(
    protected string $id,
    protected TranslatableMarkup|string $topic,
    protected string $modelOwner,
    protected array $components,
    protected string $provider,
    protected array $includes,
    protected ContextPluginManager $contextPluginManager,
  ) {}

  /**
   * Gets the context ID.
   *
   * @return string
   *   The context ID.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Gets the human-readable topic.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The topic.
   */
  public function getTopic(): TranslatableMarkup|string {
    return $this->topic;
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
   * Gets the included context IDs.
   *
   * @return string[]
   *   A list of context IDs that are included in this context.
   */
  public function getIncludes(): array {
    return $this->includes;
  }

  /**
   * Gets the component definitions for all types.
   *
   * This returns only the components directly defined on this context,
   * without resolving includes. Use getPlugins() to get the merged result
   * including included contexts.
   *
   * @return array
   *   An associative array keyed by component type name, where each value is
   *   an array with a 'plugins' key.
   */
  public function getComponents(): array {
    return $this->components;
  }

  /**
   * Gets the plugin IDs for a given component type.
   *
   * This includes plugins from this context and all transitively included
   * contexts, deduplicated while preserving order.
   *
   * @param int $type
   *   The component type constant from \Drupal\modeler_api\Api.
   *
   * @return string[]
   *   The list of plugin IDs, or an empty array if the type is not defined.
   */
  public function getPlugins(int $type): array {
    $name = Api::COMPONENT_TYPE_NAMES[$type] ?? NULL;
    if ($name === NULL) {
      return [];
    }
    $plugins = $this->components[$name]['plugins'] ?? [];
    foreach ($this->resolveIncludes() as $included) {
      $includedPlugins = $included->components[$name]['plugins'] ?? [];
      foreach ($includedPlugins as $pluginId) {
        if (!in_array($pluginId, $plugins, TRUE)) {
          $plugins[] = $pluginId;
        }
      }
    }
    return $plugins;
  }

  /**
   * Resolves all transitively included contexts.
   *
   * Only contexts with the same model owner are resolved. Circular includes
   * are prevented by tracking already visited context IDs.
   *
   * @param string[] $visited
   *   Context IDs already visited in the current resolution chain, used
   *   internally to prevent circular references.
   *
   * @return \Drupal\modeler_api\Context[]
   *   A flat list of all transitively included contexts.
   */
  protected function resolveIncludes(array $visited = []): array {
    $visited[] = $this->id;
    $resolved = [];
    foreach ($this->includes as $includeId) {
      if (in_array($includeId, $visited, TRUE)) {
        continue;
      }
      $included = $this->contextPluginManager->getContext($includeId);
      if ($included === NULL || $included->getModelOwner() !== $this->modelOwner) {
        continue;
      }
      $resolved[] = $included;
      $visited[] = $includeId;
      foreach ($included->resolveIncludes($visited) as $nested) {
        if (!in_array($nested->getId(), $visited, TRUE)) {
          $resolved[] = $nested;
          $visited[] = $nested->getId();
        }
      }
    }
    return $resolved;
  }

  /**
   * Gets the provider module name.
   *
   * @return string
   *   The module that provides this context.
   */
  public function getProvider(): string {
    return $this->provider;
  }

  /**
   * Checks if a plugin is available for a given component type.
   *
   * @param int $type
   *   The component type constant from \Drupal\modeler_api\Api.
   * @param string $pluginId
   *   The plugin ID to check.
   *
   * @return bool
   *   TRUE if the plugin is available, FALSE otherwise.
   */
  public function hasPlugin(int $type, string $pluginId): bool {
    return in_array($pluginId, $this->getPlugins($type), TRUE);
  }

}
