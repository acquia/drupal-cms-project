<?php

namespace Drupal\modeler_api\Plugin;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\modeler_api\Attribute\ModelOwner;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;

/**
 * Model owner plugin manager.
 */
class ModelOwnerPluginManager extends DefaultPluginManager {

  /**
   * All plugin instances.
   *
   * @var \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface[]
   */
  protected array $allInstances;

  /**
   * Constructs PluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/ModelerApiModelOwner',
      $namespaces,
      $module_handler,
      ModelOwnerInterface::class,
      ModelOwner::class,
    );
    $this->alterInfo('modeler_api_model_owner_info');
    $this->setCacheBackend($cache_backend, 'modeler_api_model_owner_plugins', ['modeler_api_model_owner_plugins']);
  }

  /**
   * Get a list of all plugin instances.
   *
   * @param bool $reload
   *   If TRUE, force reloading all instances.
   *
   * @return \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface[]
   *   The list of all instances.
   */
  public function getAllInstances(bool $reload = FALSE): array {
    if (!isset($this->allInstances) || $reload) {
      $this->allInstances = [];
      foreach ($this->getDefinitions() as $id => $definition) {
        try {
          $this->allInstances[$id] = $this->createInstance($id);
        }
        catch (PluginException) {
          // We ignore this on purpose.
        }
      }
    }
    return $this->allInstances;
  }

}
