<?php

namespace Drupal\modeler_api\Plugin;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\FallbackPluginManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\modeler_api\Attribute\Modeler;
use Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface;

/**
 * Modeler plugin manager.
 */
class ModelerPluginManager extends DefaultPluginManager implements FallbackPluginManagerInterface {

  /**
   * All plugin instances.
   *
   * @var \Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface[]
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
      'Plugin/ModelerApiModeler',
      $namespaces,
      $module_handler,
      ModelerInterface::class,
      Modeler::class,
    );
    $this->alterInfo('modeler_api_modeler_info');
    $this->setCacheBackend($cache_backend, 'modeler_api_modeler_plugins', ['modeler_api_modeler_plugins']);
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackPluginId($plugin_id, array $configuration = []): string {
    return 'fallback';
  }

  /**
   * Get a list of all plugin instances.
   *
   * @param bool $reload
   *   If TRUE, force reloading all instances.
   *
   * @return \Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface[]
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
